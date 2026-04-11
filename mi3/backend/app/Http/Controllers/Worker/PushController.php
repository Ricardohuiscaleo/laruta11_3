<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Notification\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    /**
     * POST /api/v1/worker/push/subscribe
     *
     * Save a push subscription for the authenticated worker.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => 'required|array',
            'subscription.endpoint' => 'required|string|url',
            'subscription.keys' => 'required|array',
            'subscription.keys.p256dh' => 'required|string',
            'subscription.keys.auth' => 'required|string',
        ]);

        $personal = $request->get('personal');

        $this->pushNotificationService->suscribir(
            $personal->id,
            $request->input('subscription')
        );

        return response()->json(['success' => true]);
    }
}
