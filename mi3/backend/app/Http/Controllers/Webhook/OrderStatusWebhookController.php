<?php

namespace App\Http\Controllers\Webhook;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/webhooks/order-status
 * Recibe cambios de estado de pedidos desde caja3 y emite eventos de broadcast.
 * Autenticado con un secret compartido (WEBHOOK_SECRET env var).
 */
class OrderStatusWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Verificar secret
        $secret = config('mi3.webhook_secret', env('WEBHOOK_SECRET', ''));
        $incoming = $request->header('X-Webhook-Secret') ?? $request->input('secret');

        if ($secret && $incoming !== $secret) {
            Log::warning('[OrderStatusWebhook] Unauthorized attempt', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'order_number' => 'required|string',
            'order_status' => 'required|string',
        ]);

        $orderNumber = $request->input('order_number');
        $newStatus   = $request->input('order_status');

        $order = DB::table('tuu_orders')
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        // Emitir evento de broadcast (no actualiza BD — caja3 ya lo hizo)
        broadcast(new OrderStatusUpdated(
            orderId: $order->id,
            orderNumber: $orderNumber,
            orderStatus: $newStatus,
            riderId: $order->rider_id ?? null,
            estimatedDeliveryTime: null,
            updatedAt: now()->toISOString(),
        ));

        Log::info('[OrderStatusWebhook] Broadcast emitido', [
            'order_number' => $orderNumber,
            'status'       => $newStatus,
        ]);

        return response()->json(['success' => true]);
    }
}
