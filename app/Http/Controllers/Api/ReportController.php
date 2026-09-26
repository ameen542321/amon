<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Store;
use App\Services\ApiStoreScopeService;
use App\Services\Reports\MonthlyStoreReportService;
use App\Services\Reports\StoreTransferReportService;
use App\Support\Api\ApiResponse;
use App\Support\ArabicPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        private readonly ApiStoreScopeService $stores,
        private readonly MonthlyStoreReportService $monthly,
        private readonly StoreTransferReportService $transfers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$store, $actor] = $this->context($request);

        return ApiResponse::success([
            ['key' => 'monthly', 'title' => 'التقرير الشهري', 'preview' => ! ($actor instanceof Accountant), 'pdf' => ! ($actor instanceof Accountant), 'date_basis' => 'business_date'],
            ['key' => 'store-transfers', 'title' => 'تقرير النقل المخزني', 'preview' => false, 'pdf' => ! ($actor instanceof Accountant), 'date_basis' => 'request_and_action_business_date'],
        ], ['store_id' => (int) $store->id, 'offline_cache' => false]);
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        [$store, $actor] = $this->context($request);
        if ($actor instanceof Accountant) {
            return ApiResponse::error('OWNER_REQUIRED', 'التقرير المالي الشهري متاح للمالك فقط.', 403);
        }
        $month = $this->month($request);
        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();
        $report = $this->monthly->buildMonthlyReportData($store, $month, $start, $end, false);

        return ApiResponse::success([
            'store_id' => (int) $store->id,
            'month' => $month,
            'period' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'source' => 'MonthlyStoreReportService',
            'metrics' => [
                'total_sales' => $this->money($report['totalSales']),
                'operations_count' => (int) $report['operationsCount'],
                'expenses_total' => $this->money($report['expensesTotal']),
                'internal_use' => $this->money($report['internalUseSales']),
                'owner_purchases' => $this->money($report['ownerPurchases']),
                'recognized_profit' => $this->money($report['recognizedProfit']),
                'deferred_profit' => $this->money($report['deferredProfit']),
            ],
        ]);
    }

    public function createDownload(Request $request): JsonResponse
    {
        [$store, $actor] = $this->context($request);
        if ($actor instanceof Accountant) {
            return ApiResponse::error('OWNER_REQUIRED', 'تنزيل التقارير متاح للمالك فقط.', 403);
        }
        $validated = $request->validate([
            'type' => ['required', Rule::in(['monthly', 'store-transfers'])],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'rejected', 'cancelled'])],
            'include_sales_details' => ['nullable', 'boolean'],
        ]);
        $parameters = $this->downloadParameters($validated);
        $expiresAt = now()->addMinutes(max(1, min(60, (int) config('mobile_api.report_download_ttl_minutes', 10))));
        $url = URL::temporarySignedRoute('api.v1.report-downloads.show', $expiresAt, [
            'type' => $validated['type'],
            'store_id' => (int) $store->id,
            'owner_id' => (int) $actor->getAuthIdentifier(),
            ...$parameters,
        ]);

        return ApiResponse::success(['url' => $url, 'expires_at' => $expiresAt->toIso8601String(), 'short_lived' => true]);
    }

    public function download(Request $request, string $type): Response
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
            'owner_id' => ['required', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'rejected', 'cancelled'])],
            'include_sales_details' => ['nullable', 'boolean'],
        ]);
        $store = Store::query()->whereKey($validated['store_id'])->where('user_id', $validated['owner_id'])->where('status', 'active')->firstOrFail();

        return $type === 'monthly'
            ? $this->monthlyPdf($store, $validated)
            : $this->transferPdf($store, $validated);
    }

    private function monthlyPdf(Store $store, array $input): Response
    {
        $month = $input['month'] ?? now()->format('Y-m');
        $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->endOfMonth();
        $includeSalesDetails = filter_var($input['include_sales_details'] ?? false, FILTER_VALIDATE_BOOL);
        $data = $this->monthly->buildMonthlyReportData($store, $month, $start, $end, $includeSalesDetails);
        $data['includeSalesDetails'] = $includeSalesDetails;
        $data['reportTitle'] = $this->monthly->buildMonthlyReportTitle($store->name, $month, $includeSalesDetails);
        $pdf = ArabicPdf::loadView('pdf.store-monthly-report', $data)->setOption('encoding', 'utf-8');

        return $pdf->download($this->monthly->buildSafeReportFileName($data['reportTitle'], $store->id));
    }

    private function transferPdf(Store $store, array $input): Response
    {
        $filters = $this->transferFilters($input);
        $report = $this->transfers->build($store, $filters, false);
        $pdf = ArabicPdf::loadView('pdf.store-transfer-report', compact('store', 'filters') + $report)->setOption('encoding', 'utf-8');

        return $pdf->download('تقرير_النقل_المخزني_'.$store->id.'_'.$filters['from'].'_'.$filters['to'].'.pdf');
    }

    private function context(Request $request): array
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        $actor = $request->attributes->get('api_actor');

        return [$this->stores->resolve($actor, (int) $validated['store_id']), $actor];
    }

    private function month(Request $request): string
    {
        return $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
    }

    private function downloadParameters(array $input): array
    {
        return $input['type'] === 'monthly'
            ? ['month' => $input['month'] ?? now()->format('Y-m'), 'include_sales_details' => (bool) ($input['include_sales_details'] ?? false)]
            : $this->transferFilters($input);
    }

    private function transferFilters(array $input): array
    {
        $to = CarbonImmutable::parse($input['to'] ?? now()->toDateString());
        $from = CarbonImmutable::parse($input['from'] ?? $to->startOfMonth()->toDateString());
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['from' => 'فترة التقرير غير صالحة أو تتجاوز 367 يومًا.']);
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'status' => $input['status'] ?? null];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
