# Fix Integral Recetas — Design

## D1: Migración datos tocino (BD)
SQL directo: UPDATE `product_recipes` SET quantity = quantity * 0.050, unit = 'kg' WHERE ingredient_id = 49 AND unit = 'unidad'.
Ejecutar via artisan tinker o SQL directo en producción.

## D2: Recalcular cost_price
Artisan command o tinker: iterar todos los productos con receta, llamar `RecipeService::recalculateCostPrice()`.

## D3: Editor inline recetas (mi3-frontend)
Refactorizar `app/admin/recetas/page.tsx`:
- Agregar estado `selectedProductId: number | null`
- Click en fila → `setSelectedProductId(p.id)` en vez de `router.push`
- Cuando `selectedProductId !== null`, renderizar editor inline (reutilizar lógica de `[productId]/page.tsx`)
- Botón "Volver" → `setSelectedProductId(null)` + refetch lista
- Eliminar import de `useRouter`

## D4: Stock deduction compuesto (caja3)
Modificar `deductProduct()` en `caja3/api/confirm_transfer_payment.php`:
- Después de obtener receta, para cada ingrediente verificar si `is_composite=1`
- Si es compuesto, consultar `ingredient_recipes` para obtener hijos
- Descontar hijos proporcionalmente en vez del ingrediente padre
- Misma lógica en `process_sale_inventory.php` y `create_order.php`

Función helper:
```php
function resolveIngredientDeduction($pdo, $ingredient_id, $recipe_quantity, $recipe_unit) {
    // Check if composite
    $comp = $pdo->prepare("SELECT is_composite FROM ingredients WHERE id = ?");
    $comp->execute([$ingredient_id]);
    $is_composite = $comp->fetchColumn();
    
    if ($is_composite) {
        // Get children from ingredient_recipes
        $children = $pdo->prepare("SELECT ir.child_ingredient_id, ir.quantity, ir.unit, i.current_stock 
            FROM ingredient_recipes ir JOIN ingredients i ON ir.child_ingredient_id = i.id 
            WHERE ir.ingredient_id = ?");
        $children->execute([$ingredient_id]);
        $result = [];
        foreach ($children->fetchAll() as $child) {
            $result[] = [
                'ingredient_id' => $child['child_ingredient_id'],
                'quantity' => $child['quantity'] * $recipe_quantity, // scale by parent qty
                'unit' => $child['unit'],
                'current_stock' => $child['current_stock']
            ];
        }
        return $result;
    }
    
    return [['ingredient_id' => $ingredient_id, 'quantity' => $recipe_quantity, 'unit' => $recipe_unit, 'current_stock' => null]];
}
```

## D5: Label costo con unidad
En tablas de ingredientes, cambiar header "Costo/u" → "Costo/unidad" y mostrar en cada celda: `$14.000/kg`.
