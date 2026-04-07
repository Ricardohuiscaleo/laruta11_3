# 🗺️ Roadmap La Ruta 11 — Matriz Eisenhower

> Fecha: 6 de Abril 2026
> Autor: Ricardo Huiscaleo + Kiro

---

## 🔴 CUADRANTE 1: URGENTE + IMPORTANTE (Hacer primero)

### 1.1 Crédito R11 (Crédito para trabajadores / no militares)

**Objetivo:** Replicar el sistema de crédito RL6 pero para trabajadores de La Ruta 11 y personas de confianza (no militares). El cobro se hace por descuento de sueldo el 1ro de cada mes (o días antes según fecha de pago).

**¿Por qué es urgente?** Genera ingresos recurrentes, fideliza al equipo, y la lógica ya existe en RL6 — es copiar, adaptar y lanzar.

**Diferencias clave con RL6:**

| Aspecto | RL6 (Militares) | R11 (Trabajadores) |
|---------|-----------------|---------------------|
| Público | Militares del Regimiento RL6 | Trabajadores de La Ruta 11 y personas de confianza |
| Registro | Formulario con carnet militar + selfie | Aprobación directa desde admin (sin formulario público) |
| Cobro | Día 21 vía Webpay (el usuario paga) | Día 1 (o antes) descuento automático de sueldo |
| Bloqueo | Día 22 si no pagó | Día 2 si no se procesó el descuento |
| Validación | `es_militar_rl6 = 1` | `es_credito_r11 = 1` |
| Prefijo orden | `RL6-xxx` | `R11C-xxx` |

**Base de datos — Nuevos campos en `usuarios`:**
```sql
-- Campos R11 Credit (misma estructura que RL6)
es_credito_r11          TINYINT(1) DEFAULT 0,
credito_r11_aprobado    TINYINT(1) DEFAULT 0,
limite_credito_r11      DECIMAL(10,2) DEFAULT 0.00,
credito_r11_usado       DECIMAL(10,2) DEFAULT 0.00,
credito_r11_bloqueado   TINYINT(1) DEFAULT 0,
fecha_aprobacion_r11    TIMESTAMP NULL,
fecha_ultimo_pago_r11   DATE NULL,
relacion_r11            VARCHAR(100) NULL  -- 'trabajador', 'familiar', 'confianza'
```

**Nueva tabla:**
```sql
CREATE TABLE r11_credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','debit','refund') NOT NULL,
    description VARCHAR(255),
    order_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
);
```

**APIs a crear (copiando de RL6):**

| API | Origen RL6 | Destino R11 | App |
|-----|-----------|-------------|-----|
| `get_credit.php` | `app3/api/rl6/get_credit.php` | `app3/api/r11/get_credit.php` | app3 |
| `use_credit.php` | `app3/api/rl6/use_credit.php` | `app3/api/r11/use_credit.php` | app3 |
| `create_payment.php` | `app3/api/rl6/create_payment.php` | `app3/api/r11/create_payment.php` | app3 |
| `payment_callback.php` | `app3/api/rl6/payment_callback.php` | `app3/api/r11/payment_callback.php` | app3 |
| `get_statement.php` | `app3/api/rl6/get_statement.php` | `app3/api/r11/get_statement.php` | app3 |
| `refund_credit.php` | `caja3/api/rl6_refund_credit.php` | `caja3/api/r11_refund_credit.php` | caja3 |
| `process_manual_payment.php` | `caja3/api/rl6/process_manual_payment.php` | `caja3/api/r11/process_manual_payment.php` | caja3 |
| `approve_credit.php` | `caja3/api/approve_militar_rl6.php` | `caja3/api/approve_credito_r11.php` | caja3 |
| `get_creditos_r11.php` | `caja3/api/get_militares_rl6.php` | `caja3/api/get_creditos_r11.php` | caja3 |

**Frontend — app3 (cliente):**
- Nueva pestaña/sección "Crédito R11" (similar a la sección RL6)
- Página `app3/src/pages/r11.astro` — Estado de cuenta R11
- Página `app3/src/pages/pagar-credito-r11.astro` — Pagar crédito
- En checkout: agregar `r11_credit` como método de pago
- Mostrar crédito disponible R11 en el perfil del usuario

**Frontend — caja3 (admin/cajero):**
- Nueva vista "Créditos R11" en admin (similar a "Militares RL6")
- Aprobar/rechazar créditos R11
- Procesar pagos manuales R11
- Ver reporte de créditos R11 en ArqueoApp
- En MiniComandas: manejar anulación de pedidos R11 (reintegro)

**Flujo de cobro mensual (diferencia principal):**
```
Día 28-30 del mes:
  → Cron job genera reporte de deudas R11
  → Envía email recordatorio a cada trabajador con deuda
  → Notifica al admin con resumen

Día 1 del mes siguiente:
  → Admin procesa descuentos de sueldo manualmente
  → O: sistema genera descuento automático en nómina (mi3)
  → Registra pago como 'refund' en r11_credit_transactions
  → Resetea credito_r11_usado = 0

Día 2:
  → Cron job bloquea usuarios que no pagaron
  → credito_r11_bloqueado = 1
```

**Estimación:** 3-5 días (80% es copiar RL6 y adaptar)

---

### 1.2 Completar bloqueos automáticos RL6 (pendiente)

**Estado actual:** Documentado pero NO implementado.

**Tareas:**
- Crear `app3/api/rl6/check_overdue_payments.php` (cron job día 22)
- Crear `app3/api/rl6/validate_credit_purchase.php` (validar en checkout)
- Agregar validación `credito_bloqueado = 0` en `use_credit.php`
- Email recordatorio día 18-19
- Email aviso de bloqueo día 22
- Aplicar misma lógica para R11

**Estimación:** 1-2 días

---

## 🟡 CUADRANTE 2: IMPORTANTE + NO URGENTE (Planificar)

### 2.1 mi3 — App RRHH para trabajadores

**Objetivo:** App self-service donde los trabajadores de La Ruta 11 gestionan su información laboral. Hoy todo es manual en `caja3/src/pages/personal/`.

**URL:** `mi.laruta11.cl` (o `rrhh.laruta11.cl`)

**Lo que ya existe (base):**
- `caja3/api/personal/get_personal.php` — Lista de personal
- `caja3/api/personal/get_pagos_nomina.php` — Pagos de nómina por mes
- `caja3/api/personal/get_turnos.php` — Turnos
- `caja3/api/personal/get_ajustes.php` — Ajustes de sueldo
- `caja3/api/personal/send_liquidacion_email.php` — Envío de liquidaciones
- Tabla `personal` en BD
- Tabla `pagos_nomina` en BD
- Tabla `presupuesto_nomina` en BD

**Módulos de mi3:**

| Módulo | Descripción | Prioridad |
|--------|-------------|-----------|
| Mi Sueldo | Ver liquidación actual y historial | Alta |
| Mis Turnos | Ver calendario de turnos, solicitar cambios | Alta |
| Mi Crédito R11 | Ver estado de crédito, historial, saldo | Alta |
| Mis Descuentos | Ver descuentos aplicados (crédito, adelantos, etc) | Media |
| Mi Perfil | Datos personales, contacto de emergencia | Media |
| Solicitudes | Pedir días libres, cambios de turno, adelantos | Baja |
| Documentos | Descargar liquidaciones en PDF | Baja |

**Stack técnico:**
- Astro + React (mismo que app3/caja3)
- Autenticación: login con RUT + contraseña (tabla `personal`)
- APIs: reutilizar `caja3/api/personal/` + nuevas APIs específicas
- BD: misma base de datos compartida

**Flujo de autenticación:**
```
Trabajador ingresa a mi.laruta11.cl
→ Login con RUT + contraseña
→ Valida contra tabla `personal`
→ Genera session_token
→ Dashboard con módulos según rol
```

**Estimación:** 2-3 semanas

---

### 2.2 admin3 — App de gestión de negocio

**Objetivo:** Reemplazar el admin actual (roto, 6900+ líneas en un solo archivo) con una app moderna y modular.

**URL:** `admin.laruta11.cl`

**Estado actual del admin:**
- `caja3/src/pages/admin/index.astro` — 6,928 líneas, monolítico, frágil
- `caja3/admin2/` — Intento de rewrite con React + Vite, solo tiene Dashboard
- Funcionalidades dispersas entre admin, admin2, y páginas sueltas de caja3

**Módulos de admin3:**

| Módulo | Descripción | Existe en admin actual? |
|--------|-------------|------------------------|
| Dashboard | KPIs, ventas, métricas, gráficos | ✅ Sí (funciona) |
| Ventas | Detalle de ventas, filtros, exportar | ✅ Parcial |
| Productos | CRUD productos, categorías, combos, precios | ✅ Sí (funciona) |
| Inventario | Ingredientes, recetas, stock, mermas, compras | ✅ Parcial |
| Pedidos | Historial, estados, búsqueda | ⚠️ "En desarrollo" |
| Usuarios | Gestión de clientes, estadísticas | ✅ Sí |
| Créditos RL6 | Aprobación, reportes, pagos | ✅ Sí |
| Créditos R11 | Aprobación, reportes, descuentos | ❌ Nuevo |
| Personal/RRHH | Nómina, turnos, liquidaciones | ✅ Parcial (en personal/) |
| Finanzas | Ingresos, egresos, utilidades, punto equilibrio | ✅ Parcial |
| Configuración | Food trucks, horarios, delivery zones | ✅ Disperso |

**Stack técnico:**
- React + Vite (evolución de admin2)
- Tailwind CSS
- Chart.js para gráficos
- APIs: reutilizar las existentes de caja3/api/

**Estrategia de migración:**
```
Fase 1: Crear admin3 con Dashboard + Productos (lo más usado)
Fase 2: Migrar Ventas + Inventario + Pedidos
Fase 3: Migrar Usuarios + Créditos (RL6 + R11)
Fase 4: Agregar Personal/RRHH + Finanzas
Fase 5: Deprecar admin actual
```

**Estimación:** 4-6 semanas (por fases)

---

## 🟠 CUADRANTE 3: URGENTE + NO IMPORTANTE (Investigar)

### 3.1 Integración Mercado Pago para nómina

**Pregunta:** ¿Se puede usar Mercado Pago empresa para dispersar fondos y pagar nómina?

**Investigación necesaria:**
- API de Mercado Pago: ¿tiene "disbursements" o "payouts"?
- ¿Se puede transferir desde cuenta empresa a cuentas bancarias de trabajadores?
- Costos de comisión por transferencia
- Alternativas: Khipu, Fintoc, transferencias manuales con registro

**Flujo ideal:**
```
Día 1 del mes:
  → Sistema calcula sueldo neto (sueldo base - descuentos R11 - otros)
  → Admin revisa y aprueba nómina
  → Sistema dispersa pagos vía Mercado Pago API
  → Registra pagos en BD
  → Envía liquidación por email a cada trabajador
```

**Nota:** Esto NO bloquea el crédito R11 ni mi3. El descuento de sueldo se puede hacer manualmente al principio y automatizar después.

**Estimación investigación:** 1-2 días
**Estimación implementación:** 1-2 semanas (si la API lo permite)

---

## 🔵 CUADRANTE 4: NI URGENTE NI IMPORTANTE (Backlog)

### 4.1 Refactorizar admin actual

**Decisión:** NO refactorizar. Reemplazar con admin3.

El archivo `caja3/src/pages/admin/index.astro` tiene 6,928 líneas en un solo archivo. Es más eficiente construir admin3 desde cero y migrar funcionalidades gradualmente.

**Acción:** Mantener admin actual funcionando hasta que admin3 tenga paridad de features, luego deprecar.

### 4.2 Limpieza adicional de código

- Eliminar archivos .md de documentación obsoleta en raíz de app3/ y caja3/
- Consolidar APIs duplicadas entre app3 y caja3
- Unificar patrones de conexión a BD (algunos usan PDO, otros mysqli)

---

## 📅 Timeline sugerido

```
Semana 1-2:  Crédito R11 + Bloqueos RL6
Semana 3-4:  mi3 (RRHH) — Módulos básicos
Semana 5-8:  admin3 — Fases 1-2
Semana 9-10: admin3 — Fases 3-4 + Mercado Pago investigación
Semana 11+:  admin3 — Fase 5 + mejoras continuas
```

## 🏗️ Arquitectura final

```
app.laruta11.cl     → app3/   (clientes)
caja.laruta11.cl    → caja3/  (cajeros/POS)
mi.laruta11.cl      → mi3/    (RRHH trabajadores)
admin.laruta11.cl   → admin3/ (gestión negocio)
```

Todas comparten la misma base de datos MySQL y las APIs de `caja3/api/`.
