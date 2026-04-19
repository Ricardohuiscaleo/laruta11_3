<?php

declare(strict_types=1);

namespace App\Services\Compra;

use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $model;
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = (string) env('GOOGLE_API_KEY', '');
        $this->model = (string) env('GEMINI_MODEL', 'gemini-2.5-flash-lite');
    }

    // ─── Public Methods ───

    /**
     * Classify image type using Gemini multimodal.
     *
     * @return array{tipo_imagen: string, confianza: float, razon: string, tokens: array}|null
     */
    public function clasificar(string $imageBase64): ?array
    {
        $prompt = $this->buildClassificationPrompt();
        $schema = $this->buildClassificationSchema();

        $response = $this->callGemini($prompt, $imageBase64, $schema, 8, 256);

        if ($response === null) {
            return null;
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }

        // Normalize invalid tipo_imagen
        $validTypes = ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'];
        if (!in_array($parsed['tipo_imagen'] ?? '', $validTypes, true)) {
            $parsed['tipo_imagen'] = 'desconocido';
        }

        $tokens = $this->extractTokens($response);

        return [
            'tipo_imagen' => $parsed['tipo_imagen'],
            'confianza' => (float) ($parsed['confianza'] ?? 0.5),
            'razon' => $parsed['razon'] ?? '',
            'tokens' => $tokens,
        ];
    }

    /**
     * Analyze image with type-specific prompt and context.
     *
     * @return array{data: array, tokens: array}|null
     */
    public function analizar(string $imageBase64, string $tipo, array $contexto): ?array
    {
        $prompt = $this->buildAnalysisPrompt($tipo, $contexto);
        $schema = $this->buildExtractionSchema();

        $response = $this->callGemini($prompt, $imageBase64, $schema, 20, 2048);

        if ($response === null) {
            return null;
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }

        $parsed = $this->normalizeAmounts($parsed);
        $tokens = $this->extractTokens($response);

        return [
            'data' => $parsed,
            'tokens' => $tokens,
        ];
    }

    // ─── Core API Call ───

    /**
     * Execute POST to Gemini generateContent endpoint.
     */
    private function callGemini(string $prompt, string $imageBase64, array $schema, int $timeout, int $maxOutputTokens = 2048): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiService] GOOGLE_API_KEY not configured');
            return null;
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $jsonPayload = json_encode($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            Log::error("[GeminiService] cURL error: {$curlError}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Log::error("[GeminiService] HTTP {$httpCode}: " . substr((string) $responseBody, 0, 500));
            return null;
        }

        $decoded = json_decode((string) $responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('[GeminiService] Failed to decode API response JSON');
            return null;
        }

        return $decoded;
    }

    // ─── Response Parsing ───

    /**
     * Extract JSON data from Gemini response.
     */
    private function parseResponse(array $response): ?array
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            Log::error('[GeminiService] No text in response candidates');
            return null;
        }

        $text = trim($text);

        // Try direct JSON decode first
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: try extracting from markdown ```json...``` blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $jsonText = trim($matches[1]);
            $decoded = json_decode($jsonText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        Log::error('[GeminiService] Failed to parse response: ' . substr($text, 0, 300));
        return null;
    }

    /**
     * Extract token usage metadata from response.
     */
    private function extractTokens(array $response): array
    {
        $usage = $response['usageMetadata'] ?? [];
        return [
            'prompt' => (int) ($usage['promptTokenCount'] ?? 0),
            'candidates' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total' => (int) ($usage['totalTokenCount'] ?? 0),
        ];
    }

    // ─── Amount Normalization ───

    /**
     * Normalize monetary amounts to integers (Chilean pesos).
     * Copied from AnalisisService::normalizeAmounts().
     */
    private function normalizeAmounts(array $data): array
    {
        $isBascula = ($data['tipo_imagen'] ?? '') === 'bascula';
        if ($isBascula) {
            if (isset($data['monto_total']) && is_numeric($data['monto_total']) && $data['monto_total'] < 1000) {
                $data['monto_total'] = (int) ($data['monto_total'] * 100);
            }
            if (!empty($data['items'])) {
                foreach ($data['items'] as &$item) {
                    if (isset($item['precio_unitario']) && is_numeric($item['precio_unitario']) && $item['precio_unitario'] < 200) {
                        $item['precio_unitario'] = (int) ($item['precio_unitario'] * 100);
                    }
                    if (isset($item['subtotal']) && is_numeric($item['subtotal']) && $item['subtotal'] < 1000) {
                        $item['subtotal'] = (int) ($item['subtotal'] * 100);
                    }
                }
                unset($item);
            }
        }

        foreach (['monto_neto', 'iva', 'monto_total'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = (int) round((float) $data[$field]);
            }
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['precio_unitario']) && is_numeric($item['precio_unitario'])) {
                    $item['precio_unitario'] = (int) round((float) $item['precio_unitario']);
                }
                if (isset($item['subtotal']) && is_numeric($item['subtotal'])) {
                    $item['subtotal'] = (int) round((float) $item['subtotal']);
                }
                $descuento = (int) abs((float) ($item['descuento'] ?? 0));
                if ($descuento > 0 && isset($item['subtotal'])) {
                    $item['subtotal'] -= $descuento;
                    $cantidad = max(1, (float) ($item['cantidad'] ?? 1));
                    $item['precio_unitario'] = (int) round($item['subtotal'] / $cantidad);
                    $item['notas_descuento'] = "Descuento -\${$descuento} aplicado";
                }
                unset($item['descuento']);
            }
            unset($item);
        }

        return $data;
    }

    // ─── Schema Builders ───

    private function buildClassificationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo_imagen' => [
                    'type' => 'string',
                    'enum' => ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'],
                ],
                'confianza' => [
                    'type' => 'number',
                ],
                'razon' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['tipo_imagen', 'confianza', 'razon'],
        ];
    }

    private function buildExtractionSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo_imagen' => ['type' => 'string'],
                'proveedor' => ['type' => 'string'],
                'rut_proveedor' => ['type' => 'string'],
                'fecha' => ['type' => 'string'],
                'metodo_pago' => [
                    'type' => 'string',
                    'enum' => ['cash', 'transfer', 'card', 'credit'],
                ],
                'tipo_compra' => [
                    'type' => 'string',
                    'enum' => ['ingredientes', 'insumos', 'equipamiento', 'otros'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nombre' => ['type' => 'string'],
                            'cantidad' => ['type' => 'number'],
                            'unidad' => ['type' => 'string'],
                            'precio_unitario' => ['type' => 'integer'],
                            'subtotal' => ['type' => 'integer'],
                            'descuento' => ['type' => 'integer'],
                            'empaque_detalle' => ['type' => 'string'],
                        ],
                        'required' => ['nombre', 'cantidad', 'unidad', 'precio_unitario', 'subtotal'],
                    ],
                ],
                'monto_neto' => ['type' => 'integer'],
                'iva' => ['type' => 'integer'],
                'monto_total' => ['type' => 'integer'],
                'peso_bascula' => ['type' => 'number'],
                'unidad_bascula' => ['type' => 'string'],
                'notas_ia' => ['type' => 'string'],
            ],
            'required' => ['tipo_imagen', 'items', 'monto_total'],
        ];
    }

    // ─── Prompt Builders ───

    private function buildClassificationPrompt(): string
    {
        return <<<'PROMPT'
Observa esta imagen y clasifica su tipo.

Tipos posibles:
- "boleta": documento de venta con RUT, productos, total (supermercado, tienda)
- "factura": documento tributario formal con RUT emisor/receptor, IVA, detalle de items
- "producto": foto de un producto físico (caja, saco, bolsa, bandeja de ingrediente)
- "bascula": foto de báscula/balanza digital mostrando peso en display
- "transferencia": comprobante de transferencia bancaria o Mercado Pago
- "desconocido": no se puede determinar

Pistas:
- Si hay textos con RUT (XX.XXX.XXX-X), montos ($), "BOLETA" o "FACTURA" → boleta o factura
- Si se ven productos físicos (cajas, sacos, bolsas, bandejas) sin texto de montos → producto
- Si se ve una báscula/balanza digital con displays numéricos → bascula
- Si hay textos con "Transferencia", "Mercado Pago", "Comprobante" → transferencia
- "FACTURA ELECTRÓNICA" en texto → factura (no boleta)

Responde con tipo_imagen, confianza (0.0-1.0) y razon (breve explicación).
PROMPT;
    }

    private function buildAnalysisPrompt(string $tipo, array $contexto): string
    {
        return match ($tipo) {
            'boleta' => $this->promptBoleta($contexto),
            'factura' => $this->promptFactura($contexto),
            'producto' => $this->promptProducto($contexto),
            'bascula' => $this->promptBascula($contexto),
            'transferencia' => $this->promptTransferencia($contexto),
            default => $this->promptGeneral($contexto),
        };
    }

    // ─── Helper: format context for prompts ───

    private function formatSuppliers(array $contexto): string
    {
        $suppliers = $contexto['suppliers'] ?? [];
        return !empty($suppliers) ? implode(', ', array_slice($suppliers, 0, 15)) : '';
    }

    private function formatProducts(array $contexto): string
    {
        $products = $contexto['products'] ?? [];
        return !empty($products) ? implode(', ', array_slice($products, 0, 25)) : '';
    }

    private function formatRutMap(array $contexto): string
    {
        $rutMap = $contexto['rut_map'] ?? [];
        if (empty($rutMap)) {
            return '';
        }
        $lines = [];
        foreach ($rutMap as $rut => $nombre) {
            $lines[] = "RUT {$rut} = {$nombre}";
        }
        return implode("\n", $lines);
    }

    private function jsonFormat(): string
    {
        return <<<'JSON'
{
  "tipo_imagen": "boleta|factura|producto|bascula|transferencia",
  "proveedor": "nombre",
  "rut_proveedor": "XX.XXX.XXX-Y o null",
  "fecha": "YYYY-MM-DD",
  "metodo_pago": "cash|transfer|card|credit",
  "tipo_compra": "ingredientes|insumos|equipamiento|otros",
  "items": [{"nombre": "...", "cantidad": N, "unidad": "kg|unidad|g|L", "precio_unitario": N, "subtotal": N, "descuento": 0, "empaque_detalle": null}],
  "monto_neto": N, "iva": N, "monto_total": N,
  "peso_bascula": null, "unidad_bascula": null,
  "notas_ia": "observaciones"
}
JSON;
    }

    // ─── Type-specific prompts (adapted from AnalisisService for Gemini) ───

    private function promptBoleta(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);
        $patterns = implode("\n", array_slice($contexto['patterns'] ?? [], 0, 10));

        return <<<PROMPT
Analiza esta imagen de boleta de compra chilena y extrae datos estructurados.

REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X \$4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN \$4790 c/u")
- Cada código de barras (78...) = 1 producto separado. NUNCA fusionar.
- Descuentos (OFERTA SEMANA, DESCTO CONVENI) van en campo "descuento" como valor positivo
- Después de "TOTAL" NO hay productos
- "TARJETA DE DEBITO" o "VENTA DEBITO" → metodo_pago = "card"
- Montos en pesos chilenos enteros (sin decimales)
- Si no hay IVA explícito: monto_neto = round(total/1.19), iva = total - monto_neto

RUTs conocidos:
81.201.000-K = Jumbo/Santa Isabel (Cencosud)
81.537.600-5 = Unimarc (Rendic/SMU)
{$rutMap}

Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}
{$patterns}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptFactura(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Analiza esta imagen de factura de proveedor chilena y extrae datos estructurados.

REGLAS FACTURA CHILENA:
- PROVEEDOR = empresa EMISORA (arriba/encabezado), NO el destinatario
- "SEÑORES" o "CLIENTE" = COMPRADOR (La Ruta 11, Ricardo Huiscaleo) — NO es proveedor
- "Yumbel", "Arica" son direcciones del comprador, NO proveedores
- RUT del proveedor está en el encabezado, cerca del nombre de la empresa emisora
- URLs/dominios identifican al proveedor: ariztiaatunegocio.cl → Ariztía, agrosuper.cl → Agrosuper

EMPAQUE EN FACTURAS MAYORISTAS:
- "SALCHICHA BIG MONT 800G 10U 8X1" CANT=2 → 10×8×2 = 160 unidades
- "NNu" = unidades/paquete, "NNxN" = paquetes/caja, CANT = bultos comprados
- nombre: LIMPIO sin empaque. precio_unitario: subtotal/cantidad_total
- empaque_detalle: "10u/paq × 8paq/caja × 2 cajas = 160 unidades"

FORMATO VANNI (RUT 76.979.850-1): cantidades directas, precios netos, TOTAL incluye IVA.

{$rutMap}
Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptProducto(array $contexto): string
    {
        $products = $this->formatProducts($contexto);
        $equivalences = '';
        foreach (array_slice($contexto['equivalences'] ?? [], 0, 10) as $eq) {
            $equivalences .= "- {$eq['nombre']} → {$eq['ingrediente']} ({$eq['cantidad_por_unidad']} {$eq['unidad']})\n";
        }

        return <<<PROMPT
Analiza esta imagen de producto (ingrediente físico) y extrae datos estructurados.

REGLAS:
- Identifica qué producto es (tomate, papa, carne, pan, etc.)
- Estima cantidad basándote en el tamaño visual y contexto
- Caja estándar de tomates ≈ 18-20 kg, saco de papas ≈ 25 kg
- Bolsas azules/rosadas con productos redondos oscuros = probablemente Palta Hass
- Si hay texto de peso visible, úsalo. Si no, estima.
- tipo_imagen = "producto", tipo_compra = "ingredientes"

Equivalencias conocidas:
{$equivalences}

Ingredientes del negocio: {$products}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptBascula(array $contexto): string
    {
        $products = $this->formatProducts($contexto);

        return <<<PROMPT
Analiza esta imagen de báscula/balanza digital y extrae datos estructurados.

REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO (\$/kg), TOTAL (\$)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 (\$45 = \$4.500/kg)
- Si número < 1000 en total → multiplicar ×100 (\$237 = \$23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"

Ingredientes del negocio: {$products}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptTransferencia(array $contexto): string
    {
        $personMap = '';
        foreach ($contexto['person_map'] ?? [] as $person => $supplier) {
            $personMap .= "- {$person} → {$supplier}\n";
        }

        return <<<PROMPT
Analiza esta imagen de comprobante de transferencia bancaria y extrae datos estructurados.

REGLAS:
- PROVEEDOR = DESTINATARIO de la transferencia (NO el banco, NO Mercado Pago)
- metodo_pago = "transfer" siempre
- Extrae: destinatario, monto, fecha

Mapeo personas → proveedores:
{$personMap}
- Si el destinatario no está en el mapeo, usar su nombre como proveedor
- Para ARIAKA: item = "Servicios Delivery", tipo_compra = "otros"
- Para Abastible/Elton San Martin: item = "gas 15", tipo_compra = "ingredientes"

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptGeneral(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Analiza esta imagen y determina qué tipo de contenido es. Extrae datos estructurados.

Tipos posibles: boleta, factura, producto, bascula, transferencia.

- Boleta/Factura: proveedor, RUT, items, montos, IVA
- Producto: identificar producto, estimar cantidad
- Báscula: leer displays (peso, precio/kg, total). Notación abreviada: ×100
- Transferencia: destinatario = proveedor (NO el banco)

Montos en pesos chilenos enteros. Si no hay IVA: neto=round(total/1.19).

{$rutMap}
Proveedores: {$suppliers}
Ingredientes: {$products}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }
}
