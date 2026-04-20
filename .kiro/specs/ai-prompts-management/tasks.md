# Implementation Plan: AI Prompts Management

## Overview

Migrate the 17 hardcoded AI prompts from `GeminiService.php` to a database-backed system with versioning, caching, and admin CRUD UI. Implementation follows the existing `checklist_ai_prompts` pattern, adding `pipeline` and `variables` columns, a versions history table, and a PromptsManager component inside the Compras → Consola tab.

## Tasks

- [x] 1. Create database migration for `ai_prompts` and `ai_prompt_versions` tables
  - Create migration file `2026_04_17_000001_create_ai_prompts_tables.php` in `mi3/backend/database/migrations/`
  - Use raw `DB::statement()` pattern (matching existing migration style)
  - `ai_prompts`: id, slug, pipeline, label, description, prompt_text (MEDIUMTEXT), variables (JSON), prompt_version, is_active, created_at, updated_at
  - Add UNIQUE INDEX on (slug, pipeline), INDEX on (pipeline, is_active)
  - `ai_prompt_versions`: id, ai_prompt_id, prompt_text (MEDIUMTEXT), prompt_version, created_at
  - Add FOREIGN KEY from ai_prompt_id → ai_prompts(id) ON DELETE CASCADE
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 2. Create seed migration to populate the 17 prompts from GeminiService
  - Create migration file `2026_04_17_000002_seed_ai_prompts.php`
  - Use reflection or direct reading of GeminiService prompt methods to extract current prompt texts
  - Insert 17 rows with correct slug, pipeline, label, variables JSON, prompt_version=1, is_active=true
  - Follow the slug mapping from design: classification/legacy, boleta/legacy, factura/legacy, producto/legacy, bascula/legacy, transferencia/legacy, general/legacy, boleta/multi-agent-rules, factura/multi-agent-rules, producto/multi-agent-rules, bascula/multi-agent-rules, transferencia/multi-agent-rules, general/multi-agent-rules, vision/multi-agent-phases, text-analysis/multi-agent-phases, validation/multi-agent-phases, reconciliation/multi-agent-phases
  - Make idempotent: use `insertOrIgnore` or check existence before insert
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 10.3_

- [x] 3. Create Eloquent models
  - [x] 3.1 Create `AiPrompt` model in `mi3/backend/app/Models/AiPrompt.php`
    - Define table, fillable, casts (variables → array, is_active → boolean)
    - Add scopes: `scopeActive`, `scopeBySlug`, `scopeByPipeline`
    - Add `versions()` hasMany relationship to AiPromptVersion
    - _Requirements: 1.1, 1.3_
  - [x] 3.2 Create `AiPromptVersion` model in `mi3/backend/app/Models/AiPromptVersion.php`
    - Define table, fillable, casts
    - Add `prompt()` belongsTo relationship to AiPrompt
    - _Requirements: 1.4_

- [x] 4. Implement AiPromptService
  - Create `mi3/backend/app/Services/Compra/AiPromptService.php`
  - Implement `getPrompt(string $slug, string $pipeline, array $variables = []): string` with cache-through pattern (key: `ai_prompts:{slug}:{pipeline}`, TTL 3600s, tag `ai_prompts`)
  - Implement variable interpolation: replace `{key}` tokens with string-cast values, ignore extra keys, leave unmatched tokens
  - Implement `getAll(): Collection` and `getAllByPipeline(string $pipeline): Collection`
  - Implement `update(int $id, string $promptText, ?string $description = null): AiPrompt` — snapshot to versions, increment version, flush cache, wrap in DB transaction
  - Implement `getHistory(int $id): Collection` — return versions ordered by created_at desc
  - Implement `revertToVersion(int $id, int $versionId): AiPrompt` — validate version belongs to prompt, snapshot current, set text from version, increment version, flush cache
  - Implement `flushCache(): void`
  - Throw `RuntimeException` when prompt not found in DB
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 5.4, 5.6, 6.1, 6.2, 6.3, 6.4_

- [ ]* 4.1 Write property test for variable interpolation
  - **Property 1: Variable Interpolation Correctness**
  - Test with arbitrary prompt texts containing `{placeholder}` tokens and arbitrary variable maps
  - Assert: (a) matched keys are replaced, (b) unmatched tokens remain literal, (c) extra keys are ignored
  - **Validates: Requirements 4.1, 4.2, 4.3, 4.4**

- [ ]* 4.2 Write property test for version monotonicity
  - **Property 3: Version Monotonicity**
  - Apply N sequential updates to a prompt, assert prompt_version equals initial + N
  - **Validates: Requirements 5.2, 6.2**

- [ ]* 4.3 Write property test for version snapshot on mutation
  - **Property 2: Version Snapshot on Mutation**
  - After each update/revert, assert a new row exists in ai_prompt_versions with the previous text and version
  - **Validates: Requirements 5.1, 6.1**

- [ ] 5. Checkpoint — Run migrations and verify seed
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Create AiPromptController
  - Create `mi3/backend/app/Http/Controllers/Admin/AiPromptController.php`
  - `index(Request $request): JsonResponse` — return all prompts via AiPromptService::getAll()
  - `show(int $id): JsonResponse` — return prompt with version history
  - `update(Request $request, int $id): JsonResponse` — validate prompt_text required|string|min:1, delegate to service
  - `revert(int $id, int $versionId): JsonResponse` — delegate to service
  - Return consistent JSON format: `{ success: true, data: ... }`
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 5.5_

- [x] 7. Register API routes for ai-prompts
  - Add routes inside the existing admin middleware group in `mi3/backend/routes/api.php`
  - `GET    compras/ai-prompts` → AiPromptController@index
  - `GET    compras/ai-prompts/{id}` → AiPromptController@show
  - `PUT    compras/ai-prompts/{id}` → AiPromptController@update
  - `POST   compras/ai-prompts/{id}/revert/{versionId}` → AiPromptController@revert
  - Routes inherit `auth:sanctum` + `admin` middleware from the group
  - _Requirements: 7.5, 7.6_

- [x] 8. Refactor GeminiService to use AiPromptService
  - Inject `AiPromptService` via constructor DI in `GeminiService`
  - Replace each hardcoded prompt method (`buildClassificationPrompt`, `promptBoleta`, `promptFactura`, `promptProducto`, `promptBascula`, `promptTransferencia`, `promptGeneral`) to call `$this->promptService->getPrompt(slug, pipeline, variables)`
  - Replace multi-agent prompt methods (`buildVisionPrompt`, `buildTextAnalysisPrompt`, `buildValidationPrompt`, `buildReconciliationPrompt`) similarly
  - Replace text-rules methods (`textRulesBoleta`, etc.) similarly
  - Keep hardcoded prompts as private constants for fallback: wrap getPrompt calls in try/catch, log error and return fallback on RuntimeException
  - Pass context variables (suppliers, products, rutMap, patterns, jsonFormat, etc.) as the variables array
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [ ] 9. Checkpoint — Verify backend API and GeminiService refactor
  - Ensure all tests pass, ask the user if questions arise.

- [x] 10. Build PromptsManager frontend component
  - [x] 10.1 Create `PromptsManager.tsx` in `mi3/frontend/components/admin/compras/`
    - Fetch all prompts from `GET /compras/ai-prompts` on mount using `comprasApi`
    - Group prompts by pipeline (legacy, multi-agent-rules, multi-agent-phases)
    - Display pipeline sections with collapsible prompt cards showing label, slug, pipeline, version number
    - Show available variables list from the `variables` JSON field for each prompt
    - _Requirements: 8.1, 10.1, 10.2_
  - [x] 10.2 Create `PromptEditor.tsx` in `mi3/frontend/components/admin/compras/`
    - Monospace textarea with auto-resize for editing prompt_text
    - Save button calls `PUT /compras/ai-prompts/{id}` with updated text
    - Cancel button reverts to original text
    - Optimistic UI update with error rollback on API failure
    - Display available variables as reference chips/tags next to the editor
    - _Requirements: 8.2, 8.3, 8.6, 10.2_
  - [x] 10.3 Create `PromptHistory.tsx` in `mi3/frontend/components/admin/compras/`
    - Fetch version history from `GET /compras/ai-prompts/{id}` (included in show response)
    - Display list of versions with version number and timestamp
    - Revert button calls `POST /compras/ai-prompts/{id}/revert/{versionId}`
    - Optimistic UI with error rollback
    - _Requirements: 8.4, 8.5, 8.6_

- [x] 11. Integrate PromptsManager into Consola tab
  - Modify `mi3/frontend/app/admin/compras/consola/page.tsx` (loaded via `ComprasSection.tsx` lazy import)
  - Add sub-tab navigation: "Logs" (existing extraction logs) and "Prompts IA" (new PromptsManager)
  - Default to "Logs" tab, lazy-load PromptsManager when "Prompts IA" is selected
  - _Requirements: 8.1_

- [ ]* 11.1 Write unit tests for PromptsManager components
  - Test prompt grouping by pipeline
  - Test edit flow: click edit → modify text → save → API call
  - Test revert flow: view history → click revert → API call
  - Test error handling: API failure → rollback UI
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

- [ ] 12. Final checkpoint — Full integration verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- The seed migration extracts prompt texts directly from GeminiService methods to ensure exact parity
- GeminiService keeps hardcoded fallbacks during transition; these can be removed once DB prompts are confirmed stable
- Frontend uses the existing `comprasApi` helper — no new dependencies needed
- Property tests validate variable interpolation, version monotonicity, and snapshot correctness
