# Fix Integral Recetas — Tasks

## Fase 1: Fix datos BD (urgente)

- [x] 1. Fix tocino en product_recipes: UPDATE quantity×0.050, unit='kg' WHERE ingredient_id=49 AND unit='unidad' (7 filas)
- [x] 2. Recalcular cost_price de todos los productos con receta via artisan tinker

## Fase 2: Frontend mi3

- [x] 3. Editor inline de recetas: refactorizar `app/admin/recetas/page.tsx` — click abre editor inline con estado `selectedProductId`, no `router.push`
- [x] 4. Label "Costo/u" → mostrar unidad real del ingrediente ($/kg, $/unidad) en editor de recetas y sub-recetas

## Fase 3: Stock deduction caja3

- [x] 5. Crear helper `resolveIngredientDeduction()` en caja3 que descompone ingredientes compuestos en hijos
- [x] 6. Integrar helper en `deductProduct()` de `confirm_transfer_payment.php`, `process_sale_inventory.php`, `create_order.php`

## Fase 4: Deploy + verificación

- [x] 7. Commit + push + deploy mi3-frontend + caja3
- [x] 8. Verificar en producción: costos correctos, editor inline funciona, stock deduction descompone compuestos
