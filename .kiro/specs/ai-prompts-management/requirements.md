# Requirements Document

## Introduction

Este documento define los requisitos para migrar los 17 prompts de IA actualmente hardcodeados en `GeminiService.php` a un sistema respaldado por base de datos con versionamiento, caché y una interfaz de administración CRUD. El sistema permite al dueño del negocio editar, versionar y activar prompts sin necesidad de despliegues de código.

## Glossary

- **AiPromptService**: Servicio backend central que gestiona la lectura, escritura, caché e interpolación de variables de los prompts de IA
- **AiPromptController**: Controlador REST que expone endpoints API para operaciones CRUD de prompts
- **PromptsManager**: Componente React en la pestaña Consola que permite la gestión visual de prompts
- **GeminiService**: Servicio existente que construye y envía prompts a la API de Google Gemini para extracción de datos de compras
- **Prompt**: Plantilla de texto con placeholders `{variable}` que se envía a la API de Gemini
- **Pipeline**: Agrupación lógica de prompts: `legacy`, `multi-agent-rules`, `multi-agent-phases`
- **Slug**: Identificador máquina de un prompt (e.g., `boleta`, `factura`, `vision`)
- **Version_Snapshot**: Copia del texto de un prompt guardada en `ai_prompt_versions` antes de cada edición
- **Cache_Tag**: Etiqueta `ai_prompts` usada para invalidar todas las entradas de caché de prompts simultáneamente
- **Variable_Interpolation**: Proceso de reemplazar tokens `{placeholder}` en el texto del prompt con valores reales en tiempo de ejecución

## Requirements

### Requirement 1: Almacenamiento de Prompts en Base de Datos

**User Story:** Como dueño del negocio, quiero que los prompts de IA estén almacenados en base de datos, para poder modificarlos sin necesidad de despliegues de código.

#### Acceptance Criteria

1. THE Database SHALL store prompts in an `ai_prompts` table with columns: id, slug, pipeline, label, description, prompt_text, variables, prompt_version, is_active, created_at, updated_at
2. THE Database SHALL enforce a unique constraint on the combination of slug and pipeline columns
3. THE Database SHALL restrict the pipeline column to one of: `legacy`, `multi-agent-rules`, `multi-agent-phases`
4. THE Database SHALL store version history in an `ai_prompt_versions` table with columns: id, ai_prompt_id, prompt_text, prompt_version, created_at
5. THE Database SHALL enforce a foreign key from `ai_prompt_versions.ai_prompt_id` to `ai_prompts.id` with cascade delete

### Requirement 2: Seed Migration de Prompts Existentes

**User Story:** Como desarrollador, quiero que una migración seed pueble la tabla con los 17 prompts actuales de GeminiService, para que el sistema funcione inmediatamente después del despliegue.

#### Acceptance Criteria

1. WHEN the seed migration runs, THE System SHALL insert exactly 17 rows into `ai_prompts` matching every prompt method in GeminiService
2. WHEN the seed migration completes, THE System SHALL have populated each row with the exact prompt text currently hardcoded in GeminiService
3. THE Seed_Migration SHALL assign correct slug and pipeline values according to the prompt slug mapping defined in the design (e.g., `classification`/`legacy`, `boleta`/`multi-agent-rules`, `vision`/`multi-agent-phases`)
4. WHEN the seed migration runs, THE System SHALL set prompt_version to 1 and is_active to true for all seeded rows
5. WHEN the seed migration runs on a database that already contains the prompts, THE System SHALL skip insertion without error (idempotent)

### Requirement 3: Lectura de Prompts con Caché

**User Story:** Como sistema de extracción, quiero leer prompts desde la base de datos con una capa de caché, para no agregar latencia a cada operación de extracción.

#### Acceptance Criteria

1. WHEN AiPromptService receives a getPrompt request with a slug and pipeline, THE AiPromptService SHALL first check the cache using key pattern `ai_prompts:{slug}:{pipeline}`
2. WHILE the cache contains a valid entry for the requested slug and pipeline, THE AiPromptService SHALL return the cached prompt text without querying the database
3. WHEN the cache does not contain the requested prompt, THE AiPromptService SHALL query the database for an active prompt matching the slug and pipeline
4. WHEN a prompt is fetched from the database, THE AiPromptService SHALL store it in cache with a TTL of 3600 seconds using the `ai_prompts` cache tag
5. IF a prompt with the given slug and pipeline does not exist in the database, THEN THE AiPromptService SHALL throw a RuntimeException with a descriptive message

### Requirement 4: Interpolación de Variables

**User Story:** Como sistema de extracción, quiero que los placeholders `{variable}` en los prompts sean reemplazados con valores de contexto en tiempo de ejecución, para que los prompts incluyan datos dinámicos del negocio.

#### Acceptance Criteria

1. WHEN AiPromptService returns a prompt with variables provided, THE AiPromptService SHALL replace every `{key}` token in the prompt text with the corresponding value from the variables array
2. WHEN a variable key in the array has no matching `{key}` token in the prompt text, THE AiPromptService SHALL ignore the extra variable without error
3. WHEN the prompt text contains a `{key}` token with no corresponding variable provided, THE AiPromptService SHALL leave the token unreplaced in the output
4. THE AiPromptService SHALL cast all variable values to string before interpolation

### Requirement 5: Actualización de Prompts con Versionamiento

**User Story:** Como administrador, quiero editar el texto de un prompt y que el sistema guarde automáticamente un historial de versiones, para poder revertir cambios si una edición causa problemas.

#### Acceptance Criteria

1. WHEN an admin updates a prompt, THE AiPromptService SHALL insert a row into `ai_prompt_versions` containing the previous prompt_text and prompt_version before applying the update
2. WHEN an admin updates a prompt, THE AiPromptService SHALL increment the prompt_version by exactly 1
3. WHEN an admin updates a prompt, THE AiPromptService SHALL flush all cache entries tagged with `ai_prompts`
4. THE AiPromptService SHALL wrap the version snapshot insertion and prompt update in a single database transaction
5. IF the prompt_text submitted is empty, THEN THE AiPromptController SHALL reject the request with HTTP 422 and a validation error message
6. IF the database transaction fails during update, THEN THE AiPromptService SHALL roll back both the version snapshot and the prompt update, leaving the prompt unchanged

### Requirement 6: Reversión a Versión Anterior

**User Story:** Como administrador, quiero revertir un prompt a una versión anterior, para poder deshacer rápidamente una edición problemática.

#### Acceptance Criteria

1. WHEN an admin requests a revert to a specific version, THE AiPromptService SHALL create a new version snapshot of the current text before applying the revert
2. WHEN an admin reverts a prompt, THE AiPromptService SHALL replace the current prompt_text with the text from the specified version and increment prompt_version by 1
3. WHEN an admin reverts a prompt, THE AiPromptService SHALL flush all cache entries tagged with `ai_prompts`
4. IF the specified version ID does not belong to the given prompt, THEN THE AiPromptService SHALL reject the revert with an error

### Requirement 7: API REST para Gestión de Prompts

**User Story:** Como frontend de administración, quiero endpoints REST para listar, ver, editar y revertir prompts, para poder construir la interfaz de gestión.

#### Acceptance Criteria

1. WHEN a GET request is made to the prompts index endpoint, THE AiPromptController SHALL return all prompts as a JSON array
2. WHEN a GET request is made to the prompts show endpoint with a prompt ID, THE AiPromptController SHALL return the prompt details including its version history
3. WHEN a PUT request is made to the prompts update endpoint, THE AiPromptController SHALL validate the payload and delegate to AiPromptService for update
4. WHEN a POST request is made to the revert endpoint with a prompt ID and version ID, THE AiPromptController SHALL delegate to AiPromptService for reversion
5. THE AiPromptController SHALL require authentication via `auth:sanctum` middleware and admin role for all endpoints
6. IF an unauthenticated or non-admin user accesses any prompt endpoint, THEN THE AiPromptController SHALL return HTTP 401 or 403

### Requirement 8: Interfaz de Administración (PromptsManager)

**User Story:** Como dueño del negocio, quiero una interfaz visual dentro de la pestaña Consola para ver, editar y revertir prompts agrupados por pipeline, para gestionar los prompts de IA de forma intuitiva.

#### Acceptance Criteria

1. WHEN the PromptsManager component mounts, THE PromptsManager SHALL fetch all prompts from the API and display them grouped by pipeline
2. WHEN an admin clicks edit on a prompt, THE PromptsManager SHALL display a monospace textarea with the current prompt text
3. WHEN an admin saves an edited prompt, THE PromptsManager SHALL send the updated text to the API and reflect the change in the UI
4. WHEN an admin views a prompt's history, THE PromptsManager SHALL display a list of previous versions with version number and timestamp
5. WHEN an admin clicks revert on a version, THE PromptsManager SHALL call the revert API endpoint and update the displayed prompt text
6. IF an API call fails during edit or revert, THEN THE PromptsManager SHALL display an error message and roll back the optimistic UI update

### Requirement 9: Compatibilidad Retroactiva de GeminiService

**User Story:** Como sistema de extracción, quiero que GeminiService lea prompts desde la base de datos en lugar de los hardcodeados, sin cambiar el comportamiento de extracción, para que la migración sea transparente.

#### Acceptance Criteria

1. THE GeminiService SHALL inject AiPromptService via constructor dependency injection
2. WHEN GeminiService needs a prompt, THE GeminiService SHALL call AiPromptService.getPrompt() with the appropriate slug, pipeline, and context variables
3. WHEN using the seeded (original) prompt texts, THE GeminiService SHALL produce extraction results identical to the previous hardcoded implementation
4. IF AiPromptService throws a RuntimeException for a missing prompt, THEN THE GeminiService SHALL log the error and fall back to the hardcoded prompt text during the transition period

### Requirement 10: Variables JSON Documentadas

**User Story:** Como administrador, quiero ver qué variables dinámicas acepta cada prompt, para saber qué placeholders puedo usar al editar un prompt.

#### Acceptance Criteria

1. THE Database SHALL store a `variables` JSON column in `ai_prompts` documenting the available placeholder names for each prompt
2. WHEN the PromptsManager displays a prompt for editing, THE PromptsManager SHALL show the list of available variables alongside the editor
3. WHEN the seed migration runs, THE Seed_Migration SHALL populate the variables column with the correct placeholder names for each prompt
