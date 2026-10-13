<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Services\WorkspaceUiService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request, WorkspaceUiService $workspace): JsonResponse
    {
        $account = auth($request->is('accountant/*') ? 'accountant' : 'web')->user();
        abort_unless($account, 401);
        $isAdmin = ($account->role ?? '') === 'admin';
        abort_if($isAdmin && !$request->is('admin/*'), 403);
        abort_if(!$isAdmin && $request->is('admin/*'), 403);
        $validated = $request->validate(['q' => 'nullable|string|max:100', 'store_id' => 'nullable|integer|min:1']);
        $stores = $isAdmin ? collect() : $workspace->stores($account);
        if (!empty($validated['store_id'])) {
            abort_unless($stores->contains('id', (int) $validated['store_id']), 403);
            $stores = $stores->where('id', (int) $validated['store_id']);
        }
        $tasks = $request->routeIs('*.api.workspace.tasks');
        $term = trim($validated['q'] ?? '');
        return ApiResponse::success($tasks ? $workspace->tasks($account, $stores) : ['items' => mb_strlen($term) >= 2 ? $workspace->search($account, $stores, $term) : []], [
            'account_scope' => ($account instanceof Accountant ? 'accountant' : 'user').':'.$account->getAuthIdentifier(),
        ]);
    }
}
