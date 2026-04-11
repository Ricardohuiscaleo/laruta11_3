# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-11)

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

- Deployment UUID: `wbusyrtxs0xxlc ewvgn4e06n`
- Fix aplicado: `key:generate + route:clear` en Dockerfile
- Esperando resultado del build
- Si pasa: la ruta `/api/v1/auth/google/redirect` debería funcionar y redirigir a Google OAuth
