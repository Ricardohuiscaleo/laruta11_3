<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\R11CreditTransaction;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::where('es_credito_r11', 1)
            ->with('personal')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'nombre' => $u->nombre,
                    'email' => $u->email,
                    'personal' => $u->personal,
                    'limite' => (float) $u->limite_credito_r11,
                    'usado' => (float) $u->credito_r11_usado,
                    'disponible' => (float) ($u->limite_credito_r11 - $u->credito_r11_usado),
                    'aprobado' => (bool) $u->credito_r11_aprobado,
                    'bloqueado' => (bool) $u->credito_r11_bloqueado,
                    'relacion_r11' => $u->relacion_r11,
                    'fecha_aprobacion' => $u->fecha_aprobacion_r11,
                ];
            });

        return response()->json(['success' => true, 'data' => $usuarios]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'limite_credito_r11' => 'required|numeric|min:0',
        ]);

        $usuario = Usuario::findOrFail($id);
        $usuario->update([
            'credito_r11_aprobado' => 1,
            'limite_credito_r11' => $data['limite_credito_r11'],
            'fecha_aprobacion_r11' => now()->toDateString(),
        ]);

        return response()->json(['success' => true, 'data' => $usuario]);
    }

    public function reject(int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->update([
            'credito_r11_aprobado' => 0,
            'limite_credito_r11' => 0,
        ]);

        return response()->json(['success' => true, 'data' => $usuario]);
    }

    public function manualPayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $usuario = Usuario::findOrFail($id);
        $monto = (float) $data['monto'];

        R11CreditTransaction::create([
            'user_id' => $usuario->id,
            'amount' => $monto,
            'type' => 'refund',
            'description' => $data['descripcion'] ?? 'Pago manual admin',
        ]);

        $nuevoUsado = max(0, (float) $usuario->credito_r11_usado - $monto);
        $updateData = [
            'credito_r11_usado' => $nuevoUsado,
            'fecha_ultimo_pago_r11' => now()->toDateString(),
        ];

        if ($nuevoUsado == 0 && $usuario->credito_r11_bloqueado) {
            $updateData['credito_r11_bloqueado'] = 0;
        }

        $usuario->update($updateData);

        return response()->json(['success' => true, 'data' => $usuario]);
    }
}
