# Implementation Plan: Recipe Management AI

## Overview

Implement a Recipe & Ingredient Management system with AI across three components: Recipe_API (Laravel backend), Recipe_Manager (Next.js frontend), and Chef_Bot (Node.js Telegram bot). Tasks are ordered to build backend first, then frontend, then bot — each step wires into the previous one.

## Tasks

- [x] 1. Backend: Models, relationships, and unit conversion
  - [x] 1.1 Create `ProductRecipe` model and add relationships to `Product` and `Ingredient` models
    - Create `mi3/backend/app/Models/ProductRecipe.php` with `$table = 'product_recipes'`, `$fillable`, `$casts`, and `product()`/`ingredient()` relationships
    - Add `recipes()` hasMany and `ingredients()` belongsToMany relationships to existing `mi3/backend/app/Models/Product.php`
    - Verify `Ingredient` model already has `recetas()` relationship; add if missing
    - _Requirements: 1.1, 2.3, 3.1_

  - [x] 1.2 Create `RecipeService` with unit conversion and cost calculation
    - Create `mi3/backend/app/Services/Recipe/RecipeService.php` following existing service-layer pattern
    - Implement `UNIT_CONVERSIONS` constant map (g, kg, ml, L, unidad)
    - Implement `calculateRecipeCost(productId)` — sum of (cost_per_unit × quantity × conversion_factor) for all recipe ingredients
    - Implement helper to recalculate and persist `cost_price` on the `products` table
    - _Requirements: 1.2, 1.3, 2.6, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 1.3 Write property tests for cost calculation and cost_price invariant
    - **Property 1: Recipe cost and margin calculation** — generate random ingredients with costs (0.01–1000), quantities (0.1–10000), units from {g,kg,ml,L,unidad}, prices (100–50000); verify calculated cost matches expected sum
    - **Validates: Requirements 1.2, 1.3**
    - **Property 2: Cost_price invariant after recipe mutations** — generate random sequences of create/add/modify/remove operations; verify `cost_price` always equals independently calculated recipe cost
    - **Validates: Requirements 2.6, 3.2, 3.3, 3.4, 3.5**

- [x] 2. Backend: Recipe CRUD endpoints
  - [x] 2.1 Implement recipe listing and detail methods in `RecipeService`
    - Implement `getRecipesWithCosts(?categoryId, ?search, ?sortBy)` returning active products with recipe details, cost, and margin
    - Implement `getRecipeDetail(productId)` returning full recipe with per-ingredient costs
    - Products without recipes return `cost_price = 0`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6_

  - [x] 2.2 Implement recipe create/update/delete methods in `RecipeService`
    - Implement `createRecipe(productId, ingredients[])` — insert `product_recipes` rows, reject duplicates, reject quantity ≤ 0, recalculate `cost_price`
    - Implement `updateRecipe(productId, ingredients[])` — replace recipe ingredients, recalculate `cost_price`
    - Implement `removeIngredient(productId, ingredientId)` — delete row, recalculate `cost_price` (set to 0 if last ingredient removed)
    - All write operations wrapped in database transactions
    - _Requirements: 2.3, 2.4, 2.5, 2.6, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 2.3 Write property tests for recipe validation
    - **Property 3: Recipe validation rejects invalid input** — generate recipe submissions with injected duplicate ingredient IDs or quantities ≤ 0; verify rejection and unchanged DB state
    - **Validates: Requirements 2.4, 2.5**
    - **Property 4: Category filter returns only matching products** — generate products across 5+ categories; verify filter returns exactly matching active products
    - **Validates: Requirements 1.6**

  - [x] 2.4 Create `RecipeController` and register API routes
    - Create `mi3/backend/app/Http/Controllers/RecipeController.php` as thin controller delegating to `RecipeService`
    - Implement methods: `index`, `show`, `store`, `update`, `destroyIngredient`
    - Register routes in `mi3/backend/routes/api.php` under admin middleware group at `/api/v1/admin/recetas`
    - Add request validation (FormRequest classes or inline validation)
    - _Requirements: 1.1, 2.1, 2.3, 3.1_

- [x] 3. Backend: Bulk adjustment, recommendations, and audit
  - [x] 3.1 Implement bulk adjustment methods in `RecipeService`
    - Implement `bulkAdjustmentPreview(scope, type, value)` — return affected ingredients with current/proposed costs and impacted recipe count
    - Implement `bulkAdjustmentApply(scope, type, value)` — update `cost_per_unit` in transaction, reject if any result would be negative, cascade recalculate all affected product `cost_price` values
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

  - [ ]* 3.2 Write property tests for bulk adjustment
    - **Property 5: Bulk adjustment correctness with cascade** — generate random scopes, percentage/fixed adjustments; verify all matching ingredients updated correctly and all affected product `cost_price` recalculated
    - **Validates: Requirements 4.3, 4.4**
    - **Property 6: Bulk adjustment rejects negative costs** — generate adjustments guaranteed to cause negatives; verify entire adjustment rejected with no changes
    - **Validates: Requirements 4.5**

  - [x] 3.3 Implement recommendations and audit methods in `RecipeService`
    - Implement `getRecommendations(targetMargin)` — calculate recommended price as `Recipe_Cost / (1 - margin/100)` rounded to nearest 100 CLP, exclude products without recipes
    - Implement `getStockAudit()` — calculate max producible units per product (min of floor(stock/quantity×conversion) across ingredients), identify limiting ingredient
    - Implement `exportAudit(format)` — return audit data as CSV
    - _Requirements: 5.1, 5.2, 5.4, 6.1, 6.2, 6.4, 6.5_

  - [ ]* 3.4 Write property tests for recommendations and audit
    - **Property 7: Price recommendation formula** — generate random costs (100–50000) and margins (10–90); verify recommended price equals `cost / (1 - margin/100)` rounded to nearest 100
    - **Validates: Requirements 5.2**
    - **Property 8: Recommendations exclude products without recipes** — generate product sets with/without recipes; verify only products with recipes appear in results
    - **Validates: Requirements 5.4**
    - **Property 9: Stock audit producibility and limiting ingredient** — generate random recipes with random stock levels; verify max producible = min(floor(stock/quantity×conversion)) and limiting ingredient is correct
    - **Validates: Requirements 6.1, 6.2**
    - **Property 10: Critical stock classification** — generate random stock and min_stock_level pairs; verify critical classification iff current_stock < min_stock_level
    - **Validates: Requirements 6.4**

  - [x] 3.5 Add bulk adjustment, recommendations, and audit routes to `RecipeController`
    - Add controller methods: `bulkPreview`, `bulkApply`, `recommendations`, `audit`, `auditExport`
    - Register routes in `mi3/backend/routes/api.php`
    - _Requirements: 4.1, 5.1, 6.1, 6.5_

- [x] 4. Checkpoint — Backend API complete
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Frontend: Recipe listing and detail pages
  - [x] 5.1 Create Recipe_Manager page layout and navigation
    - Create `mi3/frontend/app/admin/recetas/layout.tsx` following existing admin layout pattern
    - Add "Recetas" navigation link to admin sidebar
    - _Requirements: 1.5_

  - [x] 5.2 Create recipe listing page with search, sort, and category filter
    - Create `mi3/frontend/app/admin/recetas/page.tsx` with `RecipeTable` component
    - Implement searchable, sortable table with columns: name, category, price, Recipe_Cost, Margin, ingredient count
    - Display "Sin receta" and $0 cost for products without recipes
    - Implement category filter dropdown
    - Highlight products with margin below target in warning color
    - _Requirements: 1.1, 1.4, 1.5, 1.6, 5.5_

  - [x] 5.3 Create recipe detail/edit page
    - Create `mi3/frontend/app/admin/recetas/[productId]/page.tsx` with `RecipeForm` component
    - Implement `IngredientAutocomplete` component searching active ingredients
    - Implement add/remove/edit ingredient interactions with quantity and unit fields
    - Display per-ingredient costs and total Recipe_Cost with `CostBadge` component
    - Wire to Recipe_API CRUD endpoints
    - _Requirements: 2.1, 2.2, 3.1_

- [x] 6. Frontend: Bulk adjustment, recommendations, and audit pages
  - [x] 6.1 Create bulk adjustment page
    - Create `mi3/frontend/app/admin/recetas/ajuste-masivo/page.tsx` with `BulkAdjustmentForm` component
    - Implement scope selection (all, by category, by supplier), type (percentage/fixed), value input
    - Implement preview step showing affected ingredients and impacted recipes
    - Implement confirm/cancel flow
    - _Requirements: 4.1, 4.2_

  - [x] 6.2 Create price recommendations page
    - Create `mi3/frontend/app/admin/recetas/recomendaciones/page.tsx`
    - Implement comparison table: product name, current price, Recipe_Cost, current margin, recommended price, price difference
    - Target margin input with 65% default
    - Highlight products below target margin
    - _Requirements: 5.1, 5.2, 5.3, 5.5_

  - [x] 6.3 Create stock audit page
    - Create `mi3/frontend/app/admin/recetas/auditoria/page.tsx`
    - Implement audit table: product name, max producible units, limiting ingredient, stock status (sufficient/low/critical)
    - Mark ingredients below min_stock_level as critical
    - Add CSV export button
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [x] 7. Checkpoint — Frontend complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Chef_Bot: Project setup and core modules
  - [x] 8.1 Initialize Chef_Bot Node.js project
    - Create `chef-bot/` directory at workspace root with `package.json`, `index.js`, `config.js`
    - Install dependencies: `node-telegram-bot-api`, `mysql2`, `@aws-sdk/client-bedrock-runtime`, `fast-check` (dev)
    - Create `config.js` with env vars: `TELEGRAM_TOKEN`, `AUTHORIZED_CHAT_IDS`, `DB_*`, `AWS_REGION`, `API_BASE_URL`
    - Create `db/mysql.js` with mysql2 connection pool
    - _Requirements: 7.1, 10.1_

  - [x] 8.2 Implement AI engine (Bedrock client, prompt builder, response parser)
    - Create `ai/bedrockClient.js` — AWS Bedrock SDK client for Amazon Nova Micro (us-east-1) with retry logic (2 retries, exponential backoff)
    - Create `ai/promptBuilder.js` — builds prompts with system role, DB schema context for `products`/`ingredients`/`product_recipes`, example NL→SQL mappings, and user message
    - Create `ai/responseParser.js` — parses AI JSON response into `{ intent, sql, params, explanation }`
    - _Requirements: 7.1, 9.6_

  - [x] 8.3 Implement SQL_Guard validation
    - Create `guards/sqlGuard.js` with validation pipeline:
      1. Parse SQL to identify statement type
      2. Check against allowed operations (SELECT for queries; INSERT/UPDATE on `product_recipes` for modifications)
      3. Validate table references against allowlist {products, ingredients, product_recipes}
      4. Reject subqueries containing INSERT/UPDATE/DELETE
      5. Reject DELETE, DROP, ALTER, TRUNCATE, CREATE
      6. Parameterize literal values to prevent SQL injection
      7. Log rejected queries with chat_id and timestamp
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.7_

  - [ ]* 8.4 Write property tests for SQL_Guard
    - **Property 11: SQL_Guard safety validation** — generate random SQL strings with various statement types and table references; verify accept/reject behavior matches rules
    - **Validates: Requirements 7.2, 8.2, 9.2, 9.3, 9.4**
    - **Property 12: SQL_Guard parameterization** — generate SQL with embedded string/numeric literals; verify all literals extracted into params array with placeholders
    - **Validates: Requirements 9.7**

- [x] 9. Chef_Bot: Message handling, formatting, and API client
  - [x] 9.1 Implement message and callback handlers
    - Create `handlers/messageHandler.js` — routes messages through AI engine, SQL_Guard, then to query execution or modification flow
    - Create `handlers/callbackHandler.js` — handles confirmation button callbacks for modifications
    - Implement confirmation flow: parse modification → show preview with confirm/cancel buttons → execute on confirm
    - Implement help message response when AI cannot interpret input
    - _Requirements: 7.1, 7.3, 7.4, 8.1, 8.3, 8.4_

  - [x] 9.2 Implement Telegram formatter and fuzzy matching
    - Create `formatters/telegramFormatter.js` — formats recipe query results (product name, ingredients with qty/unit, total cost) and stock query results (name, stock, unit, cost, status)
    - Implement fuzzy name matching for product/ingredient suggestions when no exact match found, sorted by edit distance
    - _Requirements: 7.5, 7.6, 8.5_

  - [ ]* 9.3 Write property tests for formatter and fuzzy matching
    - **Property 13: Telegram formatter completeness** — generate random recipe/ingredient data; verify formatted message contains all required fields
    - **Validates: Requirements 7.5, 7.6**
    - **Property 14: Fuzzy name matching returns closest matches** — generate random input strings and name sets; verify suggestions sorted by edit distance with top = minimum distance
    - **Validates: Requirements 8.5**

  - [x] 9.4 Implement Recipe API client and audit logger
    - Create `api/recipeApi.js` — HTTP client calling mi3-backend Recipe_API for write operations (create/update recipe, recalculate cost)
    - Create `logger.js` — audit logging for all modifications with chat_id, timestamp, and executed SQL
    - _Requirements: 8.4, 8.6_

  - [ ]* 9.5 Write property tests for audit logging and authorization
    - **Property 15: Modification audit logging** — generate random modification operations; verify log entry contains chat_id, timestamp, and SQL
    - **Validates: Requirements 8.6**
    - **Property 16: Bot authorization enforcement** — generate random chat_ids and command types; verify SELECT allowed for all, modifications only for authorized, correct rejection message for unauthorized
    - **Validates: Requirements 10.2, 10.3, 10.4**

- [x] 10. Chef_Bot: Authentication and entry point
  - [x] 10.1 Implement authorization middleware and bot entry point
    - Implement authorization check in `messageHandler.js`: all users can SELECT, only `AUTHORIZED_CHAT_IDS` can modify, empty list rejects all modifications with logged warning
    - Create `index.js` entry point with Telegram long-polling setup
    - Add pm2 ecosystem config for deployment
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

- [x] 11. Final checkpoint — All components complete
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Backend (PHP/Laravel) uses `giorgiosironi/eris` for property tests; Bot (Node.js) uses `fast-check`
- The Chef_Bot connects directly to MySQL for reads and calls Recipe_API for writes to maintain single source of truth
- No database migrations needed — uses existing `products`, `ingredients`, `product_recipes` tables
- All monetary values in CLP (Chilean pesos), formatted with `toLocaleString('es-CL')`
