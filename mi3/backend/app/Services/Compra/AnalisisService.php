<?php

declare(strict_types=1);

namespace App\Services\Compra;

use Illuminate\Support\Facades\Log;

class AnalisisService
{
    private string $modelId = 'amazon.nova-pro-v1:0';

    public function __construct(private AwsSignatureService $signer) {}

    /**
     * Analyze image with Nova Pro using a type-specific prompt.
     *
     * @param string $imageBase64 Base64-encoded image
     * @param string $tipo        Image type from ClasificadorService
     * @param array  $labels      Rekognition labels
     * @param array  $texts       Rekognition texts
     * @param array  $contexto    DB context from ClasificadorService
     * @return array|null Structured extraction data or null on failure
     */
    public function analizar(
        string $imageBase64,
        string $tipo,
        array $labels,
        array $texts,
        array $contexto,
    ): ?array {
        $prompt = match ($tipo) {
            'boleta' => $this->promptBoleta($labels, $texts, $contexto),
            'factura' => $this->promptFactura($labels, $texts, $contexto),
            'producto' => $this->promptProducto($labels, $texts, $contexto),
            'bascula' => $this->promptBascula($labels, $texts, $contexto),
            'transferencia' => $this->promptTransferencia($labels, $texts, $contexto),
            default => $this->promptGeneral($labels, $texts, $contexto),
        };

        $region = $this->signer->getRegion();
        $endpoint = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$this->modelId}/converse";

        $body = json_encode([
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['image' => ['format' => 'jpeg', 'source' => ['bytes' => $imageBase64]]],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'inferenceConfig' => [
                'maxNewTokens' => 2048,
                'temperature' => 0.1,
            ],
        ]);

        $response = $this->signer->signedPost($endpoint, $body, 'bedrock', [], 20);

        if (!$response || ($response['__error'] ?? false)) {
            $errorMsg = $response['__message'] ?? 'no response';
            $httpCode = $response['__http_code'] ?? 0;
            Log::error("[Analisis] Nova Pro error: HTTP {$httpCode} — {$errorMsg}");
            return null;
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(array $rawResponse): ?array
    {
        $text = $rawResponse['output']['message']['content'][0]['text'] ?? null;
        if (!$text) {
            return null;
        }

        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('[Analisis] JSON parse error: ' . json_last_error_msg());
            return null;
        }

        return $this->normalizeAmounts($decoded);
    }

    /**
     * Normalize monetary amounts to integers (Chilean pesos).
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

    // ─── Helper: format Rekognition data for prompts ───

    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '(sin labels)';
        }
        return implode(', ', array_map(fn(array $l): string => $l['name'] . ' (' . $l['confidence'] . '%)', $labels));
    }

    private function formatTexts(array $texts): string
    {
        if (empty($texts)) {
            return '(sin texto OCR)';
        }
        $lines = array_map(fn(array $t): string => $t['text'] ?? '', $texts);
        return implode("\n", array_slice($lines, 0, 40));
    }

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

    // ─── Type-specific prompts ───

    private function promptBoleta(array $labels, array $texts, array $contexto): string
    {
        $ocrTexts = $this->formatTexts($texts);
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);
        $patterns = implode("\n", array_slice($contexto['patterns'] ?? [], 0, 10));

        return <<<PROMPT
Esta imagen es una BOLETA de compra chilena. El OCR ya extrajo estos textos:
{$ocrTexts}

Analiza la imagen y extrae datos estructurados. Responde SOLO JSON.

REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X $4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN $4790 c/u")
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

    private function promptFactura(array $labels, array $texts, array $contexto): string
    {
        $ocrTexts = $this->formatTexts($texts);
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Esta imagen es una FACTURA de proveedor chilena. El OCR ya extrajo estos textos:
{$ocrTexts}

Analiza la imagen y extrae datos estructurados. Responde SOLO JSON.

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

    private function promptProducto(array $labels, array $texts, array $contexto): string
    {
        $labelStr = $this->formatLabels($labels);
        $ocrTexts = $this->formatTexts($texts);
        $products = $this->formatProducts($contexto);
        $equivalences = '';
        foreach (array_slice($contexto['equivalences'] ?? [], 0, 10) as $eq) {
            $equivalences .= "- {$eq['nombre']} → {$eq['ingrediente']} ({$eq['cantidad_por_unidad']} {$eq['unidad']})\n";
        }

        return <<<PROMPT
Esta imagen es una FOTO DE PRODUCTO (ingrediente físico). Rekognition detectó:
Labels: {$labelStr}
Textos: {$ocrTexts}

Identifica el producto y estima cantidad. Responde SOLO JSON.

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

    private function promptBascula(array $labels, array $texts, array $contexto): string
    {
        $labelStr = $this->formatLabels($labels);
        $ocrTexts = $this->formatTexts($texts);
        $products = $this->formatProducts($contexto);

        return <<<PROMPT
Esta imagen es una FOTO DE BÁSCULA/BALANZA digital. Rekognition detectó:
Labels: {$labelStr}
Textos: {$ocrTexts}

Lee los displays y extrae datos. Responde SOLO JSON.

REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO ($/kg), TOTAL ($)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 ($45 = $4.500/kg)
- Si número < 1000 en total → multiplicar ×100 ($237 = $23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"

Ingredientes del negocio: {$products}

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptTransferencia(array $labels, array $texts, array $contexto): string
    {
        $ocrTexts = $this->formatTexts($texts);
        $personMap = '';
        foreach ($contexto['person_map'] ?? [] as $person => $supplier) {
            $personMap .= "- {$person} → {$supplier}\n";
        }

        return <<<PROMPT
Esta imagen es un COMPROBANTE DE TRANSFERENCIA bancaria. El OCR extrajo:
{$ocrTexts}

Extrae datos de la transferencia. Responde SOLO JSON.

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

    private function promptGeneral(array $labels, array $texts, array $contexto): string
    {
        $labelStr = $this->formatLabels($labels);
        $ocrTexts = $this->formatTexts($texts);
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Analiza esta imagen y determina qué tipo de contenido es. Rekognition detectó:
Labels: {$labelStr}
Textos: {$ocrTexts}

Tipos posibles: boleta, factura, producto, bascula, transferencia.
Extrae datos estructurados según el tipo. Responde SOLO JSON.

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
