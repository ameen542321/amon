<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Employee;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\PeopleResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeopleController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function summary(Request $request): JsonResponse
    {
        [$storeId, $actor] = $this->context($request);
        $employees = Employee::query()->where('store_id', $storeId);
        $accountants = $this->accountantsQuery($actor, $storeId);

        return ApiResponse::success([
            'store_id' => $storeId,
            'employees' => [
                'total' => (clone $employees)->count(),
                'active' => (clone $employees)->where('status', 'active')->count(),
                'inactive' => (clone $employees)->where('status', 'inactive')->count(),
            ],
            'accountants' => [
                'total' => (clone $accountants)->count(),
                'active' => (clone $accountants)->where('status', 'active')->count(),
                'suspended' => (clone $accountants)->where('status', 'suspended')->count(),
            ],
            'financial_data_included' => false,
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $validated = $this->listInput($request, ['active', 'inactive']);
        [$storeId, $actor] = $this->context($request);
        $query = Employee::query()->where('store_id', $storeId)->with('activeAccountant:id,employee_id');
        $this->applyFilters($query, $validated);
        $employees = $query->orderBy('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($employees->items())->map(fn (Employee $employee): array => PeopleResource::employee($employee, ! $actor instanceof Accountant))->values(),
            ['next_cursor' => $employees->nextCursor()?->encode(), 'per_page' => $employees->perPage()]
        );
    }

    public function employee(Request $request, int $employee): JsonResponse
    {
        [$storeId, $actor] = $this->context($request);
        $record = Employee::query()->where('store_id', $storeId)->with('activeAccountant:id,employee_id')->findOrFail($employee);

        return ApiResponse::success(PeopleResource::employee($record, ! $actor instanceof Accountant));
    }

    public function accountants(Request $request): JsonResponse
    {
        $validated = $this->listInput($request, ['active', 'suspended']);
        [$storeId, $actor] = $this->context($request);
        $query = $this->accountantsQuery($actor, $storeId);
        $this->applyFilters($query, $validated);
        $accountants = $query->orderBy('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($accountants->items())->map(fn (Accountant $accountant): array => PeopleResource::accountant($accountant, ! $actor instanceof Accountant))->values(),
            ['next_cursor' => $accountants->nextCursor()?->encode(), 'per_page' => $accountants->perPage()]
        );
    }

    public function accountant(Request $request, int $accountant): JsonResponse
    {
        [$storeId, $actor] = $this->context($request);
        $record = $this->accountantsQuery($actor, $storeId)->findOrFail($accountant);

        return ApiResponse::success(PeopleResource::accountant($record, ! $actor instanceof Accountant));
    }

    private function context(Request $request): array
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        $actor = $request->attributes->get('api_actor');
        $store = $this->stores->resolve($actor, (int) $validated['store_id']);

        return [(int) $store->id, $actor];
    }

    private function listInput(Request $request, array $statuses): array
    {
        return $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in($statuses)],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
    }

    private function applyFilters(Builder $query, array $validated): void
    {
        $query->when($validated['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status));
        $query->when($validated['search'] ?? null, function (Builder $builder, string $search): void {
            $builder->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%');
        });
    }

    private function accountantsQuery(mixed $actor, int $storeId): Builder
    {
        $query = Accountant::query()->where('store_id', $storeId);

        return $actor instanceof Accountant
            ? $query->whereKey($actor->getAuthIdentifier())
            : $query->where('user_id', $actor->getAuthIdentifier());
    }
}
