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
     * List loans for the authenticated worker, ordered by created_at desc.
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
     * POST /api/v1/worker/loans
     *
     * Create a loan request with validation.
     */
    public function store(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $data = $request->validate([
            'monto' => 'required|numeric|min:1',
            'cuotas' => 'required|integer|min:1|max:3',
            'motivo' => 'nullable|string|max:255',
        ]);

        try {
            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                (float) $data['monto'],
                (int) $data['cuotas'],
                $data['motivo'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $prestamo,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'préstamo activo') ? 409 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
