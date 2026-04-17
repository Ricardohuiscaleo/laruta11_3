# Checklist Cash Verification Bugfix Design

## Overview

Cuatro bugs afectan el sistema de checklists diarios de caja3 (POS) integrado con mi3-backend. Los bugs impactan: (1) la asignación incorrecta de checklists a workers que no son cajeros/plancheros regulares, (2) la falta de formato de moneda chilena en el input de verificación de caja, (3) el envío incorrecto del parámetro `contexto` en la subida de fotos que produce análisis IA duplicados/irrelevantes, y (4) la serialización de `scheduled_date` como objeto Carbon que el frontend no puede parsear. El fix es quirúrgico: cambios mínimos en 4 archivos sin alterar la arquitectura existente.

## Glossary

- **Bug_Condition (C)**: Conjunto de condiciones de entrada que desencadenan cada uno de los 4 bugs
- **Property (P)**: El comportamiento correcto esperado para cada bug cuando se corrige
- **Preservation**: Comportamientos existentes que NO deben cambiar: mouse clicks, progress bar, S3 upload, Telegram notifications, cash refresh, etc.
- **`crearChecklistsDiarios()`**: Función en `ChecklistService.php` que genera checklists de apertura/cierre para todos los workers con turno en una fecha dada
- **`Personal.getRolesArray()`**: Método que parsea el campo `rol` (string CSV) del modelo Personal en un array de roles
- **`Turno.tipo`**: Campo que indica el tipo de turno: `normal`, `seguridad`, `reemplazo`, `reemplazo_seguridad` — determina si el turno es de R11 (food truck) o de seguridad
- **`contexto`**: Parámetro que identifica el tipo de foto para seleccionar el prompt IA correcto (ej. `interior_apertura`, `exterior_cierre`, `plancha_apertura`)

## Bug Details

### Bug Condition

Los 4 bugs se manifiestan en condiciones independientes:

**Bug 1 — Wrong Worker Assignment**: Ocurre cuando `crearChecklistsDiarios()` genera checklists para un worker cuyo campo `personal.rol` contiene `cajero` o `planchero` como rol secundario (ej. Ricardo es `administrador,seguridad,cajero`), pero su turno asignado es de tipo `seguridad`. El sistema no verifica si el turno es de R11 (food truck) o de seguridad.

**Bug 2 — Currency Formatting**: Ocurre cuando el usuario escribe un monto numérico en el `<input type="number">` de verificación de caja. El input nativo no formatea con separador de miles ni prefijo `$`, causando confusión (ej. "29" parece $29.000 pero envía $29).

**Bug 3 — Duplicate IA Analysis**: Ocurre cuando caja3 sube una foto vía `handleUploadPhoto()` — el `FormData` solo incluye el archivo `photo` pero NO el parámetro `contexto`. El backend `ChecklistController@uploadPhoto` usa `$request->input('contexto', 'interior_apertura')` como default, causando que todas las fotos usen el mismo prompt IA.

**Bug 4 — Invalid Date**: Ocurre cuando el modelo `Checklist` serializa `scheduled_date` (cast como `date` → Carbon) a JSON. Laravel serializa Carbon como ISO 8601 completo (ej. `"2026-04-15T00:00:00.000000Z"`), pero mi3-frontend no parsea correctamente este formato, mostrando "Invalid Date".

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type {bug: 1|2|3|4, context: any}
  OUTPUT: boolean

  IF input.bug == 1:
    worker := Personal.find(input.personalId)
    turno := Turno.where(fecha=input.fecha, personal_id=input.personalId)
    roles := worker.getRolesArray()
    RETURN 'cajero' IN roles OR 'planchero' IN roles
           AND turno.tipo IN ['seguridad', 'reemplazo_seguridad']
           -- Worker has cajero/planchero as secondary role but is working security shift

  IF input.bug == 2:
    RETURN input.fieldType == 'number'
           AND input.userIsTyping == true
           AND input.itemType == 'cash_verification'
           -- Number input doesn't show Chilean currency formatting

  IF input.bug == 3:
    RETURN input.formData.has('photo')
           AND NOT input.formData.has('contexto')
           AND input.item.requires_photo == true
           -- Photo uploaded without contexto parameter

  IF input.bug == 4:
    RETURN typeof(input.scheduled_date) == 'Carbon'
           AND JSON.serialize(input.scheduled_date) contains 'T'
           -- Carbon serializes as ISO 8601, frontend can't parse
END FUNCTION
```

### Examples

- **Bug 1**: Ricardo (id=5, rol=`administrador,seguridad,cajero`) tiene turno tipo `seguridad` el 15/04. `crearChecklistsDiarios()` genera checklist de cajero para Ricardo. Camila (id=1, cajera real) ve el checklist de Ricardo primero y lo completa. Su propio checklist queda en 0%.
- **Bug 2**: Camila abre verificación de caja, presiona "No", ve input con "¿Cuánto hay en caja?". Escribe "29000" pero el input muestra `29000` sin formato. Si escribe "29" pensando que son miles, envía $29 al backend.
- **Bug 3**: Camila sube foto del exterior del food truck. El backend recibe `contexto='interior_apertura'` (default), analiza con prompt de interior, y la IA evalúa "piso limpio" en una foto de mesas y sillas exteriores.
- **Bug 4**: mi3-frontend muestra detalle de checklist. `scheduled_date` llega como `"2026-04-15T00:00:00.000000Z"`. JavaScript `new Date("2026-04-15T00:00:00.000000Z")` debería funcionar, pero el componente frontend usa un parsing que falla → "Invalid Date".

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- 3.1: El endpoint `today` SHALL CONTINUE TO refrescar `cash_expected` de items `cash_verification` no completados usando el último `saldo_nuevo` de `caja_movimientos`
- 3.2: La subida de fotos SHALL CONTINUE TO comprimir en frontend (max 800px, JPEG 0.8), subir a S3, marcar ítem completado, y ejecutar análisis IA no bloqueante
- 3.3: El flujo "Sí" en verificación de caja SHALL CONTINUE TO enviar `confirmed: true` con `actual_amount` igual a `cash_expected`, registrar "ok", y notificar vía Telegram
- 3.4: La creación automática de checklists SHALL CONTINUE TO leer turnos del día, considerar `reemplazado_por`, crear por rol y tipo, y poblar items desde templates
- 3.5: Checklists completados SHALL CONTINUE TO mostrar estado "Completado" con 100% y check verde
- 3.6: Discrepancias de caja SHALL CONTINUE TO enviar notificaciones push y Telegram a administradores

**Scope:**
Los fixes son aditivos y quirúrgicos — agregan filtros y lógica sin modificar flujos existentes. Cualquier input que NO active las condiciones de bug debe producir exactamente el mismo resultado que antes del fix.

## Hypothesized Root Cause

### Bug 1 — Wrong Worker Assignment

**Root Cause: `crearChecklistsDiarios()` no filtra por tipo de turno.**

En `ChecklistService.php` líneas 47-55, el loop itera sobre todos los turnos del día, determina el worker efectivo (`reemplazado_por` o `personal_id`), y luego usa `$personal->getRolesArray()` para obtener los roles. El método `getRolesArray()` parsea el campo `personal.rol` (ej. `"administrador,seguridad,cajero"`) y `array_intersect` con `['cajero', 'planchero']` devuelve `['cajero']` para Ricardo.

El problema es que el turno de Ricardo es `tipo='seguridad'` — no está trabajando en el food truck (R11), sino de guardia. Los checklists de cajero/planchero solo tienen sentido para turnos de R11 (`tipo='normal'` o `tipo='reemplazo'`).

### Bug 2 — Currency Formatting

**Root Cause: `<input type="number">` no soporta formato visual.**

En `ChecklistApp.jsx` línea ~342, el input es `type="number"` con `inputMode="numeric"`. Los inputs nativos de tipo number solo muestran dígitos crudos sin separadores de miles ni prefijo `$`. No hay lógica de formateo on-change.

### Bug 3 — Duplicate IA Analysis

**Root Cause: `handleUploadPhoto()` no incluye `contexto` en FormData.**

En `ChecklistApp.jsx` líneas 73-83, `handleUploadPhoto` crea un `FormData` con solo `formData.append('photo', compressed, 'photo.jpg')`. No se agrega `contexto`. El backend `ChecklistController@uploadPhoto` usa `$request->input('contexto', 'interior_apertura')` — siempre cae al default.

El item tiene `description` que identifica el sector (ej. "📸 FOTO interior", "📸 FOTO exterior", "📸 FOTO plancha"), y el checklist tiene `type` (apertura/cierre). Con ambos datos se puede derivar el `contexto` correcto.

### Bug 4 — Invalid Date

**Root Cause: El modelo `Checklist` no customiza la serialización de fechas.**

En `Checklist.php`, `scheduled_date` tiene cast `'date'`, que lo convierte a Carbon. Laravel 10+ serializa Carbon dates como ISO 8601 (`2026-04-15T00:00:00.000000Z`). Aunque JavaScript puede parsear ISO 8601, el frontend (mi3-frontend, Next.js) puede tener un formato esperado diferente o un parser que falla con el formato de microsegundos. La solución es serializar como `Y-m-d` string simple.

## Correctness Properties

Property 1: Bug Condition - Worker Assignment Filters Security Shifts

_For any_ turno where `tipo` is `seguridad` or `reemplazo_seguridad`, `crearChecklistsDiarios()` SHALL NOT generate cajero or planchero checklists for the worker assigned to that shift, even if the worker's `personal.rol` field contains `cajero` or `planchero`.

**Validates: Requirements 2.1**

Property 2: Bug Condition - Currency Input Shows Chilean Format

_For any_ numeric input in the cash verification field, the UI SHALL display the value formatted as Chilean currency with `$` prefix and dot-separated thousands (e.g., typing "29000" shows "$29.000"), while sending the raw numeric value (29000) to the backend.

**Validates: Requirements 2.2**

Property 3: Bug Condition - Photo Upload Sends Correct Contexto

_For any_ photo upload for a checklist item that requires a photo, the frontend SHALL send the `contexto` parameter derived from the item's description and the checklist's type (apertura/cierre), matching one of the valid `PhotoAnalysisService::PROMPTS` keys.

**Validates: Requirements 2.3**

Property 4: Bug Condition - Scheduled Date Serializes as Y-m-d

_For any_ Checklist serialized to JSON, the `scheduled_date` field SHALL be a string in `Y-m-d` format (e.g., `"2026-04-15"`), parseable by JavaScript `new Date()` without returning "Invalid Date".

**Validates: Requirements 2.4**

Property 5: Preservation - Existing Behavior Unchanged

_For any_ input that does NOT trigger the bug conditions (mouse clicks on buttons, non-security-shift workers, already-completed items, non-photo items), the fixed code SHALL produce exactly the same behavior as the original code, preserving all existing functionality including cash refresh, S3 upload, AI analysis, Telegram notifications, and progress tracking.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:


**File 1**: `mi3/backend/app/Services/Checklist/ChecklistService.php`

**Function**: `crearChecklistsDiarios()`

**Bug 1 Fix — Filter by turno tipo:**
1. **Add turno tipo check**: After determining `$personalId`, check if the turno `tipo` is `seguridad` or `reemplazo_seguridad`. If so, skip generating cajero/planchero checklists for this worker.
2. **Logic**: Solo generar checklists de cajero/planchero para turnos de R11 (`tipo='normal'` o `tipo='reemplazo'`). Los turnos de seguridad no trabajan en el food truck.
3. **Specific change**: After `$personal = Personal::find($personalId)`, add:
   ```php
   // Only generate cajero/planchero checklists for R11 shifts (not security)
   if (in_array($turno->tipo, ['seguridad', 'reemplazo_seguridad'])) {
       continue;
   }
   ```

---

**File 2**: `caja3/src/components/ChecklistApp.jsx`

**Function**: `ChecklistItemCard` (cash verification input)

**Bug 2 Fix — Chilean currency formatting:**
1. **Change input type**: Replace `type="number"` with `type="text"` and `inputMode="numeric"`
2. **Add format helper**: Create a `formatCLP(value)` function that formats as `$XX.XXX` (dot thousands separator, `$` prefix)
3. **Add parse helper**: Create a `parseCLP(formatted)` function that strips `$` and dots to get raw number
4. **On change**: Format the display value while storing the raw number for submission
5. **On submit**: Send the raw numeric value (`parseCLP(cashAmount)`) to `onVerifyCash`

---

**Function**: `handleUploadPhoto`

**Bug 3 Fix — Send contexto parameter:**
1. **Add contexto derivation**: Create a `getPhotoContexto(item, checklistType)` function that maps item description keywords to valid contexto values
2. **Mapping logic**:
   - Description contains "interior" → `interior_{type}` (ej. `interior_apertura`)
   - Description contains "exterior" → `exterior_{type}`
   - Description contains "plancha" → `plancha_{type}`
   - Description contains "lavaplatos" y "mesón" → `lavaplatos_meson_{type}`
   - Description contains "lavaplatos" → `lavaplatos_{type}`
   - Description contains "mesón" or "meson" → `meson_{type}`
   - Default fallback → `interior_{type}`
3. **Append to FormData**: Add `formData.append('contexto', contexto)` before the fetch call
4. **Pass checklist type**: `handleUploadPhoto` needs access to `checklist.type` — pass it through or derive from component state

---

**File 3**: `mi3/backend/app/Models/Checklist.php`

**Bug 4 Fix — Date serialization:**
1. **Add `casts` format**: Change `'scheduled_date' => 'date'` to `'scheduled_date' => 'date:Y-m-d'`
2. **Alternative**: Add a `serializeDate` method or use accessor. The `'date:Y-m-d'` cast is the simplest — Laravel will serialize the date as `"2026-04-15"` string in JSON responses.

## Testing Strategy

### Validation Approach

La estrategia de testing sigue dos fases: primero, generar contraejemplos que demuestren los bugs en código sin fixear, luego verificar que los fixes funcionan correctamente y preservan el comportamiento existente.

### Exploratory Bug Condition Checking

**Goal**: Generar contraejemplos que demuestren los 4 bugs ANTES de implementar los fixes. Confirmar o refutar el análisis de root cause.

**Test Plan**: Escribir tests que simulen cada condición de bug y verificar que el comportamiento defectuoso se reproduce.

**Test Cases**:
1. **Bug 1 — Security Worker Gets Checklist**: Crear turno `tipo=seguridad` para worker con `rol=administrador,seguridad,cajero`. Ejecutar `crearChecklistsDiarios()`. Verificar que se genera checklist de cajero para ese worker (fallará en unfixed code = bug confirmed).
2. **Bug 2 — Raw Number Display**: Renderizar `ChecklistItemCard` con `item_type=cash_verification`. Simular input "29000". Verificar que el display muestra `29000` sin formato (fallará en unfixed code = bug confirmed).
3. **Bug 3 — Missing Contexto**: Simular `handleUploadPhoto(itemId, file)`. Interceptar el fetch request. Verificar que el FormData NO contiene `contexto` (fallará en unfixed code = bug confirmed).
4. **Bug 4 — Carbon Serialization**: Serializar un Checklist con `scheduled_date` a JSON. Verificar que el output contiene `T00:00:00` en lugar de `Y-m-d` simple (fallará en unfixed code = bug confirmed).

**Expected Counterexamples**:
- Bug 1: Ricardo aparece con checklist de cajero en endpoint `today?rol=cajero`
- Bug 2: Input muestra "29000" en lugar de "$29.000"
- Bug 3: FormData solo contiene "photo", no "contexto"
- Bug 4: JSON contiene `"scheduled_date": "2026-04-15T00:00:00.000000Z"` en lugar de `"2026-04-15"`

### Fix Checking

**Goal**: Verificar que para todos los inputs donde la condición de bug se cumple, la función corregida produce el comportamiento esperado.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := fixedFunction(input)
  ASSERT expectedBehavior(result)
END FOR
```

### Preservation Checking

**Goal**: Verificar que para todos los inputs donde la condición de bug NO se cumple, la función corregida produce el mismo resultado que la función original.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT originalFunction(input) = fixedFunction(input)
END FOR
```

**Testing Approach**: Property-based testing es recomendado para preservation checking porque genera muchos casos de prueba automáticamente y detecta edge cases que tests manuales podrían omitir.

**Test Plan**: Observar comportamiento del código sin fixear para inputs normales (turnos de R11, clicks de mouse, fotos con contexto explícito), luego escribir property-based tests que capturen ese comportamiento.

**Test Cases**:
1. **R11 Shift Preservation**: Verificar que workers con turno `tipo=normal` y rol `cajero` siguen recibiendo checklists correctamente después del fix
2. **Cash Verification Flow Preservation**: Verificar que el flujo "Sí" (confirmed=true) sigue enviando `actual_amount` igual a `cash_expected` y registrando "ok"
3. **Photo Upload Preservation**: Verificar que la compresión (800px, JPEG 0.8), subida a S3, y marcado de completado siguen funcionando
4. **Completed Checklist Preservation**: Verificar que checklists completados siguen mostrando 100% y check verde

### Unit Tests

- Test `crearChecklistsDiarios()` con mix de turnos normales y de seguridad — solo normales generan checklists
- Test `formatCLP()` / `parseCLP()` con valores edge: 0, 1, 999, 1000, 29000, 1000000
- Test `getPhotoContexto()` con todas las descripciones de templates existentes
- Test serialización JSON de Checklist — `scheduled_date` debe ser `"Y-m-d"` string

### Property-Based Tests

- Generar turnos random con tipos random (normal, seguridad, reemplazo, reemplazo_seguridad) y verificar que solo turnos de R11 generan checklists
- Generar montos random (0 a 10.000.000) y verificar que `formatCLP(parseCLP(formatted)) == formatted` (roundtrip)
- Generar descripciones de items con keywords random y verificar que `getPhotoContexto()` siempre retorna un contexto válido de `PhotoAnalysisService::PROMPTS`

### Integration Tests

- Test flujo completo: crear turno seguridad → `crearChecklistsDiarios()` → endpoint `today?rol=cajero` → verificar que el checklist de seguridad NO aparece
- Test flujo foto: subir foto con contexto derivado → verificar que el análisis IA usa el prompt correcto
- Test flujo caja: ingresar monto con formato → verificar que el backend recibe el valor numérico limpio
