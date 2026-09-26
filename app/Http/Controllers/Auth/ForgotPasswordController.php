<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * عرض صفحة "نسيت كلمة المرور"
     */
    public function showLinkRequestForm()
    {
        return view('auth.forgot');
    }

    /**
     * استقبال البريد وإرسال رابط إعادة التعيين
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // إنشاء توكن وحفظه في جدول password_resets للمستخدم الموجود فقط.
            $token = Str::random(64);

            DB::table('password_resets')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token'      => bcrypt($token),
                    'created_at' => Carbon::now(),
                ]
            );

            $user->notify(new ResetPasswordNotification($token, $user->email));
        }

        // الرسالة الموحدة تمنع كشف ما إذا كان البريد مسجلاً في النظام.
        return back()->with('status', 'إذا كان البريد مسجلاً فسيصلك رابط إعادة تعيين كلمة المرور.');
    }
}
