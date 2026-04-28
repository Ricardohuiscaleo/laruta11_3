# Plan de Implementación: Verificación de Fotos de Despacho con IA

## Overview

Implementar verificación fotográfica con IA para pedidos delivery en caja3. El sistema requiere 2 fotos obligatorias por pedido delivery (productos y bolsa sellada), las analiza con Gemini API, muestra feedback en un panel visible, y almacena resultados para aprendizaje. Pedidos locales no se ven afectados.

Stack: PHP backend (caja3/api), React frontend (MiniComandas.jsx), MySQL, AWS S3, Gemini API.

## Tasks

- [x] 1. Crear tabla dispatch_photo_feedback en MySQL
  - [x] 1.1 Crear script SQL con la tabla `dispatch_photo_feedback`
    - Campos: `id` AUTO_INCREMENT, `order_id` INT NOT NULL, `photo_type` ENUM('productos','bolsa'), `photo_url` TEXT, `ai_aprobado` TINYINT(1) DEFAULT 1, `ai_puntaje` INT DEFAULT 0, `ai_feedback` TEXT, `user_retook` TINYINT(1) DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    - Índices en `order_id` y `photo_type`
    - FOREIGN KEY `order_id` → `tuu_orders(id)` ON DELETE CASCADE
    - Ubicación: `caja3/create_dispatch_photo_feedback.sql`
    - _Requirements: 7.1, 7.4_

- [x] 2. Implementar función pura generatePhotoRequirements y tests
  - [x] 2.1 Crear `caja3/src/utils/photoRequirements.js` con la función `generatePhotoRequirements(deliveryType)`
    - Retorna `[{id: 'productos', label: '📸 Foto de productos', required: true}, {id: 'bolsa', label: '🛍️ Foto en bolsa sellada', required: true}]` para `'delivery'`
    - Retorna `[]` para `'pickup'`, `'cuartel'`, o cualquier otro valor
    - Exportar también helper `getButtonState(photoReqs, uploadedPhotos)` que retorna `{enabled, text, className}` basado en completitud de fotos (no en verificación IA)
    - Exportar helper `formatPhotoProgress(uploaded, total)` que retorna string `"N/2 fotos"`
    - _Requirements: 1.1, 1.2, 1.5, 1.6, 2.1, 2.2, 2.4_

  - [ ]* 2.2 Write property test: Delivery retorna exactamente 2 fotos, local retorna vacío
    - **Property 1: Delivery retorna exactamente 2 fotos, local retorna vacío**
    - Usar fast-check para generar strings aleatorios como deliveryType
    - Verificar que `'delivery'` retorna array de 2 con ids `'productos'` y `'bolsa'` en ese orden
    - Verificar que cualquier otro string retorna array vacío
    - **Validates: Requirements 1.1, 1.2**

  - [ ]* 2.3 Write property test: Generador de requisitos es determinístico
    - **Property 2: Generador de requisitos es determinístico (idempotencia)**
    - Usar fast-check para generar deliveryTypes aleatorios
    - Llamar la función dos veces con el mismo argumento, verificar deep equal
    - **Validates: Requirements 1.5, 1.6**

  - [ ]* 2.4 Write property test: Estado del botón refleja completitud de fotos
    - **Property 3: Estado del botón refleja completitud de fotos, no verificación IA**
    - Generar conjuntos aleatorios de fotos subidas (0, 1, o 2) con verificaciones IA aleatorias
    - Verificar que `getButtonState` retorna enabled=true solo cuando ambas fotos están subidas
    - El estado de verificación IA no afecta el resultado
    - **Validates: Requirements 2.1, 2.2, 2.5**

  - [ ]* 2.5 Write property test: Indicador de progreso formatea correctamente
    - **Property 4: Indicador de progreso formatea correctamente N/2**
    - Generar N ∈ {0, 1, 2}, verificar que `formatPhotoProgress(N, 2)` retorna exactamente `"N/2 fotos"`
    - **Validates: Requirements 2.4**

- [x] 3. Checkpoint — Verificar función pura y tests
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implementar GeminiService.php backend
  - [x] 4.1 Crear `caja3/api/GeminiService.php`
    - Clase con constructor que lee `GEMINI_API_KEY` de env
    - Modelo: `gemini-2.5-flash-lite`
    - Método público `verificarFotoDespacho(string $imageBase64, array $itemsPedido, string $tipoFoto): array`
    - Método privado `callGemini(string $prompt, string $imageBase64, array $schema, int $timeout = 8): ?array`
    - Método privado `buildVerificationPrompt(array $itemsPedido, string $tipoFoto): string` — prompt para 'productos' verifica presencia de items, cantidades, orientación; prompt para 'bolsa' verifica sellado y estado
    - Método privado `buildVerificationSchema(): array` — schema con `{aprobado: boolean, puntaje: integer, feedback: string}`
    - Usar `responseMimeType => 'application/json'` y `responseSchema` para forzar JSON
    - `CURLOPT_TIMEOUT => 8`, `CURLOPT_CONNECTTIMEOUT => 5`, `temperature => 0.1`
    - Errores logueados con `error_log()`
    - Seguir patrón de `callGemini()` de mi3 GeminiService
    - Si falla: retornar `{aprobado: true, puntaje: 0, feedback: '⏳ Verificación no disponible'}`
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 4.2 Write property test: Prompt contiene todos los items del pedido
    - **Property 5: Prompt de verificación contiene todos los items del pedido**
    - Generar listas de items con nombres y cantidades aleatorios, y tipos de foto
    - Verificar que el prompt construido por `buildVerificationPrompt` contiene el nombre y cantidad de cada item
    - Nota: Extraer `buildVerificationPrompt` como función testeable o testear via output del prompt
    - **Validates: Requirements 6.1, 6.2**

- [x] 5. Modificar save_dispatch_photo.php para integrar verificación IA
  - [x] 5.1 Modificar `caja3/api/orders/save_dispatch_photo.php`
    - Aceptar nuevos parámetros POST: `photo_type` (string), `order_items` (JSON string), `user_retook` (string 'true'/'false')
    - Flujo existente de subida a S3 y guardado de URLs sin cambios
    - Si `photo_type` está presente: leer imagen desde S3 URL, convertir a base64 con `file_get_contents` + `base64_encode`
    - Llamar `GeminiService::verificarFotoDespacho()` con la imagen, items parseados, y tipo de foto
    - Insertar resultado en `dispatch_photo_feedback` con `user_retook` del parámetro
    - Retornar `{success, url, all_photos, verification: {aprobado, puntaje, feedback}}`
    - Si verificación falla: insertar fallback en DB, retornar verificación por defecto `{aprobado: true, puntaje: 0, feedback: '⏳ Verificación no disponible'}`
    - Si no hay `photo_type`: funcionar exactamente como antes sin verificación
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 7.2, 7.3, 7.5_

- [x] 6. Checkpoint — Verificar backend completo
  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Implementar UI de slots de fotos y panel de feedback en MiniComandas.jsx
  - [x] 7.1 Agregar sección de slots de fotos para pedidos delivery en `renderOrderCard`
    - Importar `generatePhotoRequirements` desde `photoRequirements.js`
    - Condicionar sección: solo visible cuando `order.delivery_type === 'delivery'`
    - Para pedidos locales: ocultar completamente la sección de fotos
    - Agregar estado local `photoSlots` por orden: `{requirementId, label, status, photoUrl, verification}`
    - Status posibles: `'empty'`, `'uploading'`, `'verifying'`, `'approved'`, `'warning'`
    - Renderizar 2 slots con grid: slot vacío muestra borde punteado + ícono cámara + etiqueta; slot con foto muestra miniatura + badge (✅/⚠️/spinner)
    - Click en slot vacío → abre `<input type="file" accept="image/*" capture="environment">`
    - Click en miniatura → abre visor fullscreen existente (`setViewingOrderPhotos`)
    - Mostrar indicador "N/2 fotos" usando `formatPhotoProgress`
    - _Requirements: 1.3, 1.4, 2.4, 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 7.2 Implementar lógica de subida con verificación IA
    - Al seleccionar foto: cambiar status del slot a `'uploading'`
    - Enviar foto a `/api/orders/save_dispatch_photo.php` con `photo_type`, `order_items` (JSON de items del pedido), y `user_retook` si aplica
    - Al recibir respuesta exitosa: cambiar status a `'verifying'` brevemente, luego a `'approved'` o `'warning'` según `verification.aprobado`
    - Guardar `verification` en el estado del slot para el panel de feedback
    - Si error de red: alert con mensaje, slot vuelve a `'empty'`
    - _Requirements: 6.1, 9.2, 5.2_

  - [x] 7.3 Implementar botón de eliminar y re-subida
    - Mostrar botón × en cada slot con foto
    - Click en × → remover foto del slot, limpiar verification, status vuelve a `'empty'`
    - Al re-subir: enviar con `user_retook = 'true'`
    - Panel de feedback se actualiza con nuevo resultado
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 7.4 Implementar Panel de Feedback IA debajo de los slots
    - Renderizar panel `bg-gray-50 rounded-lg p-3 mt-2` debajo de los slots cuando hay feedback
    - Cada foto analizada muestra: etiqueta + indicador (✅/⚠️) + texto completo del feedback
    - Mientras se analiza: mostrar "Verificando..." con spinner
    - Actualizar al eliminar y re-subir
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ]* 7.5 Write property test: Feedback del panel identifica cada foto por su etiqueta
    - **Property 6: Feedback del panel identifica cada foto por su etiqueta**
    - Generar slots con labels y feedback aleatorios
    - Verificar que el panel prefija con la etiqueta correspondiente
    - **Validates: Requirements 5.5**

  - [x] 7.6 Implementar estados del Botón de Despacho
    - Para delivery: usar `getButtonState` para determinar texto, color, y enabled
    - Botón deshabilitado ("📷 FALTAN FOTOS") → al tocar muestra alert "Faltan N de 2 fotos"
    - Botón habilitado ("📦 DESPACHAR A DELIVERY") → ejecuta `deliverOrder` existente
    - Para local: mantener botón "✅ ENTREGAR" exactamente como hoy sin cambios
    - _Requirements: 2.1, 2.2, 2.3, 2.5, 1.4_

- [x] 8. Checkpoint — Verificar UI completa
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 9. Tests de integración y limpieza final
  - [ ]* 9.1 Write integration tests
    - Test flujo delivery completo: subir foto → S3 → verificación IA → insert en dispatch_photo_feedback → respuesta con URL y resultado
    - Test flujo local: subir foto sin photo_type → sin verificación → respuesta sin campo verification
    - Test backward compatibility: subir foto sin photo_type ni order_items funciona como antes
    - Test delete + re-upload: eliminar foto → subir nueva → verificación con user_retook = true
    - Test GeminiService maneja timeouts y errores HTTP
    - _Requirements: 9.1, 9.5, 9.6_

- [x] 10. Checkpoint final — Verificar todo el feature
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada task referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Property tests validan propiedades universales de correctitud del diseño
- La verificación IA es informativa (no bloqueante): el botón se habilita con fotos subidas, independiente del resultado IA
- `GeminiService.php` de caja3 es standalone (no Laravel), usa cURL directo y `error_log()` en vez de `Log::error()`
- El campo `tuu_orders.dispatch_photo_url` no se modifica — sigue siendo JSON array de URLs
