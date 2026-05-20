# Plan de Implementacion: La Ruta 11 SaaS

## Resumen

Convertir La Ruta 11 de un monolito single-tenant (PHP/MySQL, 4 apps separadas) a un SaaS multi-tenant con Go + Next.js + PostgreSQL. El plan se divide en 4 fases, cada una con tareas especificas y verificables.

---

## Fase 1: Setup y Documentacion (1-2 semanas)

- [x] 1. Instalar y autenticar GitHub CLI (`gh`)
  - _Requerimientos: 1.6_
- [x] 2. Crear repositorio privado `laruta11-saas` en GitHub
  - `gh repo create laruta11-saas --private`
  - _Requerimientos: 1.6_
- [x] 3. Crear PostgreSQL 17 en servidor Coolify
  - Contenedor Docker: `saas-postgres`, red `coolify`, puerto 5432
  - _Requerimientos: 5.1_
- [ ] 4. Crear apps en Coolify: `saas-api` y `saas-web`
  - Via Coolify API o dashboard: build pack dockerfile, source GitHub, branch main
  - _Requerimientos: 5.2, 5.3_
- [ ] 5. Generar spec completa en `.kiro/specs/laruta11-saas/`
  - requirements.md, design.md, tasks.md, .config.kiro
  - _Requerimientos: 1.1 (documentacion)_
- [ ] 6. Crear estructura base del monorepo local
  - `/backend/`, `/frontend/`, `/public/`, `/docs/`
  - _Requerimientos: 1.1, 1.2, 1.3_
- [ ] 7. Escribir `docker-compose.yml` para desarrollo local
  - Servicios: postgres, redis, backend (Go + Air hot reload), frontend (Next.js dev)
  - _Requerimientos: 1.4_
- [ ] 8. Configurar `.gitignore` y `.github/workflows/ci.yml`
  - Lint + typecheck + build en PRs
  - _Requerimientos: 1.5_
- [ ] 9. Crear `README.md` en la raiz del monorepo
  - Vision general, quick start, estructura de carpetas
  - _Requerimientos: 1.1_
- [ ] 10. Primer commit y push al repo
  - Verificar que Coolify inicia build automatico
  - _Requerimientos: 5.3_

---

## Fase 2: Backend Core Go (3-6 semanas)

### 2.1 Setup Go Project
- [ ] 2.1.1 Inicializar modulo Go: `go mod init github.com/Ricardohuiscaleo/laruta11-saas`
- [ ] 2.1.2 Instalar dependencias: `gin-gonic/gin`, `jackc/pgx/v5`, `golang-jwt/jwt/v5`, `redis/go-redis/v9`, `gorilla/websocket`
- [ ] 2.1.3 Crear `cmd/server/main.go` con: carga config, pool PG, inicializar Gin router, start HTTP server
- [ ] 2.1.4 Crear `internal/config/config.go`: lectura de env vars (`DATABASE_URL`, `REDIS_URL`, `JWT_SECRET`, `PORT`)
- [ ] 2.1.5 Crear `internal/db/postgres.go`: pool de conexiones pgx con search_path dinamico
- [ ] 2.1.6 Configurar `.air.toml` para hot reload en desarrollo

### 2.2 Tenant System
- [ ] 2.2.1 Crear tabla `tenants` en schema `public` de saas_control via migracion SQL
- [ ] 2.2.2 Implementar `internal/tenant/resolver.go`: resolver tenant por subdominio, header X-Tenant-ID, API key
- [ ] 2.2.3 Implementar `internal/tenant/middleware.go`: Gin middleware que inyecta tenant_id en contexto
- [ ] 2.2.4 Implementar `CREATE SCHEMA tenant_{slug}` + migrar tablas base al crear nuevo tenant
- [ ] 2.2.5 Implementar `SET search_path TO tenant_{slug}` en cada request via middleware

### 2.3 API REST — Auth
- [ ] 2.3.1 Crear `internal/middleware/auth.go`: JWT middleware, extraer user_id + tenant_id del token
- [ ] 2.3.2 Implementar `POST /api/v1/auth/register`: crear usuario + tenant automatico
- [ ] 2.3.3 Implementar `POST /api/v1/auth/login`: validar credenciales, retornar JWT + refresh token
- [ ] 2.3.4 Implementar `GET /api/v1/auth/me`: retornar perfil del usuario autenticado

### 2.4 API REST — Productos y Menu
- [ ] 2.4.1 Crear `internal/models/product.go`: struct Product con tags JSON + validation
- [ ] 2.4.2 Implementar `GET /api/v1/:tenant/products`: listar productos activos con filtros (categoria, busqueda)
- [ ] 2.4.3 Implementar `GET /api/v1/:tenant/products/:id`: detalle de producto individual
- [ ] 2.4.4 Implementar `GET /api/v1/:tenant/categories`: listar categorias con conteo de productos
- [ ] 2.4.5 Implementar `GET /api/v1/:tenant/menu`: menu completo (categorias + productos) optimizado para frontend

### 2.5 API REST — Ordenes
- [ ] 2.5.1 Crear `internal/models/order.go`: struct Order + OrderItem con validacion
- [ ] 2.5.2 Implementar `POST /api/v1/:tenant/orders`: crear orden (validar stock, calcular totales, generar order_number)
- [ ] 2.5.3 Implementar `GET /api/v1/:tenant/orders`: listar ordenes con filtros (status, fecha, type)
- [ ] 2.5.4 Implementar `GET /api/v1/:tenant/orders/:id`: detalle de orden con items
- [ ] 2.5.5 Implementar `PATCH /api/v1/:tenant/orders/:id/status`: actualizar estado de orden
- [ ] 2.5.6 Integrar WebSocket broadcast al crear/actualizar ordenes

### 2.6 WebSocket Realtime
- [ ] 2.6.1 Crear `internal/ws/hub.go`: Hub central con register/unregister/broadcast channels
- [ ] 2.6.2 Crear `internal/ws/client.go`: handle individual connection con read/write pumps
- [ ] 2.6.3 Crear `internal/ws/events.go`: definir tipos de eventos (NEW_ORDER, ORDER_UPDATED, DELIVERY_UPDATE, KITCHEN_UPDATE)
- [ ] 2.6.4 Implementar endpoint `GET /ws` con upgrade a WebSocket + auth JWT
- [ ] 2.6.5 Implementar Redis Pub/Sub en el Hub para broadcast cross-instance
- [ ] 2.6.6 Testear con 10K conexiones concurrentes simuladas

### 2.7 Integracion TUU Pagos
- [ ] 2.7.1 Crear `internal/api/tuu_handler.go`: adaptar logica existente de app3/api/tuu/ a Go
- [ ] 2.7.2 Implementar webhook TUU para recibir confirmaciones de pago
- [ ] 2.7.3 Vincular pago confirmado → actualizar estado de orden → broadcast WS

---

## Fase 3: Frontend Unificado Next.js (7-12 semanas)

### 3.1 Setup Next.js
- [ ] 3.1.1 Crear proyecto Next.js 15 con `create-next-app` en `/frontend`
- [ ] 3.1.2 Configurar TypeScript estricto + TailwindCSS + PostCSS
- [ ] 3.1.3 Instalar dependencias: `next-pwa`, `@tanstack/react-query`, `zod`, `react-hook-form`, `framer-motion`, `lucide-react`
- [ ] 3.1.4 Configurar `middleware.ts` para extraer tenant del subdominio
- [ ] 3.1.5 Crear `src/lib/api.ts`: cliente HTTP tipado con baseURL dinamico por tenant

### 3.2 Rutas Tenant Publicas
- [ ] 3.2.1 `/[tenant]/page.tsx`: Landing page del restaurante (menu, horarios, info)
- [ ] 3.2.2 `/[tenant]/menu/page.tsx`: Carta digital con categorias + productos (React Query)
- [ ] 3.2.3 `/[tenant]/checkout/page.tsx`: Checkout integrado con TUU (carrito → pago)
- [ ] 3.2.4 `/[tenant]/orders/[id]/page.tsx`: Tracking de orden en vivo (WebSocket)

### 3.3 Componentes UI Reutilizables
- [ ] 3.3.1 Refactorizar `MenuApp` de app3 a `components/tenant/MenuApp.tsx`
- [ ] 3.3.2 Refactorizar `CheckoutApp` de app3 a `components/tenant/CheckoutApp.tsx`
- [ ] 3.3.3 Refactorizar `AddressAutocomplete` (Google Maps) a `components/tenant/AddressAutocomplete.tsx`
- [ ] 3.3.4 Refactorizar `OrderTracking` a `components/tenant/OrderTracking.tsx`
- [ ] 3.3.5 Implementar `useWebSocket` hook: conexion automatica, reconnect, manejo de eventos

### 3.4 PWA + Offline
- [ ] 3.4.1 Configurar `next-pwa` con estrategias de cache
- [ ] 3.4.2 Crear `public/manifest.json` con iconos, nombre, theme_color
- [ ] 3.4.3 Implementar offline page: "Sin conexion — tus pedidos se sincronizaran al reconectar"
- [ ] 3.4.4 Implementar Background Sync para ordenes pendientes (usando Workbox)

### 3.5 Admin POS
- [ ] 3.5.1 `admin/[tenant]/page.tsx`: Dashboard admin con KPIs (ventas hoy, ordenes pendientes)
- [ ] 3.5.2 `admin/[tenant]/pos/page.tsx`: POS interface (productos, carrito, pago)
- [ ] 3.5.3 `admin/[tenant]/kitchen/page.tsx`: Pantalla de comandas (WebSocket realtime)
- [ ] 3.5.4 `admin/[tenant]/tv/page.tsx`: Dashboard TV para local (ventas en vivo)

---

## Fase 4: Mobile + Billing + SaaS Dashboard (13-16 semanas)

### 4.1 Capacitor Mobile
- [ ] 4.1.1 Instalar `@capacitor/core` + `@capacitor/cli` en el proyecto frontend
- [ ] 4.1.2 Configurar `capacitor.config.ts` con bundle ID, nombre app, permisos
- [ ] 4.1.3 Agregar plugins: Push Notifications, Geolocation, Camera, Filesystem, Haptics
- [ ] 4.1.4 Build iOS via `npx cap add ios && npx cap sync`
- [ ] 4.1.5 Build Android via `npx cap add android && npx cap sync`

### 4.2 Stripe Billing
- [ ] 4.2.1 Implementar `POST /api/v1/billing/create-checkout`: crear sesion Stripe
- [ ] 4.2.2 Implementar webhook Stripe: `checkout.session.completed` → activar suscripcion
- [ ] 4.2.3 Implementar webhook Stripe: `invoice.payment_failed` → grace period 7 dias
- [ ] 4.2.4 Implementar `GET /api/v1/billing/portal`: link a Stripe Customer Portal

### 4.3 SaaS Dashboard Global
- [ ] 4.3.1 `saas/page.tsx`: Solo superadmin — lista de todos los tenants
- [ ] 4.3.2 Metricas: MRR/ARR, churn rate, tenant health, nuevos registros/mes
- [ ] 4.3.3 Feature flags UI: toggle features por tenant desde el dashboard global

### 4.4 Onboarding Flow
- [ ] 4.4.1 Pagina de registro SaaS: `saas/register` — nombre restaurante, email, password
- [ ] 4.4.2 Post-registro: crear tenant, schema, productos demo, redirect a dashboard
- [ ] 4.4.3 Welcome email via Gmail API (reutilizando integracion existente)

---

## Verificacion Final

- [ ] E2E test: registro SaaS → crear menu → pedido web → pago TUU → WebSocket notifica cocina → tracking delivery
- [ ] Load test: 100 tenants simultaneos, 1000 ordenes/minuto, WebSocket estable con 10K conexiones
- [ ] Security review: tenant isolation (intentar acceder datos de otro tenant desde uno), SQL injection, XSS
- [ ] Deploy production: push a main, verificar deploys, probar en `*.app.laruta11saas.cl`
