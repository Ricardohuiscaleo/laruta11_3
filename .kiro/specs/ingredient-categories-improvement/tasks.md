# Plan de Implementación: Mejora del Sistema de Categorías de Ingredientes

## Resumen

Implementación incremental que comienza con la corrección de datos en BD, luego establece la constante de categorías como fuente de verdad, actualiza los servicios backend (validación, IA, pipeline), modifica la API PHP para exponer categorías, y finalmente actualiza el frontend para tabs dinámicos.

## Tareas

- [x] 1. Migración SQL y constante de categorías (fundación)
  - [x] 1.1 Crear script SQL de corrección de datos
    - Crear archivo `caja3/sql/fix_ingredient_categories.sql`
    - UPDATE para corregir encoding: `'LÃ¡cteos'` → `'Lácteos'`
    - UPDATE para limpiar categoría legacy: `'Ingredientes'` → `NULL`
    - _Requisitos: 1.1, 1.2, 2.1_

  - [x] 1.2 Crear clase IngredientCategory con constante VALID_CATEGORIES
    - Crear `mi3/backend/app/Enums/IngredientCategory.php`
    - Definir constante con las 13 categorías válidas
    - Implementar método estático `isValid(?string $category): bool`
    - Implementar método estático `all(): array`
    - _Requisitos: 6.1, 6.3_

  - [ ]* 1.3 Escribir test unitario para IngredientCategory
    - Verificar que `isValid()` acepta todas las categorías válidas
    - Verificar que `isValid()` rechaza strings inválidos y null
    - Verificar que `all()` retorna exactamente 13 elementos
    - _Requisitos: 6.1, 6.2_

- [ ] 2. Backend mi3 - Validación y creación de ingredientes
  - [x] 2.1 Agregar validación de categoría en StockController::update()
    - Importar `App\Enums\IngredientCategory`
    - Usar `Rule::in(IngredientCategory::VALID_CATEGORIES)` en la validación del campo `category`
    - _Requisitos: 6.1, 6.2_

  - [x] 2.2 Actualizar CompraService::crearIngrediente() para validar categoría
    - Importar `App\Enums\IngredientCategory`
    - Validar `$data['category']` contra `IngredientCategory::isValid()` antes de guardar
    - Si categoría inválida, asignar `null` en lugar de rechazar (la IA puede enviar valores incorrectos)
    - _Requisitos: 5.3, 5.5, 2.2_

  - [ ]* 2.3 Escribir test de propiedad: validación acepta/rechaza categorías
    - **Propiedad 5: Validación de categorías rechaza valores inválidos**
    - **Valida: Requisitos 6.1, 6.2**

- [x] 3. Checkpoint - Verificar fundación backend
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

- [x] 4. GeminiService - Inferencia de categoría por IA
  - [x] 4.1 Agregar `categoria_sugerida` al schema de extracción en GeminiService
    - Importar `App\Enums\IngredientCategory`
    - En `buildExtractionSchema()`, agregar campo `categoria_sugerida` al schema de items con enum de categorías válidas + "Sin categoría"
    - _Requisitos: 5.1, 5.2_

  - [x] 4.2 Actualizar prompts de GeminiService para instruir inferencia de categoría
    - En los prompts de análisis (boleta, factura, producto, bascula), agregar instrucción para inferir categoría basándose en nombre del ítem y lista de categorías válidas
    - Incluir la lista de categorías en el prompt para que Gemini las conozca
    - _Requisitos: 5.2, 5.4_

  - [x] 4.3 Pasar `categoria_sugerida` a través del PipelineExtraccionService
    - Verificar que el campo `categoria_sugerida` de cada ítem se preserve en el resultado del pipeline
    - El campo ya fluye naturalmente en `$extracted['items']`, solo verificar que no se elimine en `postProcess()`
    - _Requisitos: 5.1, 5.3_

  - [ ]* 4.4 Escribir test de propiedad: categoría sugerida se aplica al crear ingrediente
    - **Propiedad 4: Aplicación de categoría sugerida al crear ingrediente**
    - **Valida: Requisitos 5.3, 5.5**

- [x] 5. Checkpoint - Verificar pipeline IA
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

- [x] 6. API PHP - Exponer categorías en respuesta
  - [x] 6.1 Modificar `caja3/api/compras/get_items_compra.php` para retornar categorías
    - Cambiar respuesta de array plano a objeto `{ items: [...], categories: [...], valid_categories: [...] }`
    - Agregar query `SELECT DISTINCT category, COUNT(*) FROM ingredients WHERE is_active = 1 AND category IS NOT NULL GROUP BY category`
    - Incluir array `valid_categories` con las 13 categorías hardcodeadas (mismas que la constante en mi3)
    - _Requisitos: 4.1, 4.2, 4.3, 6.3_

  - [ ]* 6.2 Escribir test de propiedad: categorías extraídas correctamente
    - **Propiedad 3: Extracción correcta de categorías desde ingredientes activos**
    - **Valida: Requisitos 4.1, 4.2, 4.3**

- [x] 7. Frontend caja3 - Tabs dinámicos en ComprasApp
  - [x] 7.1 Actualizar ComprasApp.jsx para consumir nuevo formato de API
    - Adaptar `loadIngredientes()` para manejar respuesta como objeto `{ items, categories, valid_categories }`
    - Agregar fallback: si respuesta es array plano (formato antiguo), usar lógica legacy
    - Guardar `categories` en estado del componente
    - _Requisitos: 3.1, 3.5_

  - [x] 7.2 Reemplazar tabs hardcodeados por tabs dinámicos
    - Eliminar tabs fijos "Ingredientes"/"Bebidas" del stockTab
    - Agregar tab "Todos" (siempre primero, sin filtro)
    - Agregar tab "Bebidas" (mantiene filtro por subcategory_id [10,11,27,28])
    - Generar tabs dinámicos desde `categories` retornadas por API
    - Contenedor con `overflow-x: auto` y `flex-nowrap` para scroll horizontal
    - _Requisitos: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 7.3 Implementar lógica de filtrado por categoría seleccionada
    - Tab "Todos": muestra todos los ítems
    - Tab "Bebidas": filtra por `type === 'product'` y subcategory_id en [10,11,27,28]
    - Tabs de categoría: filtra por `category === tabSeleccionado`
    - _Requisitos: 3.2, 3.3, 3.4_

  - [ ]* 7.4 Escribir test de propiedad: filtrado correcto por categoría
    - **Propiedad 2: Filtrado correcto por categoría seleccionada**
    - **Valida: Requisitos 3.2**

- [x] 8. Checkpoint final - Verificar integración completa
  - Asegurar que todos los tests pasan, preguntar al usuario si hay dudas.

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- El script SQL (1.1) debe ejecutarse manualmente en producción antes de desplegar el código
- La constante `valid_categories` en el PHP de caja3 (6.1) se mantiene sincronizada manualmente con la de mi3 (1.2) — no hay dependencia de runtime entre ambos
