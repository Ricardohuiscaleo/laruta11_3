# Implementation Tasks: Recetas, Categorías y Bebidas

## Task 1: Backend — BeverageService + BeverageController

- [ ] 1.1 Create `mi3/backend/app/Services/Recipe/BeverageService.php` with `getBeverages()` method that queries ingredients where `category = 'Bebidas'`, includes `is_low_stock` flag (`current_stock < min_stock_level`), and joins linked products via `product_recipes` → `products` to populate `linked_products` array.
- [ ] 1.2 Add `createBeverageIngredient(array $data): Ingredient` method to BeverageService — validates name uniqueness (case-insensitive) against `ingredients` table, creates record with `category = 'Bebidas'`, accepts fields: name, unit (enum: unidad/L/ml), cost_per_unit, supplier, min_stock_level.
- [ ] 1.3 Add `createBeverageProduct(array $data): array` method to BeverageService — finds or creates "Bebidas" category in `categories` table, creates `products` record with that category_id, creates `product_recipes` record linking product to ingredient_id with quantity=1 and unit from the ingredient.
- [ ] 1.4 Create `mi3/backend/app/Http/Controllers/Admin/BeverageController.php` with `index()`, `store()`, and `storeProduct()` methods. Use strict types, proper validation, and error handling following existing controller patterns (IngredientRecipeController).
- [ ] 1.5 Register routes in `mi3/backend/routes/api.php` inside admin middleware group: `GET bebidas`, `POST bebidas`, `POST bebidas/producto`. Place before any parameterized routes.

## Task 2: Backend — RecipeService Grouped Category Support

- [ ] 2.1 Add `getRecipesGroupedByCategory(?string $search = null): array` method to `mi3/backend/app/Services/Recipe/RecipeService.php`. Query active products with recipes, join `categories` for name and sort_order, exclude products whose category name is "Bebidas", group by category_id. Return `{ categories: [{id, name, sort_order, product_count}], products: {[categoryId]: [{id, name, category_id, price, recipe_cost, margin, ingredient_count}]} }`.
- [ ] 2.2 Modify `index()` in `mi3/backend/app/Http/Controllers/RecipeController.php` to accept `?grouped=true` query param. When grouped=true, call `getRecipesGroupedByCategory($search)` instead of `getRecipesWithCosts()`. Preserve backward compatibility — without `grouped` param, behavior is unchanged.

## Task 3: Frontend — Bebidas Tab

- [ ] 3.1 Create `mi3/frontend/app/admin/recetas/bebidas/page.tsx` with BebidasTab component. Display beverage ingredients in a responsive table (mobile: card layout, desktop: table). Columns: Name, Stock (with unit), Cost/Unit, Supplier, Linked Product, Status. Amber row highlight for `is_low_stock=true`. "Sin producto" badge for beverages with empty `linked_products`.
- [ ] 3.2 Add "Agregar Bebida" inline form to BebidasTab — fields: name (text, required), unit (select: unidad/L/ml), cost per unit (number, required), supplier (text, optional), min stock (number, optional). POST to `/admin/bebidas`. Show validation errors per field. On success, refresh list.
- [ ] 3.3 Add "Agregar Producto Bebida" inline form to BebidasTab — fields: name (text, required), price (number, required), description (text, optional), ingredient (select from existing beverages). POST to `/admin/bebidas/producto`. Show validation errors. On success, refresh list.
- [ ] 3.4 Modify `mi3/frontend/components/admin/sections/RecetasSection.tsx` — add `{ key: 'bebidas', label: 'Bebidas', icon: Wine }` tab (import Wine from lucide-react), add lazy import `const BebidasTab = lazy(() => import('@/app/admin/recetas/bebidas/page'))`, add render case `{activeTab === 'bebidas' && <BebidasTab />}`. Add 'bebidas' to TabKey union type.

## Task 4: Frontend — Category-Grouped Recipe List

- [ ] 4.1 Refactor `mi3/frontend/app/admin/recetas/page.tsx` RecetasPage component — change `fetchProducts` to call `/admin/recetas?grouped=true`, update state to hold `{ categories, products }` structure instead of flat array. Parse response into category groups.
- [ ] 4.2 Replace flat table with accordion-style category groups. Each category header: category name (bold), product count badge, ChevronDown/ChevronRight toggle icon. All categories expanded by default. Click header toggles expand/collapse. Use `useState<Set<number>>` for expanded category IDs.
- [ ] 4.3 Update search filter logic — when search is active, filter products across all category groups client-side, hide category groups with zero matching products. Preserve category grouping structure in filtered results.
- [ ] 4.4 Preserve existing per-product row: name, price (formatCLP), recipe cost, margin badge (green/amber), ingredient count. Keep "Sin receta" badge for products with `ingredient_count === 0`. Keep click-to-edit behavior opening RecipeEditor. Ensure mobile responsiveness with existing responsive patterns.
