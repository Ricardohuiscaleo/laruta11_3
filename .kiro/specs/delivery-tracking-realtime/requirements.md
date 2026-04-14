# Documento de Requerimientos — Delivery Tracking en Tiempo Real

## Introducción

Sistema de seguimiento de delivery en tiempo real para La Ruta 11, inspirado en PedidosYa/Rappi. Permite al equipo de operaciones monitorear todos los pedidos activos y la posición de los riders en un mapa interactivo (Vista Monitor en mi3 admin), mientras que cada cliente puede seguir su pedido específico con el estado y la ubicación del rider en tiempo real (Vista Cliente embebible). La comunicación en tiempo real se implementa sobre la infraestructura existente de Laravel Reverb + Laravel Echo. Google Maps ya está integrado en caja3 y se reutilizará. El sistema se integra con el sistema de pedidos existente en app3/caja3 (tabla `tuu_orders`).

Los riders pertenecen a ARIAKA, proveedor externo de servicio de delivery. Al cierre de cada día, el sistema genera automáticamente un resumen de liquidación y el administrador debe transferir a ARIAKA la suma de todos los `delivery_fee` de pedidos entregados. El comprobante de pago se sube al sistema y genera automáticamente una compra en el módulo de compras existente de mi3.

**Prioridad de implementación:** mi3-backend y mi3-frontend son la prioridad. La integración con caja3 y app3 se documenta pero queda pendiente para una fase posterior.

## Glosario

- **Sistema_Tracking**: Conjunto de componentes que implementan el delivery tracking en tiempo real, distribuidos entre mi3-backend (Laravel), mi3-frontend (Next.js), app3 (Astro+React) y caja3 (Astro+React)
- **API_Mi3**: Backend Laravel 11 en api-mi3.laruta11.cl, con Reverb WebSockets y Sanctum
- **Reverb**: Servidor WebSocket de Laravel (Laravel Reverb) que gestiona canales de broadcast en tiempo real
- **Echo**: Cliente JavaScript (Laravel Echo) que escucha canales Reverb desde el frontend
- **Vista_Monitor**: Página en mi3-frontend (mi.laruta11.cl/admin/delivery) con mapa interactivo de todos los pedidos activos y riders
- **Vista_Cliente**: Página en app.laruta11.cl/tracking/{order_number}, embebible vía iframe, accesible sin autenticación
- **Vista_Rider**: Sección en mi.laruta11.cl/rider para riders autenticados, mobile-first
- **Rider**: Persona de delivery registrada en la tabla `personal` con rol que incluye 'rider', perteneciente a ARIAKA
- **ARIAKA**: Proveedor externo de servicio de delivery; todos los riders son empleados de ARIAKA
- **Pedido_Activo**: Registro en `tuu_orders` con `delivery_type = 'delivery'` y `order_status` en ('sent_to_kitchen', 'preparing', 'ready', 'out_for_delivery')
- **Tabla_TuuOrders**: Tabla `tuu_orders` de la base de datos MySQL compartida
- **Tabla_Personal**: Tabla `personal` de la base de datos MySQL compartida
- **Tabla_RiderLocations**: Nueva tabla `rider_locations` para historial de posiciones GPS de riders
- **Tabla_DeliveryAssignments**: Nueva tabla `delivery_assignments` para asignación de riders a pedidos
- **Tabla_DailySettlements**: Nueva tabla `daily_settlements` para registrar los cierres diarios de liquidación a ARIAKA
- **Canal_Monitor**: Canal Reverb privado `delivery.monitor` para la Vista_Monitor (solo admins)
- **Canal_Pedido**: Canal Reverb privado `order.{order_number}` para la Vista_Cliente de un pedido específico
- **Canal_Rider**: Canal Reverb privado `rider.{rider_id}` para actualizaciones de posición del rider
- **Google_Maps**: API de Google Maps JavaScript ya integrada en caja3, que se reutilizará en mi3-frontend y app3
- **Modulo_Compras**: Módulo de compras existente en mi3-backend/mi3-frontend que gestiona gastos del negocio con soporte de adjuntos en S3
- **Cierre_Diario**: Proceso automático al final del día que genera el resumen de liquidación a ARIAKA

## Requerimientos

### Requerimiento 1: Vista Monitor — Mapa Interactivo de Operaciones

**User Story:** Como administrador de La Ruta 11, quiero ver un mapa interactivo con todos los pedidos activos y la posición de los riders en tiempo real, para que pueda monitorear las operaciones de delivery desde mi3 admin.

#### Criterios de Aceptación

1. WHEN un Administrador accede a mi.laruta11.cl/admin/delivery, THE Vista_Monitor SHALL mostrar un mapa de Google Maps centrado en la ubicación del food truck con todos los Pedidos_Activos marcados con pins diferenciados por estado (preparando, listo, en camino).
2. WHEN un Rider actualiza su posición GPS, THE Vista_Monitor SHALL actualizar la posición del marcador del Rider en el mapa en tiempo real vía Canal_Monitor sin recargar la página.
3. THE Vista_Monitor SHALL mostrar un panel lateral con la lista de Pedidos_Activos, incluyendo: número de orden, nombre del cliente, dirección de entrega, estado actual, rider asignado y tiempo estimado de entrega.
4. WHEN un Pedido_Activo cambia de estado en Tabla_TuuOrders, THE Vista_Monitor SHALL actualizar el pin del mapa y el estado en el panel lateral en tiempo real vía Canal_Monitor.
5. THE Vista_Monitor SHALL mostrar marcadores diferenciados para cada Rider activo con su nombre, indicando si está disponible (sin pedido asignado) u ocupado (con pedido asignado).
6. WHEN un Administrador hace clic en el marcador de un Pedido_Activo en el mapa, THE Vista_Monitor SHALL mostrar un popup con los detalles del pedido: items, monto total, datos del cliente, rider asignado y opción de reasignar.
7. THE Vista_Monitor SHALL mostrar la ruta trazada en el mapa entre la posición actual del Rider y la dirección de entrega del pedido asignado, usando Google Maps Directions API.
8. WHILE la Vista_Monitor está activa, THE Sistema_Tracking SHALL mantener la conexión WebSocket con Canal_Monitor y reconectar automáticamente si se pierde la conexión.

### Requerimiento 2: Vista Monitor — Gestión y Asignación de Riders

**User Story:** Como administrador, quiero asignar riders a pedidos y gestionar el estado de los pedidos desde la Vista Monitor, para que pueda coordinar las entregas eficientemente.

#### Criterios de Aceptación

1. WHEN un Administrador selecciona un Pedido_Activo sin rider asignado, THE Vista_Monitor SHALL mostrar un selector con los Riders disponibles (activos, sin pedido en curso) para asignar.
2. WHEN un Administrador asigna un Rider a un pedido, THE Sistema_Tracking SHALL actualizar el campo `rider_id` en Tabla_TuuOrders, crear un registro en Tabla_DeliveryAssignments y emitir un evento por Canal_Monitor y Canal_Rider para notificar el cambio.
3. WHEN un Administrador cambia el estado de un pedido desde la Vista_Monitor, THE Sistema_Tracking SHALL actualizar `order_status` en Tabla_TuuOrders vía `PATCH /api/v1/admin/delivery/orders/{id}/status` y emitir el evento de cambio de estado por Canal_Monitor y Canal_Pedido correspondiente.
4. THE Vista_Monitor SHALL permitir al Administrador filtrar los Pedidos_Activos por estado (todos, preparando, listo, en camino) y por Rider asignado.
5. WHEN un pedido cambia a estado 'delivered', THE Sistema_Tracking SHALL actualizar `order_status = 'delivered'` en Tabla_TuuOrders, registrar `delivered_at` en Tabla_DeliveryAssignments y emitir evento de cierre por Canal_Pedido para notificar al cliente.
6. THE Vista_Monitor SHALL mostrar métricas en tiempo real: total de pedidos activos, riders disponibles, riders en ruta y tiempo promedio de entrega del día.

### Requerimiento 3: Vista Cliente — Seguimiento de Pedido Embebible

**User Story:** Como cliente de La Ruta 11, quiero ver el estado de mi pedido y la ubicación del rider en tiempo real desde una página de seguimiento, para que sepa cuándo llegará mi pedido sin tener que llamar.

#### Criterios de Aceptación

1. WHEN un cliente accede a app.laruta11.cl/tracking/{order_number}, THE Vista_Cliente SHALL mostrar el estado actual del pedido obtenido de Tabla_TuuOrders, con una línea de progreso visual con los pasos: Recibido → Preparando → Listo → En camino → Entregado.
2. WHEN el pedido tiene un Rider asignado con posición GPS disponible, THE Vista_Cliente SHALL mostrar un mapa de Google Maps con la posición del Rider y la dirección de entrega del cliente, actualizándose en tiempo real vía Canal_Pedido.
3. WHEN el estado del pedido cambia, THE Vista_Cliente SHALL actualizar la línea de progreso y mostrar una notificación visual del nuevo estado en tiempo real vía Canal_Pedido sin recargar la página.
4. THE Vista_Cliente SHALL mostrar el tiempo estimado de entrega calculado como la diferencia entre `estimated_delivery_time` en Tabla_TuuOrders y el momento actual, actualizándose cada minuto.
5. WHEN el pedido llega a estado 'delivered', THE Vista_Cliente SHALL mostrar una pantalla de confirmación de entrega con mensaje de agradecimiento y opción de dejar una reseña.
6. IF el `order_number` no existe en Tabla_TuuOrders o el pedido no es de tipo delivery, THEN THE Vista_Cliente SHALL mostrar un mensaje de error descriptivo indicando que el pedido no fue encontrado.
7. THE Vista_Cliente SHALL ser accesible sin autenticación, usando únicamente el `order_number` como identificador del pedido.
8. THE Vista_Cliente SHALL mostrar el nombre del Rider asignado y una foto de perfil genérica (o la foto del rider si está disponible en Tabla_Personal).
9. THE Vista_Cliente SHALL renderizarse sin headers, footers ni navegación propia, con un diseño limpio apto para ser embebida vía iframe en app3 (post-pedido) y en caja3 (para el operador).
10. THE Vista_Cliente SHALL incluir la cabecera HTTP `X-Frame-Options: SAMEORIGIN` reemplazada por `Content-Security-Policy: frame-ancestors *` para permitir el embedding desde los dominios de La Ruta 11.

**Nota de integración (pendiente fase posterior):** La integración del iframe en app3 (post-pedido) y en caja3 (panel operador) se documenta aquí pero no se implementa en esta fase. app3 deberá embeber la Vista_Cliente en la página de confirmación de pedido. caja3 deberá mostrarla en un panel lateral del monitor de operaciones.

### Requerimiento 4: Gestión de Ubicación GPS del Rider

**User Story:** Como rider de La Ruta 11, quiero que mi posición GPS se actualice automáticamente desde mi dispositivo móvil, para que el monitor y los clientes puedan ver mi ubicación en tiempo real.

#### Criterios de Aceptación

1. WHEN un Rider autenticado en mi3 activa el modo delivery, THE Sistema_Tracking SHALL comenzar a capturar la posición GPS del dispositivo usando la Geolocation API del navegador cada 15 segundos y enviarla vía `POST /api/v1/rider/location`.
2. WHEN el Sistema_Tracking recibe una actualización de posición de un Rider, THE API_Mi3 SHALL persistir la posición en Tabla_RiderLocations con rider_id, latitud, longitud, precisión y timestamp, y emitir un evento de broadcast por Canal_Monitor y Canal_Rider.
3. WHEN un Rider tiene un pedido asignado, THE Sistema_Tracking SHALL también emitir la actualización de posición por el Canal_Pedido del pedido asignado para que el cliente pueda verla.
4. IF el dispositivo del Rider no tiene GPS disponible o el permiso es denegado, THEN THE Sistema_Tracking SHALL mostrar un mensaje de error al Rider indicando que debe habilitar el GPS para continuar.
5. WHEN un Rider desactiva el modo delivery o cierra la sesión, THE Sistema_Tracking SHALL dejar de emitir actualizaciones de posición y marcar al Rider como inactivo en el canal.
6. THE Sistema_Tracking SHALL almacenar únicamente las últimas 100 posiciones por Rider en Tabla_RiderLocations para limitar el crecimiento de la tabla.
7. WHILE el Rider está en modo delivery, THE Sistema_Tracking SHALL mostrar en la Vista_Rider su posición actual en un mapa pequeño y el pedido asignado con la dirección de entrega.

### Requerimiento 5: Integración con Sistema de Pedidos Existente

**User Story:** Como administrador técnico, quiero que el sistema de tracking se integre con el sistema de pedidos existente en app3/caja3, para que no sea necesario duplicar datos ni cambiar el flujo de creación de pedidos.

#### Criterios de Aceptación

1. THE Sistema_Tracking SHALL leer los Pedidos_Activos directamente de Tabla_TuuOrders usando los campos existentes: `order_status`, `delivery_type`, `delivery_address`, `rider_id`, `estimated_delivery_time`, `customer_name`, `customer_phone`.
2. WHEN caja3 actualiza el `order_status` de un pedido delivery a 'out_for_delivery', THE Sistema_Tracking SHALL detectar el cambio vía un endpoint webhook `POST /api/v1/webhooks/order-status` llamado desde caja3 y emitir los eventos de broadcast correspondientes.
3. WHEN caja3 actualiza el `order_status` de un pedido delivery a cualquier estado, THE Sistema_Tracking SHALL emitir el evento de cambio de estado por Canal_Monitor y por el Canal_Pedido correspondiente.
4. THE Sistema_Tracking SHALL exponer un endpoint `GET /api/v1/public/orders/{order_number}/tracking` sin autenticación que retorne el estado actual del pedido, datos del rider asignado y última posición GPS conocida, para ser consumido por la Vista_Cliente.
5. WHEN se crea un nuevo pedido de tipo delivery en app3 (create_order.php), THE Sistema_Tracking SHALL generar automáticamente la URL de tracking `app.laruta11.cl/tracking/{order_number}` y almacenarla para ser enviada al cliente.
6. THE Sistema_Tracking SHALL respetar los estados existentes del enum `order_status` de Tabla_TuuOrders: pending, sent_to_kitchen, preparing, ready, out_for_delivery, delivered, completed, cancelled.

**Nota de integración (pendiente fase posterior):** Los cambios en app3 (create_order.php) y caja3 (webhook de cambio de estado) se documentan aquí pero no se implementan en esta fase. En esta fase, los cambios de estado se gestionan exclusivamente desde la Vista_Monitor de mi3.

### Requerimiento 6: Canales WebSocket y Eventos de Broadcast

**User Story:** Como administrador técnico, quiero que la comunicación en tiempo real use la infraestructura existente de Laravel Reverb + Echo, para que no sea necesario agregar servicios externos.

#### Criterios de Aceptación

1. THE Sistema_Tracking SHALL usar Laravel Reverb (ya configurado en mi3-backend) como servidor WebSocket y Laravel Echo (ya configurado en mi3-frontend) como cliente, sin agregar servicios externos de terceros.
2. THE Sistema_Tracking SHALL implementar tres tipos de canales Reverb: Canal_Monitor (privado, solo admins), Canal_Pedido (privado por order_number, accesible con el order_number como token), y Canal_Rider (privado, solo el rider autenticado y admins).
3. WHEN se emite un evento de actualización de posición de Rider, THE API_Mi3 SHALL broadcast el evento `RiderLocationUpdated` con payload: rider_id, nombre, latitud, longitud, timestamp, pedido_asignado_id.
4. WHEN se emite un evento de cambio de estado de pedido, THE API_Mi3 SHALL broadcast el evento `OrderStatusUpdated` con payload: order_id, order_number, order_status, rider_id, estimated_delivery_time, updated_at.
5. THE Sistema_Tracking SHALL implementar autorización de canales privados en `routes/channels.php` de mi3-backend: Canal_Monitor requiere rol admin, Canal_Pedido requiere que el order_number exista en Tabla_TuuOrders, Canal_Rider requiere que el rider_id corresponda al usuario autenticado o sea admin.
6. THE API_Mi3 SHALL exponer un endpoint de autorización de canales en `POST /broadcasting/auth` compatible con Laravel Echo para autenticar los canales privados.

### Requerimiento 7: Modelo de Datos — Nuevas Tablas

**User Story:** Como administrador técnico, quiero que las nuevas tablas necesarias para el tracking estén definidas, para que el sistema pueda almacenar posiciones GPS, asignaciones de riders y cierres diarios de liquidación.

#### Criterios de Aceptación

1. THE Sistema_Tracking SHALL crear la tabla `rider_locations` con columnas: `id` (BIGINT PK AUTO_INCREMENT), `rider_id` (INT NOT NULL FK → personal), `latitud` (DECIMAL 10,8 NOT NULL), `longitud` (DECIMAL 11,8 NOT NULL), `precision_metros` (INT DEFAULT 0), `velocidad_kmh` (DECIMAL 5,2 NULL), `heading` (DECIMAL 5,2 NULL), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP), con índice en (rider_id, created_at).
2. THE Sistema_Tracking SHALL crear la tabla `delivery_assignments` con columnas: `id` (INT PK AUTO_INCREMENT), `order_id` (INT NOT NULL FK → tuu_orders), `rider_id` (INT NOT NULL FK → personal), `assigned_by` (INT NOT NULL FK → personal), `assigned_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP), `picked_up_at` (TIMESTAMP NULL), `delivered_at` (TIMESTAMP NULL), `status` (ENUM 'assigned','picked_up','delivered','cancelled' DEFAULT 'assigned'), `notes` (VARCHAR 255 NULL), con índice en (order_id), (rider_id, status).
3. THE Sistema_Tracking SHALL crear la tabla `daily_settlements` con columnas: `id` (INT PK AUTO_INCREMENT), `settlement_date` (DATE NOT NULL UNIQUE), `total_orders_delivered` (INT NOT NULL DEFAULT 0), `total_delivery_fees` (DECIMAL 10,2 NOT NULL DEFAULT 0), `settlement_data` (JSON NULL — desglose por rider), `status` (ENUM 'pending','paid' DEFAULT 'pending'), `payment_voucher_url` (VARCHAR 500 NULL — URL S3 del comprobante), `paid_at` (TIMESTAMP NULL), `paid_by` (INT NULL FK → personal), `compra_id` (INT NULL FK → compras — referencia a la compra generada), `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP), `updated_at` (TIMESTAMP NULL), con índice en (settlement_date), (status).
4. THE Sistema_Tracking SHALL agregar el campo `tracking_url` (VARCHAR 255 NULL) a Tabla_TuuOrders si no existe, para almacenar la URL de seguimiento del cliente.
5. THE Sistema_Tracking SHALL agregar los campos `rider_last_lat` (DECIMAL 10,8 NULL) y `rider_last_lng` (DECIMAL 11,8 NULL) a Tabla_TuuOrders para almacenar la última posición conocida del rider asignado, optimizando la carga inicial de la Vista_Cliente.

### Requerimiento 8: Infraestructura y Endpoints API

**User Story:** Como administrador técnico, quiero que los endpoints del sistema de tracking estén organizados en mi3-backend, para que el sistema sea mantenible y coherente con la arquitectura existente.

#### Criterios de Aceptación

1. THE Sistema_Tracking SHALL agregar los siguientes endpoints a mi3-backend bajo el prefijo `/api/v1/`: `GET /admin/delivery/orders` (pedidos activos con riders), `PATCH /admin/delivery/orders/{id}/status` (cambiar estado), `POST /admin/delivery/orders/{id}/assign-rider` (asignar rider), `GET /admin/delivery/riders` (riders disponibles con última posición), `POST /rider/location` (actualizar posición GPS), `GET /rider/current-assignment` (pedido asignado al rider), `PATCH /rider/current-assignment/status` (marcar recogido o entregado), `GET /public/orders/{order_number}/tracking` (datos de tracking sin auth), `POST /webhooks/order-status` (webhook desde caja3 — pendiente fase posterior).
2. THE API_Mi3 SHALL proteger los endpoints `/admin/delivery/*` con el middleware `EnsureIsAdmin` existente y los endpoints `/rider/*` con el middleware `EnsureIsWorker` existente, verificando además que el trabajador tenga rol 'rider'.
3. THE endpoint `GET /public/orders/{order_number}/tracking` SHALL retornar: order_number, order_status, customer_name, delivery_address, rider_name, rider_photo_url, rider_lat, rider_lng, estimated_delivery_time, items_count, sin exponer datos sensibles del cliente.
4. THE Sistema_Tracking SHALL agregar en mi3-frontend las páginas: `/admin/delivery` (Vista_Monitor), `/rider` (Vista_Rider) y en app3 la página `/tracking/[order_number]` (Vista_Cliente — pendiente fase posterior).
5. THE Sistema_Tracking SHALL reutilizar la Google Maps API Key ya configurada en caja3, agregándola como variable de entorno `NEXT_PUBLIC_GOOGLE_MAPS_KEY` en mi3-frontend.
6. THE Sistema_Tracking SHALL implementar los modelos Eloquent `RiderLocation`, `DeliveryAssignment` y `DailySettlement` en mi3-backend con sus migraciones Laravel correspondientes.

### Requerimiento 9: Vista Rider — Perfil y Gestión de Entregas

**User Story:** Como rider de ARIAKA asignado a La Ruta 11, quiero tener una sección dedicada en mi3 accesible desde mi celular, para que pueda gestionar mis entregas, ver el pedido asignado y actualizar los estados sin necesidad de contactar al administrador.

#### Criterios de Aceptación

1. WHEN un Rider autenticado accede a mi.laruta11.cl/rider, THE Vista_Rider SHALL mostrar una interfaz mobile-first con: toggle de modo delivery (activo/inactivo), pedido asignado actual (si existe) con dirección de entrega, y botones de acción según el estado.
2. THE Vista_Rider SHALL ser una ruta protegida que requiere autenticación con rol 'rider' en mi3-frontend; IF un usuario sin rol rider intenta acceder, THEN THE Sistema_Tracking SHALL redirigir al dashboard correspondiente a su rol.
3. WHEN el Rider activa el modo delivery desde la Vista_Rider, THE Sistema_Tracking SHALL iniciar la captura de GPS y marcar al Rider como disponible en el Canal_Monitor.
4. WHEN el Rider tiene un pedido asignado, THE Vista_Rider SHALL mostrar: número de orden, nombre del cliente, dirección de entrega completa, monto del pedido, y un mapa pequeño con la ruta desde la posición actual del Rider hasta la dirección de entrega.
5. WHEN el Rider hace clic en "Marcar como Recogido", THE Sistema_Tracking SHALL actualizar `picked_up_at` en Tabla_DeliveryAssignments, cambiar el `order_status` a 'out_for_delivery' en Tabla_TuuOrders y emitir el evento `OrderStatusUpdated` por Canal_Monitor y Canal_Pedido.
6. WHEN el Rider hace clic en "Marcar como Entregado", THE Sistema_Tracking SHALL actualizar `delivered_at` en Tabla_DeliveryAssignments, cambiar el `order_status` a 'delivered' en Tabla_TuuOrders y emitir el evento `OrderStatusUpdated` por Canal_Monitor y Canal_Pedido.
7. WHILE el Rider está en modo delivery, THE Vista_Rider SHALL mostrar un mapa con la posición actual del Rider actualizándose en tiempo real.
8. WHEN el Rider desactiva el modo delivery, THE Sistema_Tracking SHALL detener la captura de GPS y marcar al Rider como inactivo en Canal_Monitor.

### Requerimiento 10: Recaudación Diaria y Liquidación a ARIAKA

**User Story:** Como administrador de La Ruta 11, quiero que el sistema genere automáticamente el resumen de cierre diario de delivery y me alerte si no he subido el comprobante de pago a ARIAKA antes de las 12:00 del día siguiente, para que pueda gestionar la liquidación sin perder información.

#### Criterios de Aceptación

1. WHEN el día termina (proceso automático a las 23:59 vía Laravel Scheduler), THE Sistema_Tracking SHALL generar un registro en Tabla_DailySettlements con: `settlement_date` = fecha del día, `total_orders_delivered` = cantidad de pedidos con `order_status = 'delivered'` del día, `total_delivery_fees` = suma de `delivery_fee` de esos pedidos, `settlement_data` = JSON con desglose por rider (rider_id, nombre, cantidad de pedidos, subtotal de fees), `status = 'pending'`.
2. THE Sistema_Tracking SHALL mostrar el resumen de cierre diario en mi3-frontend en la sección de delivery admin, con: fecha, total de pedidos entregados, desglose por rider, y monto total a transferir a ARIAKA (siempre 1 sola transferencia independiente del número de riders).
3. WHEN son las 12:00 del día siguiente y el `status` del Cierre_Diario sigue en 'pending', THE Sistema_Tracking SHALL enviar una notificación push al administrador y mostrar un badge de alerta en el dashboard de mi3-frontend indicando que el comprobante de pago a ARIAKA está pendiente.
4. WHEN un Administrador sube el comprobante de transferencia a ARIAKA, THE Sistema_Tracking SHALL subir el archivo a S3 (usando la misma lógica que el Modulo_Compras), actualizar `payment_voucher_url`, `paid_at`, `paid_by` y `status = 'paid'` en Tabla_DailySettlements.
5. THE Sistema_Tracking SHALL calcular el monto total a transferir a ARIAKA como la suma de todos los `delivery_fee` de registros en Tabla_TuuOrders donde `order_status = 'delivered'` y `DATE(delivered_at) = settlement_date`, sin importar cuántos riders trabajaron ese día.
6. IF un día no tiene pedidos entregados, THEN THE Sistema_Tracking SHALL igualmente crear el registro en Tabla_DailySettlements con `total_orders_delivered = 0` y `total_delivery_fees = 0`, y NO SHALL generar alerta de comprobante pendiente para ese día.

### Requerimiento 11: Integración con Módulo de Compras al Liquidar ARIAKA

**User Story:** Como administrador de La Ruta 11, quiero que al subir el comprobante de pago a ARIAKA el sistema genere automáticamente una compra en el módulo de compras existente, para que el gasto de delivery quede registrado contablemente sin trabajo manual.

#### Criterios de Aceptación

1. WHEN un Administrador sube el comprobante de transferencia a ARIAKA y el Cierre_Diario se marca como 'paid', THE Sistema_Tracking SHALL crear automáticamente un registro en la tabla `compras` del Modulo_Compras con: proveedor = ARIAKA (proveedor ya existente en la BD), categoría = "Servicio Delivery", monto = `total_delivery_fees` del Cierre_Diario, fecha = `paid_at`, adjunto = `payment_voucher_url` (S3), descripción = "Servicio delivery {settlement_date} - {total_orders_delivered} pedidos".
2. WHEN la compra es creada automáticamente, THE Sistema_Tracking SHALL actualizar el campo `compra_id` en Tabla_DailySettlements con el ID de la compra generada, para mantener la trazabilidad entre el cierre y la compra.
3. THE compra generada automáticamente SHALL aparecer en el listado normal de compras de mi3-frontend, indistinguible de una compra ingresada manualmente, con todos los campos requeridos por el Modulo_Compras.
4. IF la creación automática de la compra falla, THEN THE Sistema_Tracking SHALL registrar el error en los logs de mi3-backend, marcar el Cierre_Diario como 'paid' de todas formas (el comprobante ya fue subido), y mostrar una alerta al Administrador indicando que debe crear la compra manualmente.
5. THE Sistema_Tracking SHALL usar el mismo mecanismo de subida a S3 que usa el Modulo_Compras para el comprobante, garantizando que el adjunto sea accesible desde el detalle de la compra generada.

## Requerimientos No Funcionales

### Nota Futura: Arquitectura SaaS Multi-Tenant

**Estado:** Investigación pendiente — NO implementar en esta fase.

Esta nota documenta la dirección estratégica futura del sistema para cuando La Ruta 11 escale o se convierta en un producto SaaS.

**Contexto:** El VPS actual (Ubuntu 24.04, KVM 2, 8GB RAM, 100GB disco) está pagado hasta 2027-02-04 y se mantiene para La Ruta 11. El dominio pocos.click (caduca 2026-12-21) es candidato para el producto SaaS.

**Stack candidato a evaluar:**
- AWS Lambda + DynamoDB o Aurora PostgreSQL para el backend serverless
- Next.js + React para el frontend
- Stripe Billing para la facturación multi-tenant
- Amazon Location Service como reemplazo de Google Maps (mejor pricing a escala)

**Tareas de investigación a realizar en el futuro:**
- Evaluar modelo de datos multi-tenant (schema-per-tenant vs row-level isolation)
- Analizar costos AWS Lambda vs VPS dedicado para el volumen proyectado
- Definir estrategia de migración de datos desde MySQL actual
- Evaluar Amazon Location Service vs Google Maps API en términos de costo y funcionalidad
- Diseñar el modelo de billing con Stripe (planes, trials, facturación por pedido o por mes)
