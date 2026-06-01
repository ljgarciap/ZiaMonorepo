<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Sincroniza lecturas IoT cada 5 minutos
Schedule::command('zia:sync-telemetry')->everyFiveMinutes();

