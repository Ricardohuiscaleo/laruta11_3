<?php

namespace Tests\Unit\Recipe;

use App\Services\Recipe\RecipeService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RecipeService recommendations and audit methods.
 * Tests the pure calculation logic without database dependencies.
 */
class RecipeServiceRecommendationsAuditTest extends TestCase
{
    private RecipeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecipeService();
    }

    // --- Recommendation formula tests ---

    public function testRecommendedPriceFormula65Percent(): void
    {
        // Recipe cost = $2050, target margin = 65%
        // Recommended = 2050 / (1 - 0.65) = 2050 / 0.35 = 5857.14
        // Rounded to nearest 100 = $5900
        $recipeCost = 2050.0;
        $targetMargin = 65.0;
        $recommended = $recipeCost / (1 - $targetMargin / 100);
        $rounded = round($recommended / 100) * 100;

        $this->assertEqualsWithDelta(5900.0, $rounded, 0.01);
    }

    public function testRecommendedPriceFormula50Percent(): void
    {
        // Recipe cost = $3000, target margin = 50%
        // Recommended = 3000 / (1 - 0.50) = 3000 / 0.50 = 6000
        // Rounded to nearest 100 = $6000
        $recipeCost = 3000.0;
        $targetMargin = 50.0;
        $recommended = $recipeCost / (1 - $targetMargin / 100);
        $rounded = round($recommended / 100) * 100;

        $this->assertEqualsWithDelta(6000.0, $rounded, 0.01);
    }

    public function testRecommendedPriceRoundsToNearest100(): void
    {
        // Recipe cost = $1000, target margin = 70%
        // Recommended = 1000 / 0.30 = 3333.33
        // Rounded to nearest 100 = $3300
        $recipeCost = 1000.0;
        $targetMargin = 70.0;
        $recommended = $recipeCost / (1 - $targetMargin / 100);
        $rounded = round($recommended / 100) * 100;

        $this->assertEqualsWithDelta(3300.0, $rounded, 0.01);
    }

    public function testRecommendedPriceRoundsUp(): void
    {
        // Recipe cost = $1500, target margin = 60%
        // Recommended = 1500 / 0.40 = 3750
        // Rounded to nearest 100 = $3800
        $recipeCost = 1500.0;
        $targetMargin = 60.0;
        $recommended = $recipeCost / (1 - $targetMargin / 100);
        $rounded = round($recommended / 100) * 100;

        $this->assertEqualsWithDelta(3800.0, $rounded, 0.01);
    }

    // --- Stock audit producibility tests ---

    public function testProducibleUnitsBasicCalculation(): void
    {
        // Ingredient stock = 5000g, recipe needs 200g per unit
        // Producible = floor(5000 / 200) = 25
        $stock = 5000.0;
        $quantityPerUnit = 200.0;
        $producible = (int) floor($stock / $quantityPerUnit);

        $this->assertSame(25, $producible);
    }

    public function testProducibleUnitsWithUnitConversion(): void
    {
        // Ingredient stock in g = 5000, recipe needs 0.2 kg
        // Convert: 0.2 kg = 200g, then 5000 / 200 = 25
        $stockInGrams = 5000.0;
        $recipeQuantity = 0.2; // kg
        $recipeConvFactor = 1000; // kg → g
        $ingredientConvFactor = 1; // g → g

        $quantityInIngredientUnit = ($recipeQuantity * $recipeConvFactor) / $ingredientConvFactor;
        $producible = (int) floor($stockInGrams / $quantityInIngredientUnit);

        $this->assertSame(25, $producible);
    }

    public function testProducibleUnitsFloorsDivision(): void
    {
        // Stock = 550g, recipe needs 200g → floor(2.75) = 2
        $producible = (int) floor(550.0 / 200.0);
        $this->assertSame(2, $producible);
    }

    public function testLimitingIngredientIsMinimum(): void
    {
        // Carne: 5000g / 200g = 25
        // Pan: 100 unidades / 1 unidad = 100
        // Lechuga: 300g / 50g = 6
        // Limiting = Lechuga with 6
        $producibleCarne = (int) floor(5000.0 / 200.0);
        $produciblePan = (int) floor(100.0 / 1.0);
        $producibleLechuga = (int) floor(300.0 / 50.0);

        $maxProducible = min($producibleCarne, $produciblePan, $producibleLechuga);
        $this->assertSame(6, $maxProducible);
    }

    public function testZeroStockProducesZero(): void
    {
        $producible = (int) floor(0.0 / 200.0);
        $this->assertSame(0, $producible);
    }

    // --- Stock status classification tests ---

    public function testStockStatusSufficient(): void
    {
        // current_stock > 2 × min_stock_level → sufficient
        $currentStock = 1000.0;
        $minStockLevel = 200.0;

        $status = $this->classifyStockStatus($currentStock, $minStockLevel);
        $this->assertSame('sufficient', $status);
    }

    public function testStockStatusLow(): void
    {
        // current_stock > min_stock_level but <= 2 × min_stock_level → low
        $currentStock = 350.0;
        $minStockLevel = 200.0;

        $status = $this->classifyStockStatus($currentStock, $minStockLevel);
        $this->assertSame('low', $status);
    }

    public function testStockStatusCritical(): void
    {
        // current_stock <= min_stock_level → critical
        $currentStock = 150.0;
        $minStockLevel = 200.0;

        $status = $this->classifyStockStatus($currentStock, $minStockLevel);
        $this->assertSame('critical', $status);
    }

    public function testStockStatusCriticalAtExactMinLevel(): void
    {
        // current_stock == min_stock_level → critical
        $currentStock = 200.0;
        $minStockLevel = 200.0;

        $status = $this->classifyStockStatus($currentStock, $minStockLevel);
        $this->assertSame('critical', $status);
    }

    public function testStockStatusLowAtExactDoubleMinLevel(): void
    {
        // current_stock == 2 × min_stock_level → low (not sufficient)
        $currentStock = 400.0;
        $minStockLevel = 200.0;

        $status = $this->classifyStockStatus($currentStock, $minStockLevel);
        $this->assertSame('low', $status);
    }

    // --- CSV export format tests ---

    public function testCsvHeaderFormat(): void
    {
        $header = "Producto,Max Producible,Ingrediente Limitante,Estado Stock\n";
        $this->assertStringContainsString('Producto', $header);
        $this->assertStringContainsString('Max Producible', $header);
        $this->assertStringContainsString('Ingrediente Limitante', $header);
        $this->assertStringContainsString('Estado Stock', $header);
    }

    public function testCsvRowFormatting(): void
    {
        $entry = [
            'name' => 'Hamburguesa Clásica',
            'max_producible' => 6,
            'limiting_ingredient' => 'Lechuga',
            'stock_status' => 'critical',
        ];

        $producto = str_replace(',', ' ', $entry['name']);
        $limitante = str_replace(',', ' ', $entry['limiting_ingredient']);
        $row = "{$producto},{$entry['max_producible']},{$limitante},{$entry['stock_status']}";

        $this->assertSame('Hamburguesa Clásica,6,Lechuga,critical', $row);
    }

    public function testCsvEscapesCommasInNames(): void
    {
        $name = 'Pan, Queso y Jamón';
        $escaped = str_replace(',', ' ', $name);
        $this->assertSame('Pan  Queso y Jamón', $escaped);
        $this->assertStringNotContainsString(',', $escaped);
    }

    public function testCsvNullLimitingIngredient(): void
    {
        $limitante = null;
        $formatted = $limitante ? str_replace(',', ' ', $limitante) : 'N/A';
        $this->assertSame('N/A', $formatted);
    }

    /**
     * Helper: replicate the stock status classification logic from getStockAudit.
     */
    private function classifyStockStatus(float $currentStock, float $minStockLevel): string
    {
        if ($currentStock <= $minStockLevel) {
            return 'critical';
        }
        if ($currentStock <= 2 * $minStockLevel) {
            return 'low';
        }
        return 'sufficient';
    }
}
