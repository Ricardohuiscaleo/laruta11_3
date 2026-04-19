# Plan de Implementación: Pipeline Multi-Agente para Extracción de Compras

## Resumen

Implementación incremental del pipeline de 4 agentes especializados (Visión → Análisis → Validación → Reconciliación) con auto-aprendizaje por correcciones del usuario. Se mantiene backward compatibility con el pipeline Gemini de 2 fases existente.

## Tareas

- [x] 1. Migración de BD y modelo ExtractionFeedback
  - [x] 1.1 Crear migración `2026_04_19_create_extraction_feedback_table.php`
    - Tabla `extraction_feedback` con campos: id, extraction_log_id, compra_id, proveedor, tipo_imagen, field_name, original_value, corrected_value, created_at
    - Índices: idx_proveedor, idx_tipo_imagen, idx_extraction_log, idx_created_at
    - _Requisitos: 6.1, 6.2_
  - [x] 1.2 Crear modelo `ExtractionFeedback.php`
    - Eloquent model con $fillable, $table, timestamps=false
    - Relación belongsTo con AiExtractionLog
    - _Requisitos: 6.1, 6.2_
  - [x] 1.3 Agregar relación `feedbacks()` en AiExtractionLog si no existe
    - hasMany hacia ExtractionFeedback
    - _Requisitos: 6.1_

- [x] 2. GeminiService — nuevos métodos de agentes
  - [x] 2.1 Implementar `callGeminiText()` (método privado text-only)
    - Nuevo método que envía prompt + schema a Gemini SIN imagen (sin `inline_data`)
    - Misma estructura curl que `callGemini()` pero sin el part de imagen
    - Parámetros: prompt, schema, timeout, maxOutputTokens
    - _Requisitos: 1.1, 2.1, 3.7, 4.6, 8.1, 8.2_
  - [x] 2.2 Implementar `percibir(string $imageBase64): ?array`
    - Prompt de visión completa: texto crudo + descripción visual + clasificación + confianza + razón
    - Schema con campos: texto_crudo, descripcion_visual, tipo_imagen, confianza, razon
    - Usa `callGemini()` existente (CON imagen), timeout 15s
    - _Requisitos: 1.1, 1.2, 1.3, 1.4, 1.7_
  - [x] 2.3 Implementar `analizarTexto(string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples = []): ?array`
    - Prompt específico por tipo (reutiliza lógica de prompts existentes pero sin imagen)
    - Inyecta few-shot examples si los hay
    - Usa `callGeminiText()` (SIN imagen), timeout 12s
    - _Requisitos: 2.1, 2.2, 2.3, 2.4_
  - [x] 2.4 Implementar `validar(array $datosExtraidos, array $contextoBd): ?array`
    - Prompt de validación: coherencia aritmética, fiscal, lógica
    - Schema con campos: datos_validados, inconsistencias[]
    - Usa `callGeminiText()` (SIN imagen), timeout 8s
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7_
  - [x] 2.5 Implementar `reconciliar(array $datos, array $inconsistencias, string $textoCrudo, array $contextoBd): ?array`
    - Prompt de reconciliación: resolver inconsistencias o generar preguntas
    - Schema con campos: datos_finales, correcciones_aplicadas[], preguntas[]
    - Usa `callGeminiText()` (SIN imagen), timeout 8s
    - _Requisitos: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  - [x] 2.6 Write property test: Propiedad 1 — imagen procesada exactamente una vez
    - **Propiedad 1: Imagen procesada exactamente una vez**
    - Mock de GeminiService, verificar que solo `percibir()` recibe imagen, los demás usan `callGeminiText()`
    - **Valida: Requisitos 1.1, 1.5, 2.1, 3.7, 4.6, 8.1, 8.2**
  - [x] 2.7 Write property test: Propiedad 2 — estructura de salida del Agente Visión
    - **Propiedad 2: Estructura de salida del Agente Visión**
    - Generar respuestas Gemini aleatorias válidas, verificar parsing correcto
    - **Valida: Requisitos 1.2, 1.7**

- [x] 3. Checkpoint — Verificar métodos de GeminiService
  - Ejecutar tests unitarios de GeminiService, asegurar que compilan y pasan. Preguntar al usuario si hay dudas.

- [x] 4. FeedbackService — motor de auto-aprendizaje
  - [x] 4.1 Crear `FeedbackService.php` con método `capturarFeedback(int $extractionLogId, ?int $compraId, array $datosGuardados): void`
    - Obtener extracted_data del log
    - Calcular diff campo a campo (incluye items con comparación por nombre)
    - Insertar registros en extraction_feedback por cada campo diferente
    - _Requisitos: 6.1, 6.2_
  - [x] 4.2 Implementar `getFewShotExamples(?string $proveedor, ?string $tipoImagen, int $limit = 5): array`
    - Query a extraction_feedback filtrado por proveedor o tipo_imagen
    - Ordenado por created_at DESC, limitado a $limit
    - _Requisitos: 6.3, 6.5_
  - [x] 4.3 Implementar `formatearEjemplos(array $corrections): string`
    - Formatear correcciones como texto natural para inyectar en prompt
    - Formato: "En extracciones anteriores de [proveedor], el usuario corrigió [campo] de '[original]' a '[corregido]'"
    - _Requisitos: 6.4_
  - [x] 4.4 Implementar `computeDiff(array $original, array $final): array`
    - Comparación campo a campo entre datos extraídos y datos guardados
    - Manejo especial para array de items (comparar por nombre de item)
    - _Requisitos: 6.1, 6.2_
  - [x] 4.5 Write property test: Propiedad 5 — inyección correcta de few-shot examples
    - **Propiedad 5: Inyección correcta de few-shot examples**
    - Generar N feedback records aleatorios, verificar inclusión en prompt
    - **Valida: Requisitos 2.4, 6.3, 6.5**
  - [x] 4.6 Write property test: Propiedad 15 — diff de feedback captura todas las diferencias
    - **Propiedad 15: Diff de feedback captura todas las diferencias**
    - Generar pares de datos con diferencias aleatorias, verificar registros creados
    - **Valida: Requisitos 6.1, 6.2**

- [x] 5. PipelineExtraccionService — orquestador multi-agente
  - [x] 5.1 Implementar `ejecutarMultiAgente(string $imageUrl, ?callable $onEvent = null): array`
    - Orquestar secuencialmente: percibir → analizarTexto → validar → reconciliar
    - Emitir eventos SSE para cada fase (vision, analisis, validacion, reconciliacion)
    - Cargar contexto BD con ClasificadorService::cargarContexto()
    - Obtener few-shot examples via FeedbackService
    - Degradación graceful: si agente 3 falla → skip validación; si agente 4 falla → skip reconciliación
    - _Requisitos: 5.1, 5.2, 5.5_
  - [x] 5.2 Implementar logging y cálculo de costos en ejecutarMultiAgente
    - Registrar en ai_extraction_logs con model_id "pipeline:multi-agent-gemini"
    - Desglose de tokens por agente en raw_response
    - Calcular estimated_cost_usd con fórmula de tarifas Gemini
    - _Requisitos: 5.3, 5.4, 8.3_
  - [x] 5.3 Implementar detección automática de pipeline (env flag)
    - Si `MULTI_AGENT_PIPELINE=true` (o default), usar ejecutarMultiAgente
    - Si no, fallback a ejecutarGemini existente
    - Mantener backward compatibility total
    - _Requisitos: 5.5, 8.1_
  - [x] 5.4 Write property test: Propiedad 6 — validación aritmética detecta inconsistencias
    - **Propiedad 6: Validación aritmética detecta inconsistencias correctamente**
    - Generar items con precios/cantidades/subtotales aleatorios, verificar detección con tolerancia 2%
    - **Valida: Requisitos 3.1, 3.5**
  - [x] 5.5 Write property test: Propiedad 7 — validación fiscal detecta IVA incorrecto
    - **Propiedad 7: Validación fiscal detecta IVA incorrecto**
    - Generar combinaciones monto_neto/iva aleatorias, verificar detección
    - **Valida: Requisitos 3.2**
  - [x] 5.6 Write property test: Propiedad 10 — reconciliación sin inconsistencias es pass-through
    - **Propiedad 10: Reconciliación sin inconsistencias es pass-through**
    - Generar datos consistentes, verificar que salida = entrada y preguntas vacías
    - **Valida: Requisitos 4.5**
  - [x] 5.7 Write property test: Propiedad 12 — eventos SSE emitidos en orden correcto
    - **Propiedad 12: Eventos SSE emitidos en orden correcto**
    - Verificar orden estricto: vision→analisis→validacion→reconciliacion→completado
    - **Valida: Requisitos 5.1, 5.2**

- [x] 6. Checkpoint — Verificar pipeline backend completo
  - Ejecutar todos los tests del pipeline multi-agente. Asegurar que ejecutarMultiAgente funciona con mocks. Preguntar al usuario si hay dudas.

- [x] 7. Frontend — ExtractionPipeline.tsx con 4 fases y reconciliación
  - [x] 7.1 Actualizar ExtractionPipeline.tsx para soportar 4 fases multi-agente
    - Agregar tipo PhaseId con 'vision' | 'analisis' | 'validacion' | 'reconciliacion'
    - Definir MULTI_AGENT_PHASES con iconos: Eye, Brain, ShieldCheck, Scale
    - Detectar engine "multi-agent" en primer evento SSE y cambiar fases dinámicamente
    - _Requisitos: 7.1, 7.5_
  - [x] 7.2 Implementar PhaseDetails para fases de validación y reconciliación
    - Mostrar resumen de inconsistencias encontradas en fase validación
    - Mostrar correcciones automáticas aplicadas en fase reconciliación
    - Mostrar conteo de tokens y tiempo por fase
    - _Requisitos: 7.2, 7.5_
  - [x] 7.3 Implementar UI de preguntas de reconciliación
    - Interface ReconciliationQuestion con campo, descripcion, opciones[]
    - Renderizar preguntas como cards con botones seleccionables por opción
    - Callback onReconciliationNeeded para notificar al parent
    - Responsive a 320px mínimo
    - _Requisitos: 7.3, 7.4, 7.6_
  - [x] 7.4 Actualizar page.tsx de registro para manejar flujo de reconciliación
    - Recibir preguntas de reconciliación del pipeline
    - Enviar respuestas al backend (POST con respuestas seleccionadas)
    - Aplicar correcciones al formulario de compra
    - _Requisitos: 7.3, 7.4_

- [x] 8. Wiring — Captura de feedback en CompraController
  - [x] 8.1 Inyectar FeedbackService en CompraController
    - Agregar dependencia en constructor
    - _Requisitos: 6.1_
  - [x] 8.2 Capturar feedback al guardar compra en método `store()`
    - Si el request incluye `extraction_log_id`, invocar FeedbackService::capturarFeedback()
    - Pasar extraction_log_id, compra_id resultante, y datos guardados
    - No bloquear el guardado si el feedback falla (try/catch con Log::warning)
    - _Requisitos: 6.1, 6.2_

- [x] 9. ExtraccionController — usar ejecutarMultiAgente
  - [x] 9.1 Actualizar `extractPipeline()` para usar ejecutarMultiAgente cuando disponible
    - Verificar flag de env o config para decidir qué pipeline usar
    - Mantener fallback a ejecutar() si multi-agente no está habilitado
    - _Requisitos: 5.1, 5.5_

- [x] 10. Checkpoint final — Integración completa
  - Ejecutar todos los tests. Verificar flujo completo: upload imagen → pipeline 4 agentes → SSE en frontend → guardar compra → captura feedback. Preguntar al usuario si hay dudas.

## Notas

- Todas las tareas son obligatorias, incluyendo los property-based tests
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Se mantiene backward compatibility: `ejecutarGemini()` sigue funcionando, `ejecutarMultiAgente()` es el nuevo pipeline
- El frontend detecta dinámicamente si el backend envía 4 fases o 2 fases
