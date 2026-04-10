<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Credit\R11CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        private readonly R11CreditService $creditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $usuario = $personal->usuario;

        if (!$usuario || !$usuario->es_credito_r11) {
            return response()->json([
                'success' => false,
                'error' => 'No tienes crédito R11 activo',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->creditService->getCreditInfo($usuario),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $usuario = $personal->usuario;

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'error' => 'No tienes usuario vinculado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->creditService->getTransactions($usuario->id),
        ]);
    }
}
