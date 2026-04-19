<?php

namespace App\Enums;

class IngredientCategory
{
    public const VALID_CATEGORIES = [
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

    public static function isValid(?string $category): bool
    {
        return $category !== null && in_array($category, self::VALID_CATEGORIES, true);
    }

    public static function all(): array
    {
        return self::VALID_CATEGORIES;
    }
}
