# Documento de Requerimientos — La Ruta 11 SaaS

## Introduccion

La Ruta 11 opera actualmente como un sistema monolito para un unico restaurante con 4 aplicaciones separadas (app3, caja3, mi3, landing3) compartiendo una base de datos MySQL unica, sin multi-tenancy. El objetivo de este feature es convertir la plataforma en un SaaS multi-tenant que permita a multiples restaurantes operar con sus propias instancias aisladas, manteniendo todo el codigo en un solo monorepo privado.

## Glosario

- **SaaS**: Software as a Service — modelo de distribucion de software donde una plataforma sirve a multiples clientes (tenants) desde una infraestructura compartida.
- **Multi-tenancy**: Arquitectura que permite a multiples clientes (restaurantes) compartir la misma infraestructura de software con datos completamente aislados.
- **Schema-per-tenant**: Estrategia de aislamiento donde cada restaurante tiene su propio schema PostgreSQL (`tenant_xxx`) dentro de la misma instancia de base de datos, logrando aislamiento total sin duplicar infraestructura.
- **Tenant**: Un restaurante cliente individual que usa la plataforma SaaS.
- **Tenant Router**: Middleware Go que resuelve el subdominio o API key al schema PostgreSQL correspondiente.
- **RLS**: Row-Level Security — funcionalidad de PostgreSQL que restringe acceso a filas segun el usuario/rol conectado. Capa extra de seguridad sobre schema-per-tenant.
- **Goroutine**: Hilo ligero de Go que consume ~4KB de memoria. Permite manejar cientos de miles de conexiones WebSocket concurrentes.
- **PWA**: Progressive Web App — aplicacion web que puede instalarse en dispositivos moviles como app nativa, con soporte offline, notificaciones push y acceso a hardware via Capacitor.
- **Capacitor.js**: Framework que envuelve una PWA en un contenedor nativo para iOS/Android, exponiendo APIs nativas (camara, GPS, push notifications, filesystem) desde codigo web.
- **WebSocket nativo Go**: Implementacion de WebSocket sin librerias externas usando el paquete `net/http` + goroutines. Sin dependencias como Pusher, Socket.io o Reverb.
- **pgx**: Driver PostgreSQL nativo para Go, mas rapido que `database/sql` + `lib/pq`.
- **Redis Pub/Sub**: Mecanismo de mensajeria publish/subscribe de Redis para broadcasting de eventos entre multiples instancias del backend Go.
- **SSR**: Server-Side Rendering — Next.js renderiza paginas en el servidor para SEO y carga inicial rapida.
- **ISR**: Incremental Static Regeneration — Next.js regenera paginas estaticas en background sin rebuild completo.
- **TUU**: Pasarela de pago chilena para facturacion electronica (Webpay, transferencias, tarjetas). Se mantiene la integracion existente.

## Requerimientos

### Requerimiento 1: Arquitectura Monorepo Go + Next.js + PostgreSQL

**User Story:** Como desarrollador, quiero un monorepo privado en GitHub con backend Go y frontend Next.js unificado, para mantener todo el codigo SaaS en un solo repositorio con deploys automatizados via Coolify.

#### Criterios de Aceptacion

1. THE monorepo SHALL contener una carpeta `/backend` con un proyecto Go (modulo `github.com/Ricardohuiscaleo/laruta11-saas`) usando Gin como router HTTP y pgx como driver PostgreSQL.
2. THE monorepo SHALL contener una carpeta `/frontend` con un proyecto Next.js 15 (App Router) + TypeScript + TailwindCSS, configurado como PWA con next-pwa.
3. THE monorepo SHALL contener una carpeta `/public` para archivos estaticos compartidos entre tenants (favicons, manifest.json, robots.txt).
4. THE monorepo SHALL incluir `docker-compose.yml` en la raiz para desarrollo local con servicios: postgres, redis, backend, frontend.
5. THE monorepo SHALL configurar GitHub Actions (`.github/workflows/ci.yml`) con lint + typecheck + build para PRs.
6. THE monorepo SHALL ser privado (`--private`) y usar SSH como protocolo Git.

---

### Requerimiento 2: Multi-tenancy Schema-per-Tenant en PostgreSQL 17

**User Story:** Como administrador SaaS, quiero que cada restaurante tenga sus datos completamente aislados en schemas PostgreSQL separados, para garantizar zero data leakage, backups independientes, y cumplimiento GDPR.

#### Criterios de Aceptacion

1. THE base de datos `saas_control` SHALL contener un schema `public` con tablas de metadata: `tenants` (id, nombre, subdomain, schema_name, plan, activo, created_at), `users` (email, password_hash, tenant_id, role), `api_keys` (key_hash, tenant_id, scopes), `subscriptions` (stripe_id, tenant_id, plan, status, current_period_end).
2. WHEN un nuevo restaurante se registra, THE sistema SHALL ejecutar `CREATE SCHEMA tenant_{id}` y correr las migraciones base dentro de ese schema (tablas: categories, products, orders, order_items, personal, turnos, checklists, etc.).
3. THE middleware `TenantMiddleware` en Go SHALL resolver el tenant desde: (a) subdominio `{tenant}.app.laruta11saas.cl`, (b) header `X-Tenant-ID` para API calls, (c) API key para integraciones externas.
4. THE TenantMiddleware SHALL ejecutar `SET search_path TO tenant_{id}` en cada request para que todas las queries PostgreSQL operen dentro del schema correcto automaticamente.
5. ALL las queries SQL SHALL usar `search_path` en lugar de prefijos explicitos de schema, permitiendo que pgx maneje el contexto del tenant.
6. THE sistema SHALL implementar Row-Level Security como capa adicional: politicas RLS dentro de cada schema que validen que el `current_setting('app.tenant_id')` coincida con los datos.

---

### Requerimiento 3: Backend Go con WebSocket Realtime

**User Story:** Como usuario del sistema, quiero actualizaciones en tiempo real de ordenes, delivery tracking, y notificaciones sin refrescar la pagina, usando WebSocket nativo de Go para minima latencia y maxima escalabilidad.

#### Criterios de Aceptacion

1. THE backend Go SHALL implementar un WebSocket Hub (patron pub/sub con goroutines) que maneje conexiones WebSocket concurrentes con ~4KB de overhead por conexion.
2. THE WebSocket Hub SHALL soportar los siguientes canales: `orders:{tenant_id}` (nuevas ordenes/actualizaciones), `delivery:{tenant_id}:{order_id}` (tracking en vivo), `kitchen:{tenant_id}` (comandas para cocina), `dashboard:{tenant_id}` (stats de ventas en TV).
3. THE WebSocket Hub SHALL usar Redis Pub/Sub para broadcasting cross-instance: cuando una instancia publica un evento, Redis lo distribuye a todas las demas instancias del backend.
4. THE conexion WebSocket SHALL autenticarse via JWT token enviado como query parameter `?token=xxx` durante el handshake.
5. THE backend SHALL exponer endpoints REST versionados bajo `/api/v1/` con OpenAPI 3.1 spec autogenerada via swaggo/swag.
6. THE backend SHALL compilar a un unico binario estatico (~15MB) sin dependencias externas, listo para Docker distroless.

---

### Requerimiento 4: Frontend Next.js 15 Unificado + PWA

**User Story:** Como cliente de un restaurante, quiero una experiencia rapida tipo app nativa en mi telefono que funcione online y offline, con instalacion en pantalla de inicio y notificaciones push.

#### Criterios de Aceptacion

1. THE frontend Next.js SHALL usar App Router con las siguientes rutas: `/[tenant]/` (landing/menu), `/[tenant]/menu` (carta digital), `/[tenant]/checkout` (pago), `/[tenant]/orders/[id]` (tracking), `/[tenant]/profile` (perfil usuario), `/admin/[tenant]/` (dashboard admin POS), `/admin/[tenant]/pos` (punto de venta), `/admin/[tenant]/kitchen` (pantalla cocina), `/admin/[tenant]/tv` (dashboard TV).
2. THE frontend SHALL implementar middleware `middleware.ts` que redirija `app.laruta11saas.cl` al tenant correspondiente basado en subdominio (usando `x-forwarded-host` de Traefik).
3. THE frontend SHALL configurar `next-pwa` con: cache strategy (NetworkFirst para API, CacheFirst para estaticos), offline fallback page, background sync para ordenes pendientes.
4. THE frontend SHALL integrar Capacitor.js para mobile apps nativas (iOS/Android) usando `@capacitor/core` + plugins: PushNotifications, Geolocation, Camera, Filesystem, Haptics, SplashScreen.
5. THE frontend SHALL usar React Query (TanStack Query) para data fetching con: optimistic updates en acciones frecuentes, stale-while-revalidate, infinite scroll en menus, WebSocket integration via custom queryClient.
6. THE frontend SHALL implementar UI components reutilizables siguiendo el design system del restaurante: AddressAutocomplete, CheckoutApp, MenuApp, OrderTracking, ErrorBoundary, LoadingScreen (mismos componentes existentes pero refactorizados para multi-tenant).

---

### Requerimiento 5: Infraestructura Coolify + Deploy Automatico

**User Story:** Como DevOps, quiero que cada push a `main` despliegue automaticamente las apps Go y Next.js en Coolify con zero-downtime, usando PostgreSQL 17 como base de datos.

#### Criterios de Aceptacion

1. THE servidor SHALL tener PostgreSQL 17 corriendo en Docker (`saas-postgres`) en la red `coolify` con puerto 5432 expuesto.
2. Coolify SHALL tener 2 aplicaciones configuradas: `saas-api` (Go, domain `api.laruta11saas.cl`, puerto 8080) y `saas-web` (Next.js, domain `app.laruta11saas.cl`, puerto 3000).
3. Ambas apps SHALL usar build pack `dockerfile`, source GitHub App conectado al repo `Ricardohuiscaleo/laruta11-saas`, branch `main`, auto-deploy on push.
4. `saas-api` Dockerfile SHALL usar multi-stage build: `golang:1.25-alpine` para compilar → `gcr.io/distroless/static` para runtime (~15MB imagen final).
5. `saas-web` Dockerfile SHALL usar `node:22-alpine` con standalone output de Next.js.
6. Las variables de entorno SHALL configurarse via Coolify dashboard/API para cada app: `DATABASE_URL`, `REDIS_URL`, `JWT_SECRET`, `TUU_API_KEY`, `GOOGLE_MAPS_KEY`, `GEMINI_API_KEY`, etc.
7. Los dominios SHALL usar SSL automatico via Traefik + Let's Encrypt (gestionado por Coolify).

---

### Requerimiento 6: Pipeline Migracion (4 Fases)

**User Story:** Como project manager, quiero un plan de migracion fase por fase que minimice riesgos, permita operar el sistema actual mientras se construye el SaaS, y tenga hitos claros de entrega.

#### Criterios de Aceptacion

1. **Fase 1 (Setup)**: Repositorio creado, PostgreSQL corriendo, Coolify apps configuradas, CI/CD funcional. Duracion: 1-2 semanas.
2. **Fase 2 (Backend Core)**: API REST Go completa con CRUD de tenants, autenticacion JWT, WebSocket Hub, schema-per-tenant migrations, integracion TUU. Duracion: 3-6 semanas.
3. **Fase 3 (Frontend Unificado)**: Next.js con todas las rutas multi-tenant, PWA con offline, UI components refactorizados, React Query + WebSocket integration. Duracion: 7-12 semanas.
4. **Fase 4 (Mobile + Billing)**: Capacitor.js iOS/Android, Stripe billing, onboarding flow, admin dashboard SaaS global. Duracion: 13-16 semanas.

---

### Requerimiento 7: Seguridad y Aislamiento

**User Story:** Como CTO, quiero garantizar que los datos de cada restaurante esten completamente aislados y protegidos, cumpliendo con mejores practicas de seguridad SaaS.

#### Criterios de Aceptacion

1. THE sistema SHALL implementar Row-Level Security en PostgreSQL como defensa en profundidad: el schema-per-tenant aisla, RLS verifica.
2. ALL las conexiones a la base de datos SHALL usar un unico usuario PostgreSQL (`saas_admin`) pero con `search_path` dinamico por request — NUNCA compartir credenciales entre tenants.
3. THE JWT tokens SHALL incluir `tenant_id` en el payload y ser validados en cada request por middleware.
4. NO secrets/tokens SHALL hardcodearse en codigo. Todo via variables de entorno.
5. Rate limiting SHALL implementarse por tenant (no global) usando Redis: 100 req/min para endpoints publicos, 300 req/min para autenticados.
6. CORS SHALL restringirse a origenes del tenant especifico (subdominio).
7. File uploads (S3) SHALL organizarse por tenant: `s3://laruta11-images/{tenant_id}/...`.
8. Sanitizacion de inputs server-side via validacion de tipos Go (struct tags `validate:"required,min=1"`).

---

### Requerimiento 8: Modelo de Negocio SaaS

**User Story:** Como fundador, quiero un sistema de tiers SaaS con feature flags y billing via Stripe, para monetizar la plataforma desde el dia 1.

#### Criterios de Aceptacion

1. THE sistema SHALL soportar 3 tiers: **Starter** (1 sucursal, menu digital, pedidos basicos), **Pro** (3 sucursales, POS, delivery tracking, reportes), **Enterprise** (ilimitado, custom domain, white-label, API access).
2. Feature flags SHALL implementarse via tabla `tenant_features` en schema `public` de `saas_control`: `{tenant_id, feature_key, enabled, quota, usage}`.
3. Billing SHALL integrarse con Stripe Checkout + webhooks para: suscripcion mensual/anual, pro-rating en upgrades, grace period de 7 dias para pagos fallidos.
4. THE sistema SHALL tener un dashboard SaaS global (solo superadmin) en `/admin/saas` con: lista de tenants, MRR/ARR, churn rate, tenant health (ultima actividad, errores, storage usado).

---

## Stack Tecnologico Final

| Capa | Tecnologia | Justificacion |
|------|------------|---------------|
| Backend API | Go 1.25 + Gin | Binario nativo, 20x mas rapido que PHP/Laravel, goroutines para realtime |
| Realtime | WebSocket nativo Go + Redis Pub/Sub | Sin dependencias externas, 100K+ conexiones con ~50 lineas de codigo |
| Frontend | Next.js 15 + TypeScript | SSR/ISR para SEO, App Router, PWA nativa, un solo codebase web+mobile |
| Mobile | PWA + Capacitor.js | Mismo codigo Next.js empaquetado para iOS/Android, APIs nativas |
| Base de Datos | PostgreSQL 17 | Schema-per-tenant, RLS, JSONB, full-text search, mejor que MySQL para SaaS |
| Cache / Colas | Redis 7 | Sesiones, rate limiting, job queues, Pub/Sub broadcasting |
| Billing | Stripe | Subscripciones, invoicing, tax automatico, webhooks |
| Storage | AWS S3 | Imagenes por tenant, firmado de URLs |
| Deploy | Coolify + Docker | Auto-deploy on push, Traefik SSL, zero-downtime |
| CI/CD | GitHub Actions | Lint + typecheck + build en PRs |
| Monitoreo | Coolify Sentinel | Health checks, metricas de containers |
