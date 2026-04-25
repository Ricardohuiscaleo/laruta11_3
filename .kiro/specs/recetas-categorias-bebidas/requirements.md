# Requirements Document

## Introduction

El sistema de gestión de recetas en mi3 (mi.laruta11.cl) necesita mejoras para organizar mejor los productos y sus recetas. Actualmente, la vista de recetas muestra todos los productos en una lista plana sin distinción entre comida y bebidas. Se requiere: (1) separar las bebidas como sección independiente, (2) permitir crear nuevas recetas y bebidas, y (3) organizar las recetas por categoría de producto para facilitar la navegación.

## Glossary

- **Recipe_Manager**: El módulo de gestión de recetas en mi3, compuesto por el backend (RecipeController + RecipeService) y el frontend (RecetasSection con sus tabs).
- **Beverage_Section**: Nueva sección/tab dentro del Recipe_Manager dedicada exclusivamente a la gestión de bebidas.
- **Category_View**: Vista agrupada de recetas donde los productos se organizan por su categoría (Churrascos, Hamburguesas, Completos, Pizzas, Combos, Papas).
- **Product_Category**: Registro en la tabla `categories` que agrupa productos del menú (FK `category_id` en `products`).
- **Ingredient_Category**: Valor del campo `category` (varchar) en la tabla `ingredients` que clasifica ingredientes (Carnes, Vegetales, Bebidas, etc.).
- **Beverage_Ingredient**: Ingrediente cuya Ingredient_Category es "Bebidas" en la tabla `ingredients`.
- **Beverage_Product**: Producto en la tabla `products` que representa una bebida vendible al cliente.
- **Recipe**: Conjunto de registros en `product_recipes` que vinculan un producto con sus ingredientes y cantidades.
- **Admin_User**: Usuario autenticado con rol administrador que accede al Recipe_Manager via mi3.

## Requirements

### Requirement 1: Separate Beverages Tab

**User Story:** As an Admin_User, I want to see beverages in a dedicated tab separate from food recipes, so that I can manage beverage inventory and pricing without scrolling through food products.

#### Acceptance Criteria

1. WHEN the Admin_User navigates to the Recipe_Manager, THE Beverage_Section SHALL be available as a distinct tab labeled "Bebidas" alongside the existing tabs.
2. WHEN the Admin_User selects the Beverage_Section tab, THE Recipe_Manager SHALL display only Beverage_Ingredient items (ingredients where Ingredient_Category equals "Bebidas").
3. THE Beverage_Section SHALL display for each Beverage_Ingredient: name, current stock, unit, cost per unit, and supplier.
4. WHEN the Admin_User selects the Beverage_Section tab, THE Recipe_Manager SHALL NOT display food products or non-beverage ingredients.
5. WHEN a Beverage_Ingredient has stock below its min_stock_level, THE Beverage_Section SHALL highlight that item with a visual warning indicator.

### Requirement 2: Create New Beverages

**User Story:** As an Admin_User, I want to add new beverages to the system, so that I can expand the beverage catalog as the business grows.

#### Acceptance Criteria

1. WHEN the Admin_User activates the "add beverage" action in the Beverage_Section, THE Recipe_Manager SHALL present a form with fields: name (required), unit (required, one of: unidad, L, ml), cost per unit (required, numeric greater than zero), supplier (optional), and min stock level (optional, defaults to 1).
2. WHEN the Admin_User submits a valid new beverage form, THE Recipe_Manager SHALL create a new record in the `ingredients` table with Ingredient_Category set to "Bebidas".
3. IF the Admin_User submits a beverage form with a name that already exists in the `ingredients` table, THEN THE Recipe_Manager SHALL display an error message indicating the name is duplicated and SHALL NOT create the record.
4. IF the Admin_User submits a beverage form with missing required fields or invalid values, THEN THE Recipe_Manager SHALL display specific validation errors for each invalid field.
5. WHEN a new Beverage_Ingredient is successfully created, THE Beverage_Section SHALL refresh its list to include the new item without requiring a full page reload.

### Requirement 3: Create New Beverage Products

**User Story:** As an Admin_User, I want to create beverage products that can be sold to customers, so that beverages appear in the menu and can be ordered.

#### Acceptance Criteria

1. WHEN the Admin_User activates the "add beverage product" action in the Beverage_Section, THE Recipe_Manager SHALL present a form with fields: name (required), price (required, numeric greater than zero), description (optional), and linked Beverage_Ingredient (required, selectable from existing Beverage_Ingredients).
2. WHEN the Admin_User submits a valid beverage product form, THE Recipe_Manager SHALL create a new record in the `products` table and a corresponding record in `product_recipes` linking the Beverage_Product to the selected Beverage_Ingredient with quantity 1.
3. WHEN a Beverage_Product is created and a Product_Category with name "Bebidas" exists in the `categories` table, THE Recipe_Manager SHALL assign that category_id to the new product.
4. WHEN a Beverage_Product is created and no Product_Category with name "Bebidas" exists, THE Recipe_Manager SHALL create a new `categories` record with name "Bebidas" and assign its id to the new product.
5. IF the Admin_User submits a beverage product form with missing required fields, THEN THE Recipe_Manager SHALL display specific validation errors for each invalid field.

### Requirement 4: Organize Recipes by Product Category

**User Story:** As an Admin_User, I want recipes grouped by product category, so that I can quickly find and manage recipes for specific product types like Churrascos or Hamburguesas.

#### Acceptance Criteria

1. WHEN the Admin_User views the recipe list tab ("Recetas"), THE Category_View SHALL display products grouped under their respective Product_Category headers, using the category name from the `categories` table.
2. THE Category_View SHALL display each Product_Category header with the category name and the count of products within that category.
3. WHEN the Admin_User clicks on a Product_Category header, THE Category_View SHALL expand or collapse the list of products under that category.
4. THE Category_View SHALL display categories sorted by their `sort_order` field from the `categories` table.
5. WHEN a product has no Recipe (zero records in `product_recipes`), THE Category_View SHALL display that product with a "Sin receta" badge to distinguish it from products with recipes.
6. WHEN the Admin_User uses the search filter, THE Category_View SHALL filter products across all categories, display only matching products while maintaining the category grouping structure, and hide any Product_Category group that contains zero matching products.
7. THE Category_View SHALL preserve the existing per-product information: name, price, recipe cost, margin percentage, and ingredient count.
8. THE Category_View SHALL exclude Beverage_Products from the grouped recipe list, since beverages are managed in the Beverage_Section.

### Requirement 5: Create Recipes for Products Without One

**User Story:** As an Admin_User, I want to create recipes for products that currently lack one, so that I can track costs and margins for all menu items.

#### Acceptance Criteria

1. WHEN the Admin_User clicks on a product with a "Sin receta" badge in the Category_View, THE Recipe_Manager SHALL open the recipe editor for that product.
2. THE Recipe_Manager SHALL allow the Admin_User to add ingredients to the recipe using the existing ingredient autocomplete that searches by name across all Ingredient_Categories.
3. WHEN the Admin_User saves a new recipe with at least one ingredient, THE Recipe_Manager SHALL create the corresponding `product_recipes` records and recalculate the product's `cost_price`.
4. IF the Admin_User attempts to save a recipe with zero ingredients, THEN THE Recipe_Manager SHALL display a validation error requiring at least one ingredient.
5. WHEN the Admin_User returns to the Category_View after creating a recipe, THE Category_View SHALL reflect the updated recipe cost and margin for that product.

### Requirement 6: Beverage Section Displays Linked Products

**User Story:** As an Admin_User, I want to see which beverage ingredients are linked to sellable products, so that I can identify beverages that are in inventory but not yet on the menu.

#### Acceptance Criteria

1. THE Beverage_Section SHALL display for each Beverage_Ingredient whether it is linked to a Beverage_Product (via `product_recipes`).
2. WHEN a Beverage_Ingredient is linked to one or more Beverage_Products, THE Beverage_Section SHALL display the product name and sale price next to the ingredient.
3. WHEN a Beverage_Ingredient is not linked to any Beverage_Product, THE Beverage_Section SHALL display a "Sin producto" indicator to signal it is not yet sellable.
