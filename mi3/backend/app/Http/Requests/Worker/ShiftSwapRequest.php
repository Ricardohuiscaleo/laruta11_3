<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class ShiftSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_turno' => 'required|date|after:today',
            'compañero_id' => 'required|integer|exists:personal,id',
            'motivo' => 'nullable|string|max:255',
        ];
    }
}
