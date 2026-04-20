# Sub-Recetas de Ingredientes Compuestos (Hamburguesa R11)

## Contexto
Hoy "Hamburguesa R11 200gr" (ingredient id=48) es un ingrediente "fantasma" — no se compra directamente. Se compra carne molida, tocino y longaniza en Shipo, pero se registra como "Hamburguesa R11" en compras, rompiendo la trazabilidad.

La receta real (según hc.html) por cada unidad de 200g:
- 150g Carne Molida
- 40g Tocino
- 10g Longaniza

## Requisitos

### R1: Crear ingredientes reales faltantes
- Crear "Carne Molida" como ingrediente (unit=kg, category=Carnes)
- Tocino (id=49): mantener unit=kg, pero el stock actual (58.50) son unidades de 50g → convertir a kg reales (58.50 × 0.05 = 2.925 kg)
- Longaniza (id=151): ya existe, unit=kg ✓

### R2: Tabla `ingredient_recipes` (sub-recetas)
- Nueva tabla para definir que un ingrediente compuesto se descompone en ingredientes reales
- Schema: `ingredient_id` (el compuesto, ej: Hamburguesa R11) → lista de `child_ingredient_id` + `quantity` + `unit`
- Cuando se descuenta stock de "Hamburguesa R11", en realidad se descuentan los ingredientes hijos

### R3: Migración Laravel
- Crear tabla `ingredient_recipes`
- INSERT ingrediente "Carne Molida"
- UPDATE tocino stock a kg reales
- INSERT sub-receta de Hamburguesa R11: 0.150kg carne + 0.040kg tocino + 0.010kg longaniza

### R4: API Backend (mi3)
- `GET /api/v1/admin/ingredient-recipes` — listar ingredientes compuestos con sus sub-recetas
- `GET /api/v1/admin/ingredient-recipes/{ingredientId}` — detalle de sub-receta
- `POST /api/v1/admin/ingredient-recipes/{ingredientId}` — crear/actualizar sub-receta
- `DELETE /api/v1/admin/ingredient-recipes/{ingredientId}` — eliminar sub-receta
- Integrar en RecipeService: al calcular costos, si un ingrediente tiene sub-receta, usar el costo de los ingredientes hijos

### R5: UI en Recetas (mi3-frontend)
- Nueva sub-tab "Sub-Recetas" en RecetasSection
- Lista de ingredientes compuestos con sus componentes
- Editor: seleccionar ingrediente compuesto → agregar ingredientes hijos con cantidad/unidad
- Calculadora de costo unitario (como hc.html pero integrada)
- Indicador visual de qué productos usan cada ingrediente compuesto

### R6: Integración con stock
- Al descontar stock por venta, si el ingrediente tiene sub-receta, descontar los hijos proporcionalmente
- Al registrar compra de carne molida/tocino/longaniza, el stock se suma a esos ingredientes directamente (no a "Hamburguesa R11")
