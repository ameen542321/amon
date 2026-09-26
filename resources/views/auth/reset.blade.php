@extends('layouts.auth')

@section('content')

<div class="auth-shell auth-panel">
    <h1 class="mb-6 text-center text-2xl font-black ui-title">إعادة تعيين كلمة المرور</h1>

    @if ($errors->any())
        <div class="ui-alert ui-alert-danger mb-5 text-sm" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <input type="hidden" name="email" value="{{ $email }}">

        <div class="mb-4">
            <label for="reset-password" class="auth-label">كلمة المرور الجديدة</label>
            <div class="relative" x-data="{ passwordVisible: false }">
                <input id="reset-password"
                       :type="passwordVisible ? 'text' : 'password'"
                       name="password"
                       class="ui-input pl-12"
                       autocomplete="new-password"
                       required>
                <button type="button" class="auth-password-toggle absolute left-1 top-1/2 -translate-y-1/2"
                        @click="passwordVisible = !passwordVisible"
                        :aria-label="passwordVisible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                        :title="passwordVisible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'">
                    <i class="fa-solid" :class="passwordVisible ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label for="reset-password-confirmation" class="auth-label">تأكيد كلمة المرور</label>
            <input id="reset-password-confirmation"
                   type="password"
                   name="password_confirmation"
                   class="ui-input"
                   autocomplete="new-password"
                   required>
        </div>

        <button class="ui-btn ui-btn-primary w-full mb-4">
            تحديث كلمة المرور
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" class="auth-link">
                العودة لتسجيل الدخول
            </a>
        </div>

    </form>

</div>

@endsection
