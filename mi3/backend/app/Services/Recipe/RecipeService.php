<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use App\Models\Ingredient;
use App\Models\IngredientRecipe;
use App\Models\Product;
use App\Models\ProductRecipe;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RecipeService
{
    /**
     * Conversion factors to normalize units to a common base.
     * kg/g → base is grams, L/ml → base is ml, unidad → no conversion.
     */
    const UNIT_CONVERSIONS = [
        'kg'     => ['base' => 'g',      'factor' => 1000],
        'g'      => ['base' => 'g',      'factor' => 1],
        'L'      => ['base' => 'ml',     'factor' => 1000],
        'ml'     => ['base' => 'ml',     'factor' => 1],
        'unidad' => ['base' => 'unidad', 'factor' => 1],
    ];

    /**
     * Calculate the total recipe cost for a product.
     *
     * For each ingredient in the recipe:
     *   1. Normalize ingredient's cost_per_unit to the base unit
     *   2. Normalize recipe quantity to the base unit
     *   3. cost_contribution = normalized_cost_per_base_unit × normalized_quantity
     *
     * @param  int  $productId
     * @return float Total recipe cost
     */
    public function calculateRecipeCost(int $productId): float
    {
        $recipeItems = ProductRecipe::where('product_id', $productId)
            ->with(['ingredient.subRecipeItems.child'])
            ->get();

        if ($recipeItems->isEmpty()) {
            return 0.0;
        }

        $totalCost = 0.0;

        foreach ($recipeItems as $item) {
            $ingredient = $item->ingredient;

            if (!$ingredient) {
                continue;
            }

            $recipeUnit = $item->unit;
            $quantity   = (float) $item->quantity;

            // If ingredient is composite, calculate cost from its children
            if ($ingredient->is_composite && $ingredient->subRecipeItems->isNotEmpty()) {
                $compositeCostPerUnit = $this->calculateCompositeCostPerUnit($ingredient);
                // composite cost is per 1 unit of the ingredient, multiply by quantity
                $totalCost += $compositeCostPerUnit * $quantity;
            } else {
                $ingredientUnit = $ingredient->unit;
                $costPerUnit    = (float) $ingredient->cost_per_unit;

                $totalCost += $this->calculateIngredientCost(
                    $costPerUnit,
                    $ingredientUnit,
                    $quantity,
                    $recipeUnit
                );
            }
        }

        return $totalCost;
    }

    /**
     * Calculate the cost per 1 unit of a composite ingredient from its children.
     */
    private function calculateCompositeCostPerUnit(Ingredient $ingredient): float
    {
        $cost = 0.0;

        foreach ($ingredient->subRecipeItems as $subItem) {
            if (!$subItem->child) {
                continue;
            }

            $cost += $this->calculateIngredientCost(
                (float) $subItem->child->cost_per_unit,
                $subItem->child->unit,
                (float) $subItem->quantity,
                $subItem->unit
            );
        }

        return $cost;
    }

    /**
     * Calculate the cost contribution of a single ingredient line.
     *
     * Normalizes both the ingredient's cost unit and the recipe's quantity unit
     * to the same base, then multiplies.
     *
     * Example: ingredient costs $5000/kg, recipe uses 200g
     *   → cost per g = 5000/1000 = $5/g
     *   → 200g in base = 200g
     *   → cost = 5 × 200 = $1000
     */
    public function calculateIngredientCost(
        float  $costPerUnit,
        string $ingredientUnit,
        float  $quantity,
        string $recipeUnit
    ): float {
        $ingredientConv = self::UNIT_CONVERSIONS[$ingredientUnit] ?? null;
        $recipeConv     = self::UNIT_CONVERSIONS[$recipeUnit] ?? null;

        // If either unit is unknown, fall back to direct multiplication
        if (!$ingredientConv || !$recipeConv) {
            return $costPerUnit * $quantity;
        }

        // Units must share the same base to be convertible
        if ($ingredientConv['base'] !== $recipeConv['base']) {
            return $costPerUnit * $quantity;
        }

        // Normalize cost to "per base unit"
        // If ingredient cost is per kg ($X/kg) and base is g → cost_per_g = X / 1000
        $costPerBaseUnit = $costPerUnit / $ingredientConv['factor'];

        // Normalize recipe quantity to base units
        // If recipe says 0.5 kg and base is g → 500g
        $quantityInBase = $quantity * $recipeConv['factor'];

        return $costPerBaseUnit * $quantityInBase;
    }

    /**
     * Calculate margin percentage.
     *
     * Margin = ((price - recipeCost) / price) × 100, rounded to 1 decimal.
     * Returns null if price is 0 (avoid division by zero).
     */
    public function calculateMargin(float $price, float $recipeCost): ?float
    {
        if ($price == 0) {
            return null;
        }

        return round((($price - $recipeCost) / $price) * 100, 1);
    }

    /**
     * Recalculate and persist the cost_price on the products table.
     *
     * Called after any recipe mutation (create, update, remove ingredient).
     */
    public function recalculateCostPrice(int $productId): float
    {
        $cost = $this->calculateRecipeCost($productId);

        Product::where('id', $productId)->update(['cost_price' => $cost]);

        return $cost;
    }

    /**
     * Create a recipe for a product by inserting product_recipes rows.
     *
     * @param  int   $productId
     * @param  array $ingredients  Each element: ['ingredient_id' => int, 'quantity' => float, 'unit' => string]
     * @return float The recalculated cost_price
     *
     * @throws ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function createRecipe(int $productId, array $ingredients): float
    {
        $this->validateIngredients($ingredients);

        return DB::transaction(function () use ($productId, $ingredients) {
            Product::findOrFail($productId);

            foreach ($ingredients as $ingredient) {
                ProductRecipe::create([
                    'product_id'    => $productId,
                    'ingredient_id' => $ingredient['ingredient_id'],
                    'quantity'      => $ingredient['quantity'],
                    'unit'          => $ingredient['unit'],
                ]);
            }

            return $this->recalculateCostPrice($productId);
        });
    }

    /**
     * Replace all recipe ingredients for a product.
     *
     * Deletes existing rows and inserts the new set.
     *
     * @param  int   $productId
     * @param  array $ingredients  Each element: ['ingredient_id' => int, 'quantity' => float, 'unit' => string]
     * @return float The recalculated cost_price
     *
     * @throws ValidationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateRecipe(int $productId, array $ingredients): float
    {
        $this->validateIngredients($ingredients);

        return DB::transaction(function () use ($productId, $ingredients) {
            Product::findOrFail($productId);

            ProductRecipe::where('product_id', $productId)->delete();

            foreach ($ingredients as $ingredient) {
                ProductRecipe::create([
                    'product_id'    => $productId,
                    'ingredient_id' => $ingredient['ingredient_id'],
                    'quantity'      => $ingredient['quantity'],
                    'unit'          => $ingredient['unit'],
                ]);
            }

            return $this->recalculateCostPrice($productId);
        });
    }

    /**
     * Remove a single ingredient from a product's recipe.
     *
     * If it was the last ingredient, cost_price becomes 0.
     *
     * @param  int $productId
     * @param  int $ingredientId
     * @return float The recalculated cost_price
     */
    public function removeIngredient(int $productId, int $ingredientId): float
    {
        return DB::transaction(function () use ($productId, $ingredientId) {
            ProductRecipe::where('product_id', $productId)
                ->where('ingredient_id', $ingredientId)
                ->delete();

            return $this->recalculateCostPrice($productId);
        });
    }

    /**
     * Validate the ingredients array for create/update operations.
     *
     * @param  array $ingredients
     * @throws ValidationException
     */
    private function validateIngredients(array $ingredients): void
    {
        // Check for duplicate ingredient_ids
        $ids = array_column($ingredients, 'ingredient_id');
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'ingredients' => ['Ingrediente duplicado en la receta'],
            ]);
        }

        // Check for quantity <= 0
        foreach ($ingredients as $ingredient) {
            if (!isset($ingredient['quantity']) || $ingredient['quantity'] <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad debe ser mayor a 0'],
                ]);
            }
        }
    }

    /**
     * Get all active products with recipe details, cost, and margin.
     *
     * Uses eager loading to avoid N+1 queries.
     *
     * @param  int|null    $categoryId  Filter by category
     * @param  string|null $search      Search by product name
     * @param  string|null $sortBy      Sort field: name, price, cost, margin
     * @return Collection
     */
    public function getRecipesWithCosts(
        ?int $categoryId = null,
        ?string $search = null,
        ?string $sortBy = null
    ): Collection {
        $query = Product::where('is_active', true)
            ->with(['recipes.ingredient']);

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $products = $query->get();

        $result = $products->map(function (Product $product) {
            $recipeCost = 0.0;
            $ingredientCount = 0;

            foreach ($product->recipes as $recipeItem) {
                if (!$recipeItem->ingredient) {
                    continue;
                }

                $ingredientCount++;
                $recipeCost += $this->calculateIngredientCost(
                    (float) $recipeItem->ingredient->cost_per_unit,
                    $recipeItem->ingredient->unit,
                    (float) $recipeItem->quantity,
                    $recipeItem->unit
                );
            }

            return [
                'id'               => $product->id,
                'name'             => $product->name,
                'category_id'      => $product->category_id,
                'price'            => (float) $product->price,
                'recipe_cost'      => $recipeCost,
                'margin'           => $this->calculateMargin((float) $product->price, $recipeCost),
                'ingredient_count' => $ingredientCount,
            ];
        });

        if ($sortBy !== null) {
            $sortField = match ($sortBy) {
                'name'   => 'name',
                'price'  => 'price',
                'cost'   => 'recipe_cost',
                'margin' => 'margin',
                default  => 'name',
            };

            $result = $result->sortBy($sortField, SORT_REGULAR, false)->values();
        }

        return $result;
    }

    /**
     * Parse a bulk adjustment scope string into an Eloquent query constraint.
     *
     * Supported scopes:
     *   'all'              → no filter (all active ingredients)
     *   'category:{name}'  → ingredients where category = {name}
     *   'supplier:{name}'  → ingredients where supplier = {name}
     *
     * @param  string $scope
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildScopeQuery(string $scope): \Illuminate\Database\Eloquent\Builder
    {
        $query = Ingredient::where('is_active', true);

        if ($scope === 'all') {
            return $query;
        }

        if (str_starts_with($scope, 'category:')) {
            $category = substr($scope, strlen('category:'));
            return $query->where('category', $category);
        }

        if (str_starts_with($scope, 'supplier:')) {
            $supplier = substr($scope, strlen('supplier:'));
            return $query->where('supplier', $supplier);
        }

        return $query;
    }

    /**
     * Calculate the proposed cost after applying an adjustment.
     *
     * @param  float  $currentCost
     * @param  string $type  'percentage' or 'fixed'
     * @param  float  $value
     * @return float
     */
    private function calculateAdjustedCost(float $currentCost, string $type, float $value): float
    {
        if ($type === 'percentage') {
            return $currentCost * (1 + $value / 100);
        }

        // fixed
        return $currentCost + $value;
    }

    /**
     * Preview a bulk cost adjustment without applying it.
     *
     * Returns an array of affected ingredients with their current cost,
     * proposed cost, and the number of recipes that use each ingredient.
     *
     * @param  string $scope  'all', 'category:{name}', or 'supplier:{name}'
     * @param  string $type   'percentage' or 'fixed'
     * @param  float  $value  Adjustment amount
     * @return array  List of ['id', 'name', 'current_cost', 'proposed_cost', 'recipe_count']
     */
    public function bulkAdjustmentPreview(string $scope, string $type, float $value): array
    {
        $ingredients = $this->buildScopeQuery($scope)
            ->withCount('recetas')
            ->get();

        return $ingredients->map(function (Ingredient $ingredient) use ($type, $value) {
            $currentCost  = (float) $ingredient->cost_per_unit;
            $proposedCost = $this->calculateAdjustedCost($currentCost, $type, $value);

            return [
                'id'            => $ingredient->id,
                'name'          => $ingredient->name,
                'current_cost'  => $currentCost,
                'proposed_cost' => round($proposedCost, 2),
                'recipe_count'  => $ingredient->recetas_count,
            ];
        })->values()->toArray();
    }

    /**
     * Apply a bulk cost adjustment inside a DB transaction.
     *
     * If ANY ingredient would end up with a negative cost_per_unit the entire
     * adjustment is rejected (ValidationException) and nothing is changed.
     *
     * After applying, every product whose recipe contains an adjusted ingredient
     * gets its cost_price recalculated.
     *
     * @param  string $scope  'all', 'category:{name}', or 'supplier:{name}'
     * @param  string $type   'percentage' or 'fixed'
     * @param  float  $value  Adjustment amount
     * @return array  ['ingredients_affected' => int, 'products_affected' => int]
     *
     * @throws ValidationException
     */
    public function bulkAdjustmentApply(string $scope, string $type, float $value): array
    {
        return DB::transaction(function () use ($scope, $type, $value) {
            $ingredients = $this->buildScopeQuery($scope)->get();

            // --- Pre-validate: reject if any result would be negative ----------
            $wouldBeNegative = [];
            foreach ($ingredients as $ingredient) {
                $proposed = $this->calculateAdjustedCost((float) $ingredient->cost_per_unit, $type, $value);
                if ($proposed < 0) {
                    $wouldBeNegative[] = [
                        'id'            => $ingredient->id,
                        'name'          => $ingredient->name,
                        'current_cost'  => (float) $ingredient->cost_per_unit,
                        'proposed_cost' => round($proposed, 2),
                    ];
                }
            }

            if (!empty($wouldBeNegative)) {
                throw ValidationException::withMessages([
                    'bulk_adjustment' => ['El ajuste resultaría en costos negativos'],
                    'affected'        => $wouldBeNegative,
                ]);
            }

            // --- Apply the adjustment ------------------------------------------
            $ingredientIds = [];
            foreach ($ingredients as $ingredient) {
                $newCost = $this->calculateAdjustedCost((float) $ingredient->cost_per_unit, $type, $value);
                $ingredient->cost_per_unit = round($newCost, 2);
                $ingredient->save();
                $ingredientIds[] = $ingredient->id;
            }

            // --- Cascade: recalculate cost_price for affected products ---------
            $affectedProductIds = ProductRecipe::whereIn('ingredient_id', $ingredientIds)
                ->distinct()
                ->pluck('product_id')
                ->toArray();

            foreach ($affectedProductIds as $productId) {
                $this->recalculateCostPrice($productId);
            }

            // --- Cascade: recalculate composite parents ---------
            foreach ($ingredientIds as $ingId) {
                $this->cascadeCompositeCosts($ingId);
            }

            return [
                'ingredients_affected' => count($ingredientIds),
                'products_affected'    => count($affectedProductIds),
            ];
        });
    }

    /**
     * Get price recommendations based on recipe cost and target margin.
     *
     * Recommended price = Recipe_Cost / (1 - targetMargin/100), rounded to nearest 100 CLP.
     * Excludes products without recipes (recipe_cost = 0).
     *
     * @param  float $targetMargin  Target margin percentage (default 65%)
     * @return array List of recommendations
     */
    public function getRecommendations(float $targetMargin = 65.0): array
    {
        $products = Product::where('is_active', true)
            ->with(['recipes.ingredient'])
            ->get();

        $recommendations = [];

        foreach ($products as $product) {
            $recipeCost = 0.0;

            foreach ($product->recipes as $recipeItem) {
                if (!$recipeItem->ingredient) {
                    continue;
                }

                $recipeCost += $this->calculateIngredientCost(
                    (float) $recipeItem->ingredient->cost_per_unit,
                    $recipeItem->ingredient->unit,
                    (float) $recipeItem->quantity,
                    $recipeItem->unit
                );
            }

            // Exclude products without recipes
            if ($recipeCost == 0) {
                continue;
            }

            $currentPrice = (float) $product->price;
            $currentMargin = $this->calculateMargin($currentPrice, $recipeCost);

            // Recommended price = Recipe_Cost / (1 - targetMargin/100)
            $recommendedPrice = $recipeCost / (1 - $targetMargin / 100);
            $recommendedPrice = round($recommendedPrice / 100) * 100;

            $recommendations[] = [
                'id'                => $product->id,
                'name'              => $product->name,
                'current_price'     => $currentPrice,
                'recipe_cost'       => $recipeCost,
                'current_margin'    => $currentMargin,
                'recommended_price' => $recommendedPrice,
                'price_difference'  => $recommendedPrice - $currentPrice,
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate max producible units per product based on ingredient stock.
     *
     * For each product with a recipe, finds the minimum of
     * floor(ingredient.current_stock / (recipe.quantity × unit_conversion))
     * across all recipe ingredients.
     *
     * Stock status:
     *   'sufficient' → limiting ingredient stock > 2× min_stock_level
     *   'low'        → limiting ingredient stock > min_stock_level
     *   'critical'   → limiting ingredient stock <= min_stock_level
     *
     * @return array List of audit entries
     */
    public function getStockAudit(): array
    {
        $products = Product::where('is_active', true)
            ->with(['recipes.ingredient'])
            ->get();

        $audit = [];

        foreach ($products as $product) {
            if ($product->recipes->isEmpty()) {
                continue;
            }

            $maxProducible = PHP_INT_MAX;
            $limitingIngredient = null;

            $hasValidIngredient = false;

            foreach ($product->recipes as $recipeItem) {
                $ingredient = $recipeItem->ingredient;

                if (!$ingredient) {
                    continue;
                }

                $hasValidIngredient = true;

                $recipeUnit = $recipeItem->unit;
                $ingredientUnit = $ingredient->unit;
                $recipeQuantity = (float) $recipeItem->quantity;
                $currentStock = (float) $ingredient->current_stock;

                // Convert recipe quantity to ingredient's unit base for comparison
                $recipeConv = self::UNIT_CONVERSIONS[$recipeUnit] ?? null;
                $ingredientConv = self::UNIT_CONVERSIONS[$ingredientUnit] ?? null;

                $quantityInIngredientUnit = $recipeQuantity;

                if ($recipeConv && $ingredientConv && $recipeConv['base'] === $ingredientConv['base']) {
                    // Convert recipe quantity to base, then to ingredient unit
                    $quantityInBase = $recipeQuantity * $recipeConv['factor'];
                    $quantityInIngredientUnit = $quantityInBase / $ingredientConv['factor'];
                }

                // Avoid division by zero
                if ($quantityInIngredientUnit <= 0) {
                    continue;
                }

                $producible = (int) floor($currentStock / $quantityInIngredientUnit);

                if ($producible < $maxProducible) {
                    $maxProducible = $producible;
                    $limitingIngredient = $ingredient;
                }
            }

            if (!$hasValidIngredient) {
                continue;
            }

            if ($maxProducible === PHP_INT_MAX) {
                $maxProducible = 0;
            }

            // Classify stock status based on limiting ingredient
            $stockStatus = 'sufficient';
            if ($limitingIngredient) {
                $minStockLevel = (float) $limitingIngredient->min_stock_level;
                $currentStock = (float) $limitingIngredient->current_stock;

                if ($currentStock <= $minStockLevel) {
                    $stockStatus = 'critical';
                } elseif ($currentStock <= 2 * $minStockLevel) {
                    $stockStatus = 'low';
                }
            }

            $audit[] = [
                'id'                   => $product->id,
                'name'                 => $product->name,
                'max_producible'       => $maxProducible,
                'limiting_ingredient'  => $limitingIngredient ? $limitingIngredient->name : null,
                'stock_status'         => $stockStatus,
            ];
        }

        return $audit;
    }

    /**
     * Export stock audit data as CSV.
     *
     * @param  string $format  Export format (currently only 'csv')
     * @return string CSV content
     */
    public function exportAudit(string $format = 'csv'): string
    {
        $audit = $this->getStockAudit();

        $csv = "Producto,Max Producible,Ingrediente Limitante,Estado Stock\n";

        foreach ($audit as $entry) {
            $producto = str_replace(',', ' ', $entry['name']);
            $limitante = $entry['limiting_ingredient']
                ? str_replace(',', ' ', $entry['limiting_ingredient'])
                : 'N/A';

            $csv .= "{$producto},{$entry['max_producible']},{$limitante},{$entry['stock_status']}\n";
        }

        return $csv;
    }

    /**
     * Get full recipe detail for a product with per-ingredient costs.
     *
     * @param  int   $productId
     * @return array Product info with ingredients and their individual costs
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getRecipeDetail(int $productId): array
    {
        $product = Product::where('is_active', true)
            ->with(['recipes.ingredient'])
            ->findOrFail($productId);

        $recipeCost = 0.0;
        $ingredients = [];

        foreach ($product->recipes as $recipeItem) {
            if (!$recipeItem->ingredient) {
                continue;
            }

            $ingredientCost = $this->calculateIngredientCost(
                (float) $recipeItem->ingredient->cost_per_unit,
                $recipeItem->ingredient->unit,
                (float) $recipeItem->quantity,
                $recipeItem->unit
            );

            $recipeCost += $ingredientCost;

            $ingredients[] = [
                'id'              => $recipeItem->ingredient->id,
                'name'            => $recipeItem->ingredient->name,
                'category'        => $recipeItem->ingredient->category,
                'quantity'        => (float) $recipeItem->quantity,
                'unit'            => $recipeItem->unit,
                'ingredient_unit' => $recipeItem->ingredient->unit,
                'cost_per_unit'   => (float) $recipeItem->ingredient->cost_per_unit,
                'ingredient_cost' => $ingredientCost,
            ];
        }

        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'description'      => $product->description,
            'image_url'        => $product->image_url,
            'sku'              => $product->sku,
            'category_id'      => $product->category_id,
            'subcategory_id'   => $product->subcategory_id,
            'price'            => (float) $product->price,
            'recipe_cost'      => $recipeCost,
            'margin'           => $this->calculateMargin((float) $product->price, $recipeCost),
            'ingredient_count' => count($ingredients),
            'ingredients'      => $ingredients,
        ];
    }

    /**
     * Get all categories and subcategories for product catalog selectors.
     */
    public function getCatalog(): array
    {
        $categories = \Illuminate\Support\Facades\DB::select(
            'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name'
        );

        $subcategories = \Illuminate\Support\Facades\DB::select(
            'SELECT id, name, category_id FROM subcategories ORDER BY name'
        );

        return [
            'categories'    => array_map(fn ($c) => (array) $c, $categories),
            'subcategories' => array_map(fn ($s) => (array) $s, $subcategories),
        ];
    }

    /**
     * Update product metadata (name, description, image, category, subcategory).
     * Auto-generates SKU if not set.
     */
    public function updateProduct(int $productId, array $data): array
    {
        $product = Product::where('is_active', true)->findOrFail($productId);

        $fillable = ['name', 'description', 'price', 'image_url', 'category_id', 'subcategory_id'];
        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $product->{$field} = $data[$field];
            }
        }

        // Auto-generate SKU if empty
        if (empty($product->sku)) {
            $product->sku = $this->generateSku($product);
        }

        $product->save();

        return [
            'id'             => $product->id,
            'name'           => $product->name,
            'description'    => $product->description,
            'image_url'      => $product->image_url,
            'sku'            => $product->sku,
            'category_id'    => $product->category_id,
            'subcategory_id' => $product->subcategory_id,
        ];
    }

    /**
     * Generate a SKU like "C01-S02-0042" from category, subcategory, and product id.
     */
    private function generateSku(Product $product): string
    {
        $catPart = $product->category_id ? sprintf('C%02d', $product->category_id) : 'C00';
        $subPart = $product->subcategory_id ? sprintf('S%02d', $product->subcategory_id) : 'S00';
        $idPart  = sprintf('%04d', $product->id);

        return "{$catPart}-{$subPart}-{$idPart}";
    }

    /**
     * Preview replacing one ingredient with another across all recipes.
     *
     * @return array{source: array, target: array, affected_products: array}
     */
    public function replaceIngredientPreview(int $sourceId, int $targetId): array
    {
        $source = Ingredient::findOrFail($sourceId);
        $target = Ingredient::findOrFail($targetId);

        $affectedRecipes = ProductRecipe::where('ingredient_id', $sourceId)
            ->with(['product'])
            ->get();

        $products = [];
        foreach ($affectedRecipes as $recipe) {
            if (!$recipe->product || !$recipe->product->is_active) {
                continue;
            }

            $targetExists = ProductRecipe::where('product_id', $recipe->product_id)
                ->where('ingredient_id', $targetId)
                ->exists();

            $products[] = [
                'product_id'    => $recipe->product_id,
                'product_name'  => $recipe->product->name,
                'quantity'      => (float) $recipe->quantity,
                'unit'          => $recipe->unit,
                'target_exists' => $targetExists,
            ];
        }

        return [
            'source' => [
                'id'   => $source->id,
                'name' => $source->name,
                'unit' => $source->unit,
                'cost_per_unit' => (float) $source->cost_per_unit,
            ],
            'target' => [
                'id'   => $target->id,
                'name' => $target->name,
                'unit' => $target->unit,
                'cost_per_unit' => (float) $target->cost_per_unit,
            ],
            'affected_products' => $products,
        ];
    }

    /**
     * Replace one ingredient with another across all recipes and recalculate costs.
     *
     * @return array{products_affected: int, cost_prices_updated: int}
     */
    public function replaceIngredientApply(int $sourceId, int $targetId): array
    {
        Ingredient::findOrFail($sourceId);
        Ingredient::findOrFail($targetId);

        return DB::transaction(function () use ($sourceId, $targetId) {
            $affectedProductIds = ProductRecipe::where('ingredient_id', $sourceId)
                ->pluck('product_id')
                ->unique()
                ->toArray();

            foreach ($affectedProductIds as $productId) {
                $targetExists = ProductRecipe::where('product_id', $productId)
                    ->where('ingredient_id', $targetId)
                    ->exists();

                if ($targetExists) {
                    ProductRecipe::where('product_id', $productId)
                        ->where('ingredient_id', $sourceId)
                        ->delete();
                } else {
                    ProductRecipe::where('product_id', $productId)
                        ->where('ingredient_id', $sourceId)
                        ->update(['ingredient_id' => $targetId]);
                }
            }

            $updated = 0;
            foreach ($affectedProductIds as $productId) {
                try {
                    $this->recalculateCostPrice($productId);
                    $updated++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("[RecipeService] Failed to recalculate cost for product {$productId}: " . $e->getMessage());
                }
            }

            return [
                'products_affected'   => count($affectedProductIds),
                'cost_prices_updated' => $updated,
            ];
        });
    }

    /**
     * Recalculate cost_per_unit for all composite ingredients that use a given child ingredient.
     * Also cascades to recalculate cost_price of products using those composites.
     *
     * Call this after updating cost_per_unit of any ingredient.
     *
     * @param int $childIngredientId The ingredient whose price changed
     * @return array{composites_updated: int, products_updated: int}
     */
    public function cascadeCompositeCosts(int $childIngredientId): array
    {
        $parentIds = IngredientRecipe::where('child_ingredient_id', $childIngredientId)
            ->pluck('ingredient_id')
            ->unique()
            ->toArray();

        if (empty($parentIds)) {
            return ['composites_updated' => 0, 'products_updated' => 0];
        }

        $compositesUpdated = 0;
        $productIds = [];

        foreach ($parentIds as $parentId) {
            $parent = Ingredient::find($parentId);
            if (!$parent || !$parent->is_composite) {
                continue;
            }

            $newCost = $this->calculateCompositeCostPerUnit($parent->load('subRecipeItems.child'));
            $parent->cost_per_unit = round($newCost, 2);
            $parent->save();
            $compositesUpdated++;

            $pIds = ProductRecipe::where('ingredient_id', $parentId)
                ->pluck('product_id')
                ->toArray();
            $productIds = array_merge($productIds, $pIds);
        }

        $productIds = array_unique($productIds);
        $productsUpdated = 0;

        foreach ($productIds as $productId) {
            try {
                $this->recalculateCostPrice($productId);
                $productsUpdated++;
            } catch (\Exception $e) {
                Log::warning("[RecipeService] cascadeCompositeCosts: failed for product {$productId}: " . $e->getMessage());
            }
        }

        return [
            'composites_updated' => $compositesUpdated,
            'products_updated'   => $productsUpdated,
        ];
    }

    /**
     * Get active products grouped by category, excluding "Bebidas" category.
     * Returns categories with metadata and products keyed by category_id.
     *
     * @param  string|null $search  Optional product name filter
     * @return array { categories: [...], products: { [categoryId]: [...] } }
     */
    public function getRecipesGroupedByCategory(?string $search = null): array
    {
        // Exclude non-food categories from the recipe grouped view
        $excludeCategoryIds = DB::table('categories')
            ->whereIn('name', ['Bebidas', 'Snacks', 'Personalizar', 'Extras', 'Combos'])
            ->pluck('id')
            ->toArray();

        $query = Product::where('is_active', true)
            ->with(['recipes.ingredient']);

        if (!empty($excludeCategoryIds)) {
            $query->where(function ($q) use ($excludeCategoryIds) {
                $q->whereNotIn('category_id', $excludeCategoryIds)
                  ->orWhereNull('category_id');
            });
        }

        if ($search !== null && $search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $products = $query->get();

        // Build product data with costs
        $productData = [];
        foreach ($products as $product) {
            $recipeCost = 0.0;
            $ingredientCount = 0;

            foreach ($product->recipes as $recipeItem) {
                if (!$recipeItem->ingredient) {
                    continue;
                }
                $ingredientCount++;
                $recipeCost += $this->calculateIngredientCost(
                    (float) $recipeItem->ingredient->cost_per_unit,
                    $recipeItem->ingredient->unit,
                    (float) $recipeItem->quantity,
                    $recipeItem->unit
                );
            }

            $catId = $product->category_id ?? 0;

            if (!isset($productData[$catId])) {
                $productData[$catId] = [];
            }

            $productData[$catId][] = [
                'id' => $product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'price' => (float) $product->price,
                'recipe_cost' => $recipeCost,
                'margin' => $this->calculateMargin((float) $product->price, $recipeCost),
                'ingredient_count' => $ingredientCount,
            ];
        }

        // Get category metadata
        $categoryIds = array_keys($productData);
        $categories = DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sort_order' => $c->sort_order ?? 0,
                'product_count' => count($productData[$c->id] ?? []),
            ])
            ->values()
            ->toArray();

        // Add "Sin categoría" if there are uncategorized products
        if (isset($productData[0]) && count($productData[0]) > 0) {
            $categories[] = [
                'id' => 0,
                'name' => 'Sin categoría',
                'sort_order' => 999,
                'product_count' => count($productData[0]),
            ];
        }

        // Convert keys to strings for JSON
        $productsGrouped = [];
        foreach ($productData as $catId => $items) {
            $productsGrouped[(string) $catId] = $items;
        }

        return [
            'categories' => $categories,
            'products' => $productsGrouped,
        ];
    }
}
