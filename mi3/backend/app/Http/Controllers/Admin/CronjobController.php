<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CronjobController extends Controller
{
    public function index(): JsonResponse
    {
        // Aggregate stats per command
        $stats = DB::table('cron_executions')
            ->select(
                'command',
                'name',
                DB::raw('COUNT(*) as total_runs'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successes"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failures"),
                DB::raw('ROUND(AVG(duration_seconds), 2) as avg_duration'),
                DB::raw('MAX(finished_at) as last_run'),
            )
            ->groupBy('command', 'name')
            ->orderBy('last_run', 'desc')
            ->get()
            ->map(function ($row) {
                $last = CronExecution::where('command', $row->command)
                    ->orderByDesc('id')->first();

                return [
                    'command'       => $row->command,
                    'name'          => $row->name,
                    'total_runs'    => (int) $row->total_runs,
                    'successes'     => (int) $row->successes,
                    'failures'      => (int) $row->failures,
                    'success_rate'  => $row->total_runs > 0
                        ? round(($row->successes / $row->total_runs) * 100, 1)
                        : 0,
                    'avg_duration'  => (float) $row->avg_duration,
                    'last_run'      => $row->last_run,
                    'last_status'   => $last?->status,
                    'last_output'   => $last?->output,
                ];
            });

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
