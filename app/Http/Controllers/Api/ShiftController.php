<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyBalance;
use App\Services\ApiStoreScopeService;
use App\Services\ShiftLifecycleService;
use App\Support\Api\ApiResponse;
use App\Support\Api\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores, private readonly ShiftLifecycleService $lifecycle) {}

    public function current(Request $request): JsonResponse
    {
        $store = $this->store($request);
        $context = $this->lifecycle->currentShiftContext($store, includeMissingDates: true);
        return ApiResponse::success([
            'business_date' => $context['business_date'],
            'shift_number' => (int) $context['shift_number'],
            'maximum_shifts' => (int) $context['max_shifts_per_business_date'],
            'shift_start' => $context['shift_start']?->toIso8601String(),
            'requires_second_shift_confirmation' => (bool) $context['requires_second_shift_confirmation'],
            'can_choose_next_business_date' => (bool) $context['can_choose_next_shift_business_date'],
            'missing_business_dates' => array_values($context['missing_business_dates']),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $query = DailyBalance::query()->where('store_id', $store->id)->whereNotNull('end_time');
        if (!empty($validated['from'])) {
            $query->whereDate('business_date', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('business_date', '<=', $validated['to']);
        }
        $rows = $query->latest('id')->cursorPaginate($validated['limit'] ?? 30);
        return ApiResponse::success(collect($rows->items())->map(fn ($row) => ShiftResource::balance($row))->values(), [
            'next_cursor' => $rows->nextCursor()?->encode(), 'per_page' => $rows->perPage(),
        ]);
    }

    public function gaps(Request $request): JsonResponse
    {
        $store = $this->store($request);
        return ApiResponse::success([
            'dates' => $this->lifecycle->missingBusinessDates($store->id),
            'open_business_dates' => $this->lifecycle->openBusinessDates($store),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function store(Request $request)
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        return $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
    }
}
