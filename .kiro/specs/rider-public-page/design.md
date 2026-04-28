# Documento de Diseño — Página Pública del Rider

## Resumen

Esta feature implementa una página pública (sin autenticación) en `mi.laruta11.cl/rider/{order_id}` que permite al rider de delivery:
- Ver los datos completos del pedido (cliente, productos, dirección, montos)
- Ver un mapa con la ruta desde el food truck hasta la dirección del cliente
- Cambiar el estado del pedido ("En camino" → "Entregado")
- Compartir su GPS en tiempo real para que el admin lo vea en `/admin/delivery`

Se crean 3 endpoints públicos en el backend Laravel (sin auth) y una nueva página Next.js con ruta dinámica `[orderId]`.

## Arquitectura

```mermaid
flowchart TB
    subgraph Frontend["Next.js (mi.laruta11.cl)"]
        Page["/rider/[orderId]/page.tsx"]
        Hook["usePublicRiderGPS hook"]
        Map["Google Maps + Directions"]
    end

    subgraph Backend["Laravel (api-mi3.laruta11.cl)"]
        PRC["PublicRiderController"]
        LS["LocationService (existente)"]
        DS["DeliveryService (existente)"]
        Events["RiderLocationUpdated / OrderStatusUpdated"]
    end

    subgraph DB["MySQL"]
        Orders["tuu_orders"]
        Items["tuu_order_items"]
        Assignments["delivery_assignments"]
        Locations["rider_locations"]
        FoodTrucks["food_trucks"]
    end

    subgraph Admin["Admin Dashboard"]
        Monitor["/admin/delivery (mapa)"]
    end

    Page -->|GET /public/rider-orders/{id}| PRC
    Page -->|PATCH /public/rider-orders/{id}/status| PRC
    Hook -->|POST /public/rider-orders/{id}/location| PRC
    PRC --> LS
    PRC --> DS
    PRC --> Orders
    PRC --> Items
    PRC --> FoodTrucks
    LS --> Locations
    LS --> Events
    DS --> Events
    Events -->|Reverb WebSocket| Monitor
```

### Flujo Principal

1. Cajera despacha pedido → se genera link `mi.laruta11.cl/rider/{order_id}`
2. Rider abre el link en su celular (sin login)
3. Página carga datos del pedido vía `GET /api/v1/public/rider-orders/{orderId}`
4. Rider ve datos, mapa con ruta, y botón "🛵 En camino"
5. Rider toca "En camino" → `PATCH status=out_for_delivery` + inicia GPS
6. GPS se envía cada 10s vía `POST location` → admin ve movimiento en mapa
7. Rider llega y toca "✅ Entregado" → `PATCH status=delivered` + detiene GPS
8. Página muestra confirmación de entrega

## Componentes e Interfaces

### Backend — PublicRiderController

Nuevo controlador en `App\Http\Controllers\Public\PublicRiderController` con 3 métodos:

```php
class PublicRiderController extends Controller
{
    // GET /api/v1/public/rider-orders/{orderId}
    public function show(int $orderId): JsonResponse;

    // PATCH /api/v1/public/rider-orders/{orderId}/status
    public function updateStatus(Request $request, int $orderId): JsonResponse;

    // POST /api/v1/public/rider-orders/{orderId}/location
    public function updateLocation(Request $request, int $orderId): JsonResponse;
}
```

#### Rutas (en `routes/api.php`)

```php
Route::prefix('v1/public/rider-orders')->group(function () {
    Route::get('{orderId}', [PublicRiderController::class, 'show']);
    Route::patch('{orderId}/status', [PublicRiderController::class, 'updateStatus']);
    Route::post('{orderId}/location', [PublicRiderController::class, 'updateLocation']);
});
```

#### Respuesta de `show()`

```json
{
  "success": true,
  "order": {
    "id": 123,
    "order_number": "RL-00123",
    "order_status": "ready",
    "customer_name": "Juan Pérez",
    "customer_phone": "+56912345678",
    "delivery_address": "Av. Providencia 1234, Santiago",
    "delivery_fee": 2500,
    "card_surcharge": 0,
    "subtotal": 8500,
    "product_price": 11000,
    "payment_method": "cash",
    "delivery_distance_km": 3.2,
    "delivery_duration_min": 15,
    "rider_last_lat": -33.4489,
    "rider_last_lng": -70.6693,
    "items": [
      { "product_name": "Hamburguesa Clásica", "quantity": 2, "product_price": 4250 }
    ],
    "food_truck": {
      "latitud": -33.4372,
      "longitud": -70.6506
    }
  }
}
```

#### Lógica de `updateStatus()`

```php
// Validar status ∈ {out_for_delivery, delivered}
// Actualizar order_status en tuu_orders
// Si delivered: actualizar delivered_at en delivery_assignments (si existe asignación activa)
// Emitir OrderStatusUpdated por canales delivery.monitor y order.{order_number}
```

#### Lógica de `updateLocation()`

```php
// Validar lat ∈ [-90,90], lng ∈ [-180,180]
// Actualizar rider_last_lat/lng en tuu_orders
// Si order tiene rider_id:
//   - Persistir en rider_locations vía LocationService
//   - Emitir RiderLocationUpdated
// Si NO tiene rider_id:
//   - Solo actualizar tuu_orders (sin persistir en rider_locations)
```

### Frontend — Estructura de Archivos

```
mi3/frontend/
├── app/rider/[orderId]/
│   └── page.tsx              # Página pública (Server Component wrapper)
├── components/rider/
│   └── PublicRiderView.tsx    # Client Component principal
└── hooks/
    └── usePublicRiderGPS.ts   # Hook GPS sin auth
```

#### `page.tsx` — Server Component

```typescript
// app/rider/[orderId]/page.tsx
interface Props { params: { orderId: string } }
export default function PublicRiderPage({ params }: Props) {
  return <PublicRiderView orderId={params.orderId} />;
}
```

#### `PublicRiderView.tsx` — Client Component

Responsabilidades:
- Fetch datos del pedido al montar (`GET /api/v1/public/rider-orders/{orderId}`)
- Renderizar datos del pedido (info, productos, montos)
- Renderizar mapa con ruta (Google Maps Directions)
- Manejar botones de acción (En camino / Entregado)
- Integrar `usePublicRiderGPS` para envío de ubicación

Estados del componente:
- `loading` → Cargando datos del pedido
- `error` → Pedido no encontrado (404)
- `ready/preparing` → Muestra datos + botón "En camino"
- `out_for_delivery` → Muestra datos + botón "Entregado" + GPS activo
- `delivered` → Muestra confirmación de entrega

#### `usePublicRiderGPS` — Hook

Similar a `useRiderGPS` existente pero:
- No usa `apiFetch` (que requiere auth) sino `fetch` directo al endpoint público
- Recibe `orderId` como parámetro para construir la URL
- Intervalo de envío: 10 segundos (vs 15s del hook autenticado)
- Se activa/desactiva externamente (no toggle interno)

```typescript
interface UsePublicRiderGPSOptions {
  orderId: string;
  enabled: boolean; // true cuando status = out_for_delivery
}

function usePublicRiderGPS({ orderId, enabled }: UsePublicRiderGPSOptions): {
  position: GeoPosition | null;
  gpsError: string | null;
}
```

### Modificación al Admin — rider_url en DeliveryController

El `DeliveryController::index()` ya retorna pedidos activos. Se agrega el campo `rider_url` a cada pedido:

```php
// En DeliveryService::getActiveOrders(), después del ->get():
$orders->transform(fn ($o) => tap($o, fn ($o) => $o->rider_url = "https://mi.laruta11.cl/rider/{$o->id}"));
```

### Modificación al Admin — QR y Link en OrderPanel

En `OrderPanel.tsx` (componente existente del admin), agregar para cada pedido:
- Botón "Copiar link rider" que copia `rider_url` al portapapeles
- Botón "QR" que genera un código QR del link usando `qrcode.react`

## Modelos de Datos

### Tablas Existentes (sin cambios de schema)

**tuu_orders** — Campos relevantes:
| Campo | Tipo | Uso |
|-------|------|-----|
| id | int (PK) | Usado como `orderId` en la URL |
| order_number | varchar | Número visible del pedido |
| order_status | varchar | Estado: ready → out_for_delivery → delivered |
| delivery_type | varchar | Filtro: solo 'delivery' |
| customer_name | varchar | Nombre del cliente |
| customer_phone | varchar | Teléfono del cliente |
| delivery_address | varchar | Dirección de entrega |
| delivery_fee | decimal | Costo del delivery |
| card_surcharge | decimal | Recargo por tarjeta |
| subtotal | decimal | Subtotal sin delivery |
| product_price | decimal | Total del pedido |
| payment_method | varchar | Método de pago |
| delivery_distance_km | decimal | Distancia estimada |
| delivery_duration_min | int | Tiempo estimado |
| rider_id | int (FK) | Rider asignado (nullable) |
| rider_last_lat | decimal | Última latitud del rider |
| rider_last_lng | decimal | Última longitud del rider |

**tuu_order_items** — Items del pedido:
| Campo | Tipo |
|-------|------|
| order_id | int (FK) |
| product_name | varchar |
| quantity | int |
| product_price | decimal |

**food_trucks** — Ubicación del food truck:
| Campo | Tipo |
|-------|------|
| latitud | decimal |
| longitud | decimal |

**delivery_assignments** — Asignaciones rider-pedido:
| Campo | Tipo |
|-------|------|
| order_id | int (FK) |
| rider_id | int (FK) |
| status | varchar |
| delivered_at | datetime (nullable) |

**rider_locations** — Historial GPS:
| Campo | Tipo |
|-------|------|
| rider_id | int (FK) |
| latitud | decimal |
| longitud | decimal |

### Interfaces TypeScript

```typescript
interface PublicOrderData {
  id: number;
  order_number: string;
  order_status: string;
  customer_name: string;
  customer_phone: string;
  delivery_address: string;
  delivery_fee: number;
  card_surcharge: number;
  subtotal: number;
  product_price: number;
  payment_method: string;
  delivery_distance_km: number | null;
  delivery_duration_min: number | null;
  rider_last_lat: number | null;
  rider_last_lng: number | null;
  items: OrderItem[];
  food_truck: { latitud: number; longitud: number } | null;
}

interface OrderItem {
  product_name: string;
  quantity: number;
  product_price: number;
}
```

## Propiedades de Correctitud

*Una propiedad es una característica o comportamiento que debe mantenerse verdadero en todas las ejecuciones válidas de un sistema — esencialmente, una declaración formal sobre lo que el sistema debe hacer. Las propiedades sirven como puente entre especificaciones legibles por humanos y garantías de correctitud verificables por máquina.*

### Propiedad 1: GET retorna datos completos para pedidos delivery válidos

*Para cualquier* pedido válido de tipo delivery en la base de datos, el endpoint `GET /api/v1/public/rider-orders/{orderId}` SHALL retornar todos los campos requeridos (`order_number`, `customer_name`, `customer_phone`, `delivery_address`, `delivery_fee`, `card_surcharge`, `subtotal`, `product_price`, `payment_method`, `items`, `food_truck`) con valores que coincidan con los datos almacenados en la base de datos.

**Valida: Requerimientos 1.1, 1.2, 1.4, 1.5**

### Propiedad 2: Todos los endpoints retornan 404 para pedidos inválidos o no-delivery

*Para cualquier* `orderId` que no exista en `tuu_orders` o que corresponda a un pedido con `delivery_type` distinto de `delivery`, los tres endpoints públicos (GET, PATCH status, POST location) SHALL retornar HTTP 404.

**Valida: Requerimientos 1.3, 2.5, 3.6**

### Propiedad 3: PATCH status actualiza orden y emite eventos correctamente

*Para cualquier* pedido delivery válido y status válido (`out_for_delivery` o `delivered`), el endpoint PATCH SHALL actualizar `order_status` en `tuu_orders`, actualizar `delivered_at` en `delivery_assignments` cuando el status es `delivered` y existe asignación activa, y emitir `OrderStatusUpdated` por los canales `delivery.monitor` y `order.{order_number}`.

**Valida: Requerimientos 2.1, 2.2, 2.3**

### Propiedad 4: PATCH rechaza status inválidos

*Para cualquier* string que NO sea `out_for_delivery` ni `delivered`, el endpoint PATCH status SHALL retornar HTTP 422 sin modificar el estado del pedido en la base de datos.

**Valida: Requerimiento 2.4**

### Propiedad 5: POST location actualiza coordenadas y persiste condicionalmente

*Para cualquier* pedido delivery válido y coordenadas válidas (lat ∈ [-90,90], lng ∈ [-180,180]), el endpoint POST location SHALL actualizar `rider_last_lat` y `rider_last_lng` en `tuu_orders`. Adicionalmente, si el pedido tiene `rider_id` asignado, SHALL persistir en `rider_locations` y emitir `RiderLocationUpdated`; si no tiene `rider_id`, SHALL solo actualizar `tuu_orders`.

**Valida: Requerimientos 3.1, 3.2, 3.3, 3.4**

### Propiedad 6: POST location rechaza coordenadas fuera de rango

*Para cualquier* latitud fuera de [-90, 90] o longitud fuera de [-180, 180], el endpoint POST location SHALL retornar HTTP 422 sin modificar datos en la base de datos.

**Valida: Requerimiento 3.5**

### Propiedad 7: Botón de acción correcto según estado del pedido

*Para cualquier* estado de pedido, la página SHALL renderizar el botón correcto: "En camino" cuando el estado es `ready` o `preparing`, "Entregado" cuando el estado es `out_for_delivery`, y ningún botón de acción cuando el estado es `delivered`.

**Valida: Requerimientos 6.1, 6.2, 6.7**

### Propiedad 8: rider_url sigue formato correcto

*Para cualquier* pedido delivery activo, el campo `rider_url` retornado por la API admin SHALL seguir el formato `https://mi.laruta11.cl/rider/{order_id}` donde `order_id` es el ID numérico del pedido.

**Valida: Requerimientos 8.1, 8.3**

## Manejo de Errores

| Escenario | Comportamiento |
|-----------|---------------|
| `orderId` no existe o no es delivery | HTTP 404 + `{ success: false, error: "Pedido no encontrado" }` |
| Status inválido en PATCH | HTTP 422 + `{ success: false, error: "Status inválido..." }` |
| Coordenadas fuera de rango | HTTP 422 + error de validación Laravel |
| GPS denegado por el navegador | Muestra warning pero permite cambiar estado |
| Fallo en API al cambiar estado | Muestra toast de error, botón queda habilitado para reintentar |
| Fallo en envío de GPS | Silencioso — reintenta en el siguiente intervalo (10s) |
| Food truck sin ubicación configurada | `food_truck: null` en respuesta, mapa muestra solo destino |

## Estrategia de Testing

### Tests Unitarios (Backend — PHPUnit)

- **PublicRiderController::show()** — Verificar respuesta completa, 404 para inválidos
- **PublicRiderController::updateStatus()** — Verificar transiciones válidas, 422 para inválidos, emisión de eventos
- **PublicRiderController::updateLocation()** — Verificar persistencia condicional, validación de coordenadas

### Tests de Propiedades (Backend — PHPUnit + Faker)

Cada propiedad de correctitud se implementa como un test con mínimo 100 iteraciones usando data providers con `Faker` para generar inputs aleatorios:

- Generar pedidos aleatorios (delivery y no-delivery) para validar Propiedades 1, 2
- Generar status aleatorios (válidos e inválidos) para validar Propiedades 3, 4
- Generar coordenadas aleatorias (válidas e inválidas) para validar Propiedades 5, 6

Tag format: `Feature: rider-public-page, Property {N}: {título}`

### Tests de Propiedades (Frontend — fast-check)

- Propiedad 7 (botón correcto por estado) se implementa con `fast-check` generando estados aleatorios y verificando el render
- Propiedad 8 (formato rider_url) se implementa con `fast-check` generando IDs numéricos aleatorios

### Tests de Integración (Frontend — Jest/Testing Library)

- Renderizar `PublicRiderView` con datos mock y verificar elementos del DOM
- Verificar link `tel:` para teléfono del cliente
- Verificar link Google Maps para dirección
- Verificar flujo de botones: En camino → Entregado → Confirmación
- Verificar estados de error y loading
