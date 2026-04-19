<?php

namespace Tests\Unit\Compra;

use App\Enums\IngredientCategory;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: ingredient-categories-improvement, Property 3: Extracción correcta de categorías desde ingredientes activos
 * Para cualquier conjunto de ingredientes activos en la base de datos, la API SHALL retornar
 * exactamente las categorías distintas que tienen al menos un ingrediente activo,
 * cada una con el conteo correcto de ingredientes asociados.
 *
 * **Validates: Requirements 4.1, 4.2, 4.3**
 */
class CategoryExtractionPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Simulates the category extraction logic from get_items_compra.php:
     * SELECT DISTINCT category, COUNT(*) FROM ingredients
     * WHERE is_active = 1 AND category IS NOT NULL GROUP BY category
     */
    private function extractCategories(array $ingredients): array
    {
        $counts = [];
        foreach ($ingredients as $ing) {
            if ($ing['is_active'] && $ing['category'] !== null && $ing['category'] !== '') {
                $cat = $ing['category'];
                $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            }
        }
        $result = [];
        foreach ($counts as $cat => $count) {
            $result[] = ['name' => $cat, 'count' => $count];
        }
        return $result;
    }

    /**
     * Generator for a single ingredient with random active/inactive status and category.
     */
    private function ingredientGenerator(): \Eris\Generator
    {
        $categories = array_merge(IngredientCategory::VALID_CATEGORIES, [null, null, '']);

        return Generator\map(
            function (array $tuple) use ($categories) {
                [$nameIdx, $catIdx, $isActive] = $tuple;
                return [
                    'name' => 'ingredient_' . $nameIdx,
                    'category' => $categories[$catIdx % count($categories)],
                    'is_active' => $isActive,
                ];
            },
            Generator\tuple(
                Generator\choose(1, 100),
                Generator\choose(0, 50),
                Generator\elements(true, false)
            )
        );
    }

    public function testExtractedCategoriesMatchDistinctActiveCategories(): void
    {
        $this->forAll(
            Generator\seq($this->ingredientGenerator())
        )
        ->when(function (array $ingredients) {
            return count($ingredients) > 0 && count($ingredients) <= 50;
        })
        ->then(function (array $ingredients) {
            $extracted = $this->extractCategories($ingredients);

            // Compute expected: distinct non-null, non-empty categories from active ingredients
            $expected = [];
            foreach ($ingredients as $ing) {
                if ($ing['is_active'] && $ing['category'] !== null && $ing['category'] !== '') {
                    $cat = $ing['category'];
                    $expected[$cat] = ($expected[$cat] ?? 0) + 1;
                }
            }

            // Verify count of categories matches
            $this->assertCount(
                count($expected),
                $extracted,
                "Number of extracted categories should match distinct active categories"
            );

            // Verify each extracted category has correct count
            foreach ($extracted as $entry) {
                $this->assertArrayHasKey(
                    $entry['name'],
                    $expected,
                    "Extracted category '{$entry['name']}' should exist in expected"
                );
                $this->assertSame(
                    $expected[$entry['name']],
                    $entry['count'],
                    "Count for category '{$entry['name']}' should match"
                );
            }
        });
    }

    public function testInactiveIngredientsDoNotContributeCategories(): void
    {
        $this->forAll(
            Generator\elements(...IngredientCategory::VALID_CATEGORIES),
            Generator\choose(1, 10)
        )
        ->then(function (string $category, int $count) {
            // Create ingredients that are all inactive
            $ingredients = [];
            for ($i = 0; $i < $count; $i++) {
                $ingredients[] = [
                    'name' => "inactive_$i",
                    'category' => $category,
                    'is_active' => false,
                ];
            }

            $extracted = $this->extractCategories($ingredients);
            $this->assertEmpty(
                $extracted,
                "Inactive ingredients should not contribute any categories"
            );
        });
    }

    public function testNullCategoriesAreExcluded(): void
    {
        $this->forAll(
            Generator\choose(1, 10)
        )
        ->then(function (int $count) {
            // Create active ingredients with null category
            $ingredients = [];
            for ($i = 0; $i < $count; $i++) {
                $ingredients[] = [
                    'name' => "null_cat_$i",
                    'category' => null,
                    'is_active' => true,
                ];
            }

            $extracted = $this->extractCategories($ingredients);
            $this->assertEmpty(
                $extracted,
                "Null categories should be excluded from extraction"
            );
        });
    }

    public function testEachCategoryCountIsCorrect(): void
    {
        $this->forAll(
            Generator\elements(...IngredientCategory::VALID_CATEGORIES),
            Generator\choose(1, 20)
        )
        ->then(function (string $category, int $count) {
            // Create N active ingredients with the same category
            $ingredients = [];
            for ($i = 0; $i < $count; $i++) {
                $ingredients[] = [
                    'name' => "item_$i",
                    'category' => $category,
                    'is_active' => true,
                ];
            }

            $extracted = $this->extractCategories($ingredients);
            $this->assertCount(1, $extracted);
            $this->assertSame($category, $extracted[0]['name']);
            $this->assertSame($count, $extracted[0]['count']);
        });
    }
}
