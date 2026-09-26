<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $accountant = Auth::guard('accountant')->user();
        $user = Auth::guard('web')->user();
        abort_unless($accountant || $user, 401);

        // يملك الرمز حسابًا واحدًا فقط حتى لا ينتقل Push بين الحارسين.
        $data = [
            'token' => $request->string('token')->toString(),
            'user_id' => $user?->id,
            'accountant_id' => $accountant?->id,
        ];

        // منع التكرار
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            $data
        );

        return response()->json(['status' => 'saved'])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
