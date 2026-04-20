<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use App\Models\Ingredient;
use App\Models\IngredientRecipe;
use App\Models\ProductRecipe;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngredientRecipeService
{
    /**
     * Unit conversion factors (same as RecipeService).
     * kg/g → base is grams, L/ml → base is ml, unidad → no conversion.
     */
    const UNIT_CONVERSIONS = RecipeService::UNIT_CONVERSIONS;

    /**
     * Get all composite ingredients with sub-recipe details.
     *
     * Returns: id, name, unit, cost_per_unit, composite_cost, max_stock, product_count.
     *
     * @return Collection
     */
    public function getCompositeIngredients(): Collection
    {
        $ingredients = Ingredient::where('is_composite', true)
            ->where('is_active', true)
            ->with(['subRecipeItems.child'])
            ->withCount(['recetas as product_count'])
            ->get();

        return $ingredients->map(function (Ingredient $ingredient) {
            return [
                'id'             => $ingredient->id,
                'name'           => $ingredient->name,
                'unit'           => $ingredient->unit,
                'cost_per_unit'  => (float) $ingredient->cost_per_unit,
                'composite_cost' => $this->calculateCompositeCostFromItems($ingredient->subRecipeItems),
                'max_stock'      => $this->calculateCompositeStockFromItems($ingredient->subRecipeItems),
                'product_count'  => (int) $ingredient->product_count,
                'children_count' => $ingredient->subRecipeItems->count(),
            ];
        });
    }

    /**
     * Get sub-recipe detail for a composite ingredient.
     *
     * Returns the ingredient with its children: name, quantity, unit, cost_per_unit, cost contribution.
     *
     * @param  int   $ingredientId
     * @return array
     *
     * @throws ModelNotFoundException
     */
    public function getSubRecipe(int $ingredientId): array
    {
        $ingredient = Ingredient::where('is_composite', true)
            ->with(['subRecipeItems.child'])
            ->findOrFail($ingredientId);

        $children = [];
        $totalCost = 0.0;

        foreach ($ingredient->subRecipeItems as $item) {
            if (!$item->child) {
                continue;
            }

            $costContribution = $this->calculateChildCost(
                (float) $item->child->cost_per_unit,
                $item->child->unit,
                (float) $item->quantity,
                $item->unit
            );

            $totalCost += $costContribution;

            $children[] = [
                'id'                => $item->child->id,
                'name'              => $item->child->name,
                'quantity'          => (float) $item->quantity,
                'unit'              => $item->unit,
                'cost_per_unit'     => (float) $item->child->cost_per_unit,
                'cost_contribution' => $costContribution,
            ];
        }

        return [
            'id'             => $ingredient->id,
            'name'           => $ingredient->name,
            'unit'           => $ingredient->unit,
            'composite_cost' => $totalCost,
            'children'       => $children,
        ];
    }

    /**
     * Replace all sub-recipe items for a composite ingredient.
     *
     * Each child: ['child_ingredient_id' => int, 'quantity' => float, 'unit' => string].
     * Marks ingredient as is_composite=1. Validates no duplicates, no self-reference.
     *
     * @param  int   $ingredientId
     * @param  array $children
     * @return float The recalculated composite cost
     *
     * @throws ValidationException
     * @throws ModelNotFoundException
     */
    public function saveSubRecipe(int $ingredientId, array $children): float
    {
        $this->validateChildren($ingredientId, $children);

        return DB::transaction(function () use ($ingredientId, $children) {
            $ingredient = Ingredient::findOrFail($ingredientId);

            // Remove existing sub-recipe items
            IngredientRecipe::where('ingredient_id', $ingredientId)->delete();

            // Insert new children
            foreach ($children as $child) {
                IngredientRecipe::create([
                    'ingredient_id'       => $ingredientId,
                    'child_ingredient_id' => $child['child_ingredient_id'],
                    'quantity'            => $child['quantity'],
                    'unit'                => $child['unit'],
                ]);
            }

            // Mark as composite
            $ingredient->is_composite = true;
            $ingredient->save();

            return $this->calculateCompositeCost($ingredientId);
        });
    }

    /**
     * Remove all sub-recipe items and unmark as composite.
     *
     * @param  int  $ingredientId
     * @return void
     *
     * @throws ModelNotFoundException
     */
    public function deleteSubRecipe(int $ingredientId): void
    {
        DB::transaction(function () use ($ingredientId) {
            $ingredient = Ingredient::findOrFail($ingredientId);

            IngredientRecipe::where('ingredient_id', $ingredientId)->delete();

            $ingredient->is_composite = false;
            $ingredient->save();
        });
    }

    /**
     * Calculate composite cost based on children: Σ(child.cost_per_unit normalized to child quantity unit).
     *
     * @param  int   $ingredientId
     * @return float
     *
     * @throws ModelNotFoundException
     */
    public function calculateCompositeCost(int $ingredientId): float
    {
        $items = IngredientRecipe::where('ingredient_id', $ingredientId)
            ->with('child')
            ->get();

        return $this->calculateCompositeCostFromItems($items);
    }

    /**
     * Calculate max producible units: min(child.current_stock / quantity_per_unit) across all children.
     *
     * @param  int   $ingredientId
     * @return int
     *
     * @throws ModelNotFoundException
     */
    public function calculateCompositeStock(int $ingredientId): int
    {
        $items = IngredientRecipe::where('ingredient_id', $ingredientId)
            ->with('child')
            ->get();

        return $this->calculateCompositeStockFromItems($items);
    }

    /**
     * Calculate composite cost from a pre-loaded collection of sub-recipe items.
     *
     * @param  Collection|\Illuminate\Database\Eloquent\Collection $items
     * @return float
     */
    private function calculateCompositeCostFromItems($items): float
    {
        $totalCost = 0.0;

        foreach ($items as $item) {
            if (!$item->child) {
                continue;
            }

            $totalCost += $this->calculateChildCost(
                (float) $item->child->cost_per_unit,
                $item->child->unit,
                (float) $item->quantity,
                $item->unit
            );
        }

        return $totalCost;
    }

    /**
     * Calculate max producible units from a pre-loaded collection of sub-recipe items.
     *
     * For each child: floor(child.current_stock / quantity converted to child's unit).
     * Returns the minimum across all children.
     *
     * @param  Collection|\Illuminate\Database\Eloquent\Collection $items
     * @return int
     */
    private function calculateCompositeStockFromItems($items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $maxProducible = PHP_INT_MAX;

        foreach ($items as $item) {
            $child = $item->child;

            if (!$child) {
                continue;
            }

            $recipeQuantity = (float) $item->quantity;
            $recipeUnit     = $item->unit;
            $childUnit      = $child->unit;
            $currentStock   = (float) $child->current_stock;

            // Convert recipe quantity to child ingredient's unit
            $quantityInChildUnit = $recipeQuantity;

            $recipeConv = self::UNIT_CONVERSIONS[$recipeUnit] ?? null;
            $childConv  = self::UNIT_CONVERSIONS[$childUnit] ?? null;

            if ($recipeConv && $childConv && $recipeConv['base'] === $childConv['base']) {
                $quantityInBase      = $recipeQuantity * $recipeConv['factor'];
                $quantityInChildUnit = $quantityInBase / $childConv['factor'];
            }

            if ($quantityInChildUnit <= 0) {
                continue;
            }

            $producible = (int) floor($currentStock / $quantityInChildUnit);

            if ($producible < $maxProducible) {
                $maxProducible = $producible;
            }
        }

        return $maxProducible === PHP_INT_MAX ? 0 : $maxProducible;
    }

    /**
     * Calculate the cost contribution of a single child ingredient line.
     *
     * Uses the same unit normalization logic as RecipeService::calculateIngredientCost.
     *
     * @param  float  $costPerUnit     Child's cost_per_unit
     * @param  string $childUnit       Child's native unit (e.g. 'kg')
     * @param  float  $quantity        Quantity in the sub-recipe
     * @param  string $recipeUnit      Unit used in the sub-recipe (e.g. 'g')
     * @return float
     */
    private function calculateChildCost(
        float  $costPerUnit,
        string $childUnit,
        float  $quantity,
        string $recipeUnit
    ): float {
        $childConv  = self::UNIT_CONVERSIONS[$childUnit] ?? null;
        $recipeConv = self::UNIT_CONVERSIONS[$recipeUnit] ?? null;

        // Unknown units → direct multiplication
        if (!$childConv || !$recipeConv) {
            return $costPerUnit * $quantity;
        }

        // Incompatible bases → direct multiplication
        if ($childConv['base'] !== $recipeConv['base']) {
            return $costPerUnit * $quantity;
        }

        // Normalize cost to "per base unit"
        $costPerBaseUnit = $costPerUnit / $childConv['factor'];

        // Normalize recipe quantity to base units
        $quantityInBase = $quantity * $recipeConv['factor'];

        return $costPerBaseUnit * $quantityInBase;
    }

    /**
     * Validate children array for save operations.
     *
     * Checks: no duplicates, no self-reference, quantity > 0.
     *
     * @param  int   $ingredientId
     * @param  array $children
     * @return void
     *
     * @throws ValidationException
     */
    private function validateChildren(int $ingredientId, array $children): void
    {
        if (empty($children)) {
            throw ValidationException::withMessages([
                'children' => ['La sub-receta debe tener al menos un ingrediente hijo'],
            ]);
        }

        // Check for duplicate child_ingredient_ids
        $childIds = array_column($children, 'child_ingredient_id');
        if (count($childIds) !== count(array_unique($childIds))) {
            throw ValidationException::withMessages([
                'children' => ['Ingrediente hijo duplicado en la sub-receta'],
            ]);
        }

        // Check for self-reference
        if (in_array($ingredientId, $childIds, true)) {
            throw ValidationException::withMessages([
                'children' => ['Un ingrediente no puede ser hijo de sí mismo'],
            ]);
        }

        // Check for quantity > 0
        foreach ($children as $child) {
            if (!isset($child['quantity']) || $child['quantity'] <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['La cantidad debe ser mayor a 0'],
                ]);
            }
        }
    }
}
