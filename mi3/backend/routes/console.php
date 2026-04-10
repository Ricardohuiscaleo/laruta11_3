<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes — Scheduler
|--------------------------------------------------------------------------
*/

// Descuento automático de crédito R11 — día 1 de cada mes a las 6:00 AM
Schedule::command('mi3:r11-auto-deduct')
    ->monthlyOn(1, '06:00')
    ->timezone('America/Santiago');

// Recordatorio de deuda R11 — día 28 de cada mes a las 10:00 AM
Schedule::command('mi3:r11-reminder')
    ->monthlyOn(28, '10:00')
    ->timezone('America/Santiago');
