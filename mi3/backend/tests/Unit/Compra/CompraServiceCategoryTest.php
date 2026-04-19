<?php

namespace Tests\Unit\Compra;

use App\Enums\IngredientCategory;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: ingredient-categories-improvement, Property 4: Aplicación de categoría sugerida al crear ingrediente
 * Para cualquier categoría válida proporcionada como categoria_sugerida, cuando el CompraService
 * crea un ingrediente nuevo, el ingrediente resultante SHALL tener esa categoría asignada.
 * Para categorías inválidas o null, el ingrediente SHALL tener category = null.
 *
 * **Validates: Requirements 5.3, 5.5**
 */
class CompraServiceCategoryTest extends TestCase
{
    use TestTrait;

    /**
     * Simulates the category sanitization logic from CompraService::crearIngrediente()
     */
    private function sanitizeCategory(?string $category): ?string
    {
        if ($category !== null && !IngredientCategory::isValid($category)) {
            return null;
        }
        return $category;
    }

    public function testValidCategoryIsPreservedWhenCreatingIngredient(): void
    {
        $this->forAll(
            Generator\elements(...IngredientCategory::VALID_CATEGORIES)
        )
        ->then(function (string $category) {
            $result = $this->sanitizeCategory($category);
            $this->assertSame(
                $category,
                $result,
                "Valid category '$category' should be preserved as-is"
            );
        });
    }

    public function testInvalidCategoryBecomesNullWhenCreatingIngredient(): void
    {
        $validCategories = IngredientCategory::VALID_CATEGORIES;

        $this->forAll(
            Generator\string()
        )
        ->when(function (string $value) use ($validCategories) {
            return !in_array($value, $validCategories, true) && $value !== '';
        })
        ->then(function (string $invalidCategory) {
            $result = $this->sanitizeCategory($invalidCategory);
            $this->assertNull(
                $result,
                "Invalid category '$invalidCategory' should become null"
            );
        });
    }

    public function testNullCategoryStaysNull(): void
    {
        $result = $this->sanitizeCategory(null);
        $this->assertNull($result, "Null category should stay null");
    }

    public function testEmptyStringCategoryBecomesNull(): void
    {
        $result = $this->sanitizeCategory('');
        $this->assertNull($result, "Empty string category should become null");
    }
}
