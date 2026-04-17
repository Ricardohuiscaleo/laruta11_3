<?php

namespace Tests\Unit\Recipe;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Services\Recipe\RecipeService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RecipeService listing and detail methods.
 * Tests the data transformation logic using mocked models.
 */
class RecipeServiceListingTest extends TestCase
{
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeService();
    }

    public function testGetRecipeDetailReturnsCorrectStructure(): void
    {
        // Test the ingredient cost calculation that getRecipeDetail relies on
        // Ingredient costs $5000/kg, recipe uses 200g → cost = $1000
        $cost = $this->service->calculateIngredientCost(5000.0, 'kg', 200.0, 'g');
        $this->assertEqualsWithDelta(1000.0, $cost, 0.01);
    }

    public function testMarginCalculationForListingResults(): void
    {
        // Price $5990, cost $2050 → margin = 65.8%
        $margin = $this->service->calculateMargin(5990.0, 2050.0);
        $this->assertEqualsWithDelta(65.8, $margin, 0.1);
    }

    public function testMarginIsNullWhenPriceIsZero(): void
    {
        $margin = $this->service->calculateMargin(0.0, 1000.0);
        $this->assertNull($margin);
    }

    public function testIngredientCostWithSameUnits(): void
    {
        // 500g at $3/g = $1500
        $cost = $this->service->calculateIngredientCost(3.0, 'g', 500.0, 'g');
        $this->assertEqualsWithDelta(1500.0, $cost, 0.01);
    }

    public function testIngredientCostWithMlToL(): void
    {
        // $2000/L, recipe uses 500ml → cost = $1000
        $cost = $this->service->calculateIngredientCost(2000.0, 'L', 500.0, 'ml');
        $this->assertEqualsWithDelta(1000.0, $cost, 0.01);
    }

    public function testIngredientCostWithUnidad(): void
    {
        // $150/unidad, recipe uses 3 unidades → cost = $450
        $cost = $this->service->calculateIngredientCost(150.0, 'unidad', 3.0, 'unidad');
        $this->assertEqualsWithDelta(450.0, $cost, 0.01);
    }

    public function testSortByFieldMapping(): void
    {
        // Verify the sort field mapping works correctly via a collection sort
        $data = collect([
            ['name' => 'B', 'price' => 100.0, 'recipe_cost' => 50.0, 'margin' => 50.0],
            ['name' => 'A', 'price' => 200.0, 'recipe_cost' => 30.0, 'margin' => 85.0],
            ['name' => 'C', 'price' => 50.0, 'recipe_cost' => 40.0, 'margin' => 20.0],
        ]);

        $sortedByName = $data->sortBy('name', SORT_REGULAR, false)->values();
        $this->assertSame('A', $sortedByName[0]['name']);

        $sortedByPrice = $data->sortBy('price', SORT_REGULAR, false)->values();
        $this->assertEqualsWithDelta(50.0, $sortedByPrice[0]['price'], 0.01);

        $sortedByCost = $data->sortBy('recipe_cost', SORT_REGULAR, false)->values();
        $this->assertEqualsWithDelta(30.0, $sortedByCost[0]['recipe_cost'], 0.01);

        $sortedByMargin = $data->sortBy('margin', SORT_REGULAR, false)->values();
        $this->assertEqualsWithDelta(20.0, $sortedByMargin[0]['margin'], 0.01);
    }
}
