<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'rol' => 'required|array|min:1',
            'rol.*' => 'string|in:administrador,cajero,planchero,delivery,seguridad,dueño,rider',
            'sueldo_base_cajero' => 'nullable|numeric|min:0',
            'sueldo_base_planchero' => 'nullable|numeric|min:0',
            'sueldo_base_admin' => 'nullable|numeric|min:0',
            'sueldo_base_seguridad' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
        ];
    }
}
