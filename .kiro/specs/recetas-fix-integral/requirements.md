# Fix Integral Recetas — Requirements

## Contexto
Tras implementar sub-recetas (ingredientes compuestos), se detectaron 5 issues que necesitan resolverse de forma coordinada.

## R1: Fix datos tocino en recetas
7 productos tienen tocino (id=49) con `unit=unidad` pero el ingrediente ahora es `unit=kg`. Cada "unidad" era 50g.
- Tocino Extra (205): 1u → 0.050 kg
- Cheeseburger (218): 2u → 0.100 kg
- Completo Tocino (194): 1u → 0.050 kg
- Pichanga Familiar (196): 1u → 0.050 kg
- Pizza Mediana (232): 1u → 0.050 kg
- Pizza Familiar (231): 2u → 0.100 kg
- Triple XXXL (193): 3u → 0.150 kg

## R2: Recalcular cost_price de todos los productos
Después de corregir datos, recalcular `products.cost_price` para todos los productos con receta usando `RecipeService::recalculateCostPrice()`.

## R3: Editor inline de recetas en mi3
Click en producto en la lista de Recetas debe abrir editor inline (como Sub-Recetas), no navegar a otra URL. El editor debe mostrar ingredientes con cantidad, unidad, costo/unidad (con unidad real), y costo calculado.

## R4: Stock deduction de ingredientes compuestos en caja3
Cuando se vende un producto cuya receta incluye un ingrediente compuesto (is_composite=1), caja3 debe descomponer en ingredientes hijos antes de descontar stock. Consultar tabla `ingredient_recipes` para obtener los hijos.

## R5: Label "Costo/u" → mostrar unidad real
En la tabla de ingredientes del editor de recetas y sub-recetas, el header "Costo/u" debe mostrar la unidad del ingrediente (ej: "$/kg", "$/unidad").
