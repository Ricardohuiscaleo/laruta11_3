# Documento de Requerimientos — Migración Pipeline Compras a Google Gemini

## Introducción

El pipeline de extracción de compras de La Ruta 11 actualmente utiliza 3 servicios AWS (Rekognition → Nova Micro → Nova Pro) para procesar imágenes de compras (boletas, facturas, productos, básculas, transferencias). Debido a un evento de seguridad en la cuenta AWS (access key comprometida), Bedrock y Rekognition están bloqueados. Este feature migra el pipeline a Google Gemini API (`gemini-2.5-flash-lite`), que al ser multimodal puede clasificar y analizar imágenes en una sola llamada, simplificando la arquitectura de 3 fases a 2 fases. El presupuesto de Google Cloud es CLP 10.000 (~$10 USD) prepago, por lo que el monitoreo de costos por tokens es crítico.

## Glosario

- **Pipeline**: Secuencia de fases de procesamiento que transforma una imagen de compra en datos estructurados JSON.
- **GeminiService**: Nuevo servicio PHP que encapsula las llamadas a la API REST de Google Gemini para clasificación y análisis multimodal.
- **PipelineExtraccionService**: Servicio orquestador existente que coordina las fases del pipeline, emite eventos SSE y aplica post-procesamiento.
- **ClasificadorService**: Servicio existente que clasifica el tipo de imagen usando Nova Micro y carga contexto de BD.
- **AnalisisService**: Servicio existente que analiza imágenes con Nova Pro usando prompts específicos por tipo.
- **RekognitionService**: Servicio existente que detecta labels y textos en imágenes vía AWS Rekognition.
- **SSE**: Server-Sent Events — protocolo de streaming unidireccional usado para mostrar progreso del pipeline en tiempo real.
- **ExtractionPipeline**: Componente React del frontend que visualiza las fases del pipeline vía SSE.
- **AiExtractionLog**: Modelo Eloquent que registra cada extracción con datos crudos, datos extraídos, confianza, tiempo y modelo usado.
- **Tipo_Imagen**: Clasificación del documento: `boleta`, `factura`, `producto`, `bascula`, `transferencia`, `desconocido`.
- **usageMetadata**: Objeto en la respuesta de Gemini que contiene `promptTokenCount`, `candidatesTokenCount` y `totalTokenCount`.
- **Structured Outputs**: Feature de Gemini que garantiza respuestas JSON válidas contra un JSON Schema definido, usando `responseMimeType: "application/json"` + `responseJsonSchema` en `generationConfig`.
- **inline_data**: Formato de Gemini API para enviar imágenes base64 dentro del request: `{"inline_data": {"mime_type": "image/jpeg", "data": "<base64>"}}`.
- **Free_Tier**: Nivel gratuito de Gemini 2.5 Flash-Lite: $0.10/1M tokens input, $0.40/1M tokens output (gratis dentro del free tier).
- **Post_Procesamiento**: Lógica de reglas de negocio aplicada después de la extracción IA: mapPersonToSupplier, matchProveedorByRut, applySupplierRules, equivalencias de productos.
- **SugerenciaService**: Servicio existente que hace matching fuzzy de proveedores e ingredientes contra la BD.
- **ComprasSection**: Componente SPA principal de la sección Compras en mi3-frontend, con tabs lazy-loaded.

## Requerimientos

### Requerimiento 1: Servicio Gemini para clasificación y análisis multimodal

**User Story:** Como sistema de compras, quiero enviar una imagen en base64 a Gemini y recibir clasificación + datos estructurados en una sola llamada, para reemplazar los 3 servicios AWS bloqueados.

#### Criterios de Aceptación

1. THE GeminiService SHALL enviar imágenes en formato `inline_data` (`{"inline_data": {"mime_type": "image/jpeg", "data": "<base64>"}}`) a la API REST de Google Gemini (`https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`) usando el modelo `gemini-2.5-flash-lite` con autenticación por API key como query parameter `key`.
2. WHEN el GeminiService recibe una imagen, THE GeminiService SHALL ejecutar una llamada multimodal que clasifique el tipo de imagen Y extraiga datos estructurados en una sola petición HTTP.
3. THE GeminiService SHALL utilizar los mismos prompts específicos por tipo definidos en AnalisisService (boleta, factura, producto, bascula, transferencia, general) adaptados para incluir la instrucción de clasificación.
4. THE GeminiService SHALL usar Structured Outputs de Gemini configurando `generationConfig` con `responseMimeType: "application/json"` y `responseJsonSchema` con el JSON Schema del formato de extracción esperado, para garantizar respuestas JSON válidas sin necesidad de parseo de texto libre.
5. THE GeminiService SHALL parsear la respuesta JSON de Gemini extrayendo el campo `candidates[0].content.parts[0].text` y decodificándolo como JSON. WHEN Structured Outputs está activo, la respuesta es JSON válido garantizado por el modelo.
6. IF la respuesta de Gemini no contiene JSON válido (caso edge sin Structured Outputs), THEN THE GeminiService SHALL intentar extraer JSON de bloques markdown (```json...```) como fallback antes de retornar null.
7. IF la llamada a Gemini falla por timeout, error HTTP o respuesta vacía, THEN THE GeminiService SHALL registrar el error en el log y retornar null para que el pipeline active el fallback.
8. THE GeminiService SHALL extraer `usageMetadata` (promptTokenCount, candidatesTokenCount, totalTokenCount) de cada respuesta de Gemini y retornarlos junto con los datos extraídos.
9. THE GeminiService SHALL configurar `generationConfig` con `temperature: 0.1`, `maxOutputTokens: 2048`, `responseMimeType: "application/json"` y `responseJsonSchema` con el schema de extracción para respuestas deterministas y tipadas.
10. THE GeminiService SHALL leer la API key desde la variable de entorno `GOOGLE_API_KEY` configurada en el servidor.
11. THE GeminiService SHALL enviar el header `Content-Type: application/json` y autenticarse pasando la API key como query parameter `key` en la URL (no como header `Authorization`).

### Requerimiento 2: Integración del pipeline con Gemini como proveedor principal

**User Story:** Como sistema de compras, quiero que el pipeline use Gemini cuando Bedrock no esté disponible, para que las extracciones sigan funcionando sin interrupción.

#### Criterios de Aceptación

1. THE PipelineExtraccionService SHALL detectar la disponibilidad de Gemini verificando que la variable de entorno `GOOGLE_API_KEY` esté configurada y no vacía.
2. WHEN `GOOGLE_API_KEY` está configurada, THE PipelineExtraccionService SHALL usar GeminiService en lugar de RekognitionService + ClasificadorService + AnalisisService.
3. WHEN el pipeline usa Gemini, THE PipelineExtraccionService SHALL ejecutar 2 fases: Fase 1 "Clasificación" (GeminiService clasifica el tipo de imagen usando contexto de BD) y Fase 2 "Análisis" (GeminiService analiza la imagen con prompt específico por tipo).
4. THE PipelineExtraccionService SHALL emitir eventos SSE compatibles con el frontend para cada fase del pipeline Gemini, usando los identificadores `clasificacion` y `analisis`.
5. THE PipelineExtraccionService SHALL preservar toda la lógica de post-procesamiento existente (mapPersonToSupplier, matchProveedorByRut, applySupplierRules, applyProductEquivalences) sin modificaciones.
6. THE PipelineExtraccionService SHALL preservar la integración con SugerenciaService (matchProveedor, matchItems, inferProveedorFromItems) sin modificaciones.
7. IF GeminiService retorna null en la fase de análisis, THEN THE PipelineExtraccionService SHALL registrar el error y retornar un resultado fallido con `fallback: manual`.
8. THE PipelineExtraccionService SHALL cargar el contexto de BD (proveedores, RUTs, productos, equivalencias, patrones aprendidos, mapeo personas) usando ClasificadorService.cargarContexto() antes de invocar GeminiService.

### Requerimiento 3: Clasificación con Gemini en dos pasos

**User Story:** Como sistema de compras, quiero que Gemini primero clasifique el tipo de imagen y luego analice con el prompt específico, para optimizar la calidad de extracción usando contexto filtrado por tipo.

#### Criterios de Aceptación

1. WHEN el pipeline Gemini inicia, THE GeminiService SHALL ejecutar primero una llamada de clasificación enviando la imagen base64 con un prompt de clasificación que determine el tipo entre: `boleta`, `factura`, `producto`, `bascula`, `transferencia`, `desconocido`.
2. THE GeminiService SHALL retornar la clasificación como un objeto con `tipo_imagen`, `confianza` (0.0-1.0) y `razon` (texto breve), idéntico al formato de ClasificadorService.
3. WHEN la clasificación retorna un tipo válido, THE PipelineExtraccionService SHALL cargar el contexto de BD filtrado por ese tipo usando ClasificadorService.cargarContexto().
4. WHEN el contexto está cargado, THE GeminiService SHALL ejecutar una segunda llamada multimodal enviando la imagen base64 con el prompt específico del tipo clasificado, incluyendo el contexto de BD.
5. IF la clasificación retorna `desconocido`, THEN THE GeminiService SHALL usar el prompt general para la fase de análisis.
6. IF la clasificación falla, THEN THE PipelineExtraccionService SHALL usar la clasificación por reglas (fallbackClassification) de ClasificadorService como respaldo.

### Requerimiento 4: Formato de salida JSON compatible

**User Story:** Como frontend de compras, quiero que los datos extraídos por Gemini tengan exactamente el mismo formato JSON que los de Nova Pro, para que no se requieran cambios en el formulario de edición ni en la lógica de guardado.

#### Criterios de Aceptación

1. THE GeminiService SHALL definir un `responseJsonSchema` (JSON Schema) que describa el formato de extracción con los campos: `tipo_imagen` (string, enum), `proveedor` (string|null), `rut_proveedor` (string|null), `fecha` (string|null, format date), `metodo_pago` (string, enum: cash/transfer/card/credit), `tipo_compra` (string, enum: ingredientes/insumos/equipamiento/otros), `items` (array de objetos con nombre, cantidad, unidad, precio_unitario, subtotal, descuento, empaque_detalle), `monto_neto` (integer|null), `iva` (integer|null), `monto_total` (integer|null), `peso_bascula` (number|null), `unidad_bascula` (string|null), `notas_ia` (string|null).
2. THE GeminiService SHALL enviar este `responseJsonSchema` en `generationConfig` junto con `responseMimeType: "application/json"` para que Gemini retorne JSON válido garantizado contra el schema, eliminando la necesidad de parseo de texto libre o instrucciones "Responde SOLO JSON" en el prompt.
3. THE GeminiService SHALL aplicar la misma normalización de montos que AnalisisService.normalizeAmounts(): convertir montos a enteros en pesos chilenos, manejar notación abreviada de básculas, y procesar descuentos por item.
4. THE PipelineExtraccionService SHALL retornar el resultado final con la misma estructura: `success`, `extraction_log_id`, `data`, `confianza`, `overall_confidence`, `processing_time_ms`, `pipeline_phases`, `sugerencias`.
5. THE GeminiService SHALL usar un schema de clasificación separado para la fase 1 con los campos: `tipo_imagen` (string, enum: boleta/factura/producto/bascula/transferencia/desconocido), `confianza` (number, minimum 0, maximum 1), `razon` (string).

### Requerimiento 5: Monitoreo de costos y tokens Gemini

**User Story:** Como administrador del sistema, quiero rastrear el consumo de tokens de Gemini por cada extracción, para monitorear el gasto contra el presupuesto de CLP 10.000.

#### Criterios de Aceptación

1. THE PipelineExtraccionService SHALL registrar en AiExtractionLog el campo `model_id` como `gemini-2.5-flash-lite` cuando se use Gemini.
2. THE PipelineExtraccionService SHALL almacenar los tokens consumidos (promptTokenCount, candidatesTokenCount, totalTokenCount) en el campo `raw_response` del AiExtractionLog junto con las fases del pipeline.
3. THE PipelineExtraccionService SHALL calcular el costo estimado de cada extracción usando las tarifas de Gemini 2.5 Flash-Lite ($0.10/1M tokens input, $0.40/1M tokens output) y almacenarlo en `raw_response.estimated_cost_usd`.
4. THE PipelineExtraccionService SHALL sumar los tokens de ambas llamadas Gemini (clasificación + análisis) para el total de la extracción.
5. THE PipelineExtraccionService SHALL incluir los datos de tokens en los eventos SSE de cada fase para que el frontend pueda mostrarlos en la consola de debug.

### Requerimiento 6: Actualización del frontend

**User Story:** Como usuario del sistema de compras, quiero ver el pipeline actualizado con las fases de Gemini y la versión v1.7, para saber que estoy usando el nuevo motor de extracción.

#### Criterios de Aceptación

1. THE ComprasSection SHALL mostrar la versión `v1.7` en el encabezado de la sección.
2. WHEN el pipeline usa Gemini, THE ExtractionPipeline SHALL mostrar 2 fases: "Clasificando imagen (Gemini)" y "Analizando con IA (Gemini)" en lugar de las 3 fases actuales.
3. WHEN el pipeline usa Bedrock (si se reactiva en el futuro), THE ExtractionPipeline SHALL mostrar las 3 fases originales: "Identificando objetos y textos", "Clasificando imagen", "Analizando con IA".
4. THE ExtractionPipeline SHALL recibir la información del motor usado (gemini o bedrock) desde los eventos SSE para adaptar la visualización dinámicamente.
5. THE ExtractionPipeline SHALL mantener toda la funcionalidad existente: indicador de lentitud, tiempo total, detalles por fase (tipo detectado, proveedor, items, monto, confianza).

### Requerimiento 7: Configuración y variables de entorno

**User Story:** Como desarrollador, quiero que la configuración de Gemini sea por variables de entorno, para poder cambiar entre proveedores IA sin modificar código.

#### Criterios de Aceptación

1. THE GeminiService SHALL leer la configuración desde variables de entorno: `GOOGLE_API_KEY` para la API key y `GEMINI_MODEL` (default: `gemini-2.5-flash-lite`) para el modelo.
2. WHEN `GOOGLE_API_KEY` no está configurada y Bedrock tampoco está disponible, THE PipelineExtraccionService SHALL retornar un error descriptivo indicando que no hay proveedor IA disponible.
3. THE GeminiService SHALL usar HTTP timeout de 20 segundos para la llamada de análisis y 8 segundos para la llamada de clasificación.
4. THE GeminiService SHALL autenticarse pasando la API key como query parameter `key` en la URL del endpoint (`?key={GOOGLE_API_KEY}`) e incluir el header `Content-Type: application/json`. Alternativamente, puede usar el header `x-goog-api-key` según la documentación oficial de Gemini.

### Requerimiento 8: Resiliencia y fallback

**User Story:** Como sistema de compras, quiero que el pipeline tenga mecanismos de fallback cuando Gemini falle, para que las compras siempre puedan registrarse.

#### Criterios de Aceptación

1. IF la llamada de clasificación de Gemini falla, THEN THE PipelineExtraccionService SHALL usar la clasificación por reglas existente (fallbackClassification de ClasificadorService) basada en labels y textos vacíos, resultando en tipo `desconocido` con prompt general.
2. IF la llamada de análisis de Gemini falla, THEN THE PipelineExtraccionService SHALL retornar `success: false` con `fallback: manual` para que el usuario ingrese los datos manualmente.
3. IF Gemini retorna un `tipo_imagen` no válido (fuera de boleta, factura, producto, bascula, transferencia, desconocido), THEN THE GeminiService SHALL normalizar el valor a `desconocido`.
4. IF la respuesta de Gemini excede el timeout configurado, THEN THE GeminiService SHALL abortar la petición y registrar el timeout en el log.
5. WHILE el pipeline está ejecutándose, THE PipelineExtraccionService SHALL capturar cualquier excepción no controlada y retornar un resultado fallido con el mensaje de error, sin interrumpir el flujo SSE.
