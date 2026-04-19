<?php

namespace Tests\Unit\Enums;

use App\Enums\IngredientCategory;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: ingredient-categories-improvement, Property 5: Validación de categorías rechaza valores inválidos
 * Para cualquier string que NO esté en VALID_CATEGORIES, isValid() retorna false,
 * y para cualquier string que SÍ esté, isValid() retorna true.
 *
 * **Validates: Requirements 6.1, 6.2**
 */
class IngredientCategoryPropertyTest extends TestCase
{
    use TestTrait;

    public function testAllValidCategoriesAreAccepted(): void
    {
        $validCategories = IngredientCategory::VALID_CATEGORIES;

        $this->forAll(
            Generator\elements(...$validCategories)
        )
        ->then(function (string $category) {
            $this->assertTrue(
                IngredientCategory::isValid($category),
                "Valid category '$category' should be accepted"
            );
        });
    }

    public function testRandomStringsNotInValidCategoriesAreRejected(): void
    {
        $validCategories = IngredientCategory::VALID_CATEGORIES;

        $this->forAll(
            Generator\string()
        )
        ->when(function (string $value) use ($validCategories) {
            return !in_array($value, $validCategories, true);
        })
        ->then(function (string $value) {
            $this->assertFalse(
                IngredientCategory::isValid($value),
                "Invalid string '$value' should be rejected"
            );
        });
    }

    public function testCaseSensitivityIsEnforced(): void
    {
        $lowercaseVariants = array_map('strtolower', IngredientCategory::VALID_CATEGORIES);
        $uppercaseVariants = array_map('strtoupper', IngredientCategory::VALID_CATEGORIES);
        // Filter out any that happen to match valid (none should, but be safe)
        $invalidVariants = array_filter(
            array_merge($lowercaseVariants, $uppercaseVariants),
            fn (string $v) => !in_array($v, IngredientCategory::VALID_CATEGORIES, true)
        );

        $this->forAll(
            Generator\elements(...array_values($invalidVariants))
        )
        ->then(function (string $variant) {
            $this->assertFalse(
                IngredientCategory::isValid($variant),
                "Case variant '$variant' should be rejected (case-sensitive)"
            );
        });
    }

    public function testEmptyStringIsRejected(): void
    {
        $this->assertFalse(IngredientCategory::isValid(''));
    }

    public function testStringsWithExtraWhitespaceAreRejected(): void
    {
        $this->forAll(
            Generator\elements(...IngredientCategory::VALID_CATEGORIES)
        )
        ->then(function (string $category) {
            $this->assertFalse(
                IngredientCategory::isValid(' ' . $category),
                "Leading whitespace should cause rejection"
            );
            $this->assertFalse(
                IngredientCategory::isValid($category . ' '),
                "Trailing whitespace should cause rejection"
            );
            $this->assertFalse(
                IngredientCategory::isValid(' ' . $category . ' '),
                "Surrounding whitespace should cause rejection"
            );
        });
    }
}
