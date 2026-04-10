<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personal_id' => 'required|integer|exists:personal,id',
            'mes' => 'required|date_format:Y-m',
            'monto' => 'required|numeric',
            'concepto' => 'required|string|max:255',
            'categoria_id' => 'required|integer|exists:ajustes_categorias,id',
            'notas' => 'nullable|string|max:500',
        ];
    }
}
