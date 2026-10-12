<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark ui-font-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/carled-180.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="CARLED">
    <meta name="theme-color" content="#00C4B4">
    <x-pwa-runtime-config />
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
    $clientUsesAccountant = request()->is('accountant/*') || (!auth('web')->check() && auth('accountant')->check());
    $clientAccount = $clientUsesAccountant ? auth('accountant')->user() : auth('web')->user();
    $clientAccountType = $clientAccount ? ($clientUsesAccountant ? 'accountant' : 'user') : null;
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
