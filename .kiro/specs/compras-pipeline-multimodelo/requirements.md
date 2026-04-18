# Requisitos — Pipeline Multi-Modelo de Extracción de Compras

## Introducción

Reescritura del sistema de extracción de datos de compras de La Ruta 11. El sistema actual usa un solo call a Nova Pro con un prompt de ~4000 tokens que intenta hacer todo (clasificar, leer texto, identificar objetos, cruzar proveedores). Esto falla con imágenes sin texto (sacos de papas, productos a granel) y es lento (~5s). El nuevo pipeline usa 3 servicios AWS en fases: Rekognition (percepción rápida), Nova Micro (clasificación + contexto BD), Nova Pro (análisis final con prompt corto y específico). El frontend muestra el progreso en tiempo real via SSE.

## Glosario

- **Pipeline**: Flujo de 3 fases secuenciales que procesan una imagen de compra
- **Fase_Percepcion**: Fase 1 — Rekognition DetectLabels + DetectText en paralelo (~1s)
- **Fase_Clasificacion**: Fase 2 — Nova Micro clasifica tipo de imagen y carga contexto BD (~0.5s)
- **Fase_Analisis**: Fase 3 — Nova Pro analiza con prompt específico al tipo detectado (~3s)
- **SSE**: Server-Sent Events — streaming HTTP unidireccional del servidor al cliente
- **ExtraccionService**: Servicio actual que será reemplazado por PipelineExtraccionService

## Requisitos

### Requisito 1: Fase de Percepción (Rekognition)

**User Story:** Como sistema, quiero extraer labels y textos de la imagen usando Rekognition antes de invocar modelos LLM, para tener señales concretas que mejoren la clasificación y reducir la carga del modelo grande.

#### Criterios de Aceptación

1. WHEN una imagen se envía al pipeline, THE Fase_Percepcion SHALL ejecutar Rekognition DetectLabels y DetectText en paralelo sobre la imagen almacenada en S3.
2. THE Fase_Percepcion SHALL retornar labels con confianza > 70% (ej: "Bag", "Potato", "Scale", "Receipt", "Text") y todos los textos detectados con sus bounding boxes.
3. WHEN Rekognition DetectLabels falla o retorna 0 labels con confianza > 70%, THE pipeline SHALL continuar sin labels (Nova Pro compensará con análisis visual directo).
4. WHEN Rekognition DetectText falla, THE pipeline SHALL continuar sin textos pre-extraídos (Nova Pro leerá textos de la imagen directamente).
5. THE Fase_Percepcion SHALL completarse en menos de 2 segundos.
6. WHEN la Fase_Percepcion completa, THE pipeline SHALL emitir un evento SSE con los labels y textos detectados al frontend.

### Requisito 2: Fase de Clasificación (Nova Micro)

**User Story:** Como sistema, quiero clasificar el tipo de imagen y cargar contexto relevante de BD antes del análisis principal, para que Nova Pro reciba un prompt corto y específico.

#### Criterios de Aceptación

1. WHEN la Fase_Percepcion completa, THE Fase_Clasificacion SHALL enviar los labels y textos a Nova Micro (sin imagen) para clasificar el tipo: boleta, factura, producto, bascula, transferencia, desconocido.
2. THE Fase_Clasificacion SHALL cargar contexto de BD según el tipo clasificado: para boleta/factura → proveedores conocidos + RUTs; para producto → ingredientes + precios históricos + equivalencias; para bascula → ingredientes + últimos pesos; para transferencia → mapeo personas→proveedores.
3. WHEN Nova Micro no puede clasificar con confianza (tipo = "desconocido"), THE pipeline SHALL enviar tipo "general" a Nova Pro con el prompt completo actual como fallback.
4. THE Fase_Clasificacion SHALL completarse en menos de 1 segundo (Nova Micro es solo texto, sin imagen).
5. WHEN la Fase_Clasificacion completa, THE pipeline SHALL emitir un evento SSE con el tipo detectado y el contexto cargado.

### Requisito 3: Fase de Análisis (Nova Pro)

**User Story:** Como sistema, quiero analizar la imagen con un prompt corto y específico al tipo detectado, para obtener mayor precisión y menor latencia que el prompt monolítico actual.

#### Criterios de Aceptación

1. WHEN la Fase_Clasificacion completa, THE Fase_Analisis SHALL enviar la imagen a Nova Pro con: los labels de Rekognition, los textos de Rekognition, el tipo clasificado, el contexto de BD, y un prompt específico para ese tipo de imagen.
2. THE Fase_Analisis SHALL usar prompts separados por tipo: prompt_boleta (~800 tokens), prompt_factura (~800 tokens), prompt_producto (~400 tokens), prompt_bascula (~400 tokens), prompt_transferencia (~400 tokens), prompt_general (~2000 tokens, fallback).
3. THE Fase_Analisis SHALL retornar el mismo formato JSON que el ExtraccionService actual para mantener compatibilidad con el frontend existente.
4. WHEN Nova Pro retorna datos, THE pipeline SHALL aplicar las mismas reglas post-extracción existentes: mapPersonToSupplier, matchProveedorByRut, applySupplierRules, product equivalences.
5. THE Fase_Analisis SHALL completarse en menos de 5 segundos.
6. WHEN la Fase_Analisis completa, THE pipeline SHALL emitir un evento SSE final con los datos extraídos.

### Requisito 4: Endpoint SSE y Streaming al Frontend

**User Story:** Como usuario, quiero ver en tiempo real qué está haciendo la IA paso a paso, para entender el proceso y confiar en los resultados.

#### Criterios de Aceptación

1. THE API SHALL exponer un endpoint POST `/api/v1/admin/compras/extract-pipeline` que retorne un stream SSE con eventos por cada fase del pipeline.
2. EACH evento SSE SHALL tener formato: `data: {"fase": "percepcion|clasificacion|analisis|completado|error", "status": "running|done|error", "data": {...}, "elapsed_ms": N}`.
3. WHEN una fase completa exitosamente, THE evento SHALL incluir los datos relevantes de esa fase (labels, textos, tipo, resultado final).
4. WHEN una fase falla, THE evento SHALL incluir el error y el pipeline SHALL continuar con las fases siguientes usando datos parciales.
5. THE endpoint SHALL mantener compatibilidad: el endpoint actual `/compras/extract` seguirá funcionando como wrapper síncrono del pipeline (espera todas las fases y retorna el resultado final).
6. THE frontend SHALL mostrar cada fase con un indicador visual: spinner mientras corre, check verde al completar, datos resumidos de cada fase.

### Requisito 5: Componente Visual del Pipeline

**User Story:** Como usuario, quiero ver un panel con los pasos de la IA mostrando qué detectó en cada fase, para tener transparencia del proceso.

#### Criterios de Aceptación

1. THE frontend SHALL mostrar un componente `ExtractionPipeline` con 3-4 pasos verticales, cada uno con: ícono, título, estado (pendiente/corriendo/completado/error), y datos resumidos.
2. WHEN la Fase_Percepcion completa, THE componente SHALL mostrar los labels detectados como badges y un resumen de textos encontrados.
3. WHEN la Fase_Clasificacion completa, THE componente SHALL mostrar el tipo de imagen detectado con un ícono representativo y el contexto cargado (ej: "3 proveedores, 45 ingredientes").
4. WHEN la Fase_Analisis completa, THE componente SHALL mostrar el resultado final: proveedor, items, monto total, y transicionar al formulario de edición existente.
5. THE componente SHALL funcionar tanto en la página de registro individual como en la subida masiva.
6. IF el pipeline total tarda más de 8 segundos, THE componente SHALL mostrar un mensaje "Tomando más tiempo de lo normal..." sin bloquear la UI.
