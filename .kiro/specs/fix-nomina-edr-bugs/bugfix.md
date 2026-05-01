# Bugfix Requirements Document

## Introduction

El sistema de nómina y Estado de Resultados (EdR) presenta 4 bugs interrelacionados que afectan la correcta asignación temporal de descuentos salariales y la visibilidad de costos de nómina en el dashboard financiero. Los crons de descuento automático (crédito R11 y adelantos) asignan los ajustes al mes incorrecto, el EdR muestra $0 en nómina para meses pasados, y persiste un error React #185 (Maximum update depth exceeded) en el panel de administración.

---

## Bug Analysis

### Current Behavior (Defect)

**Bug 1 — Descuento crédito R11 asignado al mes incorrecto:**

1.1 WHEN the `mi3:r11-auto-deduct` cron runs on the 1st of month M at 06:00 Chile time THEN the system creates the salary adjustment (`AjusteSueldo`) with `mes = M` (the current month) instead of `M-1` (the month when the credit was actually consumed)

1.2 WHEN the `mi3:r11-auto-deduct` cron runs on May 1st THEN the system creates the adjustment with `mes = 2026-05-01` and `concepto = "Descuento Crédito R11 - mayo"` when it should be `mes = 2026-04-01` and `concepto = "Descuento Crédito R11 - abril"`

**Bug 2 — Descuento adelanto de sueldo asignado al mes incorrecto:**

1.3 WHEN the `mi3:loan-auto-deduct` cron runs on the 1st of month M at 06:30 Chile time THEN the system creates the salary adjustment with `mes = M` instead of `mes = M-1` (the month when the loan was approved and should be deducted)

1.4 WHEN the `mi3:loan-auto-deduct` cron runs on May 1st THEN the system creates the adjustment with `mes = 2026-05-01` and `concepto = "Descuento adelanto de sueldo - mayo"` when it should be `mes = 2026-04-01` and `concepto = "Descuento adelanto de sueldo - abril"`

**Bug 3 — EdR muestra $0 en nómina para meses pasados:**

1.5 WHEN the admin views the Estado de Resultados (EdR) for a past month (e.g., April when today is May) AND there are no records in `pagos_nomina` or `compras` with `tipo_compra='nomina'` for that month THEN the system shows $0 for nómina cost because the `NominaService` fallback is only used when `$isCurrentMonth` is true

1.6 WHEN the admin navigates to a historical month in the EdR THEN the system skips the `NominaService` calculation (shift-based nómina) even though it is the only available source of nómina data for that month

**Bug 4 — React Error #185 (Maximum update depth exceeded) en dashboard admin:**

1.7 WHEN the admin dashboard loads and the `NominaSection` component registers its header config via `onHeaderConfig` THEN the system may enter an infinite re-render loop because the `useEffect` that calls `onHeaderConfig` includes `data` in its dependency array, and the `trailing` JSX is recreated on every render (new object reference), causing `setHeaderConfigs` in `AdminShell` to detect a "change" and trigger a re-render cascade

### Expected Behavior (Correct)

**Bug 1 — Descuento crédito R11:**

2.1 WHEN the `mi3:r11-auto-deduct` cron runs on the 1st of month M THEN the system SHALL create the salary adjustment with `mes = M-1` (previous month) using `now()->subMonth()->format('Y-m')` so the deduction is attributed to the month when the credit was consumed

2.2 WHEN the `mi3:r11-auto-deduct` cron runs on May 1st THEN the system SHALL create the adjustment with `mes = 2026-04-01` and `concepto = "Descuento Crédito R11 - abril"`

**Bug 2 — Descuento adelanto de sueldo:**

2.3 WHEN the `mi3:loan-auto-deduct` cron runs on the 1st of month M THEN the system SHALL create the salary adjustment with `mes = M-1` (previous month) using `now()->subMonth()->format('Y-m')` so the deduction is attributed to the correct payroll month

2.4 WHEN the `mi3:loan-auto-deduct` cron runs on May 1st THEN the system SHALL create the adjustment with `mes = 2026-04-01` and `concepto = "Descuento adelanto de sueldo - abril"`

**Bug 3 — EdR nómina para meses pasados:**

2.5 WHEN the admin views the EdR for any month (current or past) AND there are no records in `pagos_nomina` or `compras` with `tipo_compra='nomina'` THEN the system SHALL use `NominaService` as a fallback to calculate nómina cost from shifts and contracts, regardless of whether it is the current month

2.6 WHEN the admin navigates to a historical month in the EdR THEN the system SHALL always attempt the `NominaService` calculation as a final fallback, removing the `$isCurrentMonth` guard condition

**Bug 4 — React Error #185:**

2.7 WHEN the `NominaSection` (or any section) registers its header config via `onHeaderConfig` THEN the system SHALL avoid infinite re-render loops by stabilizing the `trailing` JSX reference (e.g., via `useMemo`) so that `setHeaderConfigs` in `AdminShell` does not detect false changes and trigger cascading re-renders

### Unchanged Behavior (Regression Prevention)

**Descuentos automáticos:**

3.1 WHEN the `mi3:r11-auto-deduct` cron runs THEN the system SHALL CONTINUE TO correctly identify all eligible debtors (`es_credito_r11 = 1`, `credito_r11_usado > 0`, active personal), create the negative `AjusteSueldo`, create the `R11CreditTransaction` refund, and reset `credito_r11_usado` to 0

3.2 WHEN the `mi3:loan-auto-deduct` cron runs THEN the system SHALL CONTINUE TO correctly identify all approved loans with pending installments, create the negative `AjusteSueldo`, and mark the loan as `pagado`

3.3 WHEN a loan is approved mid-month via `aprobar()` THEN the system SHALL CONTINUE TO create the immediate negative salary adjustment for the current month with the correct `mes` value (this is the approval-time deduction, separate from the cron-based monthly deduction)

**EdR y Dashboard:**

3.4 WHEN the admin views the EdR for the current month THEN the system SHALL CONTINUE TO fetch live sales data from `caja.laruta11.cl`, calculate CMV from `VentasService`, and show real-time nómina from `NominaService`

3.5 WHEN `pagos_nomina` records exist for a given month THEN the system SHALL CONTINUE TO use those as the primary source of nómina cost (they represent actual payments and are the source of truth)

3.6 WHEN the admin dashboard loads THEN the system SHALL CONTINUE TO render all lazy-loaded sections (DashboardSection, NominaSection, etc.) with error boundaries, WebSocket live updates, and SPA navigation without crashes

**Cálculo de liquidaciones:**

3.7 WHEN calculating payroll via `LiquidacionService` THEN the system SHALL CONTINUE TO correctly compute base salary, replacement earnings/deductions, and adjustments for each cost center (ruta11, seguridad)

3.8 WHEN the scheduler runs any of the 11 registered cron commands THEN the system SHALL CONTINUE TO log execution in `cron_executions` and send Telegram alerts on failure
