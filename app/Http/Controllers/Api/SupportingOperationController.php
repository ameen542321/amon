<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\SupportingOperationResource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportingOperationController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function summary(Request $request): JsonResponse
    {
        [$storeId, $actor] = $this->context($request);
        [$startDate, $endDate] = $this->period($request);
        $expenses = $this->expensesQuery($storeId, $startDate, $endDate);
        $internalUses = $this->internalUsesQuery($storeId, $startDate, $endDate);
        $data = [
            'store_id' => $storeId,
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'expenses' => [
                'count' => (clone $expenses)->count(),
                'total' => $this->money((clone $expenses)->sum('amount')),
            ],
            'internal_use' => [
                'count' => (clone $internalUses)->count(),
                'total' => $this->money((clone $internalUses)->sum(DB::raw('COALESCE(final_total, total, 0)'))),
            ],
            'owner_purchases' => null,
        ];
        if (! ($actor instanceof Accountant)) {
            $purchases = $this->ownerPurchasesQuery($storeId, $startDate, $endDate);
            $data['owner_purchases'] = [
                'count' => (clone $purchases)->count(),
                'total' => $this->money((clone $purchases)->sum('cost')),
            ];
        }

        return ApiResponse::success($data);
    }

    public function expenses(Request $request): JsonResponse
    {
        [$storeId] = $this->context($request);
        [$startDate, $endDate] = $this->period($request);
        $validated = $this->pagination($request);
        $expenses = $this->expensesQuery($storeId, $startDate, $endDate)
            ->orderByDesc('business_date')->orderByDesc('id')
            ->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($expenses->items())->map(fn (Expense $expense): array => SupportingOperationResource::expense($expense))->values(),
            $this->pageMeta($expenses, $startDate, $endDate)
        );
    }

    public function expense(Request $request, int $expense): JsonResponse
    {
        [$storeId] = $this->context($request);
        $record = Expense::query()->where('store_id', $storeId)->findOrFail($expense);

        return ApiResponse::success(SupportingOperationResource::expense($record));
    }

    public function internalUses(Request $request): JsonResponse
    {
        [$storeId] = $this->context($request);
        [$startDate, $endDate] = $this->period($request);
        $validated = $this->pagination($request);
        $sales = $this->internalUsesQuery($storeId, $startDate, $endDate)
            ->with('accountant:id,name')->withCount('items')
            ->orderByDesc('business_date')->orderByDesc('id')
            ->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($sales->items())->map(fn (Sale $sale): array => SupportingOperationResource::internalUse($sale))->values(),
            $this->pageMeta($sales, $startDate, $endDate)
        );
    }

    public function internalUse(Request $request, int $sale): JsonResponse
    {
        [$storeId] = $this->context($request);
        $record = Sale::query()->where('store_id', $storeId)->where('sale_type', 'internal_use')
            ->where(fn (Builder $query) => $query->whereNull('description')->orWhere('description', '!=', 'manual_invoice_entry'))
            ->with(['accountant:id,name', 'items.product'])->findOrFail($sale);

        return ApiResponse::success(SupportingOperationResource::internalUse($record, true));
    }

    public function ownerPurchases(Request $request): JsonResponse
    {
        [$storeId, $actor] = $this->context($request);
        if ($actor instanceof Accountant) {
            return ApiResponse::error('OWNER_REQUIRED', 'مشتريات المالك متاحة للمالك فقط.', 403);
        }
        [$startDate, $endDate] = $this->period($request);
        $validated = $this->pagination($request);
        $purchases = $this->ownerPurchasesQuery($storeId, $startDate, $endDate)
            ->orderByDesc('business_date')->orderByDesc('id')
            ->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($purchases->items())->map(fn (Purchase $purchase): array => SupportingOperationResource::ownerPurchase($purchase))->values(),
            $this->pageMeta($purchases, $startDate, $endDate)
        );
    }

    private function context(Request $request): array
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        $actor = $request->attributes->get('api_actor');
        $store = $this->stores->resolve($actor, (int) $validated['store_id']);

        return [(int) $store->id, $actor];
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $end = CarbonImmutable::parse($validated['end_date'] ?? now()->toDateString())->startOfDay();
        $start = CarbonImmutable::parse($validated['start_date'] ?? $end->subDays(29)->toDateString())->startOfDay();
        if ($start->gt($end) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['start_date' => 'الفترة يجب أن تكون مرتبة وألا تتجاوز 367 يومًا.']);
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    private function pagination(Request $request): array
    {
        return $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
    }

    private function expensesQuery(int $storeId, string $startDate, string $endDate): Builder
    {
        return Expense::query()->where('store_id', $storeId)->betweenAccountingDates($startDate, $endDate);
    }

    private function internalUsesQuery(int $storeId, string $startDate, string $endDate): Builder
    {
        return Sale::query()->where('store_id', $storeId)->where('sale_type', 'internal_use')
            ->where(fn (Builder $query) => $query->whereNull('description')->orWhere('description', '!=', 'manual_invoice_entry'))
            ->betweenAccountingDates($startDate, $endDate);
    }

    private function ownerPurchasesQuery(int $storeId, string $startDate, string $endDate): Builder
    {
        return Purchase::query()->where('store_id', $storeId)
            ->where(function (Builder $query) use ($startDate, $endDate): void {
                $query->whereBetween('business_date', [$startDate, $endDate])
                    ->orWhere(function (Builder $legacy) use ($startDate, $endDate): void {
                        $legacy->whereNull('business_date')->whereBetween('created_at', [
                            CarbonImmutable::parse($startDate)->startOfDay(),
                            CarbonImmutable::parse($endDate)->endOfDay(),
                        ]);
                    });
            });
    }

    private function pageMeta(mixed $paginator, string $startDate, string $endDate): array
    {
        return [
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'per_page' => $paginator->perPage(),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
