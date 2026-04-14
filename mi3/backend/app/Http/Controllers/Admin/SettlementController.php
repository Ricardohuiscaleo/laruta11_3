<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailySettlement;
use App\Services\Delivery\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlementService,
    ) {}

    /**
     * GET /api/v1/admin/delivery/settlements
     * Lista DailySettlements ordenados por settlement_date DESC.
     */
    public function index(): JsonResponse
    {
        $settlements = DailySettlement::orderBy('settlement_date', 'desc')->get();

        return response()->json([
            'success'     => true,
            'settlements' => $settlements,
        ]);
    }

    /**
     * GET /api/v1/admin/delivery/settlements/{id}
     * Detalle de un settlement.
     */
    public function show(int $id): JsonResponse
    {
        $settlement = DailySettlement::find($id);

        if (!$settlement) {
            return response()->json([
                'success' => false,
                'error'   => 'Settlement no encontrado',
            ], 404);
        }

        return response()->json([
            'success'    => true,
            'settlement' => $settlement,
        ]);
    }

    /**
     * POST /api/v1/admin/delivery/settlements/{id}/voucher
     * Sube comprobante de pago y marca el settlement como pagado.
     * Si la creación de compra falla, incluye 'warning' en la respuesta.
     */
    public function uploadVoucher(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'voucher' => 'required|file|max:10240',
        ]);

        $personal = $request->get('personal');

        $result = $this->settlementService->uploadVoucherAndPay(
            settlementId: $id,
            file: $request->file('voucher'),
            paidBy: $personal->id,
        );

        $response = [
            'success'    => true,
            'settlement' => $result['settlement'],
            'compra_id'  => $result['compra_id'],
        ];

        if (!$result['compra_created']) {
            $response['warning'] = 'El pago fue registrado pero no se pudo crear la compra automáticamente. Por favor, créala manualmente.';
        }

        return response()->json($response);
    }
}
