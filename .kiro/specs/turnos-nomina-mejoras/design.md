# Design Document — Turnos y Nómina: Mejoras

## Overview

Este diseño aborda tres mejoras al sistema de turnos y nómina de mi3: (1) exponer el desglose de reemplazos que ya calcula `LiquidacionService` a través de `PayrollController` hacia el frontend, (2) hacer el calendario de turnos más compacto con avatares y indicadores de reemplazo visibles, y (3) mostrar el crédito R11 pendiente de descuento en la liquidación.

La estrategia es de mínimo cambio: el `LiquidacionService` ya calcula correctamente los reemplazos — solo falta que `PayrollController` exponga esos datos y que el frontend los consuma.

## Arquitectura

### Flujo de datos actual vs. propuesto

```
ACTUAL:
LiquidacionService.calcular() → {total, sueldo_base, reemplazos_hechos, ...}
    ↓
PayrollController.index() → agrega por worker, DESCARTA desglose reemplazos
    ↓
NominaSection.tsx → muestra solo: base, días, reemplazos(número), ajustes, total

PROPUESTO:
LiquidacionService.calcular() → {total, sueldo_base, total_reemplazando, total_reemplazados, 
                                   reemplazos_realizados[], reemplazos_recibidos[], ...}
    ↓
LiquidacionService.calcular() + getCreditoR11Pendiente() → agrega credito_r11_pendiente
    ↓
PayrollController.index() → agrega por worker, INCLUYE desglose reemplazos + crédito R11
    ↓
NominaSection.tsx → muestra: base, días, +reemplazando, -reemplazado, ajustes, total, 
                              crédito R11 pendiente, desglose expandible
```

### Componentes modificados

| Componente | Cambio | Impacto |
|---|---|---|
| `PayrollController.php` | Exponer campos de reemplazo + crédito R11 en respuesta | Backend API |
| `NominaSection.tsx` | Tarjetas expandibles con desglose + crédito R11 | Frontend nómina |
| `TurnosSection.tsx` | Celdas compactas con avatares + indicadores reemplazo | Frontend turnos |

### Componentes con cambio menor

| Componente | Cambio | Razón |
|---|---|---|
| `LiquidacionService.php` | Agregar 2 campos al array de retorno | Ya calcula `$totalReemplazando` y `$totalReemplazados` internamente pero NO los incluye en el array de retorno de `calcular()`. Se deben agregar como `'total_reemplazando'` y `'total_reemplazados'` |

### Componentes NO modificados

| Componente | Razón |
|---|---|
| `ShiftService.php` | Ya retorna datos completos de reemplazos |
| `NominaService.php` | Ya pasa la liquidación completa al controller |
| `R11CreditService.php` | El cron funciona correctamente, no requiere cambios |
| `ShiftController.php` | API de turnos ya retorna toda la info necesaria |

## Diseño detallado

### 0. LiquidacionService — Exponer campos faltantes (Req 1)

**Cambio en `LiquidacionService::calcular()`:**

El método ya calcula `$totalReemplazando` y `$totalReemplazados` (líneas 83-91) pero NO los incluye en el array de retorno. Se deben agregar 2 campos al array de retorno:

```php
return [
    // ... campos existentes ...
    'total_reemplazando' => (int) round($totalReemplazando),   // NUEVO
    'total_reemplazados' => (int) round($totalReemplazados),   // NUEVO
    'total' => $total,
];
```

Este es un cambio de 2 líneas. No altera la lógica de cálculo existente.

### 1. PayrollController — Exposición de desglose (Req 1, 4)

**Cambio en `PayrollController::index()`:**

El loop actual que construye `$workersMap` solo extrae `sueldo_base`, `dias_trabajados`, `reemplazos_hechos`, `ajustes_total` y `gran_total`. Se debe extender para incluir:

```php
// Campos nuevos a agregar en $workersMap:
'total_reemplazando' => $liq['total_reemplazando'] ?? 0,  // suma de ambos centros
'total_reemplazado' => $liq['total_reemplazados'] ?? 0,   // suma de ambos centros  
'reemplazos_realizados' => [...],  // concatenar arrays de ambos centros
'reemplazos_recibidos' => [...],   // concatenar arrays de ambos centros
'credito_r11_pendiente' => null,   // se calcula después del loop
```

**Nota sobre nombres de campo:** El `LiquidacionService` retorna `total_reemplazados` (con 's' final) para el monto descontado. El frontend recibirá `total_reemplazado` (sin 's') para mayor claridad semántica. La conversión se hace en el controller.

**Crédito R11 pendiente:**

Después del loop de centros de costo, para cada worker con `personal.user_id`:

```php
// Pseudocódigo:
$usuario = Usuario::where('id', $personal->user_id)
    ->where('es_credito_r11', 1)
    ->where('credito_r11_usado', '>', 0)
    ->first();

if ($usuario) {
    // Verificar si ya se aplicó el descuento este mes
    $yaDescontado = AjusteSueldo::where('personal_id', $pid)
        ->where('mes', $mesDate)
        ->whereHas('categoria', fn($q) => $q->where('slug', 'descuento_credito_r11'))
        ->exists();
    
    $workersMap[$pid]['credito_r11_pendiente'] = $yaDescontado ? 0 : (float) $usuario->credito_r11_usado;
}
```

### 2. NominaSection — Tarjetas expandibles (Req 2, 4)

**Cambios en la interfaz `WorkerPayroll`:**

```typescript
interface WorkerPayroll {
  personal_id: number;
  nombre: string;
  rol: string;
  sueldo_base: number;
  dias_trabajados: number;
  reemplazos: number;
  ajustes_total: number;
  gran_total: number;
  // Nuevos campos:
  total_reemplazando: number;
  total_reemplazado: number;
  reemplazos_realizados: ReplacementGroup[];
  reemplazos_recibidos: ReplacementGroup[];
  credito_r11_pendiente?: number;
}

interface ReplacementGroup {
  personal_id: number;
  nombre: string;
  dias: number[];
  monto: number;
  pago_por: string;
}
```

**Comportamiento de expansión:**

- Estado `expandedId: number | null` para trackear qué tarjeta está expandida.
- Click en tarjeta → toggle `expandedId`.
- Sección expandida muestra:
  - Sueldo base
  - Reemplazos realizados: lista con nombre, días (ej: "días 5, 12, 18"), monto en verde
  - Reemplazos recibidos: lista con nombre, días, monto en rojo
  - Ajustes: lista con concepto y monto
  - Crédito R11 pendiente (si aplica): monto con 💳 y texto "Se descontará el día 1"

**Tarjeta compacta (estado colapsado):**

Grid de 6 columnas en vez de 4:
```
Base | Días | +Reemp | -Reemp | Ajustes | (se mantiene gran_total arriba)
```

Los campos `+Reemp` y `-Reemp` solo se muestran si son > 0, con colores verde/rojo respectivamente.

### 3. TurnosSection — Calendario compacto (Req 3)

**Cambios en el calendario de escritorio:**

La celda actual muestra solo: día (número grande), nombre del día, y conteo de turnos. Se cambia a:

```
┌─────────────────┐
│ LUN  5          │  ← día + nombre, tamaño reducido
│ 🟠 👤👤 | 🛡👤  │  ← avatares mini agrupados (R11 | Seg) + indicador reemplazo
└─────────────────┘
```

- `min-h-[72px]` en vez de `min-h-[100px]`
- Número del día: `text-lg` en vez de `text-3xl`
- Avatares: círculos de 24px con iniciales o foto miniatura
- Separador visual `|` entre R11 y Seguridad
- Borde izquierdo naranja (`border-l-3 border-orange-400`) si hay reemplazo

**Cambios en la vista móvil:**

Las tarjetas de día actuales muestran: nombre del día, número, conteo. Se agrega:
- Punto naranja (●) de 6px debajo del conteo si hay reemplazo ese día

**Panel de detalle diario (sin cambios estructurales):**

El panel ya muestra avatares con nombre y rol. Para reemplazos, se mejora mostrando:
- Nombre del titular con texto tachado (`line-through`)
- Flecha `→`
- Nombre del reemplazante
- Monto en formato CLP

## Correctness Properties

### Property 1: Invariante de cálculo de gran_total (Req 1.3)

Para cualquier combinación de sueldo_base, total_reemplazando, total_reemplazado y ajustes_total, el gran_total retornado por la API debe ser exactamente igual a `sueldo_base + total_reemplazando - total_reemplazado + ajustes_total`.

```
FOR ALL workers in API response:
  worker.gran_total == worker.sueldo_base + worker.total_reemplazando - worker.total_reemplazado + worker.ajustes_total
```

### Property 2: Consistencia de agregación entre centros de costo (Req 1.1)

Para cualquier trabajador con roles en ambos centros de costo, los totales de reemplazo agregados deben ser iguales a la suma de los totales individuales de cada centro.

```
FOR ALL workers with both ruta11 and seguridad roles:
  worker.total_reemplazando == liq_ruta11.total_reemplazando + liq_seguridad.total_reemplazando
  worker.total_reemplazado == liq_ruta11.total_reemplazados + liq_seguridad.total_reemplazados
```

### Property 3: Filtrado correcto de pago_por en reemplazos (Req 1.4, 1.5)

Para cualquier conjunto de reemplazos con distintos valores de `pago_por`, solo los reemplazos con `pago_por = empresa` contribuyen a `total_reemplazando`, y solo los con `pago_por IN (empresa, empresa_adelanto)` contribuyen a `total_reemplazado`.

```
FOR ALL replacement sets:
  total_reemplazando == SUM(monto WHERE pago_por == 'empresa')
  total_reemplazado == SUM(monto WHERE pago_por IN ('empresa', 'empresa_adelanto'))
```

### Property 4: Crédito R11 pendiente es 0 cuando ya se descontó (Req 4.2)

Para cualquier trabajador que ya tiene un ajuste de tipo `descuento_credito_r11` para el mes consultado, el campo `credito_r11_pendiente` debe ser 0 independientemente del valor de `credito_r11_usado`.

```
FOR ALL workers with existing R11 deduction adjustment for the month:
  worker.credito_r11_pendiente == 0
```

## Test Strategy

### Property-based tests (backend PHP con Pest + Faker)

1. **Invariante gran_total**: Generar workers con valores aleatorios de sueldo_base, reemplazos y ajustes. Verificar que `gran_total == sueldo_base + total_reemplazando - total_reemplazado + ajustes_total`.
2. **Agregación cross-centro**: Generar liquidaciones para ambos centros de costo y verificar que la suma coincide con el agregado.
3. **Filtrado pago_por**: Generar reemplazos con distintos `pago_por` y verificar que solo los correctos se suman.
4. **Crédito R11 idempotencia**: Verificar que si ya existe el ajuste de descuento, el pendiente es siempre 0.

### Example-based tests (frontend)

- Verificar que tarjetas muestran colores correctos para reemplazos positivos/negativos.
- Verificar que el calendario muestra indicadores de reemplazo.
- Verificar que el crédito R11 pendiente se muestra con el formato correcto.

## Migration Plan

No se requieren migraciones de base de datos. Todos los campos necesarios ya existen:
- `turnos.monto_reemplazo`, `turnos.pago_por`, `turnos.reemplazado_por` — ya en uso
- `usuarios.credito_r11_usado`, `usuarios.es_credito_r11` — ya en uso
- `ajustes_sueldo.categoria_id` — ya en uso con categoría `descuento_credito_r11`

## Deployment Notes

- Backend y frontend se despliegan independientemente via Coolify.
- El cambio es backward-compatible: el frontend simplemente ignora campos nuevos si el backend no los envía aún.
- No hay cambios en la base de datos, por lo que no se requiere coordinación de migraciones.
