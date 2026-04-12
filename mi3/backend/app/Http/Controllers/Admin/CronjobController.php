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
        $this->baseUrl = rtrim(env('COOLIFY_API_URL', 'http://host.docker.internal:8000'), '/');
        $this->token   = env('COOLIFY_API_TOKEN', '');
    }

    public function index(): JsonResponse
    {
        // Fallback: try multiple URLs if token is set
        if (empty($this->token)) {
            // Try to read from getenv directly
            $this->token = getenv('COOLIFY_API_TOKEN') ?: '';
        }
        if (empty($this->token)) {
            return response()->json([
                'success' => false,
                'message' => 'COOLIFY_API_TOKEN not configured',
                'debug_env' => array_filter(array_keys($_ENV), fn($k) => str_contains($k, 'COOLIFY')),
            ], 500);
        }

        // Try multiple base URLs (container networking varies)
        $baseUrls = array_unique(array_filter([
            $this->baseUrl,
            'http://host.docker.internal:8000',
            'http://172.17.0.1:8000',        // Docker bridge default gateway
            'http://coolify:8000',            // Coolify container name on coolify network
        ]));

        $tasks = [];

        foreach ($this->apps as $appName => $uuid) {
            foreach ($baseUrls as $baseUrl) {
                try {
                    $resp = Http::withToken($this->token)
                        ->accept('application/json')
                        ->timeout(3)
                        ->get("{$baseUrl}/api/v1/applications/{$uuid}/scheduled-tasks");

                    if (!$resp->successful()) continue;

                    // This URL works — use it for executions too
                    foreach ($resp->json() as $task) {
                        $taskUuid = $task['uuid'];

                        $execResp = Http::withToken($this->token)
                            ->accept('application/json')
                            ->timeout(3)
                            ->get("{$baseUrl}/api/v1/applications/{$uuid}/scheduled-tasks/{$taskUuid}/executions");

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
                    break; // This URL worked, don't try others for this app
                } catch (\Exception $e) {
                    continue; // Try next URL
                }
            }
        }

        return response()->json(['success' => true, 'data' => $tasks]);
    }
}
