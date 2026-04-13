<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Credit\R11CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly R11CreditService $creditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $usuario = $personal->usuario;

        $data = [
            'nombre' => $personal->nombre,
            'email' => $personal->email ?? $usuario?->email,
            'telefono' => $personal->telefono ?? $usuario?->telefono,
            'rut' => $personal->rut,
            'rol' => $personal->getRolesArray(),
            'foto_perfil' => $personal->foto_url ?? $usuario?->foto_perfil ?? null,
            'fecha_registro' => $personal->fecha_registro ?? null,
            'sueldos_base' => [
                'cajero' => (float) $personal->sueldo_base_cajero,
                'planchero' => (float) $personal->sueldo_base_planchero,
                'seguridad' => (float) $personal->sueldo_base_seguridad,
                'admin' => (float) $personal->sueldo_base_admin,
            ],
        ];

        if ($usuario && $usuario->es_credito_r11) {
            $data['credito_r11'] = $this->creditService->getCreditInfo($usuario);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
