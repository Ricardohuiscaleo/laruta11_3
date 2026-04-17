# Requirements Document

## Introduction

Recipe & Ingredient Management system with AI for La Ruta 11 restaurant. The system provides two interfaces: an admin panel in mi3-frontend (mi.laruta11.cl) for full recipe/ingredient CRUD, cost analysis, and bulk operations; and a dedicated Telegram bot (@ChefR11_bot) for quick natural language recipe queries and management via Amazon Nova Micro AI. Both interfaces share the same MySQL database with existing `products`, `ingredients`, and `product_recipes` tables.

## Glossary

- **Recipe_Manager**: The mi3-frontend admin panel module (Next.js 14 + React) that provides UI for recipe and ingredient management at mi.laruta11.cl
- **Recipe_API**: The mi3-backend Laravel 11 API endpoints that handle recipe CRUD operations, cost calculations, and bulk adjustments
- **Chef_Bot**: The Telegram bot (@ChefR11_bot) running as a Node.js process on the VPS, managed by pm2, that accepts natural language commands for recipe queries and management
- **AI_Engine**: Amazon Nova Micro model invoked via AWS Bedrock (us-east-1) that translates natural language into structured SQL queries and recipe operations
- **Product**: A menu item in the `products` table with fields including `id`, `name`, `price`, `cost_price`, `stock_quantity`
- **Ingredient**: A raw ingredient in the `ingredients` table with fields including `id`, `name`, `current_stock`, `cost_per_unit`, `unit`, `is_active`
- **Recipe**: A set of rows in the `product_recipes` table linking a Product to its Ingredients with `product_id`, `ingredient_id`, `quantity`, `unit`
- **Recipe_Cost**: The sum of (ingredient.cost_per_unit × recipe.quantity × unit_conversion_factor) for all Ingredients in a Product's Recipe
- **Margin**: The percentage difference between a Product's selling price and its Recipe_Cost, calculated as ((price - Recipe_Cost) / price) × 100
- **Bulk_Adjustment**: An operation that modifies a numeric field (cost, quantity) across multiple Ingredients or Recipes by a percentage or fixed amount
- **SQL_Guard**: A validation layer in the AI_Engine that restricts generated SQL to SELECT, INSERT, UPDATE on allowed tables only, blocking DELETE and DDL statements

## Requirements

### Requirement 1: Recipe Listing with Cost Summary

**User Story:** As a restaurant admin, I want to view all products with their current recipes and calculated costs, so that I can monitor food costs and margins at a glance.

#### Acceptance Criteria

1. WHEN the admin navigates to the Recipe_Manager, THE Recipe_API SHALL return all active Products with their associated Recipe Ingredients, quantities, units, and per-ingredient costs
2. THE Recipe_API SHALL calculate the Recipe_Cost for each Product by summing (Ingredient.cost_per_unit × Recipe.quantity × unit_conversion_factor) for all linked Ingredients
3. THE Recipe_API SHALL calculate the Margin for each Product as ((Product.price - Recipe_Cost) / Product.price) × 100, rounded to one decimal place
4. WHEN a Product has no Recipe entries, THE Recipe_Manager SHALL display "Sin receta" and a Recipe_Cost of $0
5. THE Recipe_Manager SHALL display Products in a searchable, sortable table with columns: name, category, price, Recipe_Cost, Margin, and ingredient count
6. WHEN the admin filters by category, THE Recipe_API SHALL return only Products belonging to the selected category

### Requirement 2: Recipe Creation

**User Story:** As a restaurant admin, I want to create a new recipe for a product by selecting ingredients and specifying quantities, so that I can track the cost composition of menu items.

#### Acceptance Criteria

1. WHEN the admin selects a Product without a Recipe, THE Recipe_Manager SHALL display a form to add Ingredients with quantity and unit fields
2. THE Recipe_Manager SHALL provide an autocomplete search for Ingredients filtered by the `is_active` field
3. WHEN the admin submits a new Recipe, THE Recipe_API SHALL insert rows into `product_recipes` for each Ingredient with the specified quantity and unit
4. WHEN the admin submits a Recipe with duplicate Ingredient entries, THE Recipe_API SHALL reject the request and return a descriptive error message
5. WHEN the admin submits a Recipe with a quantity less than or equal to zero, THE Recipe_API SHALL reject the request and return a descriptive error message
6. THE Recipe_API SHALL recalculate and update the Product's `cost_price` field after a Recipe is created

### Requirement 3: Recipe Editing

**User Story:** As a restaurant admin, I want to edit existing recipes by adjusting quantities, adding new ingredients, or removing ingredients, so that I can keep recipes accurate as menu items evolve.

#### Acceptance Criteria

1. WHEN the admin opens an existing Recipe, THE Recipe_Manager SHALL display all current Ingredients with their quantities, units, and individual costs
2. WHEN the admin modifies an Ingredient quantity in a Recipe, THE Recipe_API SHALL update the corresponding `product_recipes` row and recalculate the Product's `cost_price`
3. WHEN the admin adds a new Ingredient to an existing Recipe, THE Recipe_API SHALL insert a new `product_recipes` row and recalculate the Product's `cost_price`
4. WHEN the admin removes an Ingredient from a Recipe, THE Recipe_API SHALL delete the corresponding `product_recipes` row and recalculate the Product's `cost_price`
5. IF the admin attempts to remove the last Ingredient from a Recipe, THEN THE Recipe_API SHALL allow the removal and set the Product's `cost_price` to 0

### Requirement 4: Bulk Ingredient Cost Adjustment

**User Story:** As a restaurant admin, I want to adjust ingredient costs in bulk (e.g., increase all prices by 10%), so that I can quickly reflect supplier price changes across all recipes.

#### Acceptance Criteria

1. WHEN the admin initiates a Bulk_Adjustment, THE Recipe_Manager SHALL display a form with fields for: target scope (all ingredients, by category, or by supplier), adjustment type (percentage or fixed amount), and adjustment value
2. WHEN the admin requests a Bulk_Adjustment preview, THE Recipe_API SHALL return a list of affected Ingredients with their current cost, proposed new cost, and the number of Recipes impacted
3. WHEN the admin confirms a Bulk_Adjustment, THE Recipe_API SHALL update the `cost_per_unit` field for all matching Ingredients in a single database transaction
4. THE Recipe_API SHALL recalculate the `cost_price` for all Products whose Recipes contain any adjusted Ingredient
5. IF a Bulk_Adjustment would result in a negative `cost_per_unit` for any Ingredient, THEN THE Recipe_API SHALL reject the entire adjustment and return a descriptive error message

### Requirement 5: Price Recommendation

**User Story:** As a restaurant admin, I want to see price recommendations based on recipe cost and a target margin, so that I can set profitable prices for menu items.

#### Acceptance Criteria

1. WHEN the admin requests price recommendations, THE Recipe_API SHALL accept a target Margin percentage as input (default: 65%)
2. THE Recipe_API SHALL calculate the recommended price for each Product as Recipe_Cost / (1 - target_margin / 100), rounded to the nearest 100 CLP
3. THE Recipe_Manager SHALL display a comparison table with columns: Product name, current price, Recipe_Cost, current Margin, recommended price, and price difference
4. WHEN a Product has no Recipe, THE Recipe_API SHALL exclude the Product from price recommendations
5. THE Recipe_Manager SHALL highlight Products where the current Margin is below the target Margin in a warning color

### Requirement 6: Recipe vs Stock Audit

**User Story:** As a restaurant admin, I want to compare recipe ingredient requirements against current stock levels, so that I can identify shortages and plan purchases.

#### Acceptance Criteria

1. WHEN the admin requests a stock audit, THE Recipe_API SHALL calculate the maximum number of units each Product can produce based on current Ingredient stock levels
2. THE Recipe_API SHALL identify the limiting Ingredient (lowest available units) for each Product
3. THE Recipe_Manager SHALL display an audit table with columns: Product name, max producible units, limiting Ingredient, and stock status (sufficient, low, critical)
4. WHILE an Ingredient's `current_stock` is below its `min_stock_level`, THE Recipe_Manager SHALL mark the Ingredient as "critical" in the audit results
5. WHEN the admin exports the audit, THE Recipe_API SHALL return the audit data in a downloadable format

### Requirement 7: Telegram Bot Natural Language Queries

**User Story:** As a restaurant admin, I want to query recipes, ingredients, and costs via the Telegram bot using natural language, so that I can get quick answers from my phone without accessing the admin panel.

#### Acceptance Criteria

1. WHEN a user sends a natural language message to the Chef_Bot, THE AI_Engine SHALL translate the message into a structured SQL query against the `products`, `ingredients`, and `product_recipes` tables
2. THE SQL_Guard SHALL validate that generated SQL contains only SELECT statements for query operations
3. WHEN the AI_Engine generates a valid query, THE Chef_Bot SHALL execute the query against the MySQL database and format the results as a readable Telegram message
4. IF the AI_Engine cannot interpret the user's message, THEN THE Chef_Bot SHALL respond with a help message listing example queries
5. WHEN the user asks for a product's recipe, THE Chef_Bot SHALL return the product name, each ingredient with quantity and unit, and the total Recipe_Cost
6. WHEN the user asks for ingredient stock, THE Chef_Bot SHALL return the ingredient name, current stock, unit, cost per unit, and stock status relative to min_stock_level
7. THE Chef_Bot SHALL respond to queries within 5 seconds under normal operating conditions

### Requirement 8: Telegram Bot Recipe Modifications

**User Story:** As a restaurant admin, I want to create and update recipes via natural language commands in the Telegram bot, so that I can make quick changes without accessing the admin panel.

#### Acceptance Criteria

1. WHEN a user sends a recipe creation command to the Chef_Bot, THE AI_Engine SHALL parse the message into a structured recipe object with product name, ingredients, quantities, and units
2. THE SQL_Guard SHALL validate that generated SQL for modifications contains only INSERT or UPDATE statements on the `product_recipes` table
3. WHEN the Chef_Bot receives a recipe modification command, THE Chef_Bot SHALL display a confirmation message with the parsed changes before executing
4. WHEN the user confirms a modification, THE Chef_Bot SHALL execute the changes and recalculate the affected Product's `cost_price`
5. IF the user references a Product or Ingredient that does not exist in the database, THEN THE Chef_Bot SHALL respond with a descriptive error and suggest similar matches using fuzzy name matching
6. THE Chef_Bot SHALL log all modification operations with the user's Telegram chat_id, timestamp, and the executed SQL for audit purposes

### Requirement 9: AI SQL Safety and Guardrails

**User Story:** As a system administrator, I want the AI-generated SQL to be restricted to safe operations on allowed tables only, so that the database is protected from accidental or malicious modifications.

#### Acceptance Criteria

1. THE SQL_Guard SHALL maintain an allowlist of tables: `products`, `ingredients`, `product_recipes`
2. THE SQL_Guard SHALL reject any generated SQL that references tables not in the allowlist
3. THE SQL_Guard SHALL reject any generated SQL containing DELETE, DROP, ALTER, TRUNCATE, or CREATE statements
4. THE SQL_Guard SHALL reject any generated SQL containing subqueries that modify data (INSERT, UPDATE, DELETE within a SELECT)
5. IF the SQL_Guard rejects a query, THEN THE Chef_Bot SHALL respond with "Operación no permitida" and log the rejected SQL with the user's Telegram chat_id
6. THE AI_Engine SHALL include the database schema definition for allowed tables in every prompt to Amazon Nova Micro to ensure accurate column references
7. THE SQL_Guard SHALL parameterize all user-provided values in generated SQL to prevent SQL injection

### Requirement 10: Telegram Bot Authentication

**User Story:** As a system administrator, I want the Telegram bot to restrict recipe modification commands to authorized users only, so that unauthorized users cannot alter restaurant data.

#### Acceptance Criteria

1. THE Chef_Bot SHALL maintain a list of authorized Telegram chat_ids that are permitted to execute modification commands
2. WHEN an unauthorized user sends a modification command, THE Chef_Bot SHALL respond with "No tienes permisos para modificar recetas" and allow only read queries
3. WHEN an authorized user sends a query, THE Chef_Bot SHALL process both read and modification commands
4. THE Chef_Bot SHALL allow all users to execute read-only queries (SELECT) without authentication
5. IF the authorized chat_id list is empty, THEN THE Chef_Bot SHALL reject all modification commands and log a warning
