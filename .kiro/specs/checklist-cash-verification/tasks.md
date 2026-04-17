# Implementation Plan

- [x] 1. Fix wrong worker assignment — filter security shifts in `crearChecklistsDiarios()`
  - File: `mi3/backend/app/Services/Checklist/ChecklistService.php`
  - In `crearChecklistsDiarios()`, after line `$personalId = $turno->reemplazado_por ?: $turno->personal_id;` add a guard clause:
    ```php
    if (in_array($turno->tipo, ['seguridad', 'reemplazo_seguridad'])) {
        continue;
    }
    ```
  - This skips the entire turno iteration for security shifts — no cajero/planchero checklists generated for workers on guard duty
  - _Bug_Condition: isBugCondition(input) where turno.tipo IN ['seguridad', 'reemplazo_seguridad'] AND worker.rol contains 'cajero' or 'planchero'_
  - _Expected_Behavior: crearChecklistsDiarios() skips security shifts entirely, only R11 shifts (normal, reemplazo) generate checklists_
  - _Preservation: Workers with turno tipo='normal' or 'reemplazo' continue receiving checklists as before (Req 3.4)_
  - _Requirements: 1.1, 2.1, 3.4_

- [x] 2. Fix currency formatting — Chilean peso format in cash verification input
  - File: `caja3/src/components/ChecklistApp.jsx`

  - [x] 2.1 Add `formatCLP()` and `parseCLP()` helper functions
    - `formatCLP(value)`: strips non-digits, formats with dot-separated thousands, prepends "$" (e.g. `29000` → `"$29.000"`)
    - `parseCLP(formatted)`: strips "$" and dots, returns raw integer (e.g. `"$29.000"` → `29000`)
    - Place helpers above `ChecklistItemCard` component definition
    - _Requirements: 2.2_

  - [x] 2.2 Replace `type="number"` input with formatted text input
    - Change `<input type="number" inputMode="numeric" min="0" step="1" ...>` to `<input type="text" inputMode="numeric" ...>`
    - Remove `min="0"` and `step="1"` attributes (not valid for text inputs)
    - On `onChange`: pass `e.target.value` through `parseCLP()` to get raw number, then `formatCLP()` to set display value in `setCashAmount()`
    - Store formatted string in `cashAmount` state, use `parseCLP(cashAmount)` when computing `difference` and when calling `onVerifyCash`
    - Update the "Informar" button `onClick` to use `parseCLP(cashAmount)` instead of `parseFloat(cashAmount)`
    - Remove the static "$" prefix `<span>` since `formatCLP` already includes it
    - _Preservation: "Sí" button flow unchanged — still sends confirmed=true with cash_expected (Req 3.3)_
    - _Requirements: 1.2, 2.2, 3.3_

- [x] 3. Fix photo upload — send correct `contexto` parameter
  - File: `caja3/src/components/ChecklistApp.jsx`

  - [x] 3.1 Add `getPhotoContexto(item, checklistType)` function
    - Map item description keywords to contexto values:
      - "interior" → `interior_{type}` (e.g. `interior_apertura`)
      - "exterior" → `exterior_{type}`
      - "plancha" → `plancha_{type}`
      - "lavaplatos" + "mesón"/"meson" → `lavaplatos_meson_{type}`
      - "lavaplatos" → `lavaplatos_{type}`
      - "mesón"/"meson" → `meson_{type}`
      - Default fallback → `interior_{type}`
    - `checklistType` is "apertura" or "cierre"
    - Place helper above `ChecklistApp` component
    - _Requirements: 2.3_

  - [x] 3.2 Append `contexto` to FormData in `handleUploadPhoto()`
    - Find the current item from `checklist.items` using `itemId`
    - Call `getPhotoContexto(item, checklist.type)` to derive the contexto
    - Add `formData.append('contexto', contexto)` before the `fetch()` call
    - _Preservation: Photo compression (800px, JPEG 0.8), S3 upload, AI analysis, and item completion unchanged (Req 3.2)_
    - _Requirements: 1.3, 2.3, 3.2_

- [x] 4. Fix Invalid Date — change `scheduled_date` cast to `date:Y-m-d`
  - File: `mi3/backend/app/Models/Checklist.php`
  - Change `'scheduled_date' => 'date'` to `'scheduled_date' => 'date:Y-m-d'` in the `$casts` array
  - This makes Laravel serialize the date as `"2026-04-15"` string in JSON responses instead of full ISO 8601 Carbon
  - _Bug_Condition: scheduled_date cast as 'date' serializes to "2026-04-15T00:00:00.000000Z" which frontend can't parse_
  - _Expected_Behavior: scheduled_date serializes as "Y-m-d" string (e.g. "2026-04-15")_
  - _Preservation: All other date fields (started_at, completed_at) unchanged_
  - _Requirements: 1.4, 2.4_

- [x] 5. Data fix — correct Camila's checklist wrongly attributed to Ricardo
  - Run SQL to reassign Ricardo's 15-abr checklist(s) back to Camila (personal_id=1, user_name='Camila')
  - Identify affected rows: `SELECT * FROM checklists WHERE personal_id = 5 AND scheduled_date = '2026-04-15' AND rol = 'cajero'`
  - Update: `UPDATE checklists SET personal_id = 1, user_name = 'Camila' WHERE personal_id = 5 AND scheduled_date = '2026-04-15' AND rol = 'cajero'`
  - Verify Camila's original checklist (personal_id=1) for that date — if it exists at 0%, it may need to be deleted since she completed Ricardo's instead
  - _Requirements: 2.1_

- [x] 6. Checkpoint — verify all fixes work end-to-end
  - Deploy mi3-backend and caja3 to Coolify
  - Verify Bug 1: `crearChecklistsDiarios()` does NOT generate checklists for security shift workers
  - Verify Bug 2: cash verification input shows "$29.000" format while typing
  - Verify Bug 3: photo upload sends correct `contexto` (check backend logs or AI analysis results)
  - Verify Bug 4: mi3-frontend checklist detail shows valid date, no "Invalid Date"
  - Verify preservation: "Sí" cash flow, photo upload to S3, progress bar, Telegram notifications all still work
  - Ask user to confirm fixes in production
