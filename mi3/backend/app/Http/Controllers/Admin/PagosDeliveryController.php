<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailySettlement;
use App\Models\Compra;
use App\Services\Delivery\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PagosDeliveryController extends Controller
{
    public function __construct(
        private SettlementService $settlementService
    ) {}

    public function settlements(Request $request)
    {
        $query = DailySettlement::query()->orderBy('settlement_date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from')) {
            $query->where('settlement_date', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->where('settlement_date', '<=', $request->to);
        }

        $settlements = $query->paginate($request->per_page ?? 50);

        return response()->json([
            'success' => true,
            'settlements' => $settlements->items(),
            'meta' => [
                'total' => $settlements->total(),
                'page' => $settlements->currentPage(),
                'per_page' => $settlements->perPage(),
                'pending_count' => DailySettlement::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $settlement = DailySettlement::findOrFail($id);
        return response()->json([
            'success' => true,
            'settlement' => $settlement,
        ]);
    }

    public function uploadVoucher(Request $request, int $id)
    {
        $request->validate([
            'comprobante' => 'required|image|max:10240',
        ]);

        $settlement = DailySettlement::findOrFail($id);

        if ($settlement->status === 'paid') {
            return response()->json(['success' => false, 'error' => 'Esta liquidación ya está pagada'], 400);
        }

        $file = $request->file('comprobante');
        $path = $file->store('delivery-payments', 's3');

        $settlement->update([
            'payment_voucher_url' => Storage::disk('s3')->url($path),
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => $request->user()->personal_id ?? null,
        ]);

        $this->settlementService->createCompraFromSettlement($settlement);

        return response()->json([
            'success' => true,
            'settlement' => $settlement->fresh(),
        ]);
    }

    public function generate(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        try {
            $settlement = $this->settlementService->generateDailySettlement(\Carbon\Carbon::parse($request->date));
            return response()->json(['success' => true, 'settlement' => $settlement]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function history(Request $request)
    {
        $pagos = DB::table('rider_pagos')
            ->join('riders', 'rider_pagos.rider_id', '=', 'riders.id')
            ->leftJoin('tuu_orders', 'rider_pagos.order_id', '=', 'tuu_orders.id')
            ->select(
                'rider_pagos.*',
                'riders.nombre as rider_nombre',
                'tuu_orders.order_number',
                'tuu_orders.delivery_address',
                'tuu_orders.delivery_fee'
            )
            ->orderBy('rider_pagos.fecha', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json(['success' => true, 'pagos' => $pagos->items(), 'meta' => [
            'total' => $pagos->total(),
            'page' => $pagos->currentPage(),
        ]]);
    }
}
