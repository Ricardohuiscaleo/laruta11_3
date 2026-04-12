<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Loan\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    /**
     * GET /api/v1/worker/loans
     *
     * List adelantos for the authenticated worker, ordered by created_at desc.
     */
    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $prestamos = $this->loanService->getPrestamosPorPersonal($personal->id);

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * GET /api/v1/worker/loans/info
     *
     * Get adelanto info (max amount, days worked, etc.) for the authenticated worker.
     */
    public function info(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $info = $this->loanService->getInfoAdelanto($personal->id);

        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    /**
     * POST /api/v1/worker/loans
     *
     * Create an adelanto request. Cuotas is always 1 (no installments).
     */
    public function store(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $data = $request->validate([
            'monto' => 'required|numeric|min:1',
            'motivo' => 'nullable|string|max:255',
        ]);

        try {
            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                (float) $data['monto'],
                $data['motivo'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $prestamo,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'adelanto activo') ? 409 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
