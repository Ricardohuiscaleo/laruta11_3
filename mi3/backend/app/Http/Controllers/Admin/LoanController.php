<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestamo;
use App\Services\Loan\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    /**
     * GET /api/v1/admin/loans
     *
     * List all loans ordered by status priority and date.
     */
    public function index(): JsonResponse
    {
        $prestamos = $this->loanService->getTodosPrestamos();

        return response()->json([
            'success' => true,
            'data' => $prestamos,
        ]);
    }

    /**
     * POST /api/v1/admin/loans/{id}/approve
     *
     * Approve a pending loan with amount, start date, and notes.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $prestamo = Prestamo::find($id);

        if (!$prestamo) {
            return response()->json([
                'success' => false,
                'error' => 'Préstamo no encontrado',
            ], 404);
        }

        $data = $request->validate([
            'monto_aprobado' => 'required|numeric|min:1',
            'fecha_inicio_descuento' => 'nullable|date',
            'notas' => 'nullable|string|max:1000',
        ]);

        $admin = $request->get('personal');

        try {
            $this->loanService->aprobar(
                $prestamo,
                $admin->id,
                (float) $data['monto_aprobado'],
                $data['fecha_inicio_descuento'] ?? null,
                $data['notas'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $prestamo->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/admin/loans/{id}/reject
     *
     * Reject a pending loan with optional notes.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $prestamo = Prestamo::find($id);

        if (!$prestamo) {
            return response()->json([
                'success' => false,
                'error' => 'Préstamo no encontrado',
            ], 404);
        }

        $data = $request->validate([
            'notas' => 'nullable|string|max:1000',
        ]);

        $admin = $request->get('personal');

        try {
            $this->loanService->rechazar(
                $prestamo,
                $admin->id,
                $data['notas'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $prestamo->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
