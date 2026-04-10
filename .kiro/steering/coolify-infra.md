---
inclusion: manual
---

# Coolify - Conexión e Infraestructura

## Conexión API

- URL Base: `http://76.13.126.63:8000/api/v1`
- Auth Header: `Authorization: Bearer <COOLIFY_API_TOKEN>`
- Token name en Coolify: `kiro-Ruta11-Coolify`
- Versión Coolify: `4.0.0-beta.472`

## Comando curl base

```bash
curl -s -H "Authorization: Bearer $COOLIFY_TOKEN" -H "Accept: application/json" http://76.13.126.63:8000/api/v1/{endpoint}
```

## Servidor

- Nombre: `localhost` (UUID: `jc0wg0kkg88osswok0o0gso0`)
- IP: `host.docker.internal`
- Proxy: Traefik 3.6.9 (running)
- Sentinel: habilitado
- Builds concurrentes: 2
- Docker cleanup: diario a medianoche, threshold 80%
- SSL: Let's Encrypt via Traefik

## Aplicaciones Desplegadas

### Proyecto laruta11_3 (repo: Ricardohuiscaleo/laruta11_3)

| App | UUID | Dominio | Base Dir | Puerto | Environment |
|-----|------|---------|----------|--------|-------------|
| app3 | `egck4wwcg0ccc4osck4sw8ow` | https://app.laruta11.cl | /app3 | 80 | production (env_id: 1) |
| caja3 | `xockcgsc8k000o8osw8o88ko` | https://caja.laruta11.cl | /caja3 | 80 | production (env_id: 1) |
| landing3 | `dks4cg8s0wsswk08ocwggk0g` | https://laruta11.cl | /landing3 | 80 | production (env_id: 1) |
| mi3-backend | `ds24j8jlaf9ov4flk1nq4jek` | https://api-mi3.laruta11.cl | /mi3/backend | 8080 | production (env_id: 1) |
| mi3-frontend | `sxdw43i9nt3cofrzxj28hx1e` | https://mi.laruta11.cl | /mi3/frontend | 3000 | production (env_id: 1) |

### Proyecto Digitalizatodo (repo: Ricardohuiscaleo/Digitalizatodo)

| App | UUID | Dominio | Base Dir | Puerto | Environment |
|-----|------|---------|----------|--------|-------------|
| admin | `fx5kn83mhdpe1jy3nj1zenjx` | https://mi.digitalizatodo.cl | /Applications/admin-pwa | 3000 | staging (env_id: 2) |
| app | `z4c8ocwskw0sgw8cogcsoogk` | https://app.digitalizatodo.cl | /Applications/app-pwa | 3000 | staging (env_id: 2) |
| landing | `tgkcc4k4484wswwcc8g884o4` | https://digitalizatodo.cl | /Applications/landing | 80 | staging (env_id: 2) |
| ricardohuiscaleo | `gcoccsw8ooww4w8wog8kwscc` | https://ricardohuiscaleo.digitalizatodo.cl | /Applications/ricardohuiscaleo | 80 | staging (env_id: 2) |

### Proyecto saas-backend (repo: Ricardohuiscaleo/saas-backend)

| App | UUID | Dominio | Base Dir | Puerto | Environment |
|-----|------|---------|----------|--------|-------------|
| saas-backend | `bo888gk4kg8w0wossc00ccs8` | https://admin.digitalizatodo.cl | / | 80,443,8080 | staging (env_id: 2) |

## Endpoints API útiles

- `GET /version` — Versión de Coolify
- `GET /servers` — Listar servidores
- `GET /applications` — Listar todas las apps
- `GET /applications/{uuid}` — Detalle de una app
- `GET /applications/{uuid}/envs` — Variables de entorno de una app
- `POST /applications/{uuid}/restart` — Reiniciar app
- `POST /applications/{uuid}/start` — Iniciar app
- `POST /applications/{uuid}/stop` — Detener app
- `GET /applications/{uuid}/logs` — Ver logs de una app
- `PATCH /applications/{uuid}` — Actualizar configuración de una app
- `GET /databases` — Listar bases de datos
- `GET /services` — Listar servicios

## Bases de Datos MySQL

### laruta11-db (principal)

- UUID: `zs00occ8kcks40w4c88ogo08`
- Imagen: `mysql:8`
- BD: `laruta11`
- Usuario app: `laruta11_user`
- Puerto público: 3306
- Estado: `running:healthy`
- Conexión externa: `mysql://laruta11_user:<PASS>@76.13.126.63:3306/laruta11`
- Conexión interna: `mysql://laruta11_user:<PASS>@zs00occ8kcks40w4c88ogo08:3306/laruta11`

### saas-db

- UUID: `eocws4gsgkk4ck800w8g8000`
- Imagen: `mysql:8`
- BD: `saas_backend`
- Usuario app: `saas_user`
- Puerto público: 3307
- Estado: `running:healthy`
- Conexión externa: `mysql://saas_user:<PASS>@76.13.126.63:3307/saas_backend`
- Conexión interna: `mysql://saas_user:<PASS>@eocws4gsgkk4ck800w8g8000:3306/saas_backend`

> Credenciales disponibles via Coolify API: `GET /applications/{uuid}/envs`
> Variables de BD en apps: APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS

## Servicios Externos Integrados

### Redis (Coolify interno)
- Host: `coolify-redis`
- Puerto: 6379

### Supabase
- URL: configurada en `PUBLIC_SUPABASE_URL`

### AWS S3
- Bucket: `laruta11-images`
- Región: `us-east-1`
- URL: `https://laruta11-images.s3.amazonaws.com`

### Google APIs
- Maps API Key: `RUTA11_GOOGLE_MAPS_API_KEY`
- Calendar API Key: `GOOGLE_CALENDAR_API_KEY`
- OAuth (3 apps): Google Auth, Jobs, Tracker — cada una con CLIENT_ID, CLIENT_SECRET, REDIRECT_URI

### Gmail (envío de correos)
- Sender: configurado en `GMAIL_SENDER_EMAIL`
- OAuth: `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`

### Telegram (notificaciones)
- Token: `TELEGRAM_TOKEN`
- Chat ID: `TELEGRAM_CHAT_ID`

### TUU (facturación electrónica Chile)
- API Key: `TUU_API_KEY`
- RUT: `TUU_ONLINE_RUT`
- Ambiente online: `TUU_ONLINE_ENV` (production)
- Ambiente dev: `TUU_ENVIRONMENT` (dev)
- Device Serial: `TUU_DEVICE_SERIAL`

### Gemini AI
- API Key: `GEMINI_API_KEY`

### Unsplash
- Access Key: `UNSPLASH_ACCESS_KEY`

## Variables de Entorno (keys compartidas entre app3 y caja3)

Las apps app3 y caja3 comparten las mismas env vars. Se pueden consultar via:
```bash
curl -s -H "Authorization: Bearer $COOLIFY_TOKEN" -H "Accept: application/json" \
  http://76.13.126.63:8000/api/v1/applications/{uuid}/envs
```

Categorías de env vars:
- DB: APP_DB_HOST, APP_DB_NAME, APP_DB_USER, APP_DB_PASS
- Auth Google: RUTA11_GOOGLE_CLIENT_ID/SECRET, RUTA11_JOBS_CLIENT_ID/SECRET, RUTA11_TRACKER_CLIENT_ID/SECRET
- APIs: GEMINI_API_KEY, UNSPLASH_ACCESS_KEY, GOOGLE_CALENDAR_API_KEY, RUTA11_GOOGLE_MAPS_API_KEY
- AWS: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, S3_BUCKET, S3_REGION, S3_URL
- Redis: REDIS_HOST, REDIS_PORT, REDIS_PASSWORD
- Telegram: TELEGRAM_TOKEN, TELEGRAM_CHAT_ID
- TUU: TUU_API_KEY, TUU_ONLINE_RUT, TUU_ONLINE_SECRET, TUU_ONLINE_ENV, TUU_ENVIRONMENT, TUU_DEVICE_SERIAL
- Gmail: GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_SENDER_EMAIL
- Supabase: PUBLIC_SUPABASE_URL, PUBLIC_SUPABASE_ANON_KEY
- Usuarios app: ADMIN_USER/PASS, RICARDO_PASS, MANAGER_PASS, UNLOCK, RUTA11_PASS, INVENTARIO_USER/PASSWORD, CAJERA_USER/PASS
- Externos: PEDIDOSYA_EMAIL/PASSWORD, INSTAGRAM_EMAIL/PASSWORD, TUU_PLATFORM_EMAIL/PASSWORD

## Acceso SSH al Servidor

- IP: `76.13.126.63`
- Usuario: `root`
- Acceso: Vía SSH (llave autorizada en este Mac)

## Estructura de Contenedores

Los nombres de contenedores cambian en cada deploy (UUID + sufijo numérico), pero el UUID siempre es el prefijo.
Las bases de datos NO cambian de nombre (no tienen sufijo).

Contenedores de apps (nombre cambia en cada deploy):
- app3: `egck4wwcg0ccc4osck4sw8ow-{sufijo}`
- caja3: `xockcgsc8k000o8osw8o88ko-{sufijo}`
- landing3: `dks4cg8s0wsswk08ocwggk0g-{sufijo}`
- saas-backend: `bo888gk4kg8w0wossc00ccs8-{sufijo}`

Contenedores de BD (nombre fijo):
- laruta11-db: `zs00occ8kcks40w4c88ogo08`
- saas-db: `eocws4gsgkk4ck800w8g8000`

Para ejecutar comandos en apps (usa docker ps -qf para encontrar el contenedor activo):
```bash
ssh root@76.13.126.63 "docker exec \$(docker ps -qf name={UUID}) {comando}"
```

Para ejecutar comandos en BD (nombre fijo, sin necesidad de buscar):
```bash
ssh root@76.13.126.63 "docker exec {UUID-BD} mysql -ularuta11_user -p'<PASS>' laruta11 -e '{SQL}'"
```

### Comandos rápidos de acceso a MySQL producción

```bash
# laruta11 (principal)
ssh root@76.13.126.63 "docker exec zs00occ8kcks40w4c88ogo08 mysql -ularuta11_user -p'<PASS>' laruta11 -e '{SQL}'"

# saas_backend
ssh root@76.13.126.63 "docker exec eocws4gsgkk4ck800w8g8000 mysql -usaas_user -p'<PASS>' saas_backend -e '{SQL}'"
```

### Rutas de código dentro de contenedores

- app3/caja3/landing3: `/var/www/html/` (Apache + PHP)
- saas-backend: `/var/www/html/` (Laravel)

## Automatización (Cronjobs)

### saas-backend (Laravel scheduler)
```bash
* * * * * docker exec $(docker ps -qf name=bo888gk4kg8w0wossc00ccs8) php artisan schedule:run >> /dev/null 2>&1
```
Procesa pagos recurrentes, notificaciones y tareas programadas.

### laruta11_3 (app3/caja3)
Los cronjobs de PHP se ejecutan via cron dentro del contenedor o llamadas externas a endpoints API.

## Reglas de Oro

### Para laruta11_3 (app3 + caja3)
1. **Sync obligatorio**: app3 y caja3 comparten la misma BD `laruta11`. Cambios en esquema afectan ambas apps.
2. **Deploy via GitHub**: Push a `main` en `Ricardohuiscaleo/laruta11_3` dispara deploy automático en Coolify para las 3 apps (app3, caja3, landing3).
3. **Env vars compartidas**: app3 y caja3 tienen las mismas variables de entorno. Si se agrega una nueva, agregarla en AMBAS apps via Coolify API o dashboard.
4. **Base directory**: Cada app tiene su propio base_directory en el monorepo (`/app3`, `/caja3`, `/landing3`).
5. **No tocar la BD directamente** sin verificar impacto en ambas apps.

### Para saas-backend (Digitalizatodo)
1. **Migrations via Laravel**: TODOS los cambios estructurales deben realizarse vía `php artisan migrate`. No realizar cambios manuales en MySQL.
2. **Dual Push Mandatory**: Siempre que se modifique `saas-backend`, ejecutar `git subtree push` al repositorio independiente para que Coolify despliegue.
3. **Mantenimiento**: Si las migraciones fallan, verificar primero si hay archivos obsoletos en `database/migrations/` que no estén en la tabla `migrations`.

## Deployment

### Deploy manual via API
```bash
# Reiniciar una app
curl -s -X POST -H "Authorization: Bearer $COOLIFY_TOKEN" \
  http://76.13.126.63:8000/api/v1/applications/{uuid}/restart

# Ver logs de deploy
curl -s -H "Authorization: Bearer $COOLIFY_TOKEN" \
  http://76.13.126.63:8000/api/v1/applications/{uuid}/logs
```

### Deploy automático
- Trigger: Push a branch `main` en GitHub
- Webhook configurado en cada app
- Build: Dockerfile en cada base_directory

## Notas Generales

- Todas las apps usan build_pack `dockerfile` y branch `main`
- GitHub App como source (source_id: 2)
- Redirect HTTP→HTTPS habilitado en todas
- Health checks deshabilitados en todas las apps
- No hay límites de CPU/memoria configurados (ilimitado)
- Traefik maneja SSL con Let's Encrypt y gzip compression
- Docker cleanup automático diario a medianoche (threshold 80%)
