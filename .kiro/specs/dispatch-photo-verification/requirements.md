# Documento de Requisitos — Verificación de Fotos de Despacho con IA

## Introducción

Actualmente en caja3 (caja.laruta11.cl), la sección "FOTOS DEL PEDIDO (obligatorio)" en MiniComandas permite subir fotos de despacho de forma libre sin estructura ni validación. No hay control sobre qué fotos se necesitan, no se bloquea el despacho si faltan fotos, y no existe verificación automática del contenido fotográfico. Esto ha resultado en problemas reales documentados: clientes recibiendo productos mal empacados (completos volcados/de lado dentro de la caja), items faltantes, y sin evidencia verificable pre-despacho.

Este feature implementa un sistema de verificación fotográfica inteligente **exclusivamente para pedidos delivery** (`delivery_type === 'delivery'`). El sistema: (1) requiere 2 fotos obligatorias (productos visibles y bolsa sellada) para cada pedido delivery, (2) bloquea el despacho hasta completar ambas fotos, (3) introduce un botón "📦 DESPACHAR A DELIVERY" que se habilita al completar las fotos, (4) usa Gemini API (visión) para analizar cada foto y mostrar feedback en un panel visible debajo de los slots, (5) permite eliminar y re-subir fotos con re-análisis IA, y (6) almacena los resultados de verificación en una tabla `dispatch_photo_feedback` para aprendizaje continuo. Para pedidos en local (pickup/cuartel), el flujo de entrega permanece exactamente como hoy — sin fotos requeridas, sin sección de fotos visible, y botón "✅ ENTREGAR" habilitado directamente.

## Glosario

- **MiniComandas**: Componente React principal (`MiniComandas.jsx`) que muestra los pedidos activos en caja3 con sus items, pagos, y controles de despacho.
- **Pedido_Delivery**: Pedido cuyo `delivery_type === 'delivery'`, destinado a ser entregado por un rider fuera del local.
- **Pedido_Local**: Pedido cuyo `delivery_type` es `'pickup'` o `'cuartel'`, retirado en el local por el cliente.
- **Foto_Requerida**: Una de las 2 fotos obligatorias para un Pedido_Delivery: "📸 Foto de productos" (productos visibles antes de empacar) y "🛍️ Foto en bolsa sellada" (bolsa sellada lista para despacho).
- **Generador_Requisitos_Foto**: Función pura que recibe el tipo de delivery y retorna la lista de Foto_Requerida. Para Pedido_Delivery retorna 2 fotos; para Pedido_Local retorna array vacío.
- **Verificador_IA**: Servicio PHP que envía una foto junto con el contexto del pedido a Gemini API (visión) y retorna un resultado de verificación.
- **Resultado_Verificacion**: Objeto con campos: `aprobado` (boolean), `puntaje` (0-100), `feedback` (string descriptivo), retornado por el Verificador_IA.
- **Panel_Feedback_IA**: Panel visible debajo de los slots de fotos que muestra el texto completo del feedback de Gemini para cada foto analizada. No es un badge pequeño sino un panel con el texto de análisis.
- **Boton_Despacho**: Botón principal de acción en MiniComandas. Para Pedido_Delivery tiene 2 estados: "📷 FALTAN FOTOS" (deshabilitado, gris) y "📦 DESPACHAR A DELIVERY" (habilitado, verde). Para Pedido_Local tiene un solo estado: "✅ ENTREGAR" (habilitado, verde, flujo actual sin cambios).
- **API_Dispatch_Photo**: Endpoint existente `/api/orders/save_dispatch_photo.php` que sube fotos a S3 y guarda las URLs en `tuu_orders.dispatch_photo_url`.
- **GeminiService_Caja3**: Nuevo servicio PHP en caja3 que encapsula las llamadas a Gemini API para verificación visual, siguiendo el patrón de `GeminiService.php` de mi3.
- **S3Manager**: Clase PHP existente en caja3 que gestiona la subida de imágenes a AWS S3 con compresión automática.
- **Tabla_Dispatch_Photo_Feedback**: Nueva tabla `dispatch_photo_feedback` en la base de datos MySQL principal (misma que `tuu_orders`) que almacena los resultados de verificación IA y datos de re-toma para aprendizaje continuo, siguiendo el patrón de `extraction_feedback` en mi3.

## Requisitos

### Requisito 1: Fotos Deshabilitadas para Pedidos Locales, 2 Fotos Obligatorias para Delivery

**User Story:** Como cajero, quiero que el sistema me pida fotos obligatorias solo cuando el pedido es delivery, y que para pedidos locales el botón ENTREGAR funcione exactamente como hoy sin ninguna sección de fotos.

#### Criterios de Aceptación

1. WHEN un pedido es de tipo delivery (`delivery_type === 'delivery'`), THE Generador_Requisitos_Foto SHALL retornar exactamente 2 fotos requeridas: `{id: 'productos', label: '📸 Foto de productos'}` y `{id: 'bolsa', label: '🛍️ Foto en bolsa sellada'}`, en ese orden.
2. WHEN un pedido es de tipo local (`delivery_type === 'pickup'` o `delivery_type === 'cuartel'`), THE Generador_Requisitos_Foto SHALL retornar un array vacío de fotos requeridas.
3. WHEN un pedido es de tipo local, THE MiniComandas SHALL ocultar completamente la sección "FOTOS DEL PEDIDO (obligatorio)", sin mostrar slots, controles de cámara, ni panel de feedback.
4. WHEN un pedido es de tipo local, THE MiniComandas SHALL mostrar el Boton_Despacho habilitado con texto "✅ ENTREGAR" en color verde, funcionando exactamente como el flujo actual sin cambios.
5. THE Generador_Requisitos_Foto SHALL ser una función pura que recibe el tipo de delivery y retorna un array de objetos `{id: string, label: string, required: boolean}` sin efectos secundarios.
6. THE Generador_Requisitos_Foto SHALL producir resultados idénticos cuando se invoca múltiples veces con los mismos argumentos.

### Requisito 2: Estados del Botón de Despacho para Pedidos Delivery

**User Story:** Como dueño del negocio, quiero que el botón de despacho delivery cambie de "FALTAN FOTOS" a "DESPACHAR A DELIVERY" cuando las fotos estén completas, para confirmar visualmente que el pedido está listo para el rider.

#### Criterios de Aceptación

1. WHILE un Pedido_Delivery tiene alguna Foto_Requerida sin foto subida, THE MiniComandas SHALL mostrar el Boton_Despacho deshabilitado en color gris con texto "📷 FALTAN FOTOS".
2. WHEN todas las Foto_Requerida de un Pedido_Delivery tienen foto subida (2 de 2), THE MiniComandas SHALL mostrar el Boton_Despacho habilitado con texto "📦 DESPACHAR A DELIVERY" en color verde.
3. WHEN el cajero presiona el Boton_Despacho deshabilitado ("📷 FALTAN FOTOS"), THE MiniComandas SHALL mostrar un mensaje indicando cuántas fotos faltan por subir (ej: "Faltan 1 de 2 fotos").
4. THE MiniComandas SHALL mostrar un indicador de progreso junto a la sección de fotos de un Pedido_Delivery con formato "N/2 fotos" donde N es la cantidad de fotos subidas.
5. THE verificación IA (aprobado/no aprobado) SHALL ser informativa y no afectar el estado del Boton_Despacho; el bloqueo depende únicamente de que las 2 fotos estén subidas.

### Requisito 3: Sección de Fotos con Slots Etiquetados para Delivery

**User Story:** Como cajero, quiero ver claramente qué fotos necesito tomar y cuáles ya subí para un pedido delivery, para completar el proceso de despacho de forma ordenada.

#### Criterios de Aceptación

1. WHEN un pedido es de tipo delivery, THE MiniComandas SHALL mostrar una sección de fotos con 2 slots etiquetados: "📸 Foto de productos" y "🛍️ Foto en bolsa sellada".
2. WHEN un slot de Foto_Requerida está vacío, THE MiniComandas SHALL mostrar el slot con borde punteado, ícono de cámara, y la etiqueta de la foto requerida.
3. WHEN un slot de Foto_Requerida tiene foto subida, THE MiniComandas SHALL mostrar la miniatura de la foto con un indicador de estado: spinner mientras se analiza, check verde si la verificación IA aprobó, o warning amarillo si la verificación IA detectó problemas.
4. WHEN el cajero toca un slot vacío, THE MiniComandas SHALL abrir el selector de archivo/cámara del dispositivo para capturar o seleccionar una foto.
5. WHEN el cajero toca la miniatura de un slot con foto, THE MiniComandas SHALL abrir el visor de fotos existente (`setViewingOrderPhotos`) para ver la imagen en pantalla completa.

### Requisito 4: Eliminar y Re-subir Fotos con Re-análisis IA

**User Story:** Como cajero, quiero poder eliminar una foto que salió mal y subir una nueva, para que la IA re-analice la foto corregida y el sistema aprenda de las correcciones.

#### Criterios de Aceptación

1. WHEN un slot tiene una foto subida, THE MiniComandas SHALL mostrar un botón de eliminar (×) visible en el slot para permitir al cajero borrar la foto.
2. WHEN el cajero presiona el botón de eliminar en un slot, THE MiniComandas SHALL remover la foto del slot, volver el slot a estado vacío, y limpiar el feedback IA asociado.
3. WHEN el cajero sube una nueva foto en un slot que previamente tenía una foto eliminada, THE API_Dispatch_Photo SHALL enviar la nueva foto al Verificador_IA para re-análisis y actualizar el feedback.
4. WHEN una foto es re-subida después de eliminar la anterior, THE Tabla_Dispatch_Photo_Feedback SHALL registrar el nuevo análisis con `user_retook = true` para indicar que el usuario corrigió la foto.
5. THE MiniComandas SHALL actualizar el Panel_Feedback_IA con el nuevo resultado de verificación después de cada re-subida.

### Requisito 5: Panel de Feedback IA Visible Debajo de los Slots

**User Story:** Como cajero, quiero ver el análisis completo de la IA en un panel visible debajo de las fotos, no solo un badge pequeño, para entender qué detectó la IA y si necesito retomar alguna foto.

#### Criterios de Aceptación

1. WHEN al menos una foto de un Pedido_Delivery ha sido analizada por el Verificador_IA, THE MiniComandas SHALL mostrar el Panel_Feedback_IA debajo de los slots de fotos con el texto completo del feedback de cada foto analizada.
2. WHEN una foto está siendo analizada por el Verificador_IA, THE Panel_Feedback_IA SHALL mostrar un indicador de carga con texto "Verificando..." para esa foto.
3. WHEN el Verificador_IA retorna un Resultado_Verificacion con `aprobado: true`, THE Panel_Feedback_IA SHALL mostrar el feedback con un indicador visual verde (✅) y el texto descriptivo del análisis.
4. WHEN el Verificador_IA retorna un Resultado_Verificacion con `aprobado: false`, THE Panel_Feedback_IA SHALL mostrar el feedback con un indicador visual amarillo (⚠️), el texto descriptivo del problema detectado, y una sugerencia de retomar la foto.
5. THE Panel_Feedback_IA SHALL mostrar el feedback de cada foto identificado por su etiqueta (ej: "📸 Productos: ✅ Todos los items visibles y bien empacados").
6. WHEN una foto es eliminada y re-subida, THE Panel_Feedback_IA SHALL reemplazar el feedback anterior con el nuevo resultado de verificación.

### Requisito 6: Verificación con IA usando Gemini API

**User Story:** Como dueño del negocio, quiero que cada foto de despacho delivery sea analizada automáticamente por IA para detectar productos faltantes o mal empacados antes de que el pedido salga del local.

#### Criterios de Aceptación

1. WHEN una foto de un Pedido_Delivery es subida exitosamente a S3, THE API_Dispatch_Photo SHALL enviar la imagen al Verificador_IA junto con el contexto del pedido (lista de items con nombres y cantidades) y el tipo de foto (`'productos'` o `'bolsa'`).
2. THE Verificador_IA SHALL enviar la imagen en base64 a Gemini API con un prompt estructurado que incluya la lista de productos esperados y solicite verificación de: (a) presencia de los items correctos, (b) estado del empaque, (c) orientación correcta de los productos.
3. THE Verificador_IA SHALL retornar un Resultado_Verificacion con `aprobado` (boolean), `puntaje` (0-100), y `feedback` (string descriptivo en español con emoji indicador).
4. THE Verificador_IA SHALL usar `responseMimeType: 'application/json'` y `responseSchema` para forzar respuesta JSON estructurada de Gemini.
5. THE Verificador_IA SHALL completar el análisis en un máximo de 8 segundos (timeout de la llamada a Gemini API).
6. IF la llamada a Gemini API falla o excede el timeout, THEN THE Verificador_IA SHALL retornar un Resultado_Verificacion con `aprobado: true`, `puntaje: 0`, y `feedback: "⏳ Verificación no disponible"` para no bloquear el flujo de despacho.
7. THE GeminiService_Caja3 SHALL usar la variable de entorno `GEMINI_API_KEY` ya configurada en producción y el modelo `gemini-2.5-flash-lite` para análisis rápido y económico.

### Requisito 7: Tabla dispatch_photo_feedback para Almacenamiento y Aprendizaje

**User Story:** Como desarrollador, quiero almacenar los resultados de verificación IA y las correcciones del usuario en una tabla dedicada, para que el sistema pueda aprender de las correcciones y mejorar con el tiempo.

#### Criterios de Aceptación

1. THE base de datos MySQL principal (misma que `tuu_orders`) SHALL contener una tabla `dispatch_photo_feedback` con los campos: `id` (auto-increment), `order_id` (integer, FK a tuu_orders), `photo_type` (enum: 'productos', 'bolsa'), `photo_url` (text, URL de S3), `ai_aprobado` (boolean), `ai_puntaje` (integer 0-100), `ai_feedback` (text), `user_retook` (boolean, default false), y `created_at` (timestamp).
2. WHEN el Verificador_IA completa el análisis de una foto, THE API_Dispatch_Photo SHALL insertar un registro en `dispatch_photo_feedback` con el resultado de verificación y `user_retook = false`.
3. WHEN el cajero elimina una foto y sube una nueva en el mismo slot, THE API_Dispatch_Photo SHALL insertar un nuevo registro en `dispatch_photo_feedback` con `user_retook = true`, manteniendo el registro anterior para historial.
4. THE tabla `dispatch_photo_feedback` SHALL tener índices en `order_id` y `photo_type` para consultas eficientes.
5. IF la verificación IA falla (timeout, error de API), THEN THE API_Dispatch_Photo SHALL insertar un registro en `dispatch_photo_feedback` con `ai_aprobado = true`, `ai_puntaje = 0`, y `ai_feedback = '⏳ Verificación no disponible'`.

### Requisito 8: Servicio PHP de Verificación con Gemini

**User Story:** Como desarrollador, quiero un servicio PHP reutilizable para llamar a Gemini API con imágenes, para poder verificar fotos de despacho de forma consistente.

#### Criterios de Aceptación

1. THE GeminiService_Caja3 SHALL ser una clase PHP ubicada en `caja3/api/GeminiService.php` con un método `verificarFotoDespacho(string $imageBase64, array $itemsPedido, string $tipoFoto): array`.
2. THE GeminiService_Caja3 SHALL construir un prompt específico para verificación de despacho que incluya la lista de items esperados con cantidades y el tipo de foto (`'productos'` o `'bolsa'`).
3. THE GeminiService_Caja3 SHALL usar `responseSchema` con la estructura `{aprobado: boolean, puntaje: integer, feedback: string}` para forzar respuesta JSON estructurada.
4. THE GeminiService_Caja3 SHALL usar cURL para las llamadas HTTP a Gemini API, siguiendo el mismo patrón de `callGemini()` en el GeminiService de mi3.
5. THE GeminiService_Caja3 SHALL registrar errores con `error_log()` incluyendo el código HTTP y mensaje de error para debugging en producción.
6. THE GeminiService_Caja3 SHALL leer la imagen desde la URL de S3 y convertirla a base64 para enviarla a Gemini.

### Requisito 9: Integración con el Flujo de Subida Existente

**User Story:** Como desarrollador, quiero que la verificación IA se integre al flujo de subida de fotos existente sin romper la funcionalidad actual para pedidos locales.

#### Criterios de Aceptación

1. THE API_Dispatch_Photo SHALL mantener su funcionalidad actual de subida a S3 y guardado de URLs en `tuu_orders.dispatch_photo_url` sin cambios.
2. WHEN una foto de un Pedido_Delivery es subida exitosamente, THE API_Dispatch_Photo SHALL llamar al GeminiService_Caja3 para verificar la foto, insertar el resultado en `dispatch_photo_feedback`, y retornar el Resultado_Verificacion en la respuesta JSON junto con la URL de la foto.
3. THE API_Dispatch_Photo SHALL aceptar un nuevo parámetro `photo_type` (string: `'productos'` o `'bolsa'`) para contextualizar la verificación IA.
4. THE API_Dispatch_Photo SHALL aceptar un nuevo parámetro `order_items` (JSON string) con la lista de items del pedido para que el Verificador_IA tenga contexto.
5. IF la verificación IA falla, THEN THE API_Dispatch_Photo SHALL retornar `success: true` con la URL de la foto y un Resultado_Verificacion por defecto, para no bloquear la subida.
6. WHEN una foto es subida para un Pedido_Local (sin `photo_type`), THE API_Dispatch_Photo SHALL funcionar exactamente como antes, sin invocar al Verificador_IA ni insertar en `dispatch_photo_feedback`.
