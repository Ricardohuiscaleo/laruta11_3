# Documento de Requisitos: Pipeline Multi-Agente para Extracción de Compras

## Introducción

El sistema actual de extracción IA de compras en La Ruta 11 utiliza un pipeline de 2 fases con Gemini (`clasificar()` + `analizar()`), donde la imagen se envía DOS veces al modelo multimodal. Esto resulta en ~520 tokens de imagen por extracción, con un costo aproximado de $1.000 CLP por boleta procesada (10 usos = presupuesto agotado de $10.000 CLP).

Esta refactorización reemplaza el pipeline de 2 fases por una arquitectura de 4 agentes especializados donde la imagen se procesa UNA sola vez (Agente 1 — Visión), y los agentes subsiguientes operan exclusivamente con texto. Además, incorpora auto-aprendizaje: cada corrección del usuario al editar datos extraídos se captura como diff y se inyecta como few-shot examples en futuras extracciones del mismo proveedor/tipo.

## Glosario

- **Pipeline_MultiAgente**: El orquestador que coordina la ejecución secuencial de los 4 agentes de extracción, reemplazando al método `ejecutarGemini()` actual en `PipelineExtraccionService`
- **Agente_Vision**: Agente 1 — realiza UNA llamada multimodal con imagen a Gemini para extraer todo el texto crudo visible, clasificar el tipo de documento, y generar una descripción visual completa del contenido (objetos, productos, colores, contexto espacial). Es la única llamada que incluye imagen (costosa). La descripción visual es crítica para imágenes con poco texto (productos, básculas)
- **Agente_Analisis**: Agente 2 — recibe el texto crudo del Agente_Vision (sin imagen) y un prompt específico por tipo de documento + contexto de BD (proveedores, ingredientes, RUTs) para estructurar los datos en formato JSON
- **Agente_Validacion**: Agente 3 — recibe los datos estructurados del Agente_Analisis (sin imagen) y verifica coherencia aritmética y lógica: subtotal = precio × cantidad, total = suma de items, proveedor existe en BD, fecha válida. Reemplaza las reglas hardcodeadas actuales (`reconcileSingleItemTotal`, `normalizeFecha`, `mapPersonToSupplier`, etc.)
- **Agente_Reconciliacion**: Agente 4 — cuando el Agente_Validacion detecta inconsistencias irresolubles, intenta resolverlas con razonamiento. Si no puede, genera preguntas para el usuario vía frontend
- **Motor_Aprendizaje**: Subsistema que captura las correcciones del usuario (diff entre datos extraídos y datos guardados), las almacena, y las inyecta como few-shot examples en el prompt del Agente_Analisis para futuras extracciones del mismo proveedor/tipo
- **Evento_SSE**: Mensaje Server-Sent Event emitido por el Pipeline_MultiAgente al frontend para actualizar el progreso de cada fase en tiempo real
- **Correccion_Usuario**: Diferencia entre los datos extraídos por la IA (`extracted_data` en `ai_extraction_logs`) y los datos finales guardados por el usuario (`compras_detalle`), capturada como diff campo a campo
- **Pregunta_Reconciliacion**: Pregunta generada por el Agente_Reconciliacion cuando detecta una inconsistencia que no puede resolver automáticamente, presentada al usuario en el frontend para que elija la respuesta correcta
- **ExtractionPipeline_UI**: El componente React `ExtractionPipeline.tsx` en mi3-frontend que muestra las fases del pipeline en tiempo real vía SSE

## Requisitos

### Requisito 1: Agente de Visión — comprensión visual completa y extracción única de imagen

**User Story:** Como administrador, quiero que la imagen de boleta/factura se procese una sola vez extrayendo tanto el texto como la comprensión visual del contenido, para reducir el costo de tokens de imagen a la mitad sin perder información contextual.

#### Criterios de Aceptación

1. WHEN una imagen es enviada al Pipeline_MultiAgente, THE Agente_Vision SHALL realizar exactamente UNA llamada multimodal a Gemini que incluya la imagen
2. THE Agente_Vision SHALL retornar: el texto crudo completo visible en la imagen, el tipo de documento clasificado (boleta, factura, producto, bascula, transferencia, desconocido), un score de confianza entre 0.0 y 1.0, y una descripción visual del contenido de la imagen
3. THE descripción visual SHALL incluir: objetos identificados (cajas, sacos, bolsas, bandejas, gamelas, balanzas, documentos), productos reconocidos visualmente (tomates, papas, paltas, carne, pan), colores y formas relevantes (bolsa azul/rosada, productos redondos oscuros), estado del producto (fresco, empacado, a granel), y contexto espacial (mostrador de feria, estante de supermercado, cocina)
4. WHEN la imagen es de tipo "producto" o "bascula" donde hay poco o ningún texto visible, THE Agente_Vision SHALL compensar con una descripción visual detallada que permita al Agente_Analisis identificar el producto, estimar cantidad y peso sin necesidad de ver la imagen
5. WHEN el Agente_Vision completa su ejecución, THE Pipeline_MultiAgente SHALL pasar el texto crudo, la clasificación, y la descripción visual a los agentes subsiguientes sin incluir la imagen original
6. IF el Agente_Vision falla o retorna null, THEN THE Pipeline_MultiAgente SHALL registrar el error en `ai_extraction_logs` con status "failed" y retornar un resultado de fallo con fallback "manual"
7. THE Agente_Vision SHALL utilizar `responseMimeType: application/json` y un `responseSchema` que incluya los campos `texto_crudo`, `descripcion_visual`, `tipo_imagen`, `confianza` y `razon`

### Requisito 2: Agente de Análisis — estructuración de datos por tipo

**User Story:** Como administrador, quiero que los datos extraídos se estructuren usando un prompt específico por tipo de documento con contexto de BD, para obtener datos precisos sin necesidad de enviar la imagen nuevamente.

#### Criterios de Aceptación

1. WHEN el Agente_Vision retorna texto crudo y clasificación, THE Agente_Analisis SHALL recibir exclusivamente texto (sin imagen) junto con el prompt específico para el tipo de documento detectado
2. THE Agente_Analisis SHALL cargar contexto de BD (proveedores conocidos, ingredientes, mapa de RUTs, equivalencias de productos) usando el método `cargarContexto()` existente en `ClasificadorService`
3. THE Agente_Analisis SHALL retornar datos estructurados en el mismo formato JSON que el schema de extracción actual: tipo_imagen, proveedor, rut_proveedor, fecha, metodo_pago, tipo_compra, items (con nombre, cantidad, unidad, precio_unitario, subtotal, categoria_sugerida), monto_neto, iva, monto_total
4. WHEN el Motor_Aprendizaje tiene correcciones previas para el mismo proveedor o tipo de documento, THE Agente_Analisis SHALL incluir esas correcciones como few-shot examples en su prompt
5. IF el Agente_Analisis falla o retorna null, THEN THE Pipeline_MultiAgente SHALL registrar el error y retornar un resultado de fallo

### Requisito 3: Agente de Validación — verificación de coherencia inteligente

**User Story:** Como administrador, quiero que un agente de IA valide la coherencia de los datos extraídos usando razonamiento en lugar de reglas hardcodeadas, para detectar errores que las reglas fijas no capturan.

#### Criterios de Aceptación

1. WHEN el Agente_Analisis retorna datos estructurados, THE Agente_Validacion SHALL verificar coherencia aritmética: que el subtotal de cada item sea igual a precio_unitario multiplicado por cantidad, y que monto_total sea igual a la suma de subtotales de los items (con tolerancia de 2%)
2. THE Agente_Validacion SHALL verificar coherencia fiscal: que IVA sea aproximadamente 19% del monto_neto (con tolerancia de 2%), y que monto_total sea aproximadamente monto_neto más IVA
3. THE Agente_Validacion SHALL verificar coherencia lógica: que el proveedor no sea el comprador (La Ruta 11, Ricardo Huiscaleo), que la fecha no sea de empaque/vencimiento, y que el proveedor exista en la BD o sea un nombre razonable
4. THE Agente_Validacion SHALL retornar una lista de inconsistencias encontradas, cada una con el campo afectado, el valor actual, el valor esperado, y una severidad (error, advertencia)
5. WHEN no se detectan inconsistencias, THE Agente_Validacion SHALL retornar los datos validados sin modificaciones y una lista vacía de inconsistencias
6. THE Agente_Validacion SHALL reemplazar la lógica de las funciones hardcodeadas actuales: `reconcileSingleItemTotal()`, `normalizeFecha()`, `mapPersonToSupplier()`, `matchProveedorByRut()`, y `applySupplierRules()`
7. THE Agente_Validacion SHALL operar exclusivamente con texto (sin imagen), recibiendo los datos estructurados del Agente_Analisis y el contexto de BD necesario

### Requisito 4: Agente de Reconciliación — resolución de inconsistencias

**User Story:** Como administrador, quiero que las inconsistencias detectadas se resuelvan automáticamente cuando sea posible, y que se me pregunte cuando la IA no pueda decidir, para minimizar la intervención manual.

#### Criterios de Aceptación

1. WHEN el Agente_Validacion retorna inconsistencias, THE Agente_Reconciliacion SHALL intentar resolver cada inconsistencia usando razonamiento basado en el contexto disponible (texto crudo original, datos estructurados, contexto de BD)
2. WHEN el Agente_Reconciliacion resuelve una inconsistencia, THE Agente_Reconciliacion SHALL aplicar la corrección a los datos estructurados y registrar la corrección en el campo `notas_ia`
3. IF el Agente_Reconciliacion no puede resolver una inconsistencia con confianza suficiente, THEN THE Agente_Reconciliacion SHALL generar una Pregunta_Reconciliacion con las opciones posibles para que el usuario decida
4. THE Pregunta_Reconciliacion SHALL incluir: una descripción del problema, las opciones disponibles con sus valores, y el campo afectado en los datos estructurados
5. WHEN no hay inconsistencias pendientes (todas resueltas automáticamente o sin inconsistencias), THE Agente_Reconciliacion SHALL retornar los datos finales sin generar preguntas
6. THE Agente_Reconciliacion SHALL operar exclusivamente con texto (sin imagen)

### Requisito 5: Orquestación del pipeline y eventos SSE

**User Story:** Como administrador, quiero ver el progreso de los 4 agentes en tiempo real en el frontend, para entender qué está haciendo la IA en cada momento.

#### Criterios de Aceptación

1. THE Pipeline_MultiAgente SHALL ejecutar los agentes en orden secuencial: Agente_Vision → Agente_Analisis → Agente_Validacion → Agente_Reconciliacion
2. WHEN cada agente inicia y completa su ejecución, THE Pipeline_MultiAgente SHALL emitir un Evento_SSE con la fase correspondiente (vision, analisis, validacion, reconciliacion), el status (running, done, error), los datos relevantes de la fase, y el tiempo transcurrido en milisegundos
3. THE Pipeline_MultiAgente SHALL registrar el resultado completo en `ai_extraction_logs` con el campo `model_id` indicando "pipeline:multi-agent-gemini", las fases del pipeline en `raw_response`, y los tokens totales consumidos por todos los agentes
4. THE Pipeline_MultiAgente SHALL calcular el costo estimado en USD sumando los tokens de prompt y candidates de los 4 agentes, usando las tarifas del modelo Gemini configurado
5. WHEN el pipeline completa exitosamente, THE Pipeline_MultiAgente SHALL retornar el mismo formato de resultado que el pipeline actual: success, extraction_log_id, data, confianza, overall_confidence, processing_time_ms, pipeline_phases, sugerencias

### Requisito 6: Auto-aprendizaje por correcciones del usuario

**User Story:** Como administrador, quiero que la IA aprenda de mis correcciones para que cada vez extraiga datos más precisos para el mismo proveedor o tipo de documento.

#### Criterios de Aceptación

1. WHEN el usuario guarda una compra con datos editados respecto a los datos extraídos por la IA, THE Motor_Aprendizaje SHALL calcular el diff campo a campo entre `extracted_data` (de `ai_extraction_logs`) y los datos finales guardados (de `compras_detalle`)
2. THE Motor_Aprendizaje SHALL almacenar cada Correccion_Usuario en la tabla `extraction_feedback` con el extraction_log_id, compra_id, field_name, original_value, y corrected_value
3. WHEN el Agente_Analisis procesa una nueva extracción, THE Motor_Aprendizaje SHALL consultar las últimas N correcciones (máximo 5) para el mismo proveedor o tipo de documento y proveerlas como few-shot examples en el prompt
4. THE Motor_Aprendizaje SHALL formatear las correcciones como ejemplos en lenguaje natural: "En extracciones anteriores de [proveedor], el usuario corrigió [campo] de '[valor_original]' a '[valor_corregido]'"
5. IF no existen correcciones previas para el proveedor o tipo de documento, THEN THE Motor_Aprendizaje SHALL no inyectar few-shot examples y el Agente_Analisis SHALL operar con su prompt base

### Requisito 7: Frontend — visualización de 4 fases y reconciliación interactiva

**User Story:** Como administrador, quiero ver las 4 fases del pipeline en la UI y poder responder preguntas de reconciliación directamente, para tener control sobre las decisiones que la IA no puede tomar sola.

#### Criterios de Aceptación

1. WHEN el Pipeline_MultiAgente emite eventos SSE, THE ExtractionPipeline_UI SHALL mostrar 4 fases: Visión (icono ojo), Análisis (icono cerebro), Validación (icono escudo/check), Reconciliación (icono balanza)
2. WHEN el Agente_Validacion completa, THE ExtractionPipeline_UI SHALL mostrar un resumen de las inconsistencias encontradas y las correcciones automáticas aplicadas por el Agente_Reconciliacion
3. WHEN el Agente_Reconciliacion genera Preguntas_Reconciliacion, THE ExtractionPipeline_UI SHALL mostrar cada pregunta con sus opciones como botones seleccionables para que el usuario elija la respuesta correcta
4. WHEN el usuario responde una Pregunta_Reconciliacion, THE ExtractionPipeline_UI SHALL enviar la respuesta al backend para que el Pipeline_MultiAgente aplique la corrección y continúe el flujo
5. THE ExtractionPipeline_UI SHALL mostrar el conteo de tokens y tiempo transcurrido para cada fase completada
6. THE ExtractionPipeline_UI SHALL ser responsive y funcionar correctamente en pantallas móviles (min-width 320px)

### Requisito 8: Reducción de costo por extracción

**User Story:** Como dueño del negocio, quiero que el costo por extracción se reduzca significativamente, para poder procesar más boletas dentro del presupuesto mensual de $10.000 CLP.

#### Criterios de Aceptación

1. THE Pipeline_MultiAgente SHALL enviar la imagen a Gemini exactamente una vez (en el Agente_Vision), reduciendo los tokens de imagen de ~520 (2 llamadas) a ~260 (1 llamada)
2. THE Agente_Analisis, Agente_Validacion, y Agente_Reconciliacion SHALL operar exclusivamente con tokens de texto, sin incluir datos de imagen en sus llamadas a Gemini
3. THE Pipeline_MultiAgente SHALL registrar el desglose de tokens por agente (prompt y candidates) en el campo `raw_response` de `ai_extraction_logs` para permitir monitoreo de costos
4. WHILE el Motor_Aprendizaje acumula correcciones, THE Agente_Analisis SHALL producir resultados progresivamente más precisos, reduciendo la necesidad de correcciones manuales y re-procesamientos