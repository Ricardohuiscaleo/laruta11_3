<?php

namespace App\Http\Controllers;

use App\Events\StockActualizado;
use App\Events\VentaNueva;
use App\Events\VentaRegistrada;
use App\Services\Ventas\VentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Receive sale events from app3/caja3.
     * POST /api/v1/webhook/venta
     */
    public function venta(Request $request): JsonResponse
    {
        $secret = $request->header('X-Webhook-Secret');
        if ($secret !== config('app.webhook_secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'order_number' => 'required|string',
            'monto' => 'required|numeric',
            'source' => 'required|string|in:app3,caja3',
            'customer_name' => 'nullable|string',
        ]);

        broadcast(new VentaRegistrada(
            $data['order_number'],
            (float) $data['monto'],
            $data['source'],
            $data['customer_name'] ?? null,
        ));

        broadcast(new StockActualizado('venta'));

        // Broadcast updated KPIs for the admin Ventas realtime section
        $kpis = app(VentasService::class)->getKpis('shift_today');
        broadcast(new VentaNueva($kpis));

        return response()->json(['success' => true]);
    }

    /**
     * Generic stock change notification.
     * POST /api/v1/webhook/stock
     */
    public function stock(Request $request): JsonResponse
    {
        $secret = $request->header('X-Webhook-Secret');
        if ($secret !== config('app.webhook_secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        broadcast(new StockActualizado(
            $request->input('tipo', 'manual'),
            $request->input('ingredient_id'),
        ));

        return response()->json(['success' => true]);
    }
}
