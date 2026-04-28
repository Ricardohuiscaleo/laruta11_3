# Plan de Implementación: Página Pública del Rider

## Resumen

Implementar una página pública (sin auth) en `mi.laruta11.cl/rider/{order_id}` con 3 endpoints Laravel públicos y una nueva página Next.js. El rider puede ver datos del pedido, mapa con ruta, cambiar estado y compartir GPS en tiempo real. Se modifica el admin para incluir `rider_url` y QR en el panel de pedidos.

## Tareas

- [x] 1. Crear PublicRiderController con endpoint show()
  - [x] 1.1 Crear `mi3/backend/app/Http/Controllers/Public/PublicRiderController.php` con método `show(int $orderId)`
    - Query a `tuu_orders` con join a `tuu_order_items` y `food_trucks`
    - Validar que el pedido exista y sea `delivery_type = 'delivery'`, retornar 404 si no
    - Retornar JSON con todos los campos: `order_number`, `customer_name`, `customer_phone`, `delivery_address`, `delivery_fee`, `card_surcharge`, `subtotal`, `product_price`, `payment_method`, `delivery_distance_km`, `delivery_duration_min`, `order_status`, `rider_last_lat`, `rider_last_lng`, items array, y `food_truck` con lat/lng
    - _Requerimientos: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 1.2 Registrar ruta GET en `mi3/backend/routes/api.php`
    - Agregar `Route::get('{orderId}', [PublicRiderController::class, 'show'])` dentro de un grupo `v1/public/rider-orders`
    - _Requerimientos: 1.1_

  - [ ]* 1.3 Escribir test de propiedad para show() — Propiedad 1
    - **Propiedad 1: GET retorna datos completos para pedidos delivery válidos**
    - Generar pedidos aleatorios de tipo delivery con Faker, verificar que todos los campos requeridos estén presentes y coincidan con la DB
    - **Valida: Requerimientos 1.1, 1.2, 1.4, 1.5**

  - [ ]* 1.4 Escribir test de propiedad para show() — Propiedad 2
    - **Propiedad 2: Todos los endpoints retornan 404 para pedidos inválidos o no-delivery**
    - Generar IDs inexistentes y pedidos no-delivery, verificar HTTP 404
    - **Valida: Requerimientos 1.3, 2.5, 3.6**

- [x] 2. Agregar endpoints updateStatus() y updateLocation() al PublicRiderController
  - [x] 2.1 Implementar método `updateStatus(Request $request, int $orderId)`
    - Validar que status sea `out_for_delivery` o `delivered`, retornar 422 si no
    - Validar que el pedido exista y sea delivery, retornar 404 si no
    - Usar `DeliveryService::updateOrderStatus()` para actualizar estado y emitir `OrderStatusUpdated`
    - _Requerimientos: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [x] 2.2 Implementar método `updateLocation(Request $request, int $orderId)`
    - Validar `latitud` entre -90 y 90, `longitud` entre -180 y 180, retornar 422 si fuera de rango
    - Validar que el pedido exista y sea delivery, retornar 404 si no
    - Actualizar `rider_last_lat` y `rider_last_lng` en `tuu_orders`
    - Si el pedido tiene `rider_id`: usar `LocationService::updateRiderLocation()` para persistir en `rider_locations` y emitir `RiderLocationUpdated`
    - Si no tiene `rider_id`: solo actualizar `tuu_orders`
    - _Requerimientos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 2.3 Registrar rutas PATCH y POST en `mi3/backend/routes/api.php`
    - Agregar `Route::patch('{orderId}/status', ...)` y `Route::post('{orderId}/location', ...)` al grupo `v1/public/rider-orders`
    - _Requerimientos: 2.1, 3.1_

  - [ ]* 2.4 Escribir test de propiedad para updateStatus() — Propiedad 3
    - **Propiedad 3: PATCH status actualiza orden y emite eventos correctamente**
    - Generar pedidos delivery y enviar status válidos, verificar actualización en DB y emisión de eventos
    - **Valida: Requerimientos 2.1, 2.2, 2.3**

  - [ ]* 2.5 Escribir test de propiedad para updateStatus() — Propiedad 4
    - **Propiedad 4: PATCH rechaza status inválidos**
    - Generar strings aleatorios distintos de `out_for_delivery`/`delivered`, verificar HTTP 422 sin cambios en DB
    - **Valida: Requerimiento 2.4**

  - [ ]* 2.6 Escribir test de propiedad para updateLocation() — Propiedad 5
    - **Propiedad 5: POST location actualiza coordenadas y persiste condicionalmente**
    - Generar coordenadas válidas para pedidos con y sin `rider_id`, verificar persistencia condicional
    - **Valida: Requerimientos 3.1, 3.2, 3.3, 3.4**

  - [ ]* 2.7 Escribir test de propiedad para updateLocation() — Propiedad 6
    - **Propiedad 6: POST location rechaza coordenadas fuera de rango**
    - Generar latitudes fuera de [-90,90] y longitudes fuera de [-180,180], verificar HTTP 422
    - **Valida: Requerimiento 3.5**

- [x] 3. Checkpoint — Verificar backend completo
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Crear hook usePublicRiderGPS y página pública del rider
  - [x] 4.1 Crear `mi3/frontend/hooks/usePublicRiderGPS.ts`
    - Recibe `{ orderId: string, enabled: boolean }` como opciones
    - Usa `fetch` directo (no `apiFetch`) al endpoint `POST /api/v1/public/rider-orders/{orderId}/location`
    - Usa `navigator.geolocation.watchPosition` con `enableHighAccuracy: true`
    - Envía posición cada 10 segundos cuando `enabled = true`
    - Retorna `{ position: GeoPosition | null, gpsError: string | null }`
    - Limpia watch y interval al desmontar o cuando `enabled` cambia a false
    - _Requerimientos: 7.1, 7.2, 7.3, 7.4_

  - [x] 4.2 Crear `mi3/frontend/components/rider/PublicRiderView.tsx`
    - Client component que recibe `orderId: string`
    - Fetch datos del pedido al montar con `fetch` directo a `GET /api/v1/public/rider-orders/{orderId}`
    - Renderizar: número de orden, nombre cliente, teléfono con link `tel:`, lista de productos (nombre, cantidad, precio), subtotal, delivery fee, card surcharge, total, método de pago
    - Renderizar dirección con link a Google Maps (`https://www.google.com/maps/dir/?api=1&destination={address}`)
    - Mostrar distancia y tiempo estimado
    - Mapa embebido con `@vis.gl/react-google-maps` usando mapId `d51ca892b68e9c5e5e2dd701` y Directions API modo DRIVING desde food_truck hasta delivery_address
    - Botón "🛵 En camino" cuando status es `ready` o `preparing` → PATCH status `out_for_delivery` + activar GPS
    - Botón "✅ Entregado" cuando status es `out_for_delivery` → PATCH status `delivered` + detener GPS
    - Pantalla de confirmación cuando status es `delivered`
    - Loading state, error state (404), y disable de botones durante request
    - Integrar `usePublicRiderGPS` con `enabled` = true cuando status es `out_for_delivery`
    - Diseño mobile-first optimizado para viewport ≤ 480px
    - _Requerimientos: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7_

  - [x] 4.3 Crear `mi3/frontend/app/rider/[orderId]/page.tsx`
    - Server component wrapper que pasa `params.orderId` a `PublicRiderView`
    - Sin autenticación, sin `useAuth`
    - _Requerimientos: 4.5_

  - [ ]* 4.4 Escribir test de propiedad para botón de acción — Propiedad 7
    - **Propiedad 7: Botón de acción correcto según estado del pedido**
    - Usar `fast-check` para generar estados aleatorios y verificar que el botón correcto se renderiza
    - **Valida: Requerimientos 6.1, 6.2, 6.7**

- [x] 5. Checkpoint — Verificar frontend de página pública
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Modificar admin para incluir rider_url y QR
  - [x] 6.1 Agregar campo `rider_url` en `DeliveryService::getActiveOrders()`
    - En `mi3/backend/app/Services/Delivery/DeliveryService.php`, después del `->get()`, transformar cada pedido para agregar `rider_url` con formato `https://mi.laruta11.cl/rider/{id}`
    - _Requerimientos: 8.1, 8.3_

  - [x] 6.2 Instalar dependencia `qrcode.react` en el frontend
    - Ejecutar `npm install qrcode.react` en `mi3/frontend/`
    - _Requerimientos: 8.2_

  - [x] 6.3 Agregar botones "Copiar link rider" y "QR" en `OrderPanel.tsx`
    - En `mi3/frontend/components/admin/delivery/OrderPanel.tsx`, para cada pedido mostrar:
    - Botón "📋 Link rider" que copia `rider_url` al portapapeles con `navigator.clipboard.writeText()`
    - Botón "QR" que muestra/oculta un código QR generado con `qrcode.react` del `rider_url`
    - Usar `rider_url` del campo que viene de la API (agregado en 6.1)
    - _Requerimientos: 8.2, 8.3_

  - [ ]* 6.4 Escribir test de propiedad para rider_url — Propiedad 8
    - **Propiedad 8: rider_url sigue formato correcto**
    - Usar `fast-check` para generar IDs numéricos aleatorios y verificar formato `https://mi.laruta11.cl/rider/{id}`
    - **Valida: Requerimientos 8.1, 8.3**

- [x] 7. Checkpoint final — Verificar integración completa
  - Ensure all tests pass, ask the user if questions arise.

## Notas

- Las tareas marcadas con `*` son opcionales (tests de propiedad) y pueden omitirse para un MVP más rápido
- Cada tarea referencia requerimientos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- El backend usa PHP/Laravel, el frontend usa TypeScript/Next.js
- No se requieren migraciones de base de datos — se usan tablas existentes
- Se reutilizan `LocationService`, `DeliveryService`, y los eventos `RiderLocationUpdated` / `OrderStatusUpdated` existentes
