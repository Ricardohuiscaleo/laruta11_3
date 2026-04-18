# Tareas de Implementación — Pipeline Multi-Modelo

## Tarea 1: AwsSignatureService — Extraer SigV4 signing reutilizable
- [x] Crear `mi3/backend/app/Services/Compra/AwsSignatureService.php`
- [x] Extraer lógica de `signedRequest()` del `ExtraccionService.php` actual
- [x] Método `signedPost(string $url, array|string $body, string $service, array $extraHeaders = []): ?array`
- [x] Método `getCredentials(): array` (accessKey, secretKey, region)
- [x] Soporte para headers custom (X-Amz-Target para Rekognition)
- [x] Timeout configurable por llamada

## Tarea 2: RekognitionService — Percepción paralela
- [x] Crear `mi3/backend/app/Services/Compra/RekognitionService.php`
- [x] `detectLabels(string $s3Key, int $maxLabels = 15, float $minConfidence = 70): array`
- [x] `detectText(string $s3Key): array`
- [x] `perceive(string $s3Key): array` — ejecuta ambos en paralelo con `curl_multi`
- [x] Manejo de errores: si Rekognition falla, retorna arrays vacíos (no rompe el pipeline)
- [x] Filtrar DetectText: solo retornar type=LINE (no WORD duplicados)

## Tarea 3: ClasificadorService — Nova Micro clasificación + contexto BD
- [x] Crear `mi3/backend/app/Services/Compra/ClasificadorService.php`
- [x] `clasificar(array $labels, array $texts): array` — llama Nova Micro con prompt corto
- [x] `cargarContexto(string $tipo): array` — carga contexto BD filtrado por tipo
- [x] Prompt de clasificación: ~200 tokens, retorna JSON {tipo_imagen, confianza, razon}
- [x] Extraer `buildLearnedContext()` del ExtraccionService y filtrar por tipo

## Tarea 4: AnalisisService — Nova Pro con prompts específicos por tipo
- [x] Crear `mi3/backend/app/Services/Compra/AnalisisService.php`
- [x] `analizar(string $imageBase64, string $tipo, array $labels, array $texts, array $contexto): array`
- [x] Prompts específicos: `promptBoleta()`, `promptFactura()`, `promptProducto()`, `promptBascula()`, `promptTransferencia()`, `promptGeneral()`
- [x] Cada prompt incluye labels y textos de Rekognition como "datos ya extraídos por OCR"
- [x] Reutilizar `parseResponse()` y `normalizeAmounts()` del ExtraccionService actual
- [x] Formato de respuesta JSON idéntico al actual

## Tarea 5: PipelineExtraccionService — Orquestador de las 3 fases
- [x] Crear `mi3/backend/app/Services/Compra/PipelineExtraccionService.php`
- [x] `ejecutar(string $imageUrl, ?callable $onEvent = null): array`
- [x] Orquestar: Percepción → Clasificación → Análisis → Post-processing → Sugerencias
- [x] Mover post-processing del ExtraccionController al pipeline: mapPersonToSupplier, matchProveedorByRut, applySupplierRules, product equivalences
- [x] Callback `$onEvent` para emitir eventos SSE por fase
- [x] Guardar log en `ai_extraction_logs` con campo adicional `pipeline_phases` (JSON con tiempos por fase)
- [x] Manejo de errores por fase: si una fase falla, continuar con datos parciales

## Tarea 6: Endpoint SSE + ruta + compatibilidad
- [x] Agregar método `extractPipeline(Request $request): StreamedResponse` en ExtraccionController
- [x] Agregar ruta `Route::post('compras/extract-pipeline', ...)` en routes/api.php
- [x] Refactorizar `extract()` existente para usar PipelineExtraccionService internamente (sin SSE, síncrono)
- [x] Headers SSE: Content-Type text/event-stream, no-cache, X-Accel-Buffering no
- [x] Formato evento: `data: {"fase": "...", "status": "...", "data": {...}, "elapsed_ms": N}\n\n`

## Tarea 7: ExtractionPipeline.tsx — Componente visual del pipeline
- [x] Crear `mi3/frontend/components/admin/compras/ExtractionPipeline.tsx`
- [x] 3 pasos visuales: Percepción (🔍), Clasificación (🧠), Análisis (🤖)
- [x] Cada paso: spinner mientras corre, check verde al completar, X roja si error
- [x] Mostrar datos de cada fase: labels como badges, textos resumidos, tipo detectado, resultado final
- [x] Consumir SSE via fetch + ReadableStream (no EventSource, para poder enviar POST con body)
- [x] Props: `tempKey: string`, `onResult: (data: ExtractionResult) => void`, `onError: () => void`
- [x] Mobile-first responsive, accesible con aria-live para actualizaciones

## Tarea 8: Integrar pipeline en registro y subida masiva
- [x] Agregar función `extractPipeline()` en `mi3/frontend/lib/compras-api.ts`
- [x] Modificar `app/admin/compras/registro/page.tsx`: usa pipeline síncrono (endpoint ya usa pipeline internamente)
- [x] Modificar `components/admin/compras/SubidaMasiva.tsx`: usa pipeline síncrono (endpoint ya usa pipeline internamente)
- [x] Modificar `components/admin/compras/ImageUploader.tsx`: usar ExtractionPipeline visual con SSE
- [x] Fallback: si SSE falla, usar endpoint síncrono `/compras/extract` como antes
