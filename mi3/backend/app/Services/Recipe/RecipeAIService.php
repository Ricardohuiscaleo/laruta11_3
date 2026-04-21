<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecipeAIService
{
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';
    private string $model = 'gemini-2.5-flash-lite';

    public function __construct()
    {
        $this->apiKey = (string) env('GOOGLE_API_KEY', '');
    }

    /**
     * Generate a recipe suggestion using AI.
     */
    public function suggestRecipe(string $description, ?int $categoryId = null): array
    {
        $ingredients = $this->getAvailableIngredients();
        $portions = $this->getPortionStandards($categoryId);
        $categoryName = $categoryId
            ? DB::table('categories')->where('id', $categoryId)->value('name') ?? 'General'
            : 'General';

        $ingredientList = collect($ingredients)->map(
            fn ($i) => "{$i->id}|{$i->name}|{$i->unit}|\${$i->cost_per_unit}/{$i->unit}|stock:{$i->current_stock}"
        )->implode("\n");

        $portionHints = '';
        if (!empty($portions)) {
            $portionHints = "\n\nPorciones estándar para '{$categoryName}':\n";
            foreach ($portions as $p) {
                $portionHints .= "- {$p->ingredient_name}: {$p->quantity} {$p->unit}\n";
            }
        }

        $prompt = <<<PROMPT
Eres chef experto en comida rápida chilena (hamburguesas, sandwiches, completos, papas).
Crear: "{$description}"
Categoría: {$categoryName}

Inventario (id|nombre|unidad|costo|stock):
{$ingredientList}
{$portionHints}
Usa SOLO ingredientes del inventario. Incluye packaging (caja, bolsa). Responde JSON.
Precio sugerido con margen ~65%, redondeado a múltiplo de \$100 CLP.
PROMPT;

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'name' => ['type' => 'STRING'],
                'description' => ['type' => 'STRING'],
                'suggested_price' => ['type' => 'INTEGER'],
                'ingredients' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'ingredient_id' => ['type' => 'INTEGER'],
                            'name' => ['type' => 'STRING'],
                            'quantity' => ['type' => 'NUMBER'],
                            'unit' => ['type' => 'STRING'],
                            'reason' => ['type' => 'STRING'],
                        ],
                        'required' => ['ingredient_id', 'name', 'quantity', 'unit'],
                    ],
                ],
                'missing_ingredients' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'tips' => ['type' => 'STRING'],
            ],
            'required' => ['name', 'description', 'suggested_price', 'ingredients'],
        ];

        $response = $this->callGemini($prompt, $schema);
        if (!$response) {
            return ['error' => 'No se pudo generar la receta. Intenta de nuevo.'];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return ['error' => 'Respuesta vacía de IA.'];
        }

        $parsed = json_decode($text, true);
        if (!$parsed) {
            return ['error' => 'Error al parsear respuesta de IA.'];
        }

        // Enrich with real costs from DB
        $totalCost = 0;
        foreach ($parsed['ingredients'] ?? [] as &$ing) {
            $dbIng = DB::table('ingredients')->find($ing['ingredient_id']);
            if ($dbIng) {
                $ing['cost_per_unit'] = (float) $dbIng->cost_per_unit;
                $ing['ingredient_unit'] = $dbIng->unit;
                $ing['in_stock'] = (float) $dbIng->current_stock > 0;
                $cost = $this->calcCost((float) $dbIng->cost_per_unit, $dbIng->unit, (float) $ing['quantity'], $ing['unit']);
                $ing['estimated_cost'] = round($cost);
                $totalCost += $cost;
            }
        }
        unset($ing);

        $parsed['total_cost'] = round($totalCost);
        $parsed['margin'] = $parsed['suggested_price'] > 0
            ? round((1 - $totalCost / $parsed['suggested_price']) * 100, 1)
            : 0;

        return $parsed;
    }

    private function getAvailableIngredients(): array
    {
        return DB::table('ingredients')
            ->where('is_active', true)
            ->select('id', 'name', 'unit', 'cost_per_unit', 'current_stock', 'category')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    private function getPortionStandards(?int $categoryId): array
    {
        if (!$categoryId) {
            return [];
        }

        return DB::table('portion_standards')
            ->join('ingredients', 'ingredients.id', '=', 'portion_standards.ingredient_id')
            ->where('portion_standards.category_id', $categoryId)
            ->select('portion_standards.ingredient_id', 'ingredients.name as ingredient_name', 'portion_standards.quantity', 'portion_standards.unit')
            ->get()
            ->toArray();
    }

    private function calcCost(float $costPerUnit, string $ingUnit, float $qty, string $recUnit): float
    {
        $f = ['kg' => ['g', 1000], 'g' => ['g', 1], 'L' => ['ml', 1000], 'ml' => ['ml', 1], 'unidad' => ['unidad', 1]];
        $ic = $f[$ingUnit] ?? null;
        $rc = $f[$recUnit] ?? null;
        if (!$ic || !$rc || $ic[0] !== $rc[0]) {
            return $costPerUnit * $qty;
        }

        return ($costPerUnit / $ic[1]) * ($qty * $rc[1]);
    }

    private function callGemini(string $prompt, array $schema): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('[RecipeAIService] GOOGLE_API_KEY not configured');
            return null;
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        $payload = [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            Log::error("[RecipeAIService] cURL: {$err}");
            return null;
        }
        if ($code < 200 || $code >= 300) {
            Log::error("[RecipeAIService] HTTP {$code}: " . substr((string) $body, 0, 500));
            return null;
        }

        return json_decode((string) $body, true);
    }
}
