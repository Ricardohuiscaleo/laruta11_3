# Technical Design: Recetas, Categorías y Bebidas

## Overview

This feature enhances the mi3 Recipe Manager to: (1) add a dedicated Beverages tab for managing beverage ingredients and products, (2) enable creation of new beverages and beverage products, and (3) reorganize the recipe list from a flat table into a category-grouped accordion view.

## Architecture

### Backend Changes (mi3-backend — Laravel 11)

#### New Service: BeverageService

Location: `mi3/backend/app/Services/Recipe/BeverageService.php`

Responsibilities:
- Fetch all ingredients where `category = 'Bebidas'` with linked product info
- Create new beverage ingredients with validation
- Create new beverage products (product + product_recipe link)
- Ensure/create "Bebidas" product category

Methods:
- `getBeverages(): Collection` — Returns beverage ingredients with stock status and linked products
- `createBeverageIngredient(array $data): Ingredient` — Creates ingredient with category="Bebidas"
- `createBeverageProduct(array $data): array` — Creates product + recipe link, ensures Bebidas category

#### New Controller: BeverageController

Location: `mi3/backend/app/Http/Controllers/Admin/BeverageController.php`

Endpoints:
- `GET /api/v1/admin/bebidas` → `index()` — List all beverage ingredients with linked products
- `POST /api/v1/admin/bebidas` → `store()` — Create new beverage ingredient
- `POST /api/v1/admin/bebidas/producto` → `storeProduct()` — Create new beverage product

#### Modified Service: RecipeService

Changes to `getRecipesWithCosts()`:
- Add new parameter `?bool $grouped = false`
- When `grouped=true`, return data structured as `{ categories: [...], products: { [categoryId]: [...] } }` instead of flat array
- Include category name and sort_order from `categories` table via join
- Exclude products whose category is "Bebidas" (beverage product category)

New method:
- `getRecipesGroupedByCategory(?string $search = null): array` — Returns products grouped by category with category metadata. Excludes products whose `category_id` matches the "Bebidas" product category (looked up by name from `categories` table).

#### Route Registration

Add to `mi3/backend/routes/api.php` inside the admin middleware group:
```php
// Bebidas (beverage management)
Route::get('bebidas', [BeverageController::class, 'index']);
Route::post('bebidas', [BeverageController::class, 'store']);
Route::post('bebidas/producto', [BeverageController::class, 'storeProduct']);
```

### Frontend Changes (mi3-frontend — Next.js 14 + React)

#### New Tab: BebidasTab

Location: `mi3/frontend/app/admin/recetas/bebidas/page.tsx`

Component structure:
- Beverage list table with columns: Name, Stock, Unit, Cost/Unit, Supplier, Linked Product, Status
- Low stock warning (amber row highlight when `current_stock < min_stock_level`)
- "Sin producto" badge for unlinked beverages
- "Add Beverage" button → inline form/modal
- "Add Beverage Product" button → inline form/modal with Beverage_Ingredient selector

#### Modified: RecetasListTab (page.tsx)

Location: `mi3/frontend/app/admin/recetas/page.tsx`

Changes:
- Replace flat table with accordion-style category groups
- Each category header: name + product count + expand/collapse chevron
- All categories expanded by default
- Search filters across all categories, hides empty groups
- Preserve existing columns: name, price, recipe cost, margin, ingredient count
- "Sin receta" badge remains for products with 0 ingredients
- Click on any product opens existing RecipeEditor

#### Modified: RecetasSection.tsx

Location: `mi3/frontend/components/admin/sections/RecetasSection.tsx`

Changes:
- Add new tab `{ key: 'bebidas', label: 'Bebidas', icon: Wine }` (from lucide-react)
- Add lazy import for BebidasTab
- Render BebidasTab when `activeTab === 'bebidas'`

### Data Flow

```
┌─────────────────────────────────────────────────────┐
│ RecetasSection (tabs)                                │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│  │ Recetas  │ │ Bebidas  │ │ Combos   │ ...        │
│  └────┬─────┘ └────┬─────┘ └──────────┘            │
│       │             │                                │
│  ┌────▼─────┐ ┌────▼──────────────────────────┐    │
│  │Category  │ │ BebidasTab                     │    │
│  │Accordion │ │  ├─ Beverage List (ingredients)│    │
│  │ ├─Churr. │ │  ├─ Add Beverage Form         │    │
│  │ ├─Hamb.  │ │  └─ Add Beverage Product Form │    │
│  │ ├─Compl. │ └───────────────────────────────┘    │
│  │ └─Pizzas │                                       │
│  └──────────┘                                       │
└─────────────────────────────────────────────────────┘
         │                    │
    GET /admin/recetas   GET /admin/bebidas
    ?grouped=true        POST /admin/bebidas
         │               POST /admin/bebidas/producto
         ▼                    ▼
┌─────────────────┐  ┌──────────────────┐
│ RecipeService   │  │ BeverageService  │
│ (grouped query) │  │ (CRUD beverages) │
└────────┬────────┘  └────────┬─────────┘
         │                    │
         ▼                    ▼
┌─────────────────────────────────────────┐
│ MySQL: products, categories, ingredients│
│         product_recipes                 │
└─────────────────────────────────────────┘
```

## API Contracts

### GET /api/v1/admin/bebidas

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 45,
      "name": "Coca-Cola 350ml",
      "category": "Bebidas",
      "unit": "unidad",
      "cost_per_unit": 450.00,
      "current_stock": 24.00,
      "min_stock_level": 10.00,
      "supplier": "CCU",
      "is_low_stock": false,
      "linked_products": [
        { "id": 101, "name": "Coca-Cola", "price": 1200.00 }
      ]
    }
  ]
}
```

### POST /api/v1/admin/bebidas

Request:
```json
{
  "name": "Fanta 350ml",
  "unit": "unidad",
  "cost_per_unit": 420.00,
  "supplier": "CCU",
  "min_stock_level": 10
}
```

Response (201):
```json
{
  "success": true,
  "data": { "id": 46, "name": "Fanta 350ml", "category": "Bebidas", ... }
}
```

Error (422):
```json
{
  "success": false,
  "error": "Validation failed",
  "errors": { "name": ["El nombre ya existe en ingredientes"] }
}
```

### POST /api/v1/admin/bebidas/producto

Request:
```json
{
  "name": "Fanta",
  "price": 1200,
  "description": "Fanta 350ml lata",
  "ingredient_id": 46
}
```

Response (201):
```json
{
  "success": true,
  "data": {
    "product": { "id": 102, "name": "Fanta", "price": 1200, "category_id": 15 },
    "recipe": { "product_id": 102, "ingredient_id": 46, "quantity": 1, "unit": "unidad" }
  }
}
```

### GET /api/v1/admin/recetas (modified — with grouped support)

Query params: `?grouped=true&search=completo`

Response when `grouped=true`:
```json
{
  "success": true,
  "data": {
    "categories": [
      { "id": 2, "name": "Churrascos", "sort_order": 0, "product_count": 5 },
      { "id": 3, "name": "Hamburguesas", "sort_order": 0, "product_count": 8 },
      { "id": 4, "name": "Completos", "sort_order": 0, "product_count": 6 }
    ],
    "products": {
      "2": [
        { "id": 1, "name": "Churrasco Italiano", "category_id": 2, "price": 4500, "recipe_cost": 1825, "margin": 59.4, "ingredient_count": 5 }
      ],
      "3": [ ... ],
      "4": [ ... ]
    }
  }
}
```

## Correctness Properties

### Property 1: Beverage Filter Invariant
For all items returned by `GET /admin/bebidas`, every item SHALL have `category === "Bebidas"`. No item with a different category SHALL appear in the response.
- Covers: Req 1 AC 2, AC 4

### Property 2: Beverage Creation Category Invariant
For all beverage ingredients created via `POST /admin/bebidas`, the resulting database record SHALL have `category = "Bebidas"` regardless of input variations.
- Covers: Req 2 AC 2

### Property 3: Validation Rejects Invalid Beverage Input
For all inputs where `name` is empty, `unit` is not in {unidad, L, ml}, or `cost_per_unit` is not a positive number, `POST /admin/bebidas` SHALL return HTTP 422 with field-specific error messages.
- Covers: Req 2 AC 4

### Property 4: Category Grouping Completeness
For all products returned by `GET /admin/recetas?grouped=true`, every product SHALL appear under exactly one category group matching its `category_id`. The union of all products across all groups SHALL equal the total set of active non-beverage products.
- Covers: Req 4 AC 1, AC 8

### Property 5: Category Sort Order
For all consecutive category headers in the grouped response, `categories[i].sort_order <= categories[i+1].sort_order`. When sort_order is equal, categories SHALL be sorted alphabetically by name.
- Covers: Req 4 AC 4

### Property 6: Category Count Accuracy
For each category in the grouped response, `category.product_count` SHALL equal the number of products in `products[category.id]`.
- Covers: Req 4 AC 2

### Property 7: Sin Receta Badge Correctness
For all products in the grouped response, a product SHALL have `ingredient_count === 0` if and only if it has zero records in `product_recipes`. Products with `ingredient_count > 0` SHALL NOT display the "Sin receta" badge.
- Covers: Req 4 AC 5

### Property 8: Beverage Product Linked Status
For all beverage ingredients returned by `GET /admin/bebidas`, `linked_products` SHALL be non-empty if and only if there exists at least one record in `product_recipes` where `ingredient_id` matches the beverage ingredient id.
- Covers: Req 6 AC 1, AC 2, AC 3

### Property 9: Duplicate Name Rejection
For all `POST /admin/bebidas` requests where `name` matches an existing ingredient name (case-insensitive), the endpoint SHALL return HTTP 422 and SHALL NOT create a new record.
- Covers: Req 2 AC 3

### Property 10: Beverage Product Creates Both Records
For all successful `POST /admin/bebidas/producto` requests, exactly one new `products` record and exactly one new `product_recipes` record SHALL be created. The product_recipes record SHALL link the new product to the specified ingredient with quantity=1.
- Covers: Req 3 AC 2

## Test Strategy

### Property-Based Tests (via Pest + Faker)

1. **Beverage filter invariant** — Generate N random ingredients with mixed categories, call GET /admin/bebidas, assert all results have category="Bebidas"
2. **Beverage creation invariant** — Generate random valid beverage data, POST, assert DB record has category="Bebidas"
3. **Validation rejection** — Generate invalid inputs (empty names, bad units, negative costs), assert 422 responses
4. **Category grouping completeness** — Seed products across multiple categories, call grouped endpoint, assert every product appears exactly once
5. **Category sort order** — Seed categories with various sort_orders, assert response ordering
6. **Duplicate name rejection** — Create ingredient, attempt to create another with same name, assert 422

### Integration Tests (1-3 examples each)

1. **Beverage product creation flow** — Create ingredient → create product → verify both records + category assignment
2. **Low stock warning** — Create beverage with stock < min_stock, verify `is_low_stock: true`
3. **Beverage category auto-creation** — Delete Bebidas category if exists, create beverage product, verify category created
4. **Search with grouped view** — Seed products, search term, verify only matching products shown and empty groups hidden
5. **Recipe editor from Sin receta** — Verify product with no recipe can be opened in editor and recipe saved

## File Changes Summary

### New Files
| File | Purpose |
|------|---------|
| `mi3/backend/app/Services/Recipe/BeverageService.php` | Beverage business logic |
| `mi3/backend/app/Http/Controllers/Admin/BeverageController.php` | Beverage API endpoints |
| `mi3/frontend/app/admin/recetas/bebidas/page.tsx` | Beverages tab UI |

### Modified Files
| File | Change |
|------|--------|
| `mi3/backend/routes/api.php` | Add 3 beverage routes |
| `mi3/backend/app/Services/Recipe/RecipeService.php` | Add `getRecipesGroupedByCategory()` method |
| `mi3/backend/app/Http/Controllers/RecipeController.php` | Modify `index()` to support `grouped` param |
| `mi3/frontend/components/admin/sections/RecetasSection.tsx` | Add Bebidas tab |
| `mi3/frontend/app/admin/recetas/page.tsx` | Refactor to category-grouped accordion view |
