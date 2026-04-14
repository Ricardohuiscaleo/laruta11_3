# Diseño Técnico — Delivery Tracking en Tiempo Real

## Overview

Sistema de seguimiento de delivery en tiempo real para La Ruta 11. Extiende la infraestructura existente de mi3-backend (Laravel 11 + Reverb) y mi3-frontend (Next.js 14) para agregar tres vistas: Vista Monitor (admin), Vista Rider (mobile-first) y Vista Cliente (embebible, fase posterior en app3). La comunicación en tiempo real usa Laravel Reverb + Echo ya configurados. La liquidación diaria a ARIAKA se automatiza con comandos Artisan y se integra con el módulo de compras existente.

**Prioridad de implementación:** mi3-backend y mi3-frontend. La integración con app3 y caja3 queda documentada pero pendiente para una fase posterior.

---

## Architecture

### Flujo de datos principal

```
┌─────────────────────────────────────────────────────────────────────┐
│                         RIDER (móvil)                               │
│  mi.laruta11.cl/rider                                               │
│  Geolocation API → POST /api/v1/rider/location (cada 15s)          │
└──────────────────────────────┬──────────────────────────────────────┘
                               │ HTTPS
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    mi3-backend (Laravel 11)                         │
│  api-mi3.laruta11.cl                                                │
│                                                                     │
│  RiderController::updateLocation()                                  │
│    1. Persiste en rider_locations                                   │
│    2. Actualiza rider_last_lat/lng en tuu_orders                    │
│    3. broadcast(RiderLocationUpdated)                               │
│         → Canal_Monitor: delivery.monitor                           │
│         → Canal_Rider: rider.{rider_id}                             │
│         → Canal_Pedido: order.{order_number} (si tiene asignación) │
│                                                                     │
│  DeliveryController::updateStatus()                                 │
│    1. Actualiza order_status en tuu_orders                          │
│    2. broadcast(OrderStatusUpdated)                                 │
│         → Canal_Monitor: delivery.monitor                           │
│         → Canal_Pedido: order.{order_number}                        │
│                                                                     │
│  SettlementController                                               │
│    - Gestiona DailySettlements + integración Compras                │
│                                                                     │
│  Laravel Reverb (WebSocket server)                                  │
│  wss://api-mi3.laruta11.cl                                          │
└──────┬──────────────────────────────────────────────────────────────┘
       │ WebSocket (Reverb)
       ├──────────────────────────────────────────────────────────────┐
       │                                                              │
       ▼                                                              ▼
┌──────────────────────┐                              ┌──────────────────────────┐
│  ADMIN               │                              │  CLIENTE (fase posterior)│
│  mi.laruta11.cl      │                              │  app.laruta11.cl         │
│  /admin/delivery     │                              │  /tracking/{order_number}│
│                      │                              │                          │
│  Echo.private(       │                              │  Echo.private(           │
│   'delivery.monitor')│                              │   'order.{order_number}')│
│                      │                              │                          │
│  Google Maps JS API  │                              │  Google Maps JS API      │
│  - Markers riders    │                              │  - Marker rider          │
│  - Markers pedidos   │                              │  - Marker destino        │
│  - Directions API    │                              │  - Progress bar          │
└──────────────────────┘                              └──────────────────────────┘
```

### Flujo de liquidación diaria

```
23:59 (Laravel Scheduler)
  └─► delivery:generate-daily-settlement
        ├─ Calcula SUM(delivery_fee) WHERE status='delivered' AND DATE=hoy
        ├─ Crea DailySettlement (status='pending')
        └─ Si total=0 → no genera alerta

12:00 día siguiente (Laravel Scheduler)
  └─► delivery:check-pending-settlements
        └─ Si DailySettlement.status='pending' Y total>0
             └─ Push notification al admin

Admin sube comprobante
  └─► SettlementController::uploadVoucher()
        ├─ Sube a S3 (S3Manager existente)
        ├─ Actualiza DailySettlement (status='paid', payment_voucher_url, paid_at, paid_by)
        └─ Crea Compra automáticamente en tabla compras
             └─ Actualiza DailySettlement.compra_id
```

---

## Components and Interfaces

### Backend — Nuevos controladores

**`App\Http\Controllers\Admin\DeliveryController`**
- `index()` — GET /admin/delivery/orders — pedidos activos con rider y última posición
- `updateStatus(Request $request, int $id)` — PATCH /admin/delivery/orders/{id}/status
- `assignRider(Request $request, int $id)` — POST /admin/delivery/orders/{id}/assign-rider
- `riders()` — GET /admin/delivery/riders — riders disponibles con última posición GPS

**`App\Http\Controllers\Rider\RiderController`**
- `updateLocation(Request $request)` — POST /rider/location
- `currentAssignment()` — GET /rider/current-assignment
- `updateAssignmentStatus(Request $request)` — PATCH /rider/current-assignment/status

**`App\Http\Controllers\Admin\SettlementController`**
- `index()` — GET /admin/delivery/settlements — listado de liquidaciones
- `show(int $id)` — GET /admin/delivery/settlements/{id}
- `uploadVoucher(Request $request, int $id)` — POST /admin/delivery/settlements/{id}/voucher

**`App\Http\Controllers\Public\TrackingController`**
- `show(string $orderNumber)` — GET /public/orders/{order_number}/tracking

### Backend — Eventos Reverb

**`App\Events\RiderLocationUpdated`** — implements ShouldBroadcast
```
Canales: delivery.monitor, rider.{rider_id}, order.{order_number} (si asignado)
Payload: rider_id, nombre, latitud, longitud, timestamp, pedido_asignado_id
```

**`App\Events\OrderStatusUpdated`** — implements ShouldBroadcast
```
Canales: delivery.monitor, order.{order_number}
Payload: order_id, order_number, order_status, rider_id, estimated_delivery_time, updated_at
```

### Backend — Servicios

**`App\Services\Delivery\DeliveryService`**
- `getActiveOrders(): Collection` — pedidos activos con riders
- `assignRider(int $orderId, int $riderId, int $assignedBy): DeliveryAssignment`
- `updateOrderStatus(int $orderId, string $status): TuuOrder`
- `getAvailableRiders(): Collection`

**`App\Services\Delivery\LocationService`**
- `updateRiderLocation(int $riderId, float $lat, float $lng, int $precision, ?float $speed, ?float $heading): RiderLocation`
- `pruneOldLocations(int $riderId): void` — mantiene solo las últimas 100

**`App\Services\Delivery\SettlementService`**
- `generateDailySettlement(Carbon $date): DailySettlement`
- `uploadVoucherAndPay(int $settlementId, UploadedFile $file, int $paidBy): DailySettlement`
- `createCompraFromSettlement(DailySettlement $settlement): Compra`

### Backend — Comandos Artisan

**`App\Console\Commands\GenerateDailySettlementCommand`**
- Signature: `delivery:generate-daily-settlement {--date= : Fecha YYYY-MM-DD, default=hoy}`
- Cron: `59 23 * * *`
- Idempotente: usa `updateOrCreate` por `settlement_date`

**`App\Console\Commands\CheckPendingSettlementsCommand`**
- Signature: `delivery:check-pending-settlements`
- Cron: `0 12 * * *`
- Envía push notification a admins si hay settlements pending con total > 0 del día anterior

### Frontend — Nuevas páginas

**`app/admin/delivery/page.tsx`** — Vista Monitor
- Requiere rol admin (middleware existente)
- Usa `useDeliveryTracking` hook
- Renderiza `DeliveryMap` + `OrderPanel`

**`app/rider/page.tsx`** — Vista Rider
- Requiere rol rider
- Usa `useRiderGPS` hook
- Mobile-first, renderiza `RiderDashboard`

### Frontend — Nuevos hooks

**`useDeliveryTracking()`**
```typescript
// Gestiona canales Echo + estado de pedidos y riders
// Retorna: { orders, riders, metrics, assignRider, updateStatus }
// Suscribe a: Echo.private('delivery.monitor')
// Escucha: .RiderLocationUpdated, .OrderStatusUpdated
```

**`useRiderGPS(riderId: number)`**
```typescript
// Geolocation API + envío periódico cada 15s
// Retorna: { position, isActive, error, toggleDeliveryMode }
// Llama: POST /api/v1/rider/location
```

### Frontend — Nuevos componentes

| Componente | Ubicación | Responsabilidad |
|---|---|---|
| `DeliveryMap` | `components/admin/delivery/` | Google Maps con markers de riders y pedidos |
| `RiderMarker` | `components/admin/delivery/` | Marker diferenciado disponible/ocupado |
| `OrderPanel` | `components/admin/delivery/` | Panel lateral con lista de pedidos activos |
| `RiderDashboard` | `components/rider/` | Vista mobile del rider con toggle GPS |
| `SettlementPanel` | `components/admin/delivery/` | Gestión de liquidaciones diarias |
| `DeliveryMetrics` | `components/admin/delivery/` | Métricas en tiempo real |

### Vista Cliente — app3 (fase posterior)

Documentado para implementación futura:
- Página: `app3/src/pages/tracking/[order_number].astro`
- Consume: `GET /api/v1/public/orders/{order_number}/tracking`
- Sin auth, sin navegación, apta para iframe
- Header: `Content-Security-Policy: frame-ancestors *`
- Embebible desde app3 (post-pedido) y caja3 (panel operador)

---

## Data Models

### Migraciones Laravel

**`create_rider_locations_table`**
```php
Schema::create('rider_locations', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->integer('rider_id')->unsigned()->index();
    $table->decimal('latitud', 10, 8);
    $table->decimal('longitud', 11, 8);
    $table->integer('precision_metros')->default(0);
    $table->decimal('velocidad_kmh', 5, 2)->nullable();
    $table->decimal('heading', 5, 2)->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['rider_id', 'created_at']);
    $table->foreign('rider_id')->references('id')->on('personal');
});
```

**`create_delivery_assignments_table`**
```php
Schema::create('delivery_assignments', function (Blueprint $table) {
    $table->increments('id');
    $table->integer('order_id')->unsigned();
    $table->integer('rider_id')->unsigned();
    $table->integer('assigned_by')->unsigned();
    $table->timestamp('assigned_at')->useCurrent();
    $table->timestamp('picked_up_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->enum('status', ['assigned', 'picked_up', 'delivered', 'cancelled'])
          ->default('assigned');
    $table->string('notes', 255)->nullable();

    $table->index('order_id');
    $table->index(['rider_id', 'status']);
    $table->foreign('order_id')->references('id')->on('tuu_orders');
    $table->foreign('rider_id')->references('id')->on('personal');
    $table->foreign('assigned_by')->references('id')->on('personal');
});
```

**`create_daily_settlements_table`**
```php
Schema::create('daily_settlements', function (Blueprint $table) {
    $table->increments('id');
    $table->date('settlement_date')->unique();
    $table->integer('total_orders_delivered')->default(0);
    $table->decimal('total_delivery_fees', 10, 2)->default(0);
    $table->json('settlement_data')->nullable(); // desglose por rider
    $table->enum('status', ['pending', 'paid'])->default('pending');
    $table->string('payment_voucher_url', 500)->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->integer('paid_by')->unsigned()->nullable();
    $table->integer('compra_id')->unsigned()->nullable();
    $table->timestamp('created_at')->useCurrent();
    $table->timestamp('updated_at')->nullable();

    $table->index('settlement_date');
    $table->index('status');
    $table->foreign('paid_by')->references('id')->on('personal');
    $table->foreign('compra_id')->references('id')->on('compras');
});
```

**`add_tracking_fields_to_tuu_orders`**
```php
Schema::table('tuu_orders', function (Blueprint $table) {
    $table->string('tracking_url', 255)->nullable()->after('delivery_address');
    $table->decimal('rider_last_lat', 10, 8)->nullable()->after('rider_id');
    $table->decimal('rider_last_lng', 11, 8)->nullable()->after('rider_last_lat');
});
```

### Modelos Eloquent

**`App\Models\RiderLocation`**
```php
class RiderLocation extends Model {
    protected $table = 'rider_locations';
    public $timestamps = false;
    protected $fillable = ['rider_id', 'latitud', 'longitud', 'precision_metros', 'velocidad_kmh', 'heading'];

    public function rider() { return $this->belongsTo(Personal::class, 'rider_id'); }
}
```

**`App\Models\DeliveryAssignment`**
```php
class DeliveryAssignment extends Model {
    protected $table = 'delivery_assignments';
    public $timestamps = false;
    protected $fillable = ['order_id', 'rider_id', 'assigned_by', 'assigned_at', 'picked_up_at', 'delivered_at', 'status', 'notes'];
    protected $casts = ['assigned_at' => 'datetime', 'picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function order() { return $this->belongsTo(TuuOrder::class, 'order_id'); }
    public function rider() { return $this->belongsTo(Personal::class, 'rider_id'); }
}
```

**`App\Models\DailySettlement`**
```php
class DailySettlement extends Model {
    protected $table = 'daily_settlements';
    protected $fillable = ['settlement_date', 'total_orders_delivered', 'total_delivery_fees', 'settlement_data', 'status', 'payment_voucher_url', 'paid_at', 'paid_by', 'compra_id'];
    protected $casts = ['settlement_date' => 'date', 'settlement_data' => 'array', 'paid_at' => 'datetime', 'total_delivery_fees' => 'float'];

    public function compra() { return $this->belongsTo(Compra::class, 'compra_id'); }
    public function pagadoPor() { return $this->belongsTo(Personal::class, 'paid_by'); }
}
```

### Autorización de canales — `routes/channels.php`

```php
// Canal Monitor: solo admins
Broadcast::channel('delivery.monitor', function ($user) {
    return $user->esAdmin();
});

// Canal Pedido: order_number debe existir en tuu_orders
Broadcast::channel('order.{orderNumber}', function ($user, $orderNumber) {
    return TuuOrder::where('order_number', $orderNumber)->exists();
});

// Canal Rider: el rider autenticado o un admin
Broadcast::channel('rider.{riderId}', function ($user, $riderId) {
    return $user->esAdmin() || $user->personal_id == $riderId;
});
```

### Endpoints API completos

```
# Admin (middleware: auth:sanctum + worker + admin)
GET    /api/v1/admin/delivery/orders
PATCH  /api/v1/admin/delivery/orders/{id}/status
POST   /api/v1/admin/delivery/orders/{id}/assign-rider
GET    /api/v1/admin/delivery/riders
GET    /api/v1/admin/delivery/settlements
GET    /api/v1/admin/delivery/settlements/{id}
POST   /api/v1/admin/delivery/settlements/{id}/voucher

# Rider (middleware: auth:sanctum + worker + rider)
POST   /api/v1/rider/location
GET    /api/v1/rider/current-assignment
PATCH  /api/v1/rider/current-assignment/status

# Público (sin auth)
GET    /api/v1/public/orders/{order_number}/tracking

# Webhook (pendiente fase posterior)
POST   /api/v1/webhooks/order-status
```

Respuesta de `GET /public/orders/{order_number}/tracking`:
```json
{
  "order_number": "string",
  "order_status": "string",
  "customer_name": "string",
  "delivery_address": "string",
  "rider_name": "string | null",
  "rider_photo_url": "string | null",
  "rider_lat": "float | null",
  "rider_lng": "float | null",
  "estimated_delivery_time": "ISO8601 | null",
  "items_count": "int"
}
```

### Integración con Módulo de Compras

Al marcar un settlement como `paid`, `SettlementService::createCompraFromSettlement()` crea:
```php
Compra::create([
    'fecha_compra'  => $settlement->paid_at,
    'proveedor'     => 'ARIAKA',
    'tipo_compra'   => 'servicio',
    'monto_total'   => $settlement->total_delivery_fees,
    'metodo_pago'   => 'transferencia',
    'estado'        => 'pagado',
    'notas'         => "Servicio delivery {$settlement->settlement_date->format('Y-m-d')} - {$settlement->total_orders_delivered} pedidos",
    'imagen_respaldo' => [$settlement->payment_voucher_url],
    'usuario'       => $adminNombre,
]);
```

El campo `imagen_respaldo` es `json` en `Compra` (cast a array), compatible con el S3Manager existente.

---

## Correctness Properties

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas del sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre especificaciones legibles por humanos y garantías de corrección verificables por máquinas.*

### Property 1: Consistencia GPS — persistencia y broadcast

*Para cualquier* rider autenticado con modo delivery activo y cualquier posición GPS válida (lat, lng, precision), cuando se llama a `POST /api/v1/rider/location`, el sistema SHALL persistir un registro en `rider_locations` con esas coordenadas Y emitir el evento `RiderLocationUpdated` con el mismo payload.

**Validates: Requirements 4.2, 6.3**

### Property 2: Broadcast por Canal_Pedido cuando hay asignación activa

*Para cualquier* rider con una `DeliveryAssignment` activa (status='assigned' o 'picked_up'), cuando se actualiza su posición GPS, el evento `RiderLocationUpdated` SHALL emitirse también por el canal `order.{order_number}` del pedido asignado, además de `delivery.monitor` y `rider.{rider_id}`.

**Validates: Requirements 4.3, 6.3**

### Property 3: Consistencia de estado — persistencia y broadcast

*Para cualquier* pedido activo y cualquier `order_status` válido del enum (`pending`, `sent_to_kitchen`, `preparing`, `ready`, `out_for_delivery`, `delivered`, `completed`, `cancelled`), cuando se actualiza el estado vía `PATCH /admin/delivery/orders/{id}/status`, el sistema SHALL actualizar `order_status` en `tuu_orders` Y emitir `OrderStatusUpdated` con el nuevo estado en el payload.

**Validates: Requirements 2.3, 5.6, 6.4**

### Property 4: Liquidación completa — suma exacta de delivery fees

*Para cualquier* fecha con N pedidos entregados (order_status='delivered'), el campo `total_delivery_fees` del `DailySettlement` generado SHALL ser igual a `SUM(delivery_fee)` de todos los registros en `tuu_orders` donde `order_status='delivered'` y `DATE(delivered_at) = settlement_date`.

**Validates: Requirements 10.1, 10.5**

### Property 5: Idempotencia del settlement diario

*Para cualquier* fecha, ejecutar `delivery:generate-daily-settlement` dos veces el mismo día SHALL producir exactamente un registro en `daily_settlements` con los mismos valores (usando `updateOrCreate` por `settlement_date`). La segunda ejecución no debe crear un duplicado ni modificar un settlement ya marcado como `paid`.

**Validates: Requirements 10.1**

### Property 6: Límite de posiciones GPS por rider

*Para cualquier* rider_id, después de insertar más de 100 posiciones GPS, el conteo de registros en `rider_locations` para ese rider SHALL ser menor o igual a 100.

**Validates: Requirements 4.6**

### Property 7: Autorización de canales privados

*Para cualquier* usuario sin rol admin que intente suscribirse al canal `delivery.monitor`, la autorización SHALL retornar `false` (HTTP 403). *Para cualquier* usuario que no sea el rider propietario ni admin que intente suscribirse a `rider.{riderId}`, la autorización SHALL retornar `false`.

**Validates: Requirements 6.5**

### Property 8: Endpoint público no expone datos sensibles

*Para cualquier* `order_number` válido en `tuu_orders`, la respuesta de `GET /api/v1/public/orders/{order_number}/tracking` SHALL contener los campos requeridos (order_number, order_status, delivery_address, rider_name, rider_lat, rider_lng, estimated_delivery_time, items_count) y NO SHALL contener `customer_phone`, `customer_email`, ni ningún campo de pago.

**Validates: Requirements 5.4, 8.3**

### Property 9: Trazabilidad settlement → compra

*Para cualquier* `DailySettlement` marcado como `paid` con `total_delivery_fees > 0`, SHALL existir un registro en `compras` con `id = settlement.compra_id` y `monto_total = settlement.total_delivery_fees`.

**Validates: Requirements 11.1, 11.2**

---

## Error Handling

### GPS no disponible o denegado
- `useRiderGPS` captura `GeolocationPositionError` y expone `error` en el hook
- La Vista Rider muestra un banner de error con instrucciones para habilitar GPS
- El rider permanece en modo delivery pero sin emitir posición

### Pérdida de conexión WebSocket
- Laravel Echo reconecta automáticamente con backoff exponencial
- La Vista Monitor muestra un indicador de "Reconectando..." mientras no hay conexión
- Los datos del último estado conocido permanecen visibles

### Fallo en creación automática de Compra
- `SettlementService::createCompraFromSettlement()` está envuelto en try/catch
- Si falla: el settlement queda `paid` (el comprobante ya fue subido), se loguea el error con `Log::error()`, y se retorna una respuesta con `compra_created: false`
- El frontend muestra una alerta al admin indicando que debe crear la compra manualmente

### Validación de estados inválidos
- `DeliveryController::updateStatus()` valida que el nuevo estado esté en el enum permitido
- Retorna HTTP 422 con mensaje descriptivo si el estado no es válido

### Order number no encontrado (Vista Cliente)
- `TrackingController::show()` retorna HTTP 404 con JSON `{ "error": "Pedido no encontrado" }`
- La Vista Cliente (app3) muestra un mensaje descriptivo al usuario

### Límite de posiciones GPS
- `LocationService::pruneOldLocations()` se ejecuta después de cada inserción
- Usa `DELETE FROM rider_locations WHERE rider_id = ? ORDER BY created_at ASC LIMIT ?` para eliminar las más antiguas

---

## Testing Strategy

### Enfoque dual: tests de ejemplo + tests de propiedades

**Tests de ejemplo (PHPUnit):**
- Comportamientos específicos de UI y flujos de negocio concretos
- Casos de error y edge cases
- Integración entre componentes

**Tests de propiedades (PBT con fast-check en frontend, PHPUnit + generadores en backend):**
- Propiedades universales definidas en la sección anterior
- Mínimo 100 iteraciones por propiedad
- Usar mocks para Reverb y S3 en tests de propiedades

### Librería PBT

- **Backend (PHP):** `eris/eris` o generadores manuales con PHPUnit `@dataProvider` para cubrir el espacio de inputs
- **Frontend (TypeScript):** `fast-check` para propiedades de hooks y componentes

### Tests de propiedades — backend

```php
// Property 1: Consistencia GPS
// Tag: Feature: delivery-tracking-realtime, Property 1: GPS persistence and broadcast
public function test_rider_location_persisted_and_broadcast(): void {
    // Para cualquier (lat, lng) válido → rider_locations tiene el registro Y evento emitido
}

// Property 3: Consistencia de estado
// Tag: Feature: delivery-tracking-realtime, Property 3: Status consistency
public function test_order_status_update_persists_and_broadcasts(): void {
    // Para cualquier order_status válido → tuu_orders actualizado Y evento emitido
}

// Property 4: Liquidación completa
// Tag: Feature: delivery-tracking-realtime, Property 4: Settlement completeness
public function test_settlement_total_equals_sum_of_delivery_fees(): void {
    // Para cualquier conjunto de pedidos delivered → total_delivery_fees = SUM(delivery_fee)
}

// Property 5: Idempotencia del settlement
// Tag: Feature: delivery-tracking-realtime, Property 5: Settlement idempotence
public function test_generate_settlement_twice_produces_one_record(): void {
    // Ejecutar comando dos veces → exactamente 1 registro en daily_settlements
}

// Property 6: Límite GPS
// Tag: Feature: delivery-tracking-realtime, Property 6: GPS location limit
public function test_rider_locations_capped_at_100(): void {
    // Insertar 150 posiciones → COUNT <= 100
}
```

### Tests de propiedades — frontend

```typescript
// Property 7: Autorización de canales
// Tag: Feature: delivery-tracking-realtime, Property 7: Channel authorization
test.prop([fc.record({ role: fc.constantFrom('worker', 'rider') })])(
  'non-admin users cannot subscribe to delivery.monitor',
  async ({ role }) => { /* ... */ }
);

// Property 8: Endpoint público no expone datos sensibles
// Tag: Feature: delivery-tracking-realtime, Property 8: Public endpoint safety
test.prop([fc.string()])(
  'tracking response never contains customer_phone',
  async (orderNumber) => { /* ... */ }
);
```

### Tests de integración

- Verificar que Reverb emite eventos correctamente en entorno de test
- Verificar que S3Manager sube el comprobante y retorna URL válida
- Verificar que el cron genera settlements correctamente en base de datos real de test

### Variables de entorno requeridas

```env
# mi3-frontend
NEXT_PUBLIC_GOOGLE_MAPS_KEY=...   # reutilizar la key de caja3
NEXT_PUBLIC_REVERB_APP_KEY=...    # ya configurado
NEXT_PUBLIC_REVERB_HOST=...       # ya configurado
```
