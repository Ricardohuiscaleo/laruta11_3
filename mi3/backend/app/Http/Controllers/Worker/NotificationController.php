<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $result = $this->notificationService->getForPersonal($personal->id);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'no_leidas' => $result['no_leidas'],
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $personal = $request->get('personal');

        $updated = $this->notificationService->marcarLeida($id, $personal->id);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'error' => 'Notificación no encontrada',
            ], 404);
        }

        return response()->json(['success' => true]);
    }
}
