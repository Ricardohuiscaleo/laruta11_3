<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CronjobController extends Controller
{
    private string $baseUrl;
    private string $token;

    private array $apps = [
        'mi3-backend' => 'ds24j8jlaf9ov4flk1nq4jek',
        'app3'        => 'egck4wwcg0ccc4osck4sw8ow',
        'caja3'       => 'xockcgsc8k000o8osw8o88ko',
    ];

    public function __construct()
    {
        $this->baseUrl = rtrim(env('COOLIFY_API_URL', 'http://76.13.126.63:8000'), '/');
        $this->token   = env('COOLIFY_API_TOKEN', '');
    }

    public function index(): JsonResponse
    {
        if (empty($this->token)) {
            return response()->json([
                'success' => false,
                'message' => 'COOLIFY_API_TOKEN not configured',
            ], 500);
        }

        $tasks = [];

        foreach ($this->apps as $appName => $uuid) {
            try {
                $resp = Http::withToken($this->token)
                    ->accept('application/json')
                    ->timeout(5)
                    ->get("{$this->baseUrl}/api/v1/applications/{$uuid}/scheduled-tasks");

                if (!$resp->successful()) continue;

                foreach ($resp->json() as $task) {
                    $taskUuid = $task['uuid'];

                    // Fetch last 5 executions
                    $execResp = Http::withToken($this->token)
                        ->accept('application/json')
                        ->timeout(5)
                        ->get("{$this->baseUrl}/api/v1/applications/{$uuid}/scheduled-tasks/{$taskUuid}/executions");

                    $executions = $execResp->successful() ? $execResp->json() : [];
                    $total = count($executions);
                    $failures = collect($executions)->where('status', '!=', 'success')->count();
                    $lastExec = $executions[0] ?? null;

                    $tasks[] = [
                        'app'            => $appName,
                        'name'           => $task['name'],
                        'frequency'      => $task['frequency'],
                        'enabled'        => $task['enabled'],
                        'last_status'    => $lastExec['status'] ?? null,
                        'last_message'   => $lastExec['message'] ?? null,
                        'last_run'       => $lastExec['finished_at'] ?? null,
                        'last_duration'  => $lastExec['duration'] ?? null,
                        'total_runs'     => $total,
                        'failures'       => $failures,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("CronjobController: failed to fetch tasks for {$appName}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['success' => true, 'data' => $tasks]);
    }
}
