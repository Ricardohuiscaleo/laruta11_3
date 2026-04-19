<?php

namespace Tests\Unit\Enums;

use App\Enums\IngredientCategory;
use PHPUnit\Framework\TestCase;

/**
 * Feature: ingredient-categories-improvement
 * Unit tests for IngredientCategory enum class.
 * Valida: Requisitos 6.1, 6.2
 */
class IngredientCategoryTest extends TestCase
{
    public function testIsValidReturnsTrueForAllValidCategories(): void
    {
        $validCategories = [
            'Carnes',
            'Vegetales',
            'Salsas',
            'Condimentos',
            'Panes',
            'Embutidos',
            'Pre-elaborados',
            'Lácteos',
            'Bebidas',
            'Gas',
            'Servicios',
            'Packaging',
            'Limpieza',
        ];

        foreach ($validCategories as $category) {
            $this->assertTrue(
                IngredientCategory::isValid($category),
                "Expected '$category' to be valid"
            );
        }
    }

    public function testIsValidReturnsFalseForInvalidStrings(): void
    {
        $invalidCategories = ['Invalid', 'ingredientes', '', 'carnes', 'CARNES', 'Frutas'];

        foreach ($invalidCategories as $category) {
            $this->assertFalse(
                IngredientCategory::isValid($category),
                "Expected '$category' to be invalid"
            );
        }
    }

    public function testIsValidReturnsFalseForNull(): void
    {
        $this->assertFalse(IngredientCategory::isValid(null));
    }

    public function testAllReturnsExactly13Elements(): void
    {
        $this->assertCount(13, IngredientCategory::all());
    }

    public function testAllReturnsOnlyStrings(): void
    {
        foreach (IngredientCategory::all() as $category) {
            $this->assertIsString($category);
        }
    }
}
