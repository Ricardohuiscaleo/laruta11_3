# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-10)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado | Auto-deploy |
|-----|-----|-------|--------|-------------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running | ❌ Manual |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running | ❌ Manual |
| landing3 | laruta11.cl | Astro | ✅ Running | ❌ Manual |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React | ✅ Running | ❌ Manual |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 | ✅ Running | ❌ Manual |

Auto-deploy desactivado en todas las apps. Se usa Smart Deploy (hook) o hooks individuales.

### Coolify UUIDs

- app3: `egck4wwcg0ccc4osck4sw8ow`
- caja3: `xockcgsc8k000o8osw8o88ko`
- landing3: `dks4cg8s0wsswk08ocwggk0g`
- mi3-backend: `ds24j8jlaf9ov4flk1nq4jek`
- mi3-frontend: `sxdw43i9nt3cofrzxj28hx1e`
- laruta11-db: `zs00occ8kcks40w4c88ogo08`

---

## Sesión 2026-04-10/11 — Crédito R11 + mi3 RRHH

### Crédito R11 (COMPLETADO)

**Spec:** `.kiro/specs/credito-r11/`

**Lo implementado:**
1. Fixes de seguridad RL6 (validar credito_bloqueado, eliminar simulate, prevenir doble anulación, crédito negativo)
2. Schema BD migrado en producción (8 campos en usuarios, tabla r11_credit_transactions, campos en tuu_orders, migración personal)
3. 6 APIs app3: get_credit, use_credit, get_statement, register (QR+Redis+Telegram), create_payment, payment_callback
4. 5 APIs caja3: get_creditos, approve, register, refund, process_manual_payment
5. Frontend app3: r11.astro (registro QR + estado cuenta), pagar-credito-r11, payment pages, CheckoutApp r11_credit
6. Frontend caja3: CreditosR11App, integración ArqueoApp/ArqueoResumen/VentasDetalle/MiniComandas
7. Cron jobs: reminder día 28, block día 2
8. Webhook Telegram: approve/reject R11
9. QR scanner mejorado: captura RUN/serial/mrz/type completo, valida contra Registro Civil, guarda como JSON en carnet_qr_data

**Seguridad aplicada:**
- Autenticación session_token en app3, sesión admin en caja3
- CORS restringido a dominios reales
- Rate limiting con Redis para registro
- Validación amount > 0, protección doble anulación
- Sin simulate bypass en producción

### mi3 RRHH (EN PROGRESO)

**Spec:** `.kiro/specs/mi3-rrhh/`

**Arquitectura:** Next.js 14 frontend + Laravel 11 backend exclusivo

**Lo implementado:**
1. Scaffolding completo (Laravel + Next.js + Dockerfiles)
2. 12 modelos Eloquent + 3 migraciones (solicitudes_cambio_turno, notificaciones_mi3, categoría descuento_credito_r11)
3. 2 middleware (EnsureIsWorker, EnsureIsAdmin) + AuthService con Sanctum
4. 7 servicios de negocio: ShiftService (4x4), LiquidacionService, R11CreditService, NominaService, ShiftSwapService, NotificationService, GmailService
5. 14 controllers (7 Worker + 7 Admin) + 4 Form Requests
6. 2 cron commands (R11 auto-deduct día 1, reminder día 28) + SendLiquidacionEmailJob
7. Frontend: 15 páginas reales (8 worker + 7 admin) + login con Google OAuth
8. Apps creadas en Coolify con env vars configuradas
9. Google OAuth configurado (redirect URI agregado en Google Cloud Console)

**Pendiente mi3:**
- Ejecutar migraciones Laravel (solicitudes_cambio_turno, notificaciones_mi3, personal_access_tokens)
- Vincular trabajadores restantes (Camila, Neit, Andrés, Gabriel, Claudio, Dafne) con sus cuentas de usuarios
- Probar flujo completo de login con Google OAuth
- Probar las páginas del dashboard trabajador y admin con datos reales
- Configurar cron del scheduler de Laravel en el VPS

### Usuarios Admin en mi3

| Persona | usuario.id | personal.id | Rol | Acceso |
|---------|-----------|-------------|-----|--------|
| Ricardo | 4 | 5 | administrador,seguridad | ✅ Admin |
| Yojhans | 6 | 11 | dueño | ✅ Admin |

### BD — Campos R11 agregados

- `usuarios`: es_credito_r11, credito_r11_aprobado, limite_credito_r11, credito_r11_usado, credito_r11_bloqueado, fecha_aprobacion_r11, fecha_ultimo_pago_r11, relacion_r11, carnet_qr_data (JSON)
- `tuu_orders`: pagado_con_credito_r11, monto_credito_r11, payment_method ENUM incluye 'r11_credit'
- `r11_credit_transactions`: tabla nueva (id, user_id, amount, type, description, order_id, created_at)
- `personal`: user_id, rut, telefono agregados; 'rider' agregado al SET de rol
- `personal_access_tokens`: tabla Sanctum creada manualmente (requerida para auth tokens de mi3)

### Deploy — Reglas

- Auto-deploy DESACTIVADO en todas las apps
- Usar hook "Smart Deploy" para desplegar solo lo que cambió
- Hooks individuales disponibles: Deploy app3, Deploy caja3, Deploy mi3 Backend, Deploy mi3 Frontend
- Coolify API token: `3|S52ZUspC6N5G54apjgnKO6sY3VW5OixHlnY9GsMv8dc72ae8`
- Steering file: `.kiro/steering/coolify-infra.md`

### Lecciones Aprendidas

1. **Dockerfile Laravel**: No usar `composer create-project` sin `--no-scripts` — el post-install intenta migrar en producción y falla
2. **Next.js 14**: `useSearchParams()` debe estar dentro de `<Suspense>` boundary
3. **Coolify env vars**: No usar `is_build_time` en la API, solo `is_preview`
4. **Coolify .env**: Dejar `.env` vacío en el Dockerfile — Coolify inyecta env vars como variables de entorno del contenedor
5. **CORS en RL6**: Todos los endpoints tenían `Access-Control-Allow-Origin: *` — corregido a dominios específicos en R11
6. **Rate limiting**: Archivos temporales se pierden en cada deploy Docker — usar Redis
7. **Monorepo + Coolify**: Un push a main dispara rebuild de TODAS las apps — desactivar auto-deploy y usar Smart Deploy
8. **Laravel APP_KEY**: Debe configurarse como env var en Coolify — sin ella Laravel da 500 genérico
9. **Laravel bootstrap/app.php API-only**: Usar `redirectGuestsTo(fn () => null)` y `shouldRenderJsonWhen(fn () => true)` para evitar redirect a ruta `login` inexistente
10. **Google OAuth mi3**: Client ID `531902921465-1l4fa0esvcbhdlq4btejp7d1thdtj4a7`, redirect URI `https://api-mi3.laruta11.cl/api/v1/auth/google/callback`, origin `https://mi.laruta11.cl`
11. **ChileAtiende API**: Es para fichas de trámites gubernamentales, NO sirve para buscar nombres por RUT
12. **SII Chile**: Tiene nombre del contribuyente pero requiere captcha — no viable para automatización
13. **Coolify Docker cache**: Los builds pueden terminar "finished" pero servir código viejo si la imagen Docker está cacheada. Workaround: inyectar archivos vía SSH o agregar `ARG CACHE_BUST=$(date)` al Dockerfile
14. **Hotfix en contenedor Docker**: Se puede inyectar código directamente con `cat file | docker exec -i CONTAINER tee /path > /dev/null` + `php artisan route:clear`
15. **Sanctum personal_access_tokens**: Laravel Sanctum requiere la tabla `personal_access_tokens` en la BD para crear tokens. Si se usa una BD compartida existente (no creada por Laravel), hay que crear esta tabla manualmente. Sin ella, `createToken()` da 500 sin mensaje claro en el log

### Hooks Configurados

| Hook | Tipo | Acción |
|------|------|--------|
| Smart Deploy | userTriggered | Analiza git diff y despliega solo apps afectadas |
| Deploy app3 | userTriggered | Rebuild solo app3 |
| Deploy caja3 | userTriggered | Rebuild solo caja3 |
| Deploy mi3 Backend | userTriggered | Rebuild solo mi3-backend |
| Deploy mi3 Frontend | userTriggered | Rebuild solo mi3-frontend |
| Actualizar Bitácora | agentStop | Actualiza esta bitácora al final de cada sesión |
| Leer Contexto | promptSubmit | Lee bitácora al inicio de cada sesión |

### Commits de la Sesión (cronológico)

1. `fix(rl6): correcciones de seguridad`
2. `feat(r11): schema BD`
3. `feat(r11): APIs app3`
4. `feat(r11): APIs caja3`
5. `feat(r11): frontend app3`
6. `feat(r11): frontend caja3`
7. `feat(r11): cron jobs + webhook Telegram`
8. `docs(r11): spec actualizado con seguridad`
9. `docs(mi3): spec completo RRHH`
10. `feat(mi3): scaffolding Laravel + Next.js`
11. `feat(mi3): modelos Eloquent + middleware + auth`
12. `feat(mi3): servicios de negocio (7)`
13. `feat(mi3): controllers Worker + Admin (14)`
14. `feat(mi3): cron jobs R11`
15. `feat(mi3): frontend completo (15 páginas)`
16. `feat(r11): QR scanner mejorado + validación Registro Civil`
17. `fix(mi3): Dockerfile --no-scripts`
18. `fix(mi3): bootstrap API-only JSON 401`
19. `feat(mi3): Google OAuth completo`
20. `fix(mi3): login Suspense boundary`
21. `docs: bitácora + hooks`
22. `docs: bitácora actualizada con detalles finales`
23. `fix(mi3): Dockerfile key:generate + route:clear para rutas custom`

### Errores Encontrados y Resueltos (sesión continuación)

| Error | Causa | Solución |
|-------|-------|----------|
| `Route api/v1/auth/google/redirect could not be found` | Laravel no registra rutas custom porque el cache de rutas del `create-project` base persiste | Agregar `php artisan key:generate --force` y `php artisan route:clear` al Dockerfile después del COPY de código custom |
| mi3-frontend build fail: `useSearchParams() should be wrapped in suspense boundary` | Next.js 14 requiere Suspense para useSearchParams en pre-rendering | Separar LoginForm como componente interno, envolver en `<Suspense>` |
| mi3-backend 500 Server Error genérico | Faltaba APP_KEY como env var en Coolify | Generar key y agregarla vía API de Coolify |
| mi3-backend `Route [login] not defined` | Sanctum intenta redirigir a ruta `login` cuando no hay token | `redirectGuestsTo(fn () => null)` + `shouldRenderJsonWhen(fn () => true)` en bootstrap |
| Dockerfile `artisan migrate --graceful` falla en build | `APP_ENV=production` inyectado por Coolify causa que Laravel pida confirmación interactiva | `--no-scripts` en `composer create-project` y `composer install` |

### Estado del Deploy mi3-backend (último)

- Google OAuth redirect FUNCIONANDO: `https://api-mi3.laruta11.cl/api/v1/auth/google/redirect` → redirige a `accounts.google.com` correctamente
- Fix aplicado: Inyección directa de AuthController.php, AuthService.php y rutas Google OAuth en el contenedor vía SSH (hotfix)
- **PROBLEMA PERSISTENTE**: El Dockerfile de Coolify NO copia el código más reciente del repo. Los builds terminan "finished" pero el contenedor sigue con código viejo. Causa: Coolify cachea la imagen Docker agresivamente
- **Workaround actual**: Inyectar archivos directamente en el contenedor vía SSH (`docker exec -i ... tee`)
- **TODO**: Investigar cómo forzar rebuild sin cache en Coolify, o agregar un `ARG CACHE_BUST` al Dockerfile

### Estado Google OAuth (actual)

- Redirect (`/auth/google/redirect`) → ✅ Funciona, redirige a accounts.google.com
- Callback (`/auth/google/callback`) → ✅ Tabla `personal_access_tokens` creada, pendiente verificar flujo completo
- Login page (`mi.laruta11.cl/login`) → ✅ Diseño actualizado con branding mi3, botón Google conectado a API

### Errores Adicionales Resueltos (sesión final)

| Error | Causa | Solución |
|-------|-------|----------|
| Rutas Google OAuth no registradas en contenedor | Coolify cachea imagen Docker, no copia código nuevo | Inyección directa vía SSH: `cat file \| docker exec -i ... tee` |
| AuthController sin métodos googleRedirect/googleCallback | Mismo problema de cache — COPY del Dockerfile no actualiza | Inyección directa del AuthController.php y AuthService.php |
| `api.php` con `<?php` duplicado al hacer append | Error de scripting al inyectar rutas | Limpiar archivo con `head -75` antes de append |
| Google callback 500 Server Error | Tabla `personal_access_tokens` de Sanctum no existía en la BD | Crear tabla manualmente vía SSH: `CREATE TABLE personal_access_tokens (...)` |

---

## Sesión 2026-04-10 — Auditoría Sistema de Delivery

### Lo realizado: Auditoría completa de cálculos de delivery

Se revisaron todos los archivos involucrados en el sistema de delivery: fórmulas de distancia, tarifas dinámicas, APIs, y cómo se integran con la creación de órdenes.

**Archivos auditados (6 APIs PHP + 4 componentes React):**
- `app3/api/location/get_delivery_fee.php` — Cálculo principal de tarifa dinámica (Google Directions + Haversine fallback)
- `caja3/api/location/get_delivery_fee.php` — Copia idéntica para caja3
- `app3/api/get_delivery_fee.php` — Versión legacy, solo devuelve tarifa base de BD (sin distancia)
- `caja3/api/get_delivery_fee.php` — Copia legacy para caja3
- `app3/api/location/check_delivery_zone.php` — Verificación de zona por radio (tabla `delivery_zones`)
- `app3/api/location/calculate_delivery_time.php` — Cálculo de tiempo de entrega
- `app3/api/food_trucks/get_nearby.php` — Food trucks cercanos (Haversine)
- `app3/api/get_nearby_trucks.php` — Versión alternativa de trucks cercanos (PDO)
- `app3/api/create_order.php` — Creación de orden (consume delivery_fee del frontend)
- Componentes React: `CheckoutApp.jsx`, `MenuApp.jsx`, `AddressAutocomplete.jsx` (app3 y caja3)

### Algoritmo de tarifa documentado

**Fórmula Haversine (fallback):**
```
a = sin²(Δlat/2) + cos(lat₁) · cos(lat₂) · sin²(Δlng/2)
d = 2R · atan2(√a, √(1−a))    donde R = 6371 km
t = (d / 30) × 60 min          (asume 30 km/h en ciudad)
```

**Tarifa dinámica:**
```
tarifa_base = $3.500 (producción, configurable por food truck en BD campo tarifa_delivery)
si d ≤ 6 km → fee = tarifa_base
si d > 6 km → fee = tarifa_base + ⌈(d − 6) / 2⌉ × $1.000
```
Nota: el schema tiene default $2.000 pero en producción el food truck activo tiene $3.500.

**Tabla de tarifas resultante (con base real $3.500):**

| Distancia | Base | Surcharge | Total |
|-----------|------|-----------|-------|
| 3 km | $3.500 | $0 | $3.500 |
| 6 km | $3.500 | $0 | $3.500 |
| 7 km | $3.500 | $1.000 | $4.500 |
| 10 km | $3.500 | $2.000 | $5.500 |
| 15 km | $3.500 | $5.000 | $8.500 |

### Modificadores de tarifa: Convenio Ejército (RL6) y Recargo Tarjeta

**Valores reales de negocio (confirmados):**
- Delivery base: **$3.500**
- Convenio Ejército (RL6): **$2.500** (descuento de $1.000)
- Con tarjeta: **+$500** → ejército + tarjeta = **$3.000**

**Descuento Convenio Ejército (código "RL6"):**

Se activa con código `RL6` en app3 o checkbox "Descuento Delivery (28%)" en caja3. Solo aplica a 3 direcciones de cuarteles hardcodeadas:
- `Ctel. Oscar Quina 1333`
- `Ctel. Domeyco 1540`
- `Ctel. Av. Santa María 3000`

**Implementación en código:**

| Archivo | Factor | Resultado con $3.500 | ¿Correcto? |
|---------|--------|---------------------|------------|
| `app3/CheckoutApp.jsx` | `fee × 0.2857` (descuento) → paga `fee × 0.7143` | $2.500 | ✅ |
| `caja3/MenuApp.jsx` | `fee × 0.7143` | $2.500 | ✅ |
| `caja3/CheckoutApp.jsx` | `fee × 0.6` | $2.100 | ❌ BUG (debería ser $2.500) |

⚠️ **BUG en `caja3/CheckoutApp.jsx`**: Factor 0.6 da $2.100 en vez de $2.500. Debería usar `× 0.7143` como el resto.

**Recargo por pago con tarjeta:**
```
cardDeliverySurcharge = $500  (si delivery_type === 'delivery' && payment_method === 'card')
                      = $0    (cualquier otro caso)
```
Se suma al `delivery_fee` guardado en la orden. Se agrega nota: `"+$500 recargo tarjeta delivery"`.

**Fórmula completa del delivery:**
```
fee_base = $3.500 (producción, configurable en BD)
surcharge_distancia = d > 6 km ? ⌈(d − 6) / 2⌉ × $1.000 : $0
fee_bruto = fee_base + surcharge_distancia
descuento_rl6 = código "RL6" ? fee_bruto × 0.2857 : $0   (~28.57%, deja en ~71.43%)
recargo_tarjeta = delivery + tarjeta ? $500 : $0
TOTAL_DELIVERY = fee_bruto − descuento_rl6 + recargo_tarjeta
```

**Ejemplos reales (zona base ≤6 km):**

| Escenario | Cálculo | Total |
|-----------|---------|-------|
| Normal | $3.500 | $3.500 |
| Ejército (RL6) | $3.500 × 0.7143 | $2.500 |
| Normal + tarjeta | $3.500 + $500 | $4.000 |
| Ejército + tarjeta | $2.500 + $500 | $3.000 |

### Problemas encontrados (NO resueltos, pendientes de fix)

| # | Problema | Severidad | Detalle |
|---|---------|-----------|---------|
| 1 | Sin límite máximo de distancia | 🔴 Alta | Dirección en Santiago (2.000 km) generaría surcharge de ~$997.000. No hay validación de zona máxima |
| 2 | delivery_fee no se valida en backend | 🔴 Alta | `create_order.php` toma `$input['delivery_fee']` directo del frontend sin recalcular. Un usuario puede manipular el request y poner $0 |
| 3 | Factor descuento RL6 inconsistente | 🔴 Alta | `caja3/CheckoutApp.jsx` usa ×0.6 ($2.100), el resto usa ×0.7143 ($2.500). Debería dar $2.500 en todos |
| 4 | Archivos duplicados sin sincronía | 🟡 Media | 2 versiones de `get_delivery_fee.php` en cada app: `api/` (solo tarifa base) y `api/location/` (cálculo completo). Frontend usa ambos |
| 5 | `check_delivery_zone.php` desconectado | 🟡 Media | Tabla `delivery_zones` con radio 5 km existe pero `get_delivery_fee.php` no la consulta. Sistemas paralelos |
| 6 | CORS abierto en delivery APIs | 🟡 Media | `get_delivery_fee.php` tiene `Access-Control-Allow-Origin: *` en ambas versiones |
| 7 | Prep time aleatorio | 🟢 Baja | `calculate_delivery_time.php` usa `rand(10, 15)` — resultados inconsistentes entre llamadas |

### Lecciones Aprendidas (sesión delivery)

13. **Delivery fee sin validación server-side**: El frontend calcula la tarifa y la envía al backend, pero `create_order.php` la acepta sin recalcular — vulnerabilidad de manipulación de precio
14. **Archivos legacy vs dinámicos**: Existen 2 versiones de `get_delivery_fee.php` (raíz = estático, location/ = dinámico). El frontend carga ambos: el estático como fallback inicial y el dinámico al ingresar dirección
15. **Zonas de delivery desacopladas**: La tabla `delivery_zones` y el cálculo de tarifa en `get_delivery_fee.php` son sistemas independientes que no se comunican entre sí
16. **Descuento RL6 con factores distintos**: El descuento del convenio ejército se implementó con factores diferentes en cada componente (0.2857 vs 0.6 vs 0.7143) — necesita unificarse a un solo valor
17. **Recargo tarjeta se suma al delivery_fee**: El +$500 por tarjeta no se guarda en campo separado, se suma al `delivery_fee` de la orden, lo que dificulta auditoría posterior

### Pendiente — Fixes de Delivery (por prioridad)

1. **[CRÍTICO]** Agregar límite máximo de distancia (~15-20 km) en `get_delivery_fee.php` y rechazar direcciones fuera de rango
2. **[CRÍTICO]** Recalcular delivery_fee en `create_order.php` server-side en vez de confiar en el valor del frontend
3. **[CRÍTICO]** Unificar factor descuento RL6 en `caja3/CheckoutApp.jsx` (cambiar ×0.6 a ×0.7143 o definir cuál es el correcto)
4. **[MEDIO]** Unificar `get_delivery_fee.php` — eliminar versión legacy de `api/` o redirigir a `api/location/`
5. **[MEDIO]** Integrar `delivery_zones` con el cálculo de tarifa, o eliminar la tabla si no se usa
6. **[MEDIO]** Restringir CORS en APIs de delivery a dominios reales (`app.laruta11.cl`, `caja.laruta11.cl`)
7. **[BAJO]** Fijar prep time a valor constante o basado en cantidad de items en vez de `rand()`
8. **[BAJO]** Separar recargo tarjeta en campo propio en `tuu_orders` para mejor trazabilidad

### Pendiente — General (acumulado)

**mi3 RRHH:**
- Ejecutar migraciones Laravel
- Vincular trabajadores restantes
- Probar flujo Google OAuth completo
- Probar dashboard con datos reales
- Configurar cron scheduler en VPS

**Delivery:**
- Aplicar los 8 fixes listados arriba

---

## Sesión 2026-04-10 (cont.) — Verificación Schema BD vs Producción

### Lo realizado

Verificación por SSH contra la BD real en producción (`laruta11` en `76.13.126.63`). Se comparó cada tabla documentada en `database-schema.md` con la estructura real usando `DESCRIBE` por SSH.

### Hallazgos principales

**1. Tarifa delivery confirmada:** `food_trucks` tiene `tarifa_delivery = 3500` en producción (id=4, "La Ruta 11", activo=1). El schema decía default 2000 (correcto como default de columna, pero el valor real es 3500).

**2. 26 tablas no documentadas encontradas:**
- RRHH/Nómina: `personal`, `turnos`, `pagos_nomina`, `presupuesto_nomina`, `ajustes_sueldo`, `ajustes_categorias`
- TV Orders: `tv_orders`, `tv_order_items`
- POS: `tuu_pos_transactions`
- Combos: `combo_selections`
- Usuarios: `app_users` (legacy)
- Concurso: `concurso_registros`, `concurso_state`, `concurso_tracking`, `participant_likes`
- Chat/Live: `chat_messages`, `live_viewers`, `youtube_live`
- Otros: `checklist_templates`, `product_edit_requests`, `attempts`, `user_locations`, `user_journey`, `menu_categories`, `menu_subcategories`, `inventory_transactions_backup_20251110`

**3. Campos faltantes en tablas documentadas:**
- `usuarios`: faltaban 14 campos (instagram, lugar_nacimiento, nacionalidad, direccion_actual, ubicacion_actualizada, total_sessions, total_time_seconds, last_session_duration, kanban_status, notification fields, credito_disponible, fecha_aprobacion_credito) + todos los campos R11
- `tuu_orders`: faltaban `delivery_distance_km`, `delivery_duration_min`, `dispatch_photo_url`, `tv_order_id`, `pagado_con_credito_r11`, `monto_credito_r11`, y `delivery_type` no incluía 'tv'
- `products`: faltaban `is_featured`, `sale_price`

**4. Conteo real:** 65 tablas (no "80+" como decía el doc)

### Cambios aplicados al schema

- Actualizado `database-schema.md` con todos los campos faltantes
- Agregadas las 26 tablas no documentadas con estructura completa
- Corregido conteo de tablas a 65
- Marcado `tarifa_delivery` con nota de valor en producción ($3.500)
- Agregado `delivery_type` enum incluye 'tv'
- Agregados campos R11 en `usuarios`

### Lecciones Aprendidas

18. **Schema drift**: El schema documentado tenía 26 tablas sin documentar y ~20 campos faltantes en tablas existentes. Verificar contra producción periódicamente.
19. **Valor vs default**: `tarifa_delivery` tiene default 2000 en el schema de la columna, pero el registro activo en producción tiene 3500. Documentar ambos valores.
