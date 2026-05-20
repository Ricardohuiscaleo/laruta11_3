# Skill: Sincronización app3 ↔ caja3

## Descripción
Especialista en detectar y reportar desincronización entre las aplicaciones app3 (clientes) y caja3 (cajeros).

## Cuándo usar
- Sospechar que apps han divergido
- Agregar feature en una app y necesitar replicar en la otra
- Cambiar config/API en una app
- Debug de comportamiento diferente entre apps
- Migrar componentes compartidos

## Contexto
app3 y caja3 son **contenedores Docker independientes** que comparten:
- Base de datos MySQL
- Algunos componentes React
- Patrones de API
- Configuración base

Pero NO comparten:
- Filesystem (nunca usar paths relativos cross-container)
- Código fuente (cada uno tiene su propio repo/deploy)

## Áreas de Comparación

### 1. Config Files (ALTA PRIORIDAD)
- `app3/public/config.php` vs `caja3/config.php` y `caja3/public/config.php`
- Variables de entorno: `$_ENV`, `getenv()`, `$_SERVER`
- DB connection patterns
- API keys y feature flags
- URLs hardcodeadas

### 2. Shared Components (ALTA PRIORIDAD)
| Componente | app3 | caja3 | Notas |
|------------|------|-------|-------|
| AddressAutocomplete.jsx | ✅ | ✅ | Delivery location |
| CheckoutApp.jsx | ✅ | ✅ | Checkout flow |
| MenuApp.jsx | ✅ | ✅ | Menu display |
| api.js | ✅ | ✅ | API client |
| ErrorBoundary.jsx | ✅ | ✅ | Error handling |
| LoadingScreen.jsx | ✅ | ✅ | Loading states |
| SyncButton.jsx | ✅ | ✅ | Sync indicator |
| OrderManagement.jsx | ✅ | ✅ | Order admin |
| OrderNotifications.jsx | ✅ | ✅ | Notifications |
| TUUPaymentGateway.jsx | ✅ | ✅ | TUU payments |
| ProductsManager.jsx | ✅ | ✅ | Product admin |

### 3. API Location Endpoints
| Endpoint | app3 | caja3 | Notas |
|----------|------|-------|-------|
| autocomplete_proxy.php | ✅ | ✅ | Address autocomplete |
| calculate_delivery_time.php | ✅ | ✅ | Delivery time |
| check_delivery_zone.php | ✅ | ❌ | Only app3 |
| geocode.php | ✅ | ✅ | Geocoding |
| get_delivery_fee.php | ✅ | ✅ | Delivery fee |
| get_location.php | ✅ | ✅ | Location data |
| get_nearby_products.php | ✅ | ❌ | Only app3 |
| save_location.php | ✅ | ✅ | Save location |

### 4. Core API Files
- `api/db_connect.php` — Database connection
- `api/session_config.php` — Session handling
- `api/create_order.php` — Order creation
- `api/get_productos.php` — Product fetching
- `api/get_combos.php` — Combo fetching
- `api/update_order_status.php` — Order status

### 5. Utility Files
- `src/utils/effects.js`
- `src/utils/validation.js`
- `src/lib/utils.js`

## Diferencias Esperadas (No sincronizar)
- caja3 tiene componentes POS específicos (ComprasApp, DashboardApp, InventarioApp)
- app3 tiene componentes cliente específicos (tracking, reviews)
- Session storage: caja3 usa `cajaUser`, app3 usa `user`
- Checkout: caja3 fields siempre editables, app3 pre-fills desde perfil
- Track usage: removido de caja3 (404 errors), activo en app3

## Reporte de Sincronización

### 🔴 Critical
- Missing config keys
- Broken API calls
- Different data formats
- Auth patterns diferentes

### 🟡 Warning
- Missing features
- Different UI behavior
- Outdated patterns
- Different error handling

### 🟢 Info
- App-specific features (esperado)
- POS vs Customer differences
- Intentional divergences

## Cómo Sincronizar
1. Identificar diferencia
2. Determinar si es intencional o bug
3. Copiar cambio de app origen a app destino
4. Adaptar si es necesario (session storage, auth, etc.)
5. Testear en ambas apps
6. Deployar

## Reglas
- Nunca usar paths relativos cross-container
- Si caja3 necesita funcionalidad de app3: replicar localmente o llamada HTTP
- Mantener consistencia en API responses
- Mismos environment variables en ambas apps
