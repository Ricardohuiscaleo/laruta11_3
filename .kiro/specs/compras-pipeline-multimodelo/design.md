# Diseño Técnico — Pipeline Multi-Modelo de Extracción de Compras

## Arquitectura General

```
Frontend (Next.js)                    Backend (Laravel 11)
┌─────────────────┐                  ┌──────────────────────────────────┐
│ ExtractionPipe- │  POST /extract-  │  ExtraccionController            │
│ line.tsx        │──pipeline (SSE)──│    → PipelineExtraccionService   │
│                 │◄─ SSE events ────│       │                          │
│ 3 pasos visual  │                  │       ├─ Fase 1: RekognitionSvc  │
│ con streaming   │                  │       │   ├─ DetectLabels        │
└─────────────────┘                  │       │   └─ DetectText          │
                                     │       ├─ Fase 2: ClasificadorSvc │
                                     │       │   ├─ Nova Micro          │
                                     │       │   └─ Context loader      │
                                     │       └─ Fase 3: AnalisisSvc     │
                                     │           ├─ Nova Pro + prompt   │
                                     │           └─ Post-processing     │
                                     └──────────────────────────────────┘
```

## Archivos a Crear/Modificar

### Backend (mi3/backend/)

1. **`app/Services/Compra/RekognitionService.php`** (NUEVO)
   - `detectLabels(string $s3Key): array` — llama Rekognition DetectLabels
   - `detectText(string $s3Key): array` — llama Rekognition DetectText
   - Usa SigV4 signing igual que ExtraccionService actual (curl directo)
   - Retorna: `['labels' => [...], 'texts' => [...]]`

2. **`app/Services/Compra/ClasificadorService.php`** (NUEVO)
   - `clasificar(array $labels, array $texts): array` — llama Nova Micro con labels+textos
   - `cargarContexto(string $tipo): array` — carga contexto BD según tipo
   - Retorna: `['tipo_imagen' => string, 'confianza_tipo' => float, 'contexto' => array]`
   - Modelo: `amazon.nova-micro-v1:0` (solo texto, sin imagen)

3. **`app/Services/Compra/AnalisisService.php`** (NUEVO)
   - `analizar(string $imageBase64, string $tipo, array $labels, array $texts, array $contexto): array`
   - Prompts específicos por tipo (métodos privados): `promptBoleta()`, `promptFactura()`, `promptProducto()`, `promptBascula()`, `promptTransferencia()`, `promptGeneral()`
   - Modelo: `amazon.nova-pro-v1:0` (con imagen)
   - Reutiliza `signedRequest()` del ExtraccionService actual

4. **`app/Services/Compra/PipelineExtraccionService.php`** (NUEVO)
   - Orquesta las 3 fases
   - `ejecutar(string $imageUrl, ?callable $onEvent = null): array`
   - El callback `$onEvent` emite eventos SSE
   - Aplica post-processing existente (mapPersonToSupplier, etc.) — extraído del ExtraccionController

5. **`app/Services/Compra/AwsSignatureService.php`** (NUEVO)
   - Extrae la lógica de SigV4 signing del ExtraccionService actual
   - Reutilizable por RekognitionService, ClasificadorService, AnalisisService
   - `signedRequest(string $url, array $body, string $service, string $region): ?array`

6. **`app/Http/Controllers/Admin/ExtraccionController.php`** (MODIFICAR)
   - Agregar método `extractPipeline(Request $request): StreamedResponse` — endpoint SSE
   - Mantener método `extract()` existente como wrapper síncrono del pipeline
   - Mover lógica de post-processing a PipelineExtraccionService

7. **`routes/api.php`** (MODIFICAR)
   - Agregar ruta: `Route::post('compras/extract-pipeline', [ExtraccionController::class, 'extractPipeline'])`

### Frontend (mi3/frontend/)

8. **`components/admin/compras/ExtractionPipeline.tsx`** (NUEVO)
   - Componente visual con 3 pasos: Percepción → Clasificación → Análisis
   - Consume SSE del endpoint `/extract-pipeline`
   - Cada paso muestra: spinner/check, título, datos resumidos
   - Al completar, llama `onResult(data)` con el resultado final

9. **`lib/compras-api.ts`** (MODIFICAR)
   - Agregar `extractPipeline(tempKey: string, onEvent: (event) => void): Promise<ExtractionResult>`
   - Usa `EventSource` o `fetch` con `ReadableStream` para consumir SSE

10. **`app/admin/compras/registro/page.tsx`** (MODIFICAR)
    - Reemplazar llamada directa a `/compras/extract` por `ExtractionPipeline` component

11. **`components/admin/compras/SubidaMasiva.tsx`** (MODIFICAR)
    - Reemplazar llamada directa a `/compras/extract` por pipeline SSE

## Diseño de Servicios

### RekognitionService

```php
class RekognitionService
{
    public function __construct(private AwsSignatureService $signer) {}

    public function detectLabels(string $s3Key, int $maxLabels = 15): array
    {
        // POST https://rekognition.{region}.amazonaws.com
        // Header: X-Amz-Target: RekognitionService.DetectLabels
        // Body: { "Image": { "S3Object": { "Bucket": "laruta11-images", "Name": s3Key } }, "MaxLabels": 15, "MinConfidence": 70 }
        // Returns: array of ['name' => string, 'confidence' => float, 'parents' => array]
    }

    public function detectText(string $s3Key): array
    {
        // POST https://rekognition.{region}.amazonaws.com
        // Header: X-Amz-Target: RekognitionService.DetectText
        // Body: { "Image": { "S3Object": { "Bucket": "laruta11-images", "Name": s3Key } } }
        // Returns: array of ['text' => string, 'confidence' => float, 'type' => 'LINE'|'WORD']
    }

    public function perceive(string $s3Key): array
    {
        // Ejecuta detectLabels y detectText en paralelo usando curl_multi
        // Returns: ['labels' => [...], 'texts' => [...], 'elapsed_ms' => int]
    }
}
```

### ClasificadorService

```php
class ClasificadorService
{
    public function __construct(private AwsSignatureService $signer) {}

    public function clasificar(array $labels, array $texts): array
    {
        // Prompt a Nova Micro (solo texto, ~200 tokens):
        // "Dados estos labels de imagen: {labels} y estos textos: {texts},
        //  clasifica el tipo: boleta, factura, producto, bascula, transferencia, desconocido.
        //  Responde JSON: {tipo_imagen, confianza, razon}"
    }

    public function cargarContexto(string $tipo): array
    {
        // Reutiliza buildLearnedContext() del ExtraccionService actual
        // pero filtrado por tipo:
        // - boleta/factura: suppliers, rut_map, patterns
        // - producto: products, equivalences, patterns
        // - bascula: products (solo los pesables)
        // - transferencia: person_to_supplier map
    }
}
```

### AnalisisService

```php
class AnalisisService
{
    public function __construct(private AwsSignatureService $signer) {}

    public function analizar(string $imageBase64, string $tipo, array $labels, array $texts, array $contexto): array
    {
        $prompt = match($tipo) {
            'boleta' => $this->promptBoleta($labels, $texts, $contexto),
            'factura' => $this->promptFactura($labels, $texts, $contexto),
            'producto' => $this->promptProducto($labels, $texts, $contexto),
            'bascula' => $this->promptBascula($labels, $texts, $contexto),
            'transferencia' => $this->promptTransferencia($labels, $texts, $contexto),
            default => $this->promptGeneral($labels, $texts, $contexto),
        };
        // Call Nova Pro con imagen + prompt corto
        // Parse JSON response
        // Return structured data
    }

    // Cada prompt es ~400-800 tokens vs ~4000 del actual
    // Incluye labels y textos de Rekognition como "datos ya extraídos"
    // Nova Pro solo necesita organizar, interpretar y completar
}
```

### PipelineExtraccionService

```php
class PipelineExtraccionService
{
    public function __construct(
        private RekognitionService $rekognition,
        private ClasificadorService $clasificador,
        private AnalisisService $analisis,
        private SugerenciaService $sugerencias,
    ) {}

    public function ejecutar(string $imageUrl, ?callable $onEvent = null): array
    {
        $startTime = microtime(true);
        $emit = fn($fase, $status, $data) => $onEvent ? $onEvent($fase, $status, $data, $startTime) : null;

        // 1. Get S3 key and base64
        $s3Key = $this->resolveS3Key($imageUrl);
        $imageBase64 = $this->getImageBase64($imageUrl);

        // 2. FASE 1: Percepción (Rekognition paralelo)
        $emit('percepcion', 'running', null);
        $perception = $this->rekognition->perceive($s3Key);
        $emit('percepcion', 'done', $perception);

        // 3. FASE 2: Clasificación (Nova Micro + contexto BD)
        $emit('clasificacion', 'running', null);
        $classification = $this->clasificador->clasificar($perception['labels'], $perception['texts']);
        $tipo = $classification['tipo_imagen'];
        $contexto = $this->clasificador->cargarContexto($tipo);
        $emit('clasificacion', 'done', ['tipo' => $tipo, 'contexto_size' => count($contexto)]);

        // 4. FASE 3: Análisis (Nova Pro con prompt específico)
        $emit('analisis', 'running', null);
        $resultado = $this->analisis->analizar($imageBase64, $tipo, $perception['labels'], $perception['texts'], $contexto);
        $emit('analisis', 'done', $resultado);

        // 5. Post-processing (mismo que ExtraccionController actual)
        $resultado = $this->postProcess($resultado);

        // 6. Match sugerencias
        $sugerencias = $this->matchSugerencias($resultado);

        // 7. Save log + return
        $emit('completado', 'done', ['resultado' => $resultado, 'sugerencias' => $sugerencias]);
        return [...];
    }
}
```

## Endpoint SSE

```php
// ExtraccionController::extractPipeline
public function extractPipeline(Request $request): StreamedResponse
{
    $request->validate(['temp_key' => 'required|string']);

    return response()->stream(function () use ($request) {
        $imageUrl = $request->input('temp_key');

        $this->pipelineService->ejecutar($imageUrl, function ($fase, $status, $data, $startTime) {
            $elapsed = (int) round((microtime(true) - $startTime) * 1000);
            $event = json_encode([
                'fase' => $fase,
                'status' => $status,
                'data' => $data,
                'elapsed_ms' => $elapsed,
            ]);
            echo "data: {$event}\n\n";
            ob_flush();
            flush();
        });
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

## Componente Frontend ExtractionPipeline

```tsx
// Estructura visual:
// ┌─────────────────────────────────────┐
// │ 🔍 Identificando objetos y textos   │
// │    ✅ "Saco", "Papas", "Báscula"    │
// │    📝 "5.275 kg", "$23.738"         │
// ├─────────────────────────────────────┤
// │ 🧠 Clasificando imagen              │
// │    ✅ Tipo: Báscula — 45 ingredientes│
// ├─────────────────────────────────────┤
// │ 🤖 Analizando con IA               │
// │    ⏳ Procesando...                  │
// └─────────────────────────────────────┘

// Consume SSE via fetch + ReadableStream
// Cada evento actualiza el paso correspondiente
// Al completar, transiciona al formulario de edición
```

## Compatibilidad

- El endpoint `/compras/extract` actual sigue funcionando (wrapper síncrono)
- El formato de respuesta JSON es idéntico al actual
- Las reglas post-extracción (mapPersonToSupplier, etc.) se mantienen
- SubidaMasiva y ImageUploader pueden usar el pipeline o el endpoint síncrono
