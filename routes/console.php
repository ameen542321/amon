<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// التنظيف منفصل عن طلبات المستخدم حتى لا تسبب قراءة الإشعارات حذفًا خفيًا.
Schedule::command('notifications:cleanup')
    ->dailyAt('02:30')
    ->withoutOverlapping();
