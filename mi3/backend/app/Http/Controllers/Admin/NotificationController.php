<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'personal_id' => 'required|integer|exists:personal,id',
            'tipo' => 'required|string|in:turno,liquidacion,credito,ajuste,sistema',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
        ]);

        $notificacion = $this->notificationService->crear(
            $data['personal_id'],
            $data['tipo'],
            $data['titulo'],
            $data['mensaje'],
        );

        return response()->json(['success' => true, 'data' => $notificacion], 201);
    }
}
