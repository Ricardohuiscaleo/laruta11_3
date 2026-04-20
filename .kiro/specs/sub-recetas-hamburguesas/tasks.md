# Tareas: Sub-Recetas de Ingredientes Compuestos

## Fase 1: BD + Backend

- [ ] 1. Migración: crear tabla `ingredient_recipes` + columna `is_composite` en `ingredients` + INSERT "Carne Molida" + UPDATE tocino stock + seed sub-receta Hamburguesa R11
- [ ] 2. Model `IngredientRecipe` + relaciones en `Ingredient` (children, parentRecipes)
- [ ] 3. `IngredientRecipeService`: CRUD + calculateCompositeCost + calculateCompositeStock
- [ ] 4. `IngredientRecipeController`: 4 endpoints REST + rutas
- [ ] 5. Integrar en `RecipeService`: costo de ingredientes compuestos usa hijos, stock deduction descompone en hijos

## Fase 2: Frontend

- [ ] 6. Nueva sub-tab "Sub-Recetas" en RecetasSection con lista de ingredientes compuestos
- [ ] 7. Editor de sub-receta: agregar/quitar ingredientes hijos con cantidad/unidad
- [ ] 8. Calculadora de producción integrada (input cantidad → output kg por ingrediente + costos)

## Fase 3: Datos

- [ ] 9. Ejecutar migración en producción + verificar datos
