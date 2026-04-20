# Design: Sub-Recetas de Ingredientes Compuestos

## Arquitectura

### Nueva tabla `ingredient_recipes`
```sql
CREATE TABLE ingredient_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,          -- ingrediente compuesto (ej: Hamburguesa R11)
    child_ingredient_id INT NOT NULL,    -- ingrediente real (ej: Carne Molida)
    quantity DECIMAL(10,3) NOT NULL,     -- cantidad por 1 unidad del compuesto
    unit VARCHAR(20) NOT NULL DEFAULT 'kg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    FOREIGN KEY (child_ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_parent_child (ingredient_id, child_ingredient_id),
    INDEX idx_ingredient (ingredient_id)
);
```

### Ingrediente compuesto: `is_composite` flag
Agregar columna `is_composite TINYINT(1) DEFAULT 0` a `ingredients` para marcar ingredientes que tienen sub-receta.

### Flujo de stock
1. Venta de "Hamburguesa Clásica" → receta dice 1× "Hamburguesa R11 200gr"
2. Sistema detecta que "Hamburguesa R11" es compuesto (is_composite=1)
3. En vez de descontar stock de id=48, descuenta de los hijos:
   - 0.150 kg de Carne Molida
   - 0.040 kg de Tocino
   - 0.010 kg de Longaniza

### Flujo de compras
- Compra en Shipo: carne molida 4.5kg, tocino 2kg, longaniza 0.5kg
- Se registra directamente en ingredientes reales
- Stock de "Hamburguesa R11" se calcula dinámicamente: min(stock_hijo / cantidad_por_unidad)

### Cálculo de costo
- Costo de "Hamburguesa R11" = Σ(costo_hijo × cantidad_por_unidad)
- Se recalcula automáticamente cuando cambia el costo de un ingrediente hijo

## Backend (mi3)

### Model: `IngredientRecipe`
- Relaciones: belongsTo Ingredient (parent), belongsTo Ingredient (child)

### Service: `IngredientRecipeService`
- `getCompositeIngredients()` — lista ingredientes con is_composite=1 y sus hijos
- `getSubRecipe(ingredientId)` — detalle de sub-receta
- `saveSubRecipe(ingredientId, children[])` — crear/actualizar
- `deleteSubRecipe(ingredientId)` — eliminar
- `calculateCompositeCost(ingredientId)` — costo basado en hijos
- `calculateCompositeStock(ingredientId)` — stock máximo producible

### Controller: `IngredientRecipeController`
- CRUD REST en `/api/v1/admin/ingredient-recipes`

### Integración RecipeService
- `calculateIngredientCost()` → si ingrediente es compuesto, usar costo calculado de hijos
- Stock deduction → si ingrediente es compuesto, descontar hijos proporcionalmente

## Frontend (mi3)

### Nueva sub-tab "Sub-Recetas" en RecetasSection
- Lista de ingredientes compuestos como cards
- Cada card muestra: nombre, componentes, costo calculado, stock equivalente, productos que lo usan
- Click → editor inline o modal

### Editor de sub-receta
- Selector de ingrediente compuesto (autocomplete de ingredientes con is_composite=1)
- Lista de ingredientes hijos con cantidad/unidad
- Calculadora integrada (como hc.html): input cantidad a producir → muestra kg a comprar de cada ingrediente
- Botón guardar

### Calculadora de producción
- Input: "¿Cuántas hamburguesas quieres hacer?"
- Output: tabla con kg necesarios de cada ingrediente + costo total + costo unitario
- Similar a hc.html pero integrada en la UI
