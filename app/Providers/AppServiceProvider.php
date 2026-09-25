<?php

namespace App\Providers;

use App\Models\OneSignalSetting;
use App\View\Composers\DashboardNavigationComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mobile-login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            'mobile-login:'.$request->ip().':'.sha1(mb_strtolower((string) $request->input('email'))),
        ));
        RateLimiter::for('mobile-api', fn (Request $request): Limit => Limit::perMinute(120)->by(
            'mobile-api:'.($request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip()),
        ));

        View::composer('*', function ($composedView) {
            $composedView->with('settings', OneSignalSetting::first());
        });

        View::composer([
            'dashboard.navbars.user',
            'dashboard.navbars.admin',
            'dashboard.navbars.accountant',
        ], DashboardNavigationComposer::class);
    }
}
