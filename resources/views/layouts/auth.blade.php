<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#00C4B4">
    <meta name="pwa-build-version" content="{{ config('pwa.build_version') }}">
    <meta name="application-name" content="CARLED">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" sizes="any" href="{{ asset('carled.svg') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="{{ asset('fonts/cairo/cairo-arabic-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Cairo-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>@yield('title', 'CARLED - تسجيل الدخول')</title>
</head>

@php
    $clientAccount = auth('accountant')->user() ?? auth('web')->user();
    $clientAccountType = auth('accountant')->check() ? 'accountant' : (auth('web')->check() ? 'user' : null);
    $clientAccountScope = $clientAccountType && $clientAccount
        ? $clientAccountType.':'.$clientAccount->getAuthIdentifier()
        : null;
@endphp
<body class="auth-page auth-page-center">
    @if($clientAccountScope)
        <div hidden data-client-account-scope="{{ $clientAccountScope }}" aria-hidden="true"></div>
    @endif
    <x-ui.page-loader />
    <main class="w-full max-w-md">
        @yield('content')
    </main>
</body>
</html>
