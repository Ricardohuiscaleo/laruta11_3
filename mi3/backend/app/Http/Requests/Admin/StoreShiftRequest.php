<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal_id' => 'required|integer|exists:personal,id',
            'fecha' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha',
            'tipo' => 'required|in:normal,reemplazo,seguridad,reemplazo_seguridad',
            'reemplazado_por' => 'nullable|integer|exists:personal,id',
            'monto_reemplazo' => 'nullable|numeric|min:0',
            'pago_por' => 'nullable|in:empresa,empresa_adelanto,personal',
        ];
    }
}
