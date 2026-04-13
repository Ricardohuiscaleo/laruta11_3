<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Rendicion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RendicionController extends Controller
{
    /**
     * List rendiciones with summary.
     * GET /api/v1/admin/rendiciones
     */
    public function index(): JsonResponse
    {
        $rendiciones = Rendicion::orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'rendiciones' => $rendiciones]);
    }

    /**
     * Preview: calculate what a new rendición would look like.
     * GET /api/v1/admin/rendiciones/preview
     */
    public function preview(): JsonResponse
    {
        // Saldo anterior = saldo_nuevo of last approved rendición
        $lastApproved = Rendicion::where('estado', 'aprobada')
            ->orderBy('aprobado_at', 'desc')
            ->first();

        $saldoAnterior = $lastApproved ? (float) $lastApproved->saldo_nuevo : 0;

        // Compras sin rendir
        $comprasSinRendir = Compra::whereNull('rendicion_id')
            ->where('estado', 'pagado')
            ->orderBy('fecha_compra', 'asc')
            ->with('detalles')
            ->get();

        $totalCompras = $comprasSinRendir->sum('monto_total');
        $saldoResultante = $saldoAnterior - $totalCompras;

        return response()->json([
            'success' => true,
            'saldo_anterior' => $saldoAnterior,
            'total_compras' => $totalCompras,
            'saldo_resultante' => $saldoResultante,
            'compras_count' => $comprasSinRendir->count(),
            'compras' => $comprasSinRendir->map(fn ($c) => [
                'id' => $c->id,
                'fecha_compra' => $c->fecha_compra?->format('Y-m-d'),
                'proveedor' => $c->proveedor,
                'monto_total' => $c->monto_total,
                'items' => $c->detalles->map(fn ($d) => [
                    'nombre' => $d->nombre_item,
                    'cantidad' => $d->cantidad,
                    'unidad' => $d->unidad,
                ]),
                'imagenes' => $c->imagen_respaldo ?? [],
            ]),
        ]);
    }

    /**
     * Create a new rendición (Ricardo generates it).
     * POST /api/v1/admin/rendiciones
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'notas' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request) {
            $lastApproved = Rendicion::where('estado', 'aprobada')
                ->orderBy('aprobado_at', 'desc')
                ->first();

            $saldoAnterior = $lastApproved ? (float) $lastApproved->saldo_nuevo : 0;

            $comprasSinRendir = Compra::whereNull('rendicion_id')
                ->where('estado', 'pagado')
                ->get();

            $totalCompras = $comprasSinRendir->sum('monto_total');
            $saldoResultante = $saldoAnterior - $totalCompras;

            $rendicion = Rendicion::create([
                'saldo_anterior' => $saldoAnterior,
                'total_compras' => $totalCompras,
                'saldo_resultante' => $saldoResultante,
                'estado' => 'pendiente',
                'notas' => $request->input('notas'),
                'creado_por' => 'Ricardo',
            ]);

            // Link compras to this rendición
            Compra::whereNull('rendicion_id')
                ->where('estado', 'pagado')
                ->update(['rendicion_id' => $rendicion->id]);

            return response()->json([
                'success' => true,
                'rendicion' => $rendicion,
                'token' => $rendicion->token,
                'compras_rendidas' => $comprasSinRendir->count(),
            ]);
        });
    }

    /**
     * Show rendición detail (used by public page too).
     * GET /api/v1/admin/rendiciones/{token}
     */
    public function show(string $token): JsonResponse
    {
        $rendicion = Rendicion::where('token', $token)->firstOrFail();

        $compras = Compra::where('rendicion_id', $rendicion->id)
            ->with('detalles')
            ->orderBy('fecha_compra', 'asc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'fecha_compra' => $c->fecha_compra?->format('d-m-Y'),
                'proveedor' => $c->proveedor,
                'monto_total' => $c->monto_total,
                'items' => $c->detalles->map(fn ($d) => [
                    'nombre' => $d->nombre_item,
                    'cantidad' => $d->cantidad,
                    'unidad' => $d->unidad,
                    'precio_unitario' => $d->precio_unitario,
                ]),
                'imagenes' => $c->imagen_respaldo ?? [],
            ]);

        return response()->json([
            'success' => true,
            'rendicion' => $rendicion,
            'compras' => $compras,
        ]);
    }

    /**
     * Approve rendición (Yojhans approves + sets transfer amount).
     * POST /api/v1/admin/rendiciones/{token}/aprobar
     */
    public function aprobar(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'monto_transferido' => 'required|numeric|min:0',
            'notas' => 'nullable|string|max:500',
        ]);

        $rendicion = Rendicion::where('token', $token)->firstOrFail();

        if ($rendicion->estado !== 'pendiente') {
            return response()->json([
                'success' => false,
                'error' => 'Esta rendición ya fue ' . $rendicion->estado,
            ], 422);
        }

        $montoTransferido = (float) $request->input('monto_transferido');
        $saldoNuevo = $montoTransferido + $rendicion->saldo_resultante;

        $rendicion->update([
            'monto_transferido' => $montoTransferido,
            'saldo_nuevo' => $saldoNuevo,
            'estado' => 'aprobada',
            'aprobado_por' => 'Yojhans',
            'aprobado_at' => now(),
            'notas' => $request->input('notas') ?? $rendicion->notas,
        ]);

        return response()->json([
            'success' => true,
            'rendicion' => $rendicion->fresh(),
            'saldo_nuevo' => $saldoNuevo,
        ]);
    }

    /**
     * Reject rendición.
     * POST /api/v1/admin/rendiciones/{token}/rechazar
     */
    public function rechazar(Request $request, string $token): JsonResponse
    {
        $rendicion = Rendicion::where('token', $token)->firstOrFail();

        if ($rendicion->estado !== 'pendiente') {
            return response()->json([
                'success' => false,
                'error' => 'Esta rendición ya fue ' . $rendicion->estado,
            ], 422);
        }

        // Unlink compras so they can be included in next rendición
        Compra::where('rendicion_id', $rendicion->id)
            ->update(['rendicion_id' => null]);

        $rendicion->update([
            'estado' => 'rechazada',
            'notas' => $request->input('notas') ?? $rendicion->notas,
        ]);

        return response()->json(['success' => true, 'rendicion' => $rendicion->fresh()]);
    }
}
