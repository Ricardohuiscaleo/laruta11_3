# Plan de Implementación: Migración Pipeline Compras a Google Gemini

## Resumen

Migrar el pipeline de extracción de compras de AWS (Rekognition + Nova Micro + Nova Pro) a Google Gemini (`gemini-2.5-flash-lite`). Se crea un nuevo `GeminiService.php` con clasificación y análisis multimodal, se modifica `PipelineExtraccionService.php` para detectar el proveedor y delegar, se adapta el frontend para 2 fases SSE, y se implementa tracking de tokens/costos.

## Tareas

- [x] 1. Crear GeminiService con llamada base a la API
  - [x] 1.1 Crear `mi3/backend/app/Services/Compra/GeminiService.php` con constructor, propiedades (`$model`, `$apiKey`, `$baseUrl`) y método privado `callGemini(string $prompt, string $imageBase64, array $schema, int $timeout): ?array`
    - Leer `GOOGLE_API_KEY` y `GEMINI_MODEL` (default `gemini-2.5-flash-lite`) desde `env()`
    - Implementar POST con `curl` a `{baseUrl}/{model}:generateContent?key={apiKey}`
    - Enviar imagen como `inline_data` con `mime_type: image/jpeg`
    - Configurar `generationConfig`: `temperature: 0.1`, `responseMimeType: application/json`, `responseSchema` desde parámetro `$schema`
    - Extraer `usageMetadata` (promptTokenCount, candidatesTokenCount, totalTokenCount) de la respuesta
    - Manejar timeouts y errores HTTP retornando null + log
    - _Requerimientos: 1.1, 1.4, 1.5, 1.8, 1.9, 1.10, 1.11, 7.1, 7.3, 7.4_

  - [x] 1.2 Implementar `parseResponse(array $response): ?array` y `normalizeAmounts(array $data): array`
    - Extraer JSON de `candidates[0].content.parts[0].text`
    - Fallback: intentar extraer de bloques markdown ` ```json...``` ` si no es JSON directo
    - Copiar lógica de `AnalisisService::normalizeAmounts()` para montos CLP enteros, notación abreviada de básculas, y descuentos
    - _Requerimientos: 1.5, 1.6, 4.3_

  - [ ]* 1.3 Escribir test de propiedad para parseo de respuesta Gemini
    - **Propiedad 1: Parseo robusto de respuesta Gemini**
    - **Valida: Requerimientos 1.5, 1.6**

  - [ ]* 1.4 Escribir test de propiedad para normalización de montos
    - **Propiedad 3: Normalización de montos a enteros CLP**
    - **Valida: Requerimientos 4.3**

- [x] 2. Implementar clasificación y análisis en GeminiService
  - [x] 2.1 Implementar `clasificar(string $imageBase64): ?array` con schema de clasificación
    - Definir `buildClassificationSchema()` con enum `tipo_imagen`, `confianza` (number), `razon` (string)
    - Prompt de clasificación adaptado del `ClasificadorService` pero sin labels/texts de Rekognition (Gemini ve la imagen directamente)
    - Timeout de 8 segundos
    - Normalizar `tipo_imagen` inválido a `desconocido`
    - Retornar `{tipo_imagen, confianza, razon, tokens: {prompt, candidates, total}}`
    - _Requerimientos: 1.2, 1.3, 3.1, 3.2, 4.5, 7.3, 8.3_

  - [x] 2.2 Implementar `analizar(string $imageBase64, string $tipo, array $contexto): ?array` con schema de extracción
    - Definir `buildExtractionSchema()` con todos los campos del formato de compra (tipo_imagen, proveedor, rut_proveedor, fecha, metodo_pago, tipo_compra, items[], montos, peso_bascula, notas_ia)
    - Implementar selección de prompt por tipo usando `match($tipo)` — copiar prompts de `AnalisisService` adaptados para Gemini (sin labels/texts de Rekognition, instrucción directa de analizar la imagen)
    - Timeout de 20 segundos, `maxOutputTokens: 2048`
    - Aplicar `normalizeAmounts()` al resultado
    - Retornar `{data: [...], tokens: {prompt, candidates, total}}`
    - _Requerimientos: 1.2, 1.3, 1.9, 3.4, 3.5, 4.1, 4.2, 7.3_

  - [ ]* 2.3 Escribir test de propiedad para selección de prompt por tipo
    - **Propiedad 2: Selección correcta de prompt por tipo**
    - **Valida: Requerimientos 1.3**

  - [ ]* 2.4 Escribir test de propiedad para normalización de tipo_imagen inválido
    - **Propiedad 4: Normalización de tipo_imagen inválido**
    - **Valida: Requerimientos 8.3**

- [ ] 3. Checkpoint — Verificar GeminiService
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

- [-] 4. Integrar Gemini en PipelineExtraccionService
  - [x] 4.1 Agregar detección de proveedor y método `ejecutarGemini()`
    - Agregar `GeminiService` al constructor via inyección de dependencias
    - Implementar `isGeminiAvailable(): bool` — verificar `env('GOOGLE_API_KEY')` no vacío
    - Modificar `ejecutar()` para delegar a `ejecutarGemini()` cuando Gemini esté disponible
    - Si ni Gemini ni Bedrock están disponibles, retornar error descriptivo
    - _Requerimientos: 2.1, 2.2, 7.2_

  - [x] 4.2 Implementar `ejecutarGemini(string $imageUrl, ?callable $onEvent): array`
    - Fase 1: Obtener imagen base64, llamar `$this->gemini->clasificar($imageBase64)`, emitir SSE `clasificacion:done` con campo `engine: gemini`
    - Cargar contexto BD con `$this->clasificador->cargarContexto($tipo)` (reutilizar servicio existente)
    - Fase 2: Llamar `$this->gemini->analizar($imageBase64, $tipo, $contexto)`, emitir SSE `analisis:done` con campo `engine: gemini`
    - Aplicar post-procesamiento existente sin cambios (postProcess, calculateConfidence, sugerencias)
    - Si clasificación falla: usar `fallbackClassification` de ClasificadorService con arrays vacíos
    - Si análisis falla: retornar `{success: false, fallback: manual}`
    - _Requerimientos: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 3.3, 3.4, 3.6, 8.1, 8.2, 8.5_

  - [x] 4.3 Implementar tracking de tokens y costo en AiExtractionLog
    - Sumar tokens de clasificación + análisis
    - Calcular `estimated_cost_usd = (promptTokens × 0.10 + candidatesTokens × 0.40) / 1_000_000`
    - Guardar en `raw_response`: `pipeline_phases`, `tokens` (desglose y total), `estimated_cost_usd`, `engine: gemini`
    - Guardar `model_id` como `gemini-2.5-flash-lite`
    - Incluir tokens en eventos SSE de cada fase
    - _Requerimientos: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [ ]* 4.4 Escribir test de propiedad para cálculo de tokens y costo
    - **Propiedad 5: Cálculo correcto de métricas de tokens y costo**
    - **Valida: Requerimientos 5.3, 5.4, 1.8**

  - [ ]* 4.5 Escribir test de propiedad para resiliencia ante excepciones
    - **Propiedad 6: Resiliencia ante excepciones**
    - **Valida: Requerimientos 8.5**

  - [ ]* 4.6 Escribir test de propiedad para estructura invariante del resultado
    - **Propiedad 7: Estructura invariante del resultado exitoso**
    - **Valida: Requerimientos 4.4**

- [ ] 5. Checkpoint — Verificar integración backend
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

- [x] 6. Actualizar frontend para pipeline Gemini
  - [x] 6.1 Modificar `ExtractionPipeline.tsx` para detectar motor y adaptar fases
    - Agregar campo `engine` al tipo `PipelineEvent`
    - En `handleEvent`, detectar `engine` del primer evento SSE recibido
    - Si `engine === 'gemini'`: renderizar 2 fases (clasificacion, analisis) con labels "Clasificando imagen (Gemini)" y "Analizando con IA (Gemini)"
    - Si `engine === 'bedrock'` o no definido: mantener 3 fases actuales (percepcion, clasificacion, analisis)
    - Inicializar fases dinámicamente según motor detectado
    - Mantener toda funcionalidad existente: indicador de lentitud, tiempo total, detalles por fase
    - _Requerimientos: 6.2, 6.3, 6.4, 6.5_

  - [x] 6.2 Actualizar `ComprasSection.tsx` versión a v1.7
    - Cambiar `v1.6` → `v1.7` en el encabezado
    - _Requerimientos: 6.1_

- [x] 7. Configurar variable de entorno en servidor
  - [x] 7.1 Agregar `GOOGLE_API_KEY` al `.env.example` del backend
    - Agregar `GOOGLE_API_KEY=` y `GEMINI_MODEL=gemini-2.5-flash-lite` al `.env.example`
    - _Requerimientos: 7.1, 1.10_

- [ ] 8. Checkpoint final — Verificar todo el pipeline
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requerimientos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Los tests de propiedad validan las 7 propiedades de correctitud definidas en el diseño
- El post-procesamiento existente (mapPersonToSupplier, matchProveedorByRut, applySupplierRules, applyProductEquivalences) NO se modifica
- Los prompts se copian de AnalisisService pero se adaptan para Gemini (sin labels/texts de Rekognition)
