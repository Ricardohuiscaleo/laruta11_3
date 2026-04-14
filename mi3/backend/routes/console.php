<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\CronExecution;

/*
|--------------------------------------------------------------------------
| Console Routes — Scheduler
|--------------------------------------------------------------------------
| Cada comando se registra automáticamente en cron_executions via
| before/after callbacks.
*/

$commands = [
    ['cmd' => 'mi3:r11-auto-deduct',                    'name' => 'Descuento R11',              'schedule' => fn($s) => $s->monthlyOn(1, '06:00')],
    ['cmd' => 'mi3:loan-auto-deduct',                   'name' => 'Descuento Adelantos',        'schedule' => fn($s) => $s->monthlyOn(1, '06:30')],
    ['cmd' => 'mi3:r11-reminder',                       'name' => 'Recordatorio R11',           'schedule' => fn($s) => $s->monthlyOn(28, '10:00')],
    ['cmd' => 'mi3:create-daily-checklists',            'name' => 'Checklists Diarios',         'schedule' => fn($s) => $s->dailyAt('14:00')],
    ['cmd' => 'mi3:check-companion-absence',            'name' => 'Detectar Ausencia',          'schedule' => fn($s) => $s->dailyAt('19:00')],
    ['cmd' => 'mi3:detect-absences',                    'name' => 'Descuento Inasistencias',    'schedule' => fn($s) => $s->dailyAt('02:00')],
    ['cmd' => 'mi3:generate-shifts',                    'name' => 'Generar Turnos',             'schedule' => fn($s) => $s->monthlyOn(25, '10:00')],
    ['cmd' => 'mi3:checklist-reminder',                 'name' => 'Recordatorio Checklist',     'schedule' => fn($s) => $s->dailyAt('18:00')],
    ['cmd' => 'delivery:generate-daily-settlement',     'name' => 'Settlement Delivery Diario', 'schedule' => fn($s) => $s->dailyAt('23:59')],
    ['cmd' => 'delivery:check-pending-settlements',     'name' => 'Verificar Settlements',      'schedule' => fn($s) => $s->dailyAt('12:00')],
];

foreach ($commands as $c) {
    $event = Schedule::command($c['cmd'])->timezone('America/Santiago');
    ($c['schedule'])($event);

    $startTime = null;
    $event->before(function () use (&$startTime) {
        $startTime = now();
    });
    $event->after(function () use ($c, &$startTime) {
        try {
            $duration = $startTime ? now()->diffInMilliseconds($startTime) / 1000 : null;
            CronExecution::log($c['cmd'], $c['name'], 'success', null, $duration, $startTime);
        } catch (\Throwable $e) {
            // Don't let logging failures break the scheduler
        }
    });
    $event->onFailure(function () use ($c, &$startTime) {
        try {
            $duration = $startTime ? now()->diffInMilliseconds($startTime) / 1000 : null;
            CronExecution::log($c['cmd'], $c['name'], 'failed', null, $duration, $startTime);

            // Notify Telegram on failure
            $tg = app(\App\Services\Notification\TelegramService::class);
            $tg->send("⚠️ *Cron falló*: {$c['name']}\nComando: `{$c['cmd']}`\nDuración: {$duration}s\n\nPara re-ejecutar: `/retry {$c['cmd']}`");
        } catch (\Throwable $e) {
            // Don't let logging/notification failures break the scheduler
        }
    });
}
