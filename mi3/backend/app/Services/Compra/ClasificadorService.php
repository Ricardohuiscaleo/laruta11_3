<?php

declare(strict_types=1);

namespace App\Services\Compra;

use App\Models\Ingredient;
use App\Models\ProductEquivalence;
use App\Models\SupplierIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClasificadorService
{
    private string $modelId = 'amazon.nova-micro-v1:0';

    public function __construct(private AwsSignatureService $signer) {}

    /**
     * Classify image type using Nova Micro (text-only, no image needed).
     *
     * @param array $labels Rekognition DetectLabels results
     * @param array $texts  Rekognition DetectText results
     * @return array{tipo_imagen: string, confianza: float, razon: string}
     */
    public function clasificar(array $labels, array $texts): array
    {
        $labelNames = array_map(fn(array $l): string => $l['name'] ?? '', $labels);
        $textLines = array_map(fn(array $t): string => $t['text'] ?? '', $texts);

        $labelsStr = !empty($labelNames) ? implode(', ', $labelNames) : '(ninguno detectado)';
        $textsStr = !empty($textLines) ? implode(' | ', array_slice($textLines, 0, 30)) : '(ningún texto detectado)';

        $prompt = <<<PROMPT
Clasifica el tipo de imagen basándote en estos datos de Rekognition.

Labels detectados: {$labelsStr}
Textos detectados: {$textsStr}

Tipos posibles:
- "boleta": documento de venta con RUT, productos, total (supermercado, tienda)
- "factura": documento tributario formal con RUT emisor/receptor, IVA, detalle de items
- "producto": foto de un producto físico (caja, saco, bolsa, bandeja de ingrediente)
- "bascula": foto de báscula/balanza digital mostrando peso en display
- "transferencia": comprobante de transferencia bancaria o Mercado Pago
- "desconocido": no se puede determinar

Pistas:
- Si hay textos con RUT (XX.XXX.XXX-X), montos ($), "BOLETA" o "FACTURA" → boleta o factura
- Si hay labels como "Bag", "Box", "Food", "Vegetable" sin texto de montos → producto
- Si hay labels como "Scale", "Number" con textos numéricos cortos → bascula
- Si hay textos con "Transferencia", "Mercado Pago", "Comprobante" → transferencia
- "FACTURA ELECTRÓNICA" en texto → factura (no boleta)

Responde SOLO JSON: {"tipo_imagen": "...", "confianza": 0.0-1.0, "razon": "breve explicación"}
PROMPT;

        try {
            $region = $this->signer->getRegion();
            $endpoint = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$this->modelId}/converse";

            $body = json_encode([
                'messages' => [
                    ['role' => 'user', 'content' => [['text' => $prompt]]],
                ],
                'inferenceConfig' => [
                    'maxNewTokens' => 150,
                    'temperature' => 0.1,
                ],
            ]);

            $response = $this->signer->signedPost($endpoint, $body, 'bedrock', [], 8);

            if (!$response || ($response['__error'] ?? false)) {
                $errorMsg = $response['__message'] ?? 'no response';
                $httpCode = $response['__http_code'] ?? 0;
                Log::warning("[Clasificador] Nova Micro error: HTTP {$httpCode} — {$errorMsg}");
                $fallback = $this->fallbackClassification($labelNames, $textLines);
                $fallback['api_error'] = "HTTP {$httpCode}: {$errorMsg}";
                return $fallback;
            }

            $text = $response['output']['message']['content'][0]['text'] ?? '';
            $text = trim($text);

            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
                $text = trim($matches[1]);
            }

            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['tipo_imagen'])) {
                $validTypes = ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'];
                if (!in_array($decoded['tipo_imagen'], $validTypes, true)) {
                    $decoded['tipo_imagen'] = 'desconocido';
                }
                return [
                    'tipo_imagen' => $decoded['tipo_imagen'],
                    'confianza' => (float) ($decoded['confianza'] ?? 0.5),
                    'razon' => $decoded['razon'] ?? '',
                ];
            }

            Log::warning('[Clasificador] Could not parse Nova Micro response: ' . substr($text, 0, 200));
            return $this->fallbackClassification($labelNames, $textLines);
        } catch (\Exception $e) {
            Log::warning('[Clasificador] Error: ' . $e->getMessage());
            return $this->fallbackClassification($labelNames, $textLines);
        }
    }

    /**
     * Load DB context filtered by image type.
     *
     * @return array{suppliers: array, rut_map: array, products: array, patterns: array, equivalences: array, person_map: array}
     */
    public function cargarContexto(string $tipo): array
    {
        $context = [
            'suppliers' => [],
            'rut_map' => [],
            'products' => [],
            'patterns' => [],
            'equivalences' => [],
            'person_map' => [],
        ];

        try {
            // Always load suppliers (useful for all types)
            $supplierRecords = SupplierIndex::orderBy('frecuencia', 'desc')
                ->limit(20)
                ->get(['nombre_original', 'rut']);
            $context['suppliers'] = $supplierRecords->pluck('nombre_original')->toArray();

            $rutMap = [];
            foreach ($supplierRecords as $s) {
                if ($s->rut) {
                    $rutMap[$s->rut] = $s->nombre_original;
                }
            }
            $context['rut_map'] = $rutMap;

            // Type-specific context
            if (in_array($tipo, ['boleta', 'factura', 'producto', 'bascula'], true)) {
                $context['products'] = Ingredient::where('is_active', 1)
                    ->orderBy('name')
                    ->pluck('name')
                    ->toArray();
            }

            if (in_array($tipo, ['producto', 'bascula'], true)) {
                $context['equivalences'] = ProductEquivalence::all()
                    ->map(fn($eq) => [
                        'nombre' => $eq->nombre_normalizado,
                        'ingrediente' => $eq->nombre_ingrediente,
                        'cantidad_por_unidad' => $eq->cantidad_por_unidad,
                        'unidad' => $eq->unidad_real,
                    ])
                    ->toArray();
            }

            if ($tipo === 'transferencia') {
                $context['person_map'] = $this->getPersonToSupplierMap();
            }

            // Learned patterns (for boleta, factura, producto)
            if (in_array($tipo, ['boleta', 'factura', 'producto'], true)) {
                $context['patterns'] = $this->getLearnedPatterns($tipo);
            }
        } catch (\Exception $e) {
            Log::warning('[Clasificador] Failed to load context: ' . $e->getMessage());
        }

        return $context;
    }

    /**
     * Rule-based fallback when Nova Micro is unavailable.
     */
    private function fallbackClassification(array $labelNames, array $textLines): array
    {
        $allText = mb_strtolower(implode(' ', $textLines));
        $allLabels = mb_strtolower(implode(' ', $labelNames));

        // Check for document indicators
        if (str_contains($allText, 'factura electr') || str_contains($allText, 'factura elect')) {
            return ['tipo_imagen' => 'factura', 'confianza' => 0.8, 'razon' => 'Texto contiene "factura electrónica"'];
        }
        if (str_contains($allText, 'boleta') || (str_contains($allText, 'rut') && str_contains($allText, 'total'))) {
            return ['tipo_imagen' => 'boleta', 'confianza' => 0.7, 'razon' => 'Texto contiene indicadores de boleta'];
        }
        if (str_contains($allText, 'transferencia') || str_contains($allText, 'mercado pago') || str_contains($allText, 'comprobante')) {
            return ['tipo_imagen' => 'transferencia', 'confianza' => 0.7, 'razon' => 'Texto contiene indicadores de transferencia'];
        }

        // Check labels for physical objects
        $scaleLabels = ['scale', 'gauge', 'meter', 'display'];
        $productLabels = ['bag', 'box', 'food', 'vegetable', 'fruit', 'meat', 'package', 'sack'];

        foreach ($scaleLabels as $sl) {
            if (str_contains($allLabels, $sl)) {
                return ['tipo_imagen' => 'bascula', 'confianza' => 0.6, 'razon' => "Label '{$sl}' detectado"];
            }
        }
        foreach ($productLabels as $pl) {
            if (str_contains($allLabels, $pl)) {
                return ['tipo_imagen' => 'producto', 'confianza' => 0.6, 'razon' => "Label '{$pl}' detectado"];
            }
        }

        // If there's lots of text with $ signs, probably a receipt
        $dollarCount = substr_count($allText, '$');
        if ($dollarCount >= 3) {
            return ['tipo_imagen' => 'boleta', 'confianza' => 0.5, 'razon' => 'Múltiples montos detectados'];
        }

        return ['tipo_imagen' => 'desconocido', 'confianza' => 0.3, 'razon' => 'No se pudo clasificar'];
    }

    /**
     * Person-to-supplier mapping for transfer receipts.
     */
    private function getPersonToSupplierMap(): array
    {
        return [
            'karen miranda olmedo' => 'ARIAKA',
            'karen miranda' => 'ARIAKA',
            'elcia vilca' => 'ARIAKA',
            'eliana vilca' => 'ARIAKA',
            'cecilia rojas hinojosa' => 'ARIAKA',
            'cecilia rojas' => 'ARIAKA',
            'maria mondañez mamani' => 'ARIAKA',
            'maria mondanez mamani' => 'ARIAKA',
            'giovanna loza salas' => 'ARIAKA',
            'giovanna loza' => 'ARIAKA',
            'ariel araya' => 'ARIAKA',
            'ariel aliro araya villalobos' => 'ARIAKA',
            'karina roco' => 'ARIAKA',
            'elton san martin' => 'Abastible',
            'elton san martín' => 'Abastible',
            'karina andrea muñoz ahumada' => 'Ariztía (proveedor)',
            'karina muñoz' => 'Ariztía (proveedor)',
            'lucila cacera' => 'agro-lucila',
        ];
    }

    /**
     * Get learned patterns filtered by image type.
     */
    private function getLearnedPatterns(string $tipo): array
    {
        $patterns = [];

        try {
            if (in_array($tipo, ['boleta', 'factura', 'producto'], true)) {
                $productQuantities = DB::select("
                    SELECT cd.nombre_item, cd.unidad,
                           ROUND(AVG(cd.cantidad), 1) as cantidad_promedio,
                           COUNT(*) as veces_comprado, c.proveedor
                    FROM compras_detalle cd
                    JOIN compras c ON cd.compra_id = c.id
                    WHERE cd.cantidad > 0
                    GROUP BY cd.nombre_item, cd.unidad, c.proveedor
                    HAVING veces_comprado >= 2
                    ORDER BY veces_comprado DESC
                    LIMIT 15
                ");

                foreach ($productQuantities as $pq) {
                    $patterns[] = "{$pq->nombre_item} de {$pq->proveedor}: ~{$pq->cantidad_promedio} {$pq->unidad} ({$pq->veces_comprado}x)";
                }
            }

            $supplierItems = SupplierIndex::whereNotNull('items_habituales')
                ->orderBy('frecuencia', 'desc')
                ->limit(10)
                ->get();

            foreach ($supplierItems as $si) {
                $habituales = $si->items_habituales ?? [];
                if (count($habituales) > 0) {
                    $itemNames = array_filter(array_map(
                        fn($h) => $h['nombre'] ?? '',
                        array_slice($habituales, 0, 5),
                    ));
                    if (!empty($itemNames)) {
                        $patterns[] = "'{$si->nombre_original}' vende: " . implode(', ', $itemNames);
                    }
                }
            }

            $corrections = DB::select("
                SELECT field_name, original_value, corrected_value, COUNT(*) as times
                FROM extraction_feedback
                GROUP BY field_name, original_value, corrected_value
                HAVING times >= 2
                ORDER BY times DESC
                LIMIT 10
            ");

            foreach ($corrections as $c) {
                $patterns[] = "Corrección: '{$c->original_value}' en {$c->field_name} → '{$c->corrected_value}'";
            }
        } catch (\Exception $e) {
            Log::warning('[Clasificador] Failed to get patterns: ' . $e->getMessage());
        }

        return $patterns;
    }
}
