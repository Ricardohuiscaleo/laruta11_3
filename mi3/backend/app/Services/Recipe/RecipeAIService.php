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
        $this->apiKey = (string) env('GOOGLE_API_KEY', env('google_api_key', ''));
    }

    /**
     * Generate 3 recipe variants using AI.
     */
    public function suggestRecipe(string $description, ?int $categoryId = null): array
    {
        $ingredients = $this->getAvailableIngredients();
        $portions = $this->getPortionStandards($categoryId);
        $existingProducts = $this->getExistingProducts();
        $categoryName = $categoryId
            ? DB::table('categories')->where('id', $categoryId)->value('name') ?? 'General'
            : 'General';

        $ingredientList = collect($ingredients)->map(
            fn ($i) => "{$i->id}|{$i->name}|{$i->unit}|\${$i->cost_per_unit}/{$i->unit}|stock:{$i->current_stock}"
        )->implode("\n");

        $productList = collect($existingProducts)->map(
            fn ($p) => "{$p->name} (\${$p->price})"
        )->implode(', ');

        $portionHints = '';
        if (!empty($portions)) {
            $portionHints = "\nPorciones estándar para '{$categoryName}':\n";
            foreach ($portions as $p) {
                $portionHints .= "- {$p->ingredient_name}: {$p->quantity} {$p->unit}\n";
            }
        }

        $prompt = <<<PROMPT
Eres chef experto en comida rápida chilena. Genera 3 VARIANTES DIFERENTES del producto.
Crear: "{$description}"
Categoría: {$categoryName}

Productos existentes (NO repetir): {$productList}

Inventario (id|nombre|unidad|costo|stock):
{$ingredientList}
{$portionHints}
Reglas:
- Usa SOLO ingredientes del inventario
- Incluye packaging (caja, bolsa) en cada variante
- Cada variante debe ser diferente (ingredientes distintos, tamaños, estilos)
- Variante 1: versión clásica/estándar
- Variante 2: versión premium o especial
- Variante 3: versión creativa o fusión
- Precio sugerido con margen ~65%, redondeado a múltiplo de \$100 CLP
- Descripción corta para menú (máx 60 chars)
PROMPT;

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'variants' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'description' => ['type' => 'STRING'],
                            'style' => ['type' => 'STRING'],
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
                        'required' => ['name', 'description', 'style', 'suggested_price', 'ingredients'],
                    ],
                ],
            ],
            'required' => ['variants'],
        ];

        $response = $this->callGemini($prompt, $schema, 3072);
        if (!$response) {
            return ['error' => 'No se pudo generar la receta. Intenta de nuevo.'];
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return ['error' => 'Respuesta vacía de IA.'];
        }

        $parsed = json_decode($text, true);
        if (!$parsed || empty($parsed['variants'])) {
            return ['error' => 'Error al parsear respuesta de IA.'];
        }

        // Enrich each variant with real costs
        foreach ($parsed['variants'] as &$variant) {
            $totalCost = 0;
            foreach ($variant['ingredients'] ?? [] as &$ing) {
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
            $variant['total_cost'] = round($totalCost);
            $variant['margin'] = $variant['suggested_price'] > 0
                ? round((1 - $totalCost / $variant['suggested_price']) * 100, 1)
                : 0;
        }
        unset($variant);

        return $parsed;
    }

    /**
     * Generate a short product description using AI based on name + ingredients.
     * Returns: ['description' => string, 'tokens' => int, 'cost_clp' => float]
     */
    public function generateDescription(int $productId): array
    {
        $product = \App\Models\Product::findOrFail($productId);

        // Get recipe ingredients
        $ingredients = DB::table('product_recipes')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->where('product_recipes.product_id', $productId)
            ->select('ingredients.name', 'product_recipes.quantity', 'product_recipes.unit')
            ->get();

        $ingredientList = $ingredients->map(fn($i) => "{$i->name} ({$i->quantity}{$i->unit})")->implode(', ');

        $categoryName = DB::table('categories')->where('id', $product->category_id)->value('name') ?? 'Producto';

        $prompt = <<<PROMPT
Eres el copywriter de "La Ruta 11", un restaurante de comida rápida gourmet en Arica, Chile.

Genera una descripción CORTA (máximo 120 caracteres) para el menú digital del siguiente producto:

Nombre: {$product->name}
Categoría: {$categoryName}
Ingredientes: {$ingredientList}
Precio: \${$product->price}

Reglas:
- Máximo 120 caracteres
- En español chileno casual pero apetitoso
- Destaca los ingredientes principales (no todos)
- No menciones el precio
- No uses emojis
- Debe provocar antojo
PROMPT;

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'description' => ['type' => 'STRING', 'description' => 'Descripción corta del producto (max 120 chars)'],
            ],
            'required' => ['description'],
        ];

        $result = $this->callGemini($prompt, $schema, 256);

        if (!$result) {
            throw new \RuntimeException('Gemini no respondió');
        }

        // Extract tokens
        $usage = $result['usageMetadata'] ?? [];
        $promptTokens = $usage['promptTokenCount'] ?? 0;
        $candidateTokens = $usage['candidatesTokenCount'] ?? 0;
        $totalTokens = $usage['totalTokenCount'] ?? ($promptTokens + $candidateTokens);

        // Cost: Gemini Flash Lite ~$0.075/1M input, $0.30/1M output
        $costUsd = ($promptTokens * 0.075 / 1_000_000) + ($candidateTokens * 0.30 / 1_000_000);
        $costClp = round($costUsd * 950, 2); // ~950 CLP/USD

        // Parse response
        $text = '';
        $candidates = $result['candidates'] ?? [];
        if (!empty($candidates[0]['content']['parts'][0]['text'])) {
            $parsed = json_decode($candidates[0]['content']['parts'][0]['text'], true);
            $text = $parsed['description'] ?? '';
        }

        if (empty($text)) {
            throw new \RuntimeException('Gemini devolvió descripción vacía');
        }

        // Save to product
        $product->description = $text;
        $product->save();

        // Log token usage
        Log::info("[RecipeAIService] generateDescription product={$productId} tokens={$totalTokens} cost_clp={$costClp}");

        return [
            'description' => $text,
            'tokens' => $totalTokens,
            'cost_clp' => $costClp,
            'product_id' => $productId,
        ];
    }

    /**
     * Save a recipe variant as a new product.
     */
    public function saveVariant(array $data, int $categoryId): array
    {
        return DB::transaction(function () use ($data, $categoryId) {
            $product = \App\Models\Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $categoryId,
                'price' => $data['suggested_price'],
                'cost_price' => $data['total_cost'] ?? 0,
                'is_active' => false, // draft until manually activated
            ]);

            foreach ($data['ingredients'] as $ing) {
                DB::table('product_recipes')->insert([
                    'product_id' => $product->id,
                    'ingredient_id' => $ing['ingredient_id'],
                    'quantity' => $ing['quantity'],
                    'unit' => $ing['unit'],
                ]);
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'cost_price' => (float) $product->cost_price,
                'ingredient_count' => count($data['ingredients']),
            ];
        });
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

    private function getExistingProducts(): array
    {
        return DB::table('products')
            ->where('is_active', true)
            ->select('name', 'price', 'category_id')
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

    private function callGemini(string $prompt, array $schema, int $maxTokens = 2048): ?array
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
                'maxOutputTokens' => $maxTokens,
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
