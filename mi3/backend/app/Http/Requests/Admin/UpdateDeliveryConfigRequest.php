<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.config_key' => 'required|string|in:tarifa_base,card_surcharge,distance_threshold_km,surcharge_per_bracket,bracket_size_km,rl6_discount_factor',
            'items.*.config_value' => 'required',
        ];
    }

    /**
     * Add custom validation for numeric values and rl6_discount_factor range.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            $numericKeys = [
                'tarifa_base',
                'card_surcharge',
                'distance_threshold_km',
                'surcharge_per_bracket',
                'bracket_size_km',
            ];

            foreach ($items as $index => $item) {
                $key = $item['config_key'] ?? null;
                $value = $item['config_value'] ?? null;

                if ($key === null || $value === null) {
                    continue;
                }

                if (in_array($key, $numericKeys) && !is_numeric($value)) {
                    $validator->errors()->add(
                        "items.{$index}.config_value",
                        "El valor de {$key} debe ser numérico."
                    );
                }

                if ($key === 'rl6_discount_factor') {
                    if (!is_numeric($value)) {
                        $validator->errors()->add(
                            "items.{$index}.config_value",
                            "El valor de rl6_discount_factor debe ser numérico."
                        );
                    } elseif ((float) $value < 0.0 || (float) $value > 1.0) {
                        $validator->errors()->add(
                            "items.{$index}.config_value",
                            "El valor de rl6_discount_factor debe estar entre 0.0 y 1.0."
                        );
                    }
                }
            }
        });
    }
}
