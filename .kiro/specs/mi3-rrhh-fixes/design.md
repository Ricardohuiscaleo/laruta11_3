# mi3-rrhh-fixes Bugfix Design

## Overview

Este documento cubre el diseño técnico para corregir tres bugs en el módulo RRHH/admin de mi3:

1. **Rotate Foto 500** — Import faltante de `Illuminate\Http\Request` en `PersonalController.php` (ya staged, solo formalizar).
2. **Inasistencia turno nocturno** — `DetectAbsencesCommand` consulta turnos del día D en vez de D-1, causando que turnos nocturnos no se detecten o se atribuyan a la fecha incorrecta.
3. **Nómina vacía** — El backend retorna `{ruta11, seguridad}` pero el frontend espera `{resumen, centros}`, dejando la página vacía.

## Glossary

- **Bug_Condition (C)**: Condición que dispara cada bug específico
- **Property (P)**: Comportamiento correcto esperado tras el fix
- **Preservation**: Comportamiento existente que no debe cambiar
- **PersonalController**: Controller en `app/Http/Controllers/Admin/PersonalController.php` que maneja CRUD de personal
- **DetectAbsencesCommand**: Comando artisan `mi3:detect-absences` que corre a medianoche para detectar inasistencias
- **AttendanceService**: Servicio en `app/Services/Checklist/AttendanceService.php` con método `detectarAusencias()`
- **NominaService**: Servicio en `app/Services/Payroll/NominaService.php` con método `getResumen()` que retorna datos agrupados por centro de costo
- **PayrollController**: Controller en `app/Http/Controllers/Admin/PayrollController.php` que expone `GET /admin/payroll`
- **WorkerPayroll**: Shape esperada por el frontend: `{personal_id, nombre, rol, sueldo_base, dias_trabajados, reemplazos, ajustes_total, gran_total}`
- **PayrollSummary**: Shape esperada por el frontend: `{centro_costo, presupuesto, total_sueldos, total_pagado, diferencia}`

## Bug Details

### Bug 1: Rotate Foto 500

El endpoint `PATCH /api/v1/admin/personal/{id}/rotate-foto` retornaba HTTP 500 porque `PersonalController.php` no tenía el import `use Illuminate\Http\Request`. El import ya fue agregado y staged.

**Formal Specification:**
```
FUNCTION isBugCondition_RotateFoto(input)
  INPUT: input of type HTTPRequest
  OUTPUT: boolean

  RETURN input.method = "PATCH"
         AND input.path MATCHES "/api/v1/admin/personal/{id}/rotate-foto"
         AND PersonalController MISSING import "Illuminate\Http\Request"
END FUNCTION
```

### Bug 2: Inasistencia Fecha Incorrecta (Turno Nocturno)

`DetectAbsencesCommand::handle()` usa `$fecha = now()->format('Y-m-d')` (fecha D) y pasa esa fecha a `AttendanceService::detectarAusencias()`, que consulta `Turno::whereDate('fecha', $fecha)`. Los turnos nocturnos (ej: 18:00 a 01:00) tienen `fecha = D-1` (fecha de inicio del turno). Al correr a medianoche de D, el comando busca turnos con `fecha = D` y no encuentra los turnos nocturnos de D-1.

**Formal Specification:**
```
FUNCTION isBugCondition_NightShift(input)
  INPUT: input of type DetectionContext { runDate: Date, turno: Turno }
  OUTPUT: boolean

  RETURN input.turno.hora_inicio >= "18:00"
         AND input.turno.fecha = input.runDate - 1 day
         AND detectarAusencias(input.runDate) queries Turno WHERE fecha = input.runDate
         AND turno NOT FOUND (because turno.fecha = runDate - 1)
END FUNCTION
```

### Bug 3: Nómina Vacía (Data Shape Mismatch)

`PayrollController::index()` retorna directamente `NominaService::getResumen()` que tiene shape `{ruta11: {personal, pagos, presupuesto}, seguridad: {personal, pagos, presupuesto}}`. El frontend (`app/admin/nomina/page.tsx`) espera `{resumen: WorkerPayroll[], centros: PayrollSummary[]}`. Como `data.resumen` y `data.centros` son `undefined`, la página queda vacía.

**Formal Specification:**
```
FUNCTION isBugCondition_Nomina(input)
  INPUT: input of type PayrollAPIResponse
  OUTPUT: boolean

  RETURN input.data HAS KEYS ("ruta11", "seguridad")
         AND input.data NOT HAS KEYS ("resumen", "centros")
END FUNCTION
```

### Examples

- **Bug 1**: Admin hace PATCH `/api/v1/admin/personal/5/rotate-foto` con `{rotation: 90}` → recibe 500 en vez de `{success: true}`
- **Bug 2**: Planchero tiene turno nocturno `fecha=2025-01-14, hora=18:00-01:00`. Comando corre el 2025-01-15 a medianoche. Busca turnos con `fecha=2025-01-15`, no encuentra el turno (que tiene `fecha=2025-01-14`). Inasistencia no se detecta o se crea con fecha incorrecta.
- **Bug 3**: Admin abre `/admin/nomina` → API retorna `{ruta11: {...}, seguridad: {...}}` → frontend lee `data.resumen` que es `undefined` → no renderiza ningún card de trabajador ni resumen de centro de costo.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Todos los endpoints de PersonalController (index, store, update, toggle) deben seguir funcionando sin cambios
- Detección de inasistencias para turnos diurnos (cajero, administrador) debe seguir funcionando correctamente
- Trabajadores que completaron checklist (presencial o virtual) no deben recibir descuento
- `NominaService::getResumen()` debe seguir calculando liquidaciones correctamente (no modificar el servicio)
- Navegación de meses (prev/next) en la página de nómina debe seguir funcionando
- Endpoints de pagos, presupuesto y envío de liquidaciones no deben cambiar

**Scope:**
Los cambios son mínimos y focalizados: un import (Bug 1), un `subDay()` en la fecha de consulta (Bug 2), y una transformación de datos en el controller (Bug 3). Ningún cambio afecta la lógica de cálculo subyacente.

## Hypothesized Root Cause

### Bug 1: Import Faltante
`PersonalController.php` no tenía `use Illuminate\Http\Request;`. El método `rotateFoto(Request $request, int $id)` referencia `Request` como type hint, y sin el import PHP no puede resolver la clase → 500.

**Estado**: Ya confirmado y staged. El import ya existe en el archivo actual.

### Bug 2: Fecha de Consulta Incorrecta
En `DetectAbsencesCommand::handle()`, línea `$fecha = now()->format('Y-m-d')` obtiene la fecha actual D. Luego `AttendanceService::detectarAusencias($fecha)` ejecuta `Turno::whereDate('fecha', $fecha)`. Los turnos nocturnos tienen `fecha = D-1` (la fecha de inicio), por lo que no aparecen en la consulta.

**Root cause**: El comando debería pasar `D-1` (ayer) como fecha para detectar inasistencias de turnos que ya terminaron.

### Bug 3: Shape Mismatch
`NominaService::getResumen()` retorna datos agrupados por centro de costo (`ruta11`, `seguridad`) con estructura `{personal: [{personal, liquidacion}], pagos, presupuesto}`. El frontend espera una estructura plana: `resumen` (array flat de WorkerPayroll) y `centros` (array de PayrollSummary). No hay transformación intermedia en `PayrollController::index()`.

**Root cause**: Falta transformar la respuesta del servicio al shape que espera el frontend, en el controller.

## Correctness Properties

Property 1: Bug Condition - Rotate Foto Returns 200

_For any_ PATCH request to `/api/v1/admin/personal/{id}/rotate-foto` with a valid `rotation` value, the fixed PersonalController SHALL return HTTP 200 with `{success: true}` and update the `foto_rotation` field.

**Validates: Requirements 2.1**

Property 2: Bug Condition - Night Shift Absence Uses D-1

_For any_ execution of `DetectAbsencesCommand` on date D where a worker had a night shift on D-1 and did not complete their checklist, the fixed command SHALL detect the absence by querying shifts for date D-1 and create an ajuste with concepto "Inasistencia D-1" (the shift's actual date).

**Validates: Requirements 2.2, 2.3**

Property 3: Bug Condition - Nómina Returns Expected Shape

_For any_ GET request to `/admin/payroll?mes=YYYY-MM`, the fixed PayrollController SHALL return `data.resumen` as an array of WorkerPayroll objects and `data.centros` as an array of PayrollSummary objects matching the frontend interface.

**Validates: Requirements 2.4, 2.5**

Property 4: Preservation - Other PersonalController Endpoints Unchanged

_For any_ request to PersonalController endpoints other than rotate-foto, the fixed code SHALL produce exactly the same behavior as the original code.

**Validates: Requirements 3.1**

Property 5: Preservation - Daytime Shift Absence Detection Unchanged

_For any_ daytime shift (non-night shift) absence detection, the fixed code SHALL produce the same results as the original code, correctly detecting absences and creating ajustes with the correct date.

**Validates: Requirements 3.2, 3.3**

Property 6: Preservation - Payroll Calculations Unchanged

_For any_ payroll calculation, the underlying liquidation values (sueldo_base, dias_trabajados, ajustes, totals) SHALL remain identical — only the response shape changes in the controller.

**Validates: Requirements 3.4, 3.5**

## Fix Implementation

### Changes Required

#### Bug 1: Rotate Foto 500

**File**: `mi3/backend/app/Http/Controllers/Admin/PersonalController.php`

**Status**: Ya corregido y staged. El import `use Illuminate\Http\Request;` ya existe en el archivo.

No se requieren cambios adicionales.

#### Bug 2: Night Shift Absence Date

**File**: `mi3/backend/app/Console/Commands/DetectAbsencesCommand.php`

**Function**: `handle()`

**Specific Changes**:
1. **Cambiar fecha de detección a D-1**: Reemplazar `$fecha = now()->format('Y-m-d')` por `$fecha = now()->subDay()->format('Y-m-d')` para que el comando (que corre a medianoche de D) evalúe los turnos de D-1, que ya terminaron.

**File**: `mi3/backend/app/Services/Checklist/AttendanceService.php`

**Function**: `detectarAusencias()`

No requiere cambios internos — ya recibe `$fecha` como parámetro y consulta `Turno::whereDate('fecha', $fecha)`. Al recibir D-1 desde el comando, encontrará correctamente los turnos nocturnos. El concepto del ajuste ya usa `$fecha` directamente: `"Inasistencia {$fecha}"`, que será D-1 (correcto).

#### Bug 3: Nómina Data Shape Transform

**File**: `mi3/backend/app/Http/Controllers/Admin/PayrollController.php`

**Function**: `index()`

**Specific Changes**:
1. **Obtener datos del servicio**: Mantener la llamada a `$this->nominaService->getResumen($mes)` sin cambios.
2. **Transformar `resumen`**: Iterar sobre `ruta11.personal` y `seguridad.personal`, aplanar cada entrada a un objeto `WorkerPayroll` con campos: `personal_id`, `nombre`, `rol`, `sueldo_base`, `dias_trabajados`, `reemplazos` (suma de reemplazos hechos), `ajustes_total`, `gran_total` (= liquidacion.total).
3. **Transformar `centros`**: Crear dos entradas (ruta11, seguridad) con campos: `centro_costo`, `presupuesto`, `total_sueldos` (suma de sueldo_base de todos los workers del centro), `total_pagado` (suma de pagos registrados), `diferencia` (presupuesto - total_sueldos).
4. **Deduplicar workers**: Un worker puede aparecer en ambos centros de costo. En `resumen`, incluir una entrada por worker con los totales combinados (sumar sueldo_base, dias_trabajados, etc. de ambos centros).
5. **Retornar nueva shape**: `response()->json(['success' => true, 'data' => ['resumen' => [...], 'centros' => [...]]])`.

## Testing Strategy

### Validation Approach

La estrategia de testing sigue dos fases: primero, confirmar que los bugs se reproducen en el código sin fix, luego verificar que el fix corrige el problema y preserva el comportamiento existente.

### Exploratory Bug Condition Checking

**Goal**: Confirmar los bugs antes de implementar los fixes.

**Test Cases**:
1. **Bug 1 - Rotate Foto**: Enviar PATCH a `/api/v1/admin/personal/{id}/rotate-foto` sin el import → verificar que retorna 500 (ya confirmado, import staged)
2. **Bug 2 - Night Shift**: Crear turno nocturno con `fecha=D-1`, correr comando en D → verificar que no detecta la inasistencia porque busca `fecha=D`
3. **Bug 3 - Nómina Shape**: Llamar `GET /admin/payroll?mes=2025-01` → verificar que la respuesta tiene keys `ruta11`/`seguridad` pero no `resumen`/`centros`

**Expected Counterexamples**:
- Bug 1: HTTP 500 con "Class Request not found"
- Bug 2: `detectarAusencias(D)` retorna `{ausentes: [], total: 0}` cuando debería detectar al planchero ausente
- Bug 3: `data.resumen` es `undefined` en la respuesta

### Fix Checking

**Goal**: Verificar que cada fix produce el comportamiento esperado.

**Pseudocode:**
```
// Bug 1
FOR ALL request WHERE request.method = "PATCH" AND request.path = "/rotate-foto" DO
  result := rotateFoto_fixed(request)
  ASSERT result.status = 200 AND result.body.success = true
END FOR

// Bug 2
FOR ALL context WHERE turno.fecha = D-1 AND turno.hora_inicio >= "18:00" DO
  result := detectAbsences_fixed(D)  // comando corre en D, pero internamente usa D-1
  ASSERT result.ausentes CONTAINS turno.personal_id
  ASSERT ajuste.concepto = "Inasistencia D-1"
END FOR

// Bug 3
FOR ALL mes IN valid_months DO
  response := payrollIndex_fixed(mes)
  ASSERT response.data HAS KEY "resumen"
  ASSERT response.data HAS KEY "centros"
  ASSERT EACH worker IN response.data.resumen HAS KEYS (personal_id, nombre, rol, sueldo_base, dias_trabajados, reemplazos, ajustes_total, gran_total)
  ASSERT EACH centro IN response.data.centros HAS KEYS (centro_costo, presupuesto, total_sueldos, total_pagado, diferencia)
END FOR
```

### Preservation Checking

**Goal**: Verificar que el comportamiento existente no cambia.

**Pseudocode:**
```
// Bug 1 - otros endpoints no afectados
FOR ALL request WHERE request.path NOT MATCHES "/rotate-foto" DO
  ASSERT PersonalController.original(request) = PersonalController.fixed(request)
END FOR

// Bug 2 - turnos diurnos siguen detectándose correctamente
FOR ALL context WHERE turno.hora_inicio < "18:00" DO
  ASSERT detectAbsences_original(context) = detectAbsences_fixed(context)
END FOR

// Bug 3 - cálculos de liquidación no cambian
FOR ALL (personal, mes) DO
  ASSERT LiquidacionService.calcular(personal, mes) is unchanged
END FOR
```

**Testing Approach**: Property-based testing es recomendado para preservation checking del Bug 3, generando múltiples combinaciones de workers/meses y verificando que los valores numéricos en la respuesta transformada coinciden con los del servicio original.

**Test Cases**:
1. **PersonalController Preservation**: Verificar que index, store, update, toggle siguen retornando las mismas respuestas
2. **Daytime Shift Preservation**: Crear turnos diurnos, correr detección → verificar que se detectan correctamente con D-1
3. **Payroll Values Preservation**: Comparar valores numéricos de `NominaService::getResumen()` con los valores en la respuesta transformada

### Unit Tests

- Test `rotateFoto()` con import presente → retorna 200
- Test `DetectAbsencesCommand::handle()` usa `now()->subDay()` como fecha
- Test `AttendanceService::detectarAusencias()` con fecha D-1 encuentra turnos nocturnos
- Test transformación en `PayrollController::index()` produce shape correcta
- Test que workers con roles en ambos centros se combinan correctamente en `resumen`

### Property-Based Tests

- Generar combinaciones aleatorias de workers con distintos roles y verificar que `resumen` contiene todos los workers activos con liquidación > 0
- Generar meses aleatorios y verificar que `centros` siempre tiene exactamente 2 entradas (ruta11, seguridad)
- Generar turnos con horas aleatorias y verificar que la detección de inasistencias usa la fecha correcta

### Integration Tests

- Test flujo completo: crear personal → crear turno nocturno → correr detección → verificar ajuste con fecha correcta
- Test flujo nómina: crear personal con turnos y ajustes → GET /admin/payroll → verificar que el frontend puede renderizar los datos
- Test que la navegación de meses en nómina retorna datos con la shape correcta para cada mes
