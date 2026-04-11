<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePersonalRequest;
use App\Models\Personal;
use Illuminate\Http\JsonResponse;

class PersonalController extends Controller
{
    public function index(): JsonResponse
    {
        $personal = Personal::with('usuario')->get();

        return response()->json(['success' => true, 'data' => $personal]);
    }

    public function store(StorePersonalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['rol'];
        $data['rol'] = implode(',', $roles);

        // Apply default base salary of $300.000 when null or 0
        $this->applyDefaultSueldo($data, $roles);

        $personal = Personal::create($data);

        return response()->json(['success' => true, 'data' => $personal], 201);
    }

    /**
     * Apply default base salary of $300.000 for each selected role
     * when the salary field is null or 0.
     */
    private function applyDefaultSueldo(array &$data, array $roles): void
    {
        $roleToField = [
            'cajero' => 'sueldo_base_cajero',
            'planchero' => 'sueldo_base_planchero',
            'administrador' => 'sueldo_base_admin',
            'seguridad' => 'sueldo_base_seguridad',
        ];

        foreach ($roleToField as $role => $field) {
            if (in_array($role, $roles)) {
                if (empty($data[$field]) || (float) $data[$field] === 0.0) {
                    $data[$field] = 300000;
                }
            }
        }
    }

    public function update(StorePersonalRequest $request, int $id): JsonResponse
    {
        $personal = Personal::findOrFail($id);
        $data = $request->validated();
        $data['rol'] = implode(',', $data['rol']);

        $personal->update($data);

        return response()->json(['success' => true, 'data' => $personal]);
    }

    public function toggle(int $id): JsonResponse
    {
        $personal = Personal::findOrFail($id);
        $personal->update(['activo' => !$personal->activo]);

        return response()->json(['success' => true, 'data' => $personal]);
    }
}
