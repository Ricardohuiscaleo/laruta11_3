# Documento de Requerimientos — Página Pública del Rider

## Introducción

Página pública (sin autenticación) accesible en `mi.laruta11.cl/rider/{order_id}` donde el rider de delivery puede ver los datos completos del pedido, la ruta en mapa hasta la dirección del cliente, y actualizar el estado del pedido ("En camino" → "Entregado"). El link se genera cuando la cajera despacha un pedido y se comparte al rider vía QR o link directo.

A diferencia de la vista `/rider` existente (que requiere login y usa `useAuth` + `useRiderGPS`), esta página es completamente pública: quien tiene el link puede ver y actualizar el pedido. El `order_id` actúa como token de acceso implícito.

La página comparte la ubicación GPS del rider vía WebSocket (Reverb) para que el admin vea el movimiento en `/admin/delivery` en tiempo real.

## Glosario

- **Página_Rider_Pública**: Página Next.js en `mi.laruta11.cl/rider/{order_id}`, sin autenticación, mobile-first, que muestra datos del pedido y permite al rider actualizar el estado
- **API_Mi3**: Backend Laravel 11 en api-mi3.laruta11.cl con Reverb WebSockets
- **Sistema_Tracking**: Sistema de delivery tracking existente implementado en spec `delivery-tracking-realtime`
- **Tabla_TuuOrders**: Tabla `tuu_orders` de la base de datos MySQL compartida
- **Tabla_TuuOrderItems**: Tabla `tuu_order_items` con los items/productos de cada pedido
- **Tabla_DeliveryAssignments**: Tabla `delivery_assignments` para asignación de riders a pedidos
- **Tabla_RiderLocations**: Tabla `rider_locations` para historial de posiciones GPS
- **Canal_Monitor**: Canal Reverb privado `delivery.monitor` para la vista admin
- **Evento_RiderLocationUpdated**: Evento broadcast existente que emite posición GPS del rider
- **Evento_OrderStatusUpdated**: Evento broadcast existente que emite cambios de estado de pedido
- **Food_Truck**: Ubicación fija del food truck almacenada en tabla `food_trucks`
- **Google_Maps**: API de Google Maps JavaScript con mapId `d51ca892b68e9c5e5e2dd701`

## Requerimientos

### Requerimiento 1: Endpoint Público — Datos Completos del Pedido para Rider

**User Story:** Como rider, quiero obtener todos los datos del pedido desde un endpoint público, para que pueda ver la información completa sin necesidad de autenticarme.

#### Criterios de Aceptación

1. THE API_Mi3 SHALL exponer un endpoint `GET /api/v1/public/rider-orders/{orderId}` sin autenticación que retorne los datos completos del pedido identificado por `order_id`.
2. WHEN el endpoint recibe un `orderId` válido correspondiente a un pedido de tipo delivery, THE API_Mi3 SHALL retornar: `order_number`, `customer_name`, `customer_phone`, `delivery_address`, `delivery_fee`, `card_surcharge`, `subtotal`, `product_price` (total), `payment_method`, `delivery_distance_km`, `delivery_duration_min`, `order_status`, y la lista de productos del pedido con `product_name`, `quantity` y `product_price` por item.
3. IF el `orderId` no existe en Tabla_TuuOrders o el pedido no es de tipo delivery, THEN THE API_Mi3 SHALL retornar HTTP 404 con un mensaje de error descriptivo.
4. THE API_Mi3 SHALL incluir en la respuesta la ubicación del food truck (`latitud`, `longitud`) obtenida de la tabla `food_trucks` para que el frontend pueda trazar la ruta.
5. IF el pedido tiene un rider asignado con posición GPS disponible en Tabla_TuuOrders (`rider_last_lat`, `rider_last_lng`), THEN THE API_Mi3 SHALL incluir la última posición conocida del rider en la respuesta.

### Requerimiento 2: Endpoint Público — Actualización de Estado sin Autenticación

**User Story:** Como rider, quiero poder cambiar el estado del pedido desde la página pública, para que pueda marcar "En camino" y "Entregado" sin necesidad de login.

#### Criterios de Aceptación

1. THE API_Mi3 SHALL exponer un endpoint `PATCH /api/v1/public/rider-orders/{orderId}/status` sin autenticación que permita actualizar el `order_status` del pedido.
2. WHEN el rider envía status `out_for_delivery`, THE API_Mi3 SHALL actualizar `order_status` a `out_for_delivery` en Tabla_TuuOrders y emitir el Evento_OrderStatusUpdated por Canal_Monitor y por el canal `order.{order_number}`.
3. WHEN el rider envía status `delivered`, THE API_Mi3 SHALL actualizar `order_status` a `delivered` en Tabla_TuuOrders, actualizar `delivered_at` en Tabla_DeliveryAssignments si existe una asignación activa, y emitir el Evento_OrderStatusUpdated.
4. THE API_Mi3 SHALL validar que el status enviado sea únicamente `out_for_delivery` o `delivered`; IF el status es diferente, THEN THE API_Mi3 SHALL retornar HTTP 422 con mensaje de error.
5. IF el `orderId` no existe o el pedido no es de tipo delivery, THEN THE API_Mi3 SHALL retornar HTTP 404.

### Requerimiento 3: Endpoint Público — Envío de Ubicación GPS sin Autenticación

**User Story:** Como rider usando la página pública, quiero que mi ubicación GPS se envíe al servidor, para que el administrador pueda ver mi posición en el mapa de operaciones.

#### Criterios de Aceptación

1. THE API_Mi3 SHALL exponer un endpoint `POST /api/v1/public/rider-orders/{orderId}/location` sin autenticación que reciba `latitud` y `longitud` del rider.
2. WHEN el endpoint recibe coordenadas válidas, THE API_Mi3 SHALL actualizar `rider_last_lat` y `rider_last_lng` en Tabla_TuuOrders para el pedido correspondiente.
3. WHEN el pedido tiene un `rider_id` asignado, THE API_Mi3 SHALL persistir la posición en Tabla_RiderLocations asociada al `rider_id` y emitir el Evento_RiderLocationUpdated por Canal_Monitor.
4. WHEN el pedido no tiene `rider_id` asignado, THE API_Mi3 SHALL únicamente actualizar `rider_last_lat` y `rider_last_lng` en Tabla_TuuOrders sin persistir en Tabla_RiderLocations.
5. THE API_Mi3 SHALL validar que `latitud` esté entre -90 y 90 y `longitud` entre -180 y 180; IF los valores están fuera de rango, THEN THE API_Mi3 SHALL retornar HTTP 422.
6. IF el `orderId` no existe o el pedido no es de tipo delivery, THEN THE API_Mi3 SHALL retornar HTTP 404.

### Requerimiento 4: Página Frontend — Visualización de Datos del Pedido

**User Story:** Como rider, quiero ver toda la información del pedido en una página clara y optimizada para celular, para que pueda consultar los datos sin confusión.

#### Criterios de Aceptación

1. WHEN el rider accede a `mi.laruta11.cl/rider/{order_id}`, THE Página_Rider_Pública SHALL mostrar los datos del pedido obtenidos del endpoint público: número de orden, nombre del cliente, teléfono del cliente (con link `tel:`), lista de productos con nombre, cantidad y precio, subtotal, delivery fee, card surcharge (si aplica), total, y método de pago.
2. THE Página_Rider_Pública SHALL mostrar la dirección de entrega con un link directo a Google Maps (`https://www.google.com/maps/dir/?api=1&destination={delivery_address}`) para que el rider pueda abrir la navegación GPS nativa.
3. THE Página_Rider_Pública SHALL mostrar la distancia estimada (`delivery_distance_km`) y el tiempo estimado (`delivery_duration_min`) del delivery.
4. THE Página_Rider_Pública SHALL ser mobile-first con diseño responsive optimizado para pantallas de celular (viewport ≤ 480px como prioridad).
5. THE Página_Rider_Pública SHALL funcionar sin autenticación; la página no requiere login ni cookies de sesión.
6. IF el `order_id` no corresponde a un pedido válido de delivery, THEN THE Página_Rider_Pública SHALL mostrar un mensaje de error indicando que el pedido no fue encontrado.

### Requerimiento 5: Página Frontend — Mapa con Ruta Embebido

**User Story:** Como rider, quiero ver un mapa con la ruta desde el food truck hasta la dirección del cliente, para que pueda orientarme visualmente antes de iniciar la navegación.

#### Criterios de Aceptación

1. THE Página_Rider_Pública SHALL mostrar un mapa de Google Maps embebido con la ruta trazada desde la ubicación del food truck hasta la dirección de entrega del cliente, usando Google Maps Directions API con modo `DRIVING`.
2. THE Página_Rider_Pública SHALL usar el mapId `d51ca892b68e9c5e5e2dd701` ya configurado y la API key existente en `NEXT_PUBLIC_GOOGLE_MAPS_KEY`.
3. WHILE el rider tiene GPS activo y el pedido está en estado `out_for_delivery`, THE Página_Rider_Pública SHALL actualizar la posición del marcador del rider en el mapa en tiempo real.
4. THE Página_Rider_Pública SHALL mostrar marcadores diferenciados para el food truck (origen) y la dirección del cliente (destino).

### Requerimiento 6: Página Frontend — Botones de Acción y Cambio de Estado

**User Story:** Como rider, quiero botones claros para marcar "En camino" y "Entregado", para que pueda actualizar el estado del pedido con un solo toque.

#### Criterios de Aceptación

1. WHEN el pedido está en estado `ready` o `preparing`, THE Página_Rider_Pública SHALL mostrar un botón "🛵 En camino" que al ser tocado envíe `PATCH /api/v1/public/rider-orders/{orderId}/status` con status `out_for_delivery`.
2. WHEN el pedido cambia a estado `out_for_delivery`, THE Página_Rider_Pública SHALL reemplazar el botón "En camino" por un botón "✅ Entregado" que al ser tocado envíe status `delivered`.
3. WHEN el rider toca "En camino", THE Página_Rider_Pública SHALL iniciar la captura de GPS del navegador y comenzar a enviar la posición cada 10 segundos vía `POST /api/v1/public/rider-orders/{orderId}/location`.
4. WHEN el rider toca "Entregado", THE Página_Rider_Pública SHALL detener la captura y envío de GPS, y mostrar una pantalla de confirmación indicando que el pedido fue entregado.
5. WHILE se está procesando una acción de cambio de estado, THE Página_Rider_Pública SHALL deshabilitar el botón y mostrar un indicador de carga para evitar doble envío.
6. IF la llamada al endpoint de cambio de estado falla, THEN THE Página_Rider_Pública SHALL mostrar un mensaje de error y mantener el botón habilitado para reintentar.
7. WHEN el pedido ya está en estado `delivered`, THE Página_Rider_Pública SHALL mostrar un mensaje de confirmación de entrega y no mostrar botones de acción.

### Requerimiento 7: Compartir GPS del Rider vía Página Pública

**User Story:** Como administrador, quiero que cuando el rider use la página pública y marque "En camino", su GPS se comparta en tiempo real, para que pueda ver su posición en el mapa de `/admin/delivery`.

#### Criterios de Aceptación

1. WHEN el rider toca "En camino" en la Página_Rider_Pública, THE Página_Rider_Pública SHALL solicitar permiso de geolocalización al navegador y comenzar a capturar la posición GPS usando la Geolocation API con `enableHighAccuracy: true`.
2. WHILE el pedido está en estado `out_for_delivery`, THE Página_Rider_Pública SHALL enviar la posición GPS al endpoint `POST /api/v1/public/rider-orders/{orderId}/location` cada 10 segundos.
3. IF el navegador del rider deniega el permiso de GPS, THEN THE Página_Rider_Pública SHALL mostrar un mensaje indicando que debe habilitar la ubicación para compartir su posición, pero el cambio de estado a "En camino" se realiza de todas formas.
4. WHEN el rider toca "Entregado" o cierra la página, THE Página_Rider_Pública SHALL detener la captura y envío de GPS.

### Requerimiento 8: Generación del Link Rider al Despachar Pedido

**User Story:** Como cajera, quiero que al despachar un pedido se genere automáticamente el link para el rider, para que pueda compartirlo rápidamente.

#### Criterios de Aceptación

1. WHEN un pedido de tipo delivery cambia a estado `ready` desde la Vista_Monitor o desde caja3, THE Sistema_Tracking SHALL generar la URL `mi.laruta11.cl/rider/{order_id}` donde `order_id` es el ID numérico del pedido en Tabla_TuuOrders.
2. THE Vista_Monitor SHALL mostrar el link del rider junto al pedido, con opción de copiar al portapapeles y generar código QR para escaneo rápido.
3. THE API_Mi3 SHALL retornar el campo `rider_url` en la respuesta de `GET /api/v1/admin/delivery/orders` para cada pedido activo, construido como `https://mi.laruta11.cl/rider/{order_id}`.
