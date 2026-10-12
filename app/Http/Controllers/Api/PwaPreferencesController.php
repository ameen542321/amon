<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Support\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PwaPreferencesController extends Controller
{
    public function show(Request $request)
    {
        return ApiResponse::success($this->preferences($this->identity($request)));
    }

    public function update(Request $request)
    {
        $identity = $this->identity($request);
        $values = $request->validate(['announcements' => ['required', 'boolean'], 'messages' => ['required', 'boolean'], 'operations' => ['required', 'boolean']]);
        NotificationPreference::updateOrCreate($identity, $values);

        return ApiResponse::success($this->preferences($identity));
    }

    private function identity(Request $request): array
    {
        // نفس الدور الذي حمى المسار، حتى لو كانت جلستا المالك والمحاسب مفتوحتين.
        $type = $request->is('accountant/*') ? 'accountant' : 'user';
        $actor = Auth::guard($type === 'accountant' ? 'accountant' : 'web')->user();
        abort_unless($actor, 401);

        return ['actor_type' => $type, 'actor_id' => (int) $actor->getAuthIdentifier()];
    }

    private function preferences(array $identity): array
    {
        $record = NotificationPreference::where($identity)->first();

        return collect(['announcements', 'messages', 'operations'])->mapWithKeys(fn ($key) => [$key => $record ? (bool) $record->{$key} : true])->all();
    }
}
