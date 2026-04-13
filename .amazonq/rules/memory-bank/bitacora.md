# La Ruta 11 — Bitácora de Desarrollo

## Estado Actual (2026-04-13)

### Aplicaciones Desplegadas

| App | URL | Stack | Estado |
|-----|-----|-------|--------|
| app3 | app.laruta11.cl | Astro + React + PHP | ✅ Running |
| caja3 | caja.laruta11.cl | Astro + React + PHP | ✅ Running |
| landing3 | laruta11.cl | Astro | ✅ Running |
| mi3-frontend | mi.laruta11.cl | Next.js 14 + React + Echo | ✅ Running (`34ba5be`) |
| mi3-backend | api-mi3.laruta11.cl | Laravel 11 + PHP 8.3 + Reverb | ✅ Running (`34ba5be`) |
| saas-backend | admin.digitalizatodo.cl | Laravel 11 + PHP 8.4 + Reverb | ✅ Running |

### Coolify UUIDs

| App | UUID |
|-----|------|
| app3 | `egck4wwcg0ccc4osck4sw8ow` |
| caja3 | `xockcgsc8k000o8osw8o88ko` |
| landing3 | `dks4cg8s0wsswk08ocwggk0g` |
| mi3-backend | `ds24j8jlaf9ov4flk1nq4jek` |
| mi3-frontend | `sxdw43i9nt3cofrzxj28hx1e` |
| laruta11-db | `zs00occ8kcks40w4c88ogo08` |

### Scheduled Tasks

| App | Task | Frecuencia |
|-----|------|------------|
| mi3-backend | `php artisan schedule:run` (7 comandos) | `* * * * *` |
| app3 | Gmail Token Refresh | `*/30 * * * *` |
| caja3 | Daily Checklists (legacy) | `0 12 * * *` (8 AM Chile) |

### Bot Telegram (SuperKiro)

| Componente | Estado |
|-----------|--------|
| Bot | `@SuperKiro_bot` — pm2 auto-start en VPS |
| kiro-cli | v1.29.8 en `/root/.local/bin/kiro-cli` (Builder ID) |
| Workspace | `/root/laruta11_3` ✅ verificado |
| ACP | Sesión activa, acceso completo al monorepo |

---

## Tareas Pendientes

### 🔴 Críticas (afectan producción)

- [ ] **Actualizar `checklist_templates`** para creación diaria con items correctos del planchero (3 fotos: plancha, lavaplatos, mesón)
- [ ] **Corregir caja3 `get_turnos.php`** base date cajero (2026-02-01 → 2026-02-02)
- [ ] **Generar turnos mayo** en producción
- [ ] **Fix push subscriptions duplicadas** en `push_subscriptions_mi3` (44 registros para 1 usuario)

### 🟡 Verificaciones pendientes

- [ ] **Ajustes muestra IDs en vez de nombres** (#3, #10, #12) — necesita JOIN con personal para mostrar nombre del trabajador
- [ ] **Inasistencias automáticas $40.000** — verificar si descuentos del 12-13 abril son correctos o falso positivo (checklists no completados aún)
- [ ] **Nómina vacía** — endpoint no devuelve datos, investigar
- [ ] **Editar trabajadores: "validation.array"** — error al editar personal, investigar validación en PersonalController update
- [ ] Verificar prompts IA planchero dan feedback correcto (plancha/lavaplatos/mesón)
- [ ] Verificar upload S3 en compras (end-to-end)
- [ ] Verificar Gmail Token Refresh funciona 100%
- [ ] Verificar subida masiva agrupa ARIAKA correctamente
- [ ] **Deploy pendiente**: `foto_url` en `personal` — BD ya tiene columna+datos, falta commit+deploy del código backend+frontend
- [ ] **Resolver duplicado Dafne**: id=12 (sin user\_id, turnos apuntan acá) vs id=18 (user\_id=164, con foto). Migrar turnos/checklists al 18 y desactivar 12

### 🟢 Mejoras futuras

- [x] **Sistema de Rendiciones**: Implementado. Tabla `rendiciones`, página pública /rendicion/{token}, saldo encadenado, 250 compras históricas marcadas como rendidas, saldo actual $68.899
- [ ] Tareas generadas por IA desde fotos de checklist (si score < 50 → tarea automática)
- [ ] Decidir si eliminar/desactivar Portal R11 legacy (`app.laruta11.cl/r11/`)
- [ ] Ejecutar migraciones `checklists_v2` en producción (spec existe pero tabla no)
- [ ] Recalcular delivery\_fee server-side en `create_order.php` (actualmente confía en frontend)
- [ ] Unificar factor descuento RL6 en caja3 (0.6 vs 0.7143)

---

## Sesiones Recientes

### 2026-04-13k — Historial rendiciones en KPIs + realtime WebSocket

**Cambios:**
- `KpiController.php`: endpoint GET /kpis/rendiciones (historial saldo encadenado)
- `RendicionActualizada.php`: nuevo evento broadcast en canal 'compras'
- `RendicionController.php`: dispatch RendicionActualizada al crear/aprobar
- `KpisDashboard.tsx`: sección historial rendiciones + Echo listener (compra.registrada + rendicion.actualizada → refetch auto)

**Commits:** `34ba5be`
**Deploys:** mi3-frontend (`qx4vjktr`) ✅, mi3-backend (`jqiz2aak`) ✅

### 2026-04-13j — Rendiciones: lógica saldo + modal fix + WhatsApp limpio + título

**Cambios:**
- Página pública: saldo positivo = "Caja disponible" (botones $0/+Caja), saldo negativo = "Ricardo puso de su bolsillo" (botones Devolver/Devolver+Caja)
- `RendicionWhatsApp.tsx`: texto WhatsApp con labels correctos según caso
- Lógica: Ricardo compra con su plata → Yojhans devuelve si saldo negativo
- Fix: modal z-50 + pb-20 para no quedar detrás del navbar móvil
- WhatsApp simplificado: solo saldo anterior + total compras + caja disponible + link (sin detalle items)
- Título sitio: 'La Ruta 11 — Work' + descripción actualizada

**Commits:** `1e6ff80`, `f514a8e`, `ffebda5`
**Deploys:** mi3-frontend (`dnyj2a40`, `zj67aipa`, `k11fopk3`) ✅

### 2026-04-13i — Rendiciones: botones monto rápido + fotos modal + anular

**Cambios:**
- Página pública `/rendicion/{token}`: botones Exacto (redondeado ↑1000) y Smart (+100k redondeado ↑10000), fotos thumbnails + modal fullscreen, observaciones textarea
- `HistorialCompras.tsx`: rendiciones pendientes arriba con Anular/Ver, badge ✓ en rendidas
- `RendicionWhatsApp.tsx`: abre wa.me directo con texto formateado + link público, saldo anterior auto
- `RendicionController.php`: endpoint DELETE anular (desvincula compras)

**Commits:** `66c1b8e`
**Deploys:** mi3-frontend (`klsszuae`) ✅, mi3-backend (`mndd5g3s`) ✅

### 2026-04-13h — Flujo rendiciones desde Historial + WhatsApp

**Cambios:**
- `RendicionWhatsApp.tsx`: reescrito — carga saldo anterior auto del backend, genera rendición en BD, abre wa.me con texto formateado + link público
- `HistorialCompras.tsx`: badge ✓ en compras rendidas, checkboxes solo en sin rendir, onCreated refresca lista
- Flujo: Historial → seleccionar → modal → WhatsApp → link público con aprobar

**Commits:** `b9a76de`
**Deploys:** mi3-frontend (`c7rmrpis`) ✅

### 2026-04-13g — Sistema de Rendiciones completo

**Cambios:**
- Modelo `Rendicion` + migración (tabla `rendiciones` + `compras.rendicion_id`)
- `RendicionController`: preview, store, show (público), aprobar, rechazar
- Rutas públicas sin auth: GET/POST `/rendicion/{token}` (para link WhatsApp)
- Frontend público: `/rendicion/{token}` (detalle compras + botón aprobar con monto)
- BD producción: migración ejecutada, rendición histórica #1 creada (saldo $68.899), 250 compras marcadas rendidas

**Commits:** `3ba677c`
**Deploys:** mi3-frontend (`nxxnert4`) ✅, mi3-backend (`b7amrsvq`) ✅

### 2026-04-12bf — Campo `foto_url` en Personal + fotos de perfil en admin

**Cambios:** Pendiente deploy

---

> Sesiones anteriores (120 total, desde 2026-04-10) archivadas en `bitacora-archivo.md`
> Reglas del proyecto extraídas en `.kiro/steering/laruta11-rules.md`
