# Tareas: Sub-Recetas de Ingredientes Compuestos

## Fase 1: BD + Backend

- [x] 1. Migración: crear tabla `ingredient_recipes` + columna `is_composite` en `ingredients` + INSERT "Carne Molida" + UPDATE tocino stock + seed sub-receta Hamburguesa R11
- [x] 2. Model `IngredientRecipe` + relaciones en `Ingredient` (children, parentRecipes)
- [x] 3. `IngredientRecipeService`: CRUD + calculateCompositeCost + calculateCompositeStock
- [x] 4. `IngredientRecipeController`: 4 endpoints REST + rutas
- [x] 5. Integrar en `RecipeService`: costo de ingredientes compuestos usa hijos, stock deduction descompone en hijos

## Fase 2: Frontend

- [x] 6. Nueva sub-tab "Sub-Recetas" en RecetasSection con lista de ingredientes compuestos
- [x] 7. Editor de sub-receta: agregar/quitar ingredientes hijos con cantidad/unidad
- [x] 8. Calculadora de producción integrada (input cantidad → output kg por ingrediente + costos)

## Fase 3: Datos

- [ ] 9. Ejecutar migración en producción + verificar datos
