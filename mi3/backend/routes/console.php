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

// Descuento automático de cuotas de préstamo — día 1 de cada mes a las 06:30 AM
Schedule::command('mi3:loan-auto-deduct')
    ->monthlyOn(1, '06:30')
    ->timezone('America/Santiago');

// Recordatorio de deuda R11 — día 28 de cada mes a las 10:00 AM
Schedule::command('mi3:r11-reminder')
    ->monthlyOn(28, '10:00')
    ->timezone('America/Santiago');

// Crear checklists diarios — todos los días a las 14:00 Chile
Schedule::command('mi3:create-daily-checklists')
    ->dailyAt('14:00')
    ->timezone('America/Santiago');

// Detectar compañero ausente y habilitar checklist virtual — todos los días a las 19:00 Chile
Schedule::command('mi3:check-companion-absence')
    ->dailyAt('19:00')
    ->timezone('America/Santiago');

// Detectar inasistencias y aplicar descuentos — todos los días a las 02:00 Chile
Schedule::command('mi3:detect-absences')
    ->dailyAt('02:00')
    ->timezone('America/Santiago');

// Generar turnos dinámicos 4x4 — día 25 de cada mes a las 10:00 (genera mes siguiente)
Schedule::command('mi3:generate-shifts')
    ->monthlyOn(25, '10:00')
    ->timezone('America/Santiago');
