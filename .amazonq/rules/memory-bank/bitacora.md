# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-11, actualizado sesión 2026-04-11)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado | Auto-deploy |
|-----|-----|-------|--------|-------------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running | ❌ Manual |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running | ❌ Manual |
| landing3 | laruta11.cl | Astro | ✅ Running | ❌ Manual |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React | ⚠️ Rebuild manual | ❌ Manual |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 | ✅ Running (hotfix) | ❌ Manual |

Auto-deploy desactivado en todas las apps. Se usa Smart Deploy (hook) o hooks individuales.

### Coolify UUIDs

- app3: `egck4wwcg0ccc4osck4sw8ow`
- caja3: `xockcgsc8k000o8osw8o88ko`
- landing3: `dks4cg8s0wsswk08ocwggk0g`
- mi3-backend: `ds24j8jlaf9ov4flk1nq4jek`
- mi3-frontend: `sxdw43i9nt3cofrzxj28hx1e`
- laruta11-db: `zs00occ8kcks40w4c88ogo08`

---

## Sesión 2026-04-11 — Búsqueda de Imagen en S3 y VPS

### Lo realizado: Búsqueda exhaustiva de imagen `17758800463833968304311611655643.jpg`

Se buscó la imagen `17758800463833968304311611655643.jpg` en todos los sistemas disponibles: AWS S3 y disco del VPS vía SSH.

**Búsqueda en S3 (bucket `laruta11-images`):**
- Se usó Python con AWS Signature V4 (AWS CLI no instalado en el Mac) para autenticar requests
- Se listaron las 8 carpetas existentes en el bucket: `carnets-militares/`, `checklist/`, `compras/`, `despacho/`, `menu/`, `products/`, `qr-codes/`, `vehiculos/`
- Se verificó con `HEAD` request autenticado en cada carpeta + raíz del bucket
- Resultado: ❌ 404 en todas las ubicaciones — la imagen NO existe en S3

**Búsqueda en VPS (`76.13.126.63`) vía SSH:**
- Se buscó dentro de los contenedores Docker de app3 (`egck4wwcg0ccc4osck4sw8ow`) y caja3 (`xockcgsc8k000o8osw8o88ko`)
- Se buscó en volúmenes Docker (`/var/lib/docker/volumes/`)
- Se buscó en `/root` y `/data` del host
- Resultado: ❌ No encontrada en ningún directorio del VPS

**Búsqueda en Base de Datos:**
- Se consultaron TODAS las tablas con columnas de imagen/URL: `checklist_items.photo_url`, `compras.imagen_respaldo`, `tuu_orders.dispatch_photo_url`, `products.image_url`, `categories.image_url`, `combos.image_url`, `usuarios.carnet_frontal_url`, `usuarios.carnet_trasero_url`, `usuarios.selfie_url`, `usuarios.foto_perfil`, `concurso_registros.image_url`
- Se buscó con patrón parcial `%1775880%` para cubrir variaciones
- Resultado: ❌ Sin coincidencias — la imagen no está referenciada en la BD

**Análisis del nombre del archivo:**
- El nombre `17758800463833968304311611655643` es un número largo sin estructura — patrón típico de nombre generado por cámara de celular Android
- No coincide con los patrones del sistema: `pedido_{id}_{timestamp}.jpg` (despacho), `respaldo_{id}_{timestamp}.jpg` (compras), etc.
- Conclusión: la imagen nunca fue subida al sistema, o fue generada por un dispositivo pero no llegó a guardarse

### Estructura del bucket S3 documentada

```
laruta11-images/
├── carnets-militares/    # Fotos de carnets militares (registro R11)
├── checklist/            # Fotos de checklist operativo
├── compras/              # Respaldos de compras (boletas/facturas)
├── despacho/             # Fotos de despacho de pedidos
├── menu/                 # Imágenes del menú
├── products/             # Imágenes de productos
├── qr-codes/             # Códigos QR generados
├── vehiculos/            # Fotos de vehículos
└── test-aws-api.txt      # Archivo de prueba (34 bytes)
```

### Lecciones Aprendidas

27. **AWS CLI no instalado en Mac local**: Se puede usar Python con `urllib.request` + AWS Signature V4 como alternativa completa para operaciones S3 (HEAD, GET, LIST). No requiere instalar nada adicional
28. **S3 devuelve 403 (no 404) sin credenciales**: Cuando el bucket no tiene acceso público, S3 responde 403 Forbidden tanto para objetos inexistentes como para acceso denegado. Siempre usar requests autenticados para distinguir 404 real
29. **Patrones de nombres en S3**: Todas las imágenes del sistema siguen convención `{carpeta}/{tipo}_{id}_{timestamp}.jpg`. Nombres largos numéricos sin estructura son generados por cámaras de celular y no pertenecen al sistema

### Pendiente — General (actualizado)

**Imagen buscada:**
- `17758800463833968304311611655643.jpg` NO existe en S3, VPS ni BD. Verificar origen con el usuario (¿de qué dispositivo/app viene?)

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
16. **Next.js middleware vs localStorage**: El middleware de Next.js corre en el edge (server-side) y NO tiene acceso a localStorage. Para auth, guardar el token también como cookie (`document.cookie`) que sí es accesible desde el middleware
17. **Next.js cache en producción**: `x-nextjs-cache: HIT` significa que la página está sirviendo una versión cacheada del build anterior. Cambios en el código de la página requieren un redeploy para que tomen efecto
18. **router.push vs window.location.href**: En Next.js, `router.push` hace client-side navigation que NO envía cookies recién seteadas. Usar `window.location.href` para hard redirect que sí las incluye
19. **Sanctum token con pipe `|`**: El token Sanctum tiene formato `id|hash`. El `|` debe ser `encodeURIComponent`-eado al guardarlo en cookies
20. **Test Sanctum via SSH**: Se puede crear tokens de prueba con `php artisan tinker --execute` y testear endpoints con curl directamente — útil para aislar problemas frontend vs backend
21. **OAuth token en URL es un parche, no best practice**: El token Sanctum no debe viajar en query params (visible en historial). La forma correcta es que el backend setee una cookie httpOnly en el redirect del callback. Esto elimina problemas de SameSite, XSS, y manipulación de cookies
22. **PHP Error vs Exception**: `new Redis()` cuando la extensión no está instalada lanza `Error` (no `Exception`). `catch (Exception)` NO lo atrapa. Usar `catch (\Throwable)` o verificar `class_exists()` antes. Esto aplica a cualquier clase de extensión PHP opcional (Redis, Imagick, etc.)
23. **mi3 login funciona**: Google OAuth → admin dashboard OK. Nómina y cambios muestran "load failed" (esperado, backend necesita datos reales). Login page con branding mi3 🍔
24. **Redis en app3**: La extensión PHP Redis NO viene instalada en el contenedor de app3. Se instaló manualmente con `pecl install redis` + `echo extension=redis.so > /usr/local/etc/php/conf.d/redis.ini`. Se pierde en cada redeploy — agregar al Dockerfile para persistir
25. **Redis password incorrecta**: El `.env` de app3/caja3 tiene `REDIS_PASSWORD=c75556ac0f0f27e7da0f` pero la contraseña real de coolify-redis es `kEfdMKJoEvNTkqFWhEC4hHM3otMA1W/xm/NiDsVBR0I=`. Actualizar en Coolify dashboard (la API no soporta PATCH de envs individuales)
26. **Redis host**: app3 se conecta a `coolify-redis` (nombre del contenedor Docker). Funciona porque están en la misma red Docker

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

- Redirect (`/auth/google/redirect`) → ✅ Funciona
- Callback (`/auth/google/callback`) → ✅ Funciona, genera token Sanctum
- Backend Sanctum → ✅ 100% funcional (verificado via SSH con tinker + curl)
- Login flow end-to-end → ⚠️ Funciona pero con PARCHE (token en URL, cookies client-side)
- **DEUDA TÉCNICA RESUELTA**: OAuth implementado correctamente con httpOnly cookies:
  1. Backend `googleCallback` setea 3 cookies httpOnly (`mi3_token`, `mi3_role`, `mi3_user`) via `Set-Cookie` header en el redirect
  2. Backend `ExtractTokenFromCookie` middleware lee `mi3_token` de cookie y lo inyecta como `Authorization: Bearer` header
  3. Backend `login` endpoint también setea cookies httpOnly en la respuesta JSON
  4. Backend `logout` limpia las 3 cookies
  5. Frontend `api.ts` usa `credentials: 'include'` en todas las requests
  6. Frontend login page ya NO lee token de URL — solo muestra errores de OAuth
  7. Middleware Next.js lee cookies httpOnly directamente (seteadas por backend cross-domain via `.laruta11.cl`)
  8. Token NUNCA viaja en la URL ni es accesible por JavaScript
- **Deploy**: Backend hotfixed via SSH, frontend deploy disparado (`od4ljeanrj417khq6dpxged6`)
- **Pendiente**: Verificar flujo completo después del deploy del frontend. Limpiar cookies del navegador antes de probar

### Errores Adicionales Resueltos (sesión final)

| Error | Causa | Solución |
|-------|-------|----------|
| Rutas Google OAuth no registradas en contenedor | Coolify cachea imagen Docker, no copia código nuevo | Inyección directa vía SSH: `cat file \| docker exec -i ... tee` |
| AuthController sin métodos googleRedirect/googleCallback | Mismo problema de cache — COPY del Dockerfile no actualiza | Inyección directa del AuthController.php y AuthService.php |
| `api.php` con `<?php` duplicado al hacer append | Error de scripting al inyectar rutas | Limpiar archivo con `head -75` antes de append |
| Google callback 500 Server Error | Tabla `personal_access_tokens` de Sanctum no existía en la BD | Crear tabla manualmente vía SSH: `CREATE TABLE personal_access_tokens (...)` |
| Login → `/admin` redirect loop | Middleware Next.js lee cookies pero login page cacheada no las setea (build viejo) | Redeploy mi3-frontend para que tome código con `document.cookie` |
| Cookies no se setean tras OAuth redirect | `document.cookie` con `SameSite=Lax` + `router.push` no persiste cookies en redirect cross-site (Google→api→frontend) | Usar `window.location.href` (hard redirect) + `encodeURIComponent` en token + middleware permisivo que no bloquea page loads |
| R11 register.php 500 silencioso | `new Redis()` causa fatal `Error` (class not found) en app3 — extensión Redis no instalada. `catch (Exception)` no atrapa `Error` de PHP | Agregar `class_exists('Redis')` antes de instanciar + cambiar `catch (Exception)` a `catch (\Throwable)`. Fail-open si Redis no disponible |

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
- Implementar OAuth con httpOnly cookies (deuda técnica prioritaria) — IMPLEMENTADO, pendiente verificar en producción
- Ejecutar migraciones Laravel
- Vincular trabajadores restantes
- Probar dashboard con datos reales (nómina, turnos, etc.)
- Configurar cron scheduler en VPS
- Resolver problema de cache Docker en Coolify (builds no toman código nuevo)

**R11 Crédito / Onboarding:**
- Verificar que Camila pueda registrarse después del fix de Redis
- Vincular trabajadores que se registren vía /r11 con tabla personal
- Después de aprobación Telegram: auto-vincular en `personal` + enviar link a mi.laruta11.cl
- Página /r11 rediseñada como onboarding (no solo crédito) — pendiente deploy app3

**Flujo onboarding trabajador (definido):**
1. Trabajador entra a app.laruta11.cl/r11 → se registra (QR + selfie + rol)
2. Admin recibe notificación Telegram → aprueba
3. Sistema vincula automáticamente en tabla `personal` + envía link a mi.laruta11.cl
4. Trabajador entra a mi.laruta11.cl con su cuenta (Google o email) → ve dashboard

**Test de flujo completo (2026-04-11):**
- Usuario test creado: id=163, email=info@digitalizatodo.cl, password=`password`
- Registro R11 exitoso vía API: selfie subida a S3, datos guardados en BD
- Vinculado en personal: id=14, rol=cajero, user_id=163, activo=1
- Telegram: notificación enviada ✅ (confirmado por usuario)
- Email aprobación: enviado ✅ (confirmado por usuario — pero decía "Crédito R11" en vez de onboarding)
- mi3 login: disponible en mi.laruta11.cl/login con email/password

**Cambios aplicados en esta sesión (final):**
- `/r11` emoji roto (🙌→�) arreglado a 😄, textos cambiados a onboarding
- Email aprobación Telegram: "¡Ya eres parte del equipo!" con link a mi.laruta11.cl (no "Crédito R11 Aprobado")
- mi3 login: saludo dinámico según hora (Buenos días/tardes/noches, bienvenido/a)
- Deploy: app3 + caja3 + mi3-frontend en cola. Webhook caja3 hotfixed vía SSH

**Test #2 (limpio, 2026-04-11):**
- Usuario 163 reseteado completamente y re-registrado desde cero
- register.php crea registro en `personal` automáticamente
- Aprobación reseteada a 0 para probar flujo Telegram → email onboarding → mi3 login
- Pendiente: aprobar en Telegram, verificar nuevo email, login en mi3 como trabajador

**Redis:**
- Actualizar REDIS_PASSWORD en Coolify dashboard para app3 y caja3 (valor correcto: `kEfdMKJoEvNTkqFWhEC4hHM3otMA1W/xm/NiDsVBR0I=`)
- Agregar `pecl install redis` al Dockerfile de app3 para que persista entre deploys
- Actualmente: extensión instalada manualmente en contenedor (se pierde en redeploy), rate limiting fail-open
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
