# Spec: Refactorización Completa de Combos

## Contexto

Los combos de La Ruta 11 tienen problemas estructurales que causan bugs recurrentes:
- Doble fuente de verdad (tabla `combos` + tabla `products`)
- IDs desincronizados (products.id ≠ combos.id)
- Mapping hardcodeado en frontend (comboMapping en ComboModal.jsx)
- Productos desactivados siguen apareciendo en combos (ej: Tomahawk Provoleta id=2, is_active=0)
- Precios desincronizados entre `products.price` y `combos.price`
- Recetas duplicadas (ingredientes crudos en product_recipes + productos terminados en combo_items)
- ComboModal duplicado en app3 y caja3 con lógica idéntica

## Objetivo

Unificar la arquitectura de combos en una sola tabla `combo_components` que conecte productos combo (category_id=8) con sus productos hijos, eliminando la tabla `combos` separada. Gestión completa desde mi3/admin. Sincronización automática de disponibilidad de productos en combos.

---

## Requisitos

### R1: Nueva tabla `combo_components`
- Reemplaza `combo_items` + `combo_selections` + `combos`
- Campos: combo_product_id (FK→products), child_product_id (FK→products), quantity, is_fixed (0/1), selection_group (varchar), max_selections (int), price_adjustment (decimal)
- El combo ES un producto en `products` con category_id=8
- Migración debe preservar datos existentes de las 3 tablas legacy

### R2: Disponibilidad automática de productos en combos
- Cuando un producto se desactiva (is_active=0), debe desaparecer automáticamente de las opciones seleccionables en todos los combos
- Cuando se reactiva (is_active=1), debe reaparecer
- NO se elimina de `combo_components` — solo se filtra en las queries
- El filtro debe aplicarse en: API get_combos.php (app3), API get_combos.php (caja3), y nuevo endpoint mi3
- Desde caja3 al toggle on/off de un producto, los combos se actualizan en tiempo real

### R3: Gestión de combos en mi3/admin
- Nueva tab "Combos" en RecetasSection (o sección propia)
- CRUD completo: crear, editar, eliminar combos
- Editor visual: items fijos (arrastrar productos) + grupos de selección (ej: "Bebidas" con lista de opciones)
- Preview del combo: muestra cómo se verá en app3
- Cálculo automático de costo (suma de costos de items fijos + promedio de seleccionables)
- Precio sugerido con margen 65%

### R4: Sincronización de precios
- El precio del combo en `products` es la única fuente de verdad
- Eliminar `combos.price` (tabla legacy)
- Al editar precio en mi3, se refleja inmediatamente en app3 y caja3

### R5: Inventario correcto
- Al vender un combo, descontar inventario de cada producto hijo (no del combo como producto)
- Cada producto hijo descuenta sus ingredientes via `product_recipes` (ya funciona con `deductProduct`)
- Eliminar recetas de ingredientes crudos de los combos en `product_recipes` (son redundantes)
- Las bebidas seleccionadas se descontarán como producto (stock_quantity del producto bebida)

### R6: Frontend app3 — ComboModal refactorizado
- Eliminar `comboMapping` hardcodeado
- Leer combo config desde `combo_components` via API (usando product_id directamente)
- Filtrar opciones seleccionables por `is_active=1`
- Mostrar imagen y precio de cada opción
- Soporte para price_adjustment (ej: Monster +$1,000)

### R7: Frontend caja3 — ComboModal sincronizado
- Mismo refactor que app3 (compartir lógica o duplicar con misma estructura)
- Al toggle on/off de producto en caja3, broadcast via Echo/Reverb para actualizar combos en tiempo real
- Indicador visual de productos no disponibles en combo

### R8: Migración de datos
- Script que migre datos de `combos` + `combo_items` + `combo_selections` → `combo_components`
- Mapear combos.id → products.id (usando nombre como key)
- Preservar selection_groups y max_selections
- Sincronizar precios (usar products.price como fuente)
- Eliminar recetas de ingredientes crudos de combos en product_recipes
- Marcar tablas legacy como deprecated (no eliminar aún)

---

## Datos Actuales en BD

### Combos activos (tabla `combos`)
| combos.id | Nombre | Precio combos | products.id | Precio products | Fixed | Seleccionables |
|-----------|--------|---------------|-------------|-----------------|-------|----------------|
| 1 | Combo Doble Mixta | $13,890 | 187 | $14,180 | 2 | 8 |
| 2 | Combo Completo | $4,690 | 188 | $4,980 | 2 | 11 |
| 3 | Combo Gorda | $5,690 | 190 | $5,980 | 1 | 11 |
| 4 | Combo Dupla | $16,480 | 198 | $16,980 | 3 | 8 |
| 211 | Combo Completo Familiar | $13,980 | 211 | $14,270 | 2 | 1 |
| 233 | Combo Haburguesa Cásica | $8,490 | 233 | $8,490 | 2 | 5 |
| 234 | Combo Salchipapa | $5,490 | 242 | $5,490 | 2 | 4 |

### Bebidas en combo_selections (14 productos únicos)
Bilz, Canada Dry, Canada Dry Zero, Coca-Cola, Coca-Cola Zero, Crush Naranja, Fanta Naranja, Kem, Kem Xtreme, Pap, Pepsi, Pepsi Zero, Sprite, Sprite 1.5L
+ 1 producto inactivo: Tomahawk Provoleta (id=2, is_active=0) ← BUG

### Bug confirmado
Tomahawk Provoleta (id=2) está en `combo_selections` pero `is_active=0`. El frontend lo muestra porque `get_combos.php` no filtra por `is_active`.

---

## Diseño Técnico

### Tabla `combo_components`
```sql
CREATE TABLE combo_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  combo_product_id INT NOT NULL,
  child_product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  is_fixed TINYINT(1) NOT NULL DEFAULT 1,
  selection_group VARCHAR(50) NULL,
  max_selections INT NOT NULL DEFAULT 1,
  price_adjustment DECIMAL(10,2) DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_combo (combo_product_id),
  INDEX idx_child (child_product_id)
);
```

### Mapping de migración
| combos.id | → products.id |
|-----------|---------------|
| 1 | 187 |
| 2 | 188 |
| 3 | 190 |
| 4 | 198 |
| 211 | 211 |
| 233 | 233 |
| 234 | 242 |

### API Endpoints mi3-backend
- `GET /api/v1/admin/combos` — listar combos con componentes
- `GET /api/v1/admin/combos/{productId}` — detalle
- `POST /api/v1/admin/combos/{productId}` — crear/actualizar componentes
- `DELETE /api/v1/admin/combos/{productId}` — eliminar

### API app3/caja3 (refactorizar)
- `GET /api/get_combos.php` → leer de `combo_components` JOIN `products` WHERE `is_active=1`

### Flujo de disponibilidad
```
caja3: toggle producto off
  → UPDATE products SET is_active=0
  → broadcast StockActualizado (ya existe)
  → app3/caja3 ComboModal: query filtra is_active=0 automáticamente
  → Producto desaparece de opciones sin tocar combo_components
```

---

## Tareas de Implementación

### Fase 1: Backend mi3 — migración + API
- [ ] T1.1: Migración `combo_components` con datos migrados de tablas legacy
- [ ] T1.2: Modelo `ComboComponent` con relaciones
- [ ] T1.3: `ComboService` — CRUD + cálculo costos + validaciones
- [ ] T1.4: `ComboController` — endpoints REST
- [ ] T1.5: Rutas en api.php
- [ ] T1.6: Limpiar product_recipes de combos (ingredientes crudos redundantes)

### Fase 2: Frontend mi3 — gestión de combos
- [ ] T2.1: Tab "Combos" en RecetasSection
- [ ] T2.2: Lista de combos con componentes, costos, margen
- [ ] T2.3: Editor: items fijos + grupos de selección con autocomplete
- [ ] T2.4: Toggle disponibilidad de opciones
- [ ] T2.5: Cálculo de costo y precio sugerido

### Fase 3: Refactor app3
- [ ] T3.1: `get_combos.php` → leer de `combo_components` + filtrar `is_active=1`
- [ ] T3.2: `ComboModal.jsx` → eliminar comboMapping, usar product_id directo
- [ ] T3.3: Soporte price_adjustment en UI

### Fase 4: Refactor caja3
- [ ] T4.1: `get_combos.php` → sync con app3
- [ ] T4.2: `ComboModal.jsx` → eliminar comboMapping
- [ ] T4.3: `confirm_transfer_payment.php` → usar combo_components para inventario
- [ ] T4.4: `process_sale_inventory.php` → usar combo_components

### Fase 5: QA exhaustivo
- [ ] T5.1: Crear combo nuevo desde mi3 → verificar aparece en app3 y caja3
- [ ] T5.2: Desactivar Coca-Cola → verificar desaparece de TODOS los combos en app3 y caja3
- [ ] T5.3: Reactivar Coca-Cola → verificar reaparece en todos los combos
- [ ] T5.4: Vender Combo Completo → verificar inventario: Completo Italiano (-1), Papas (-1), Bebida seleccionada (-1)
- [ ] T5.5: Vender Combo Dupla → verificar 2 bebidas descontadas correctamente
- [ ] T5.6: Combo con price_adjustment (ej: Monster +$1,000) → verificar precio total
- [ ] T5.7: Combo Familiar (4 completos + papas medianas + 1 bebida 1.5L) → inventario correcto
- [ ] T5.8: Órdenes históricas con combo_data JSON → siguen mostrándose en comandas y ventas
- [ ] T5.9: Comandas muestran "Incluye:" con detalle correcto
- [ ] T5.10: Edge case: TODOS los productos de un grupo desactivados → combo muestra "Sin opciones disponibles"
- [ ] T5.11: Edge case: producto fijo desactivado → combo completo se marca como no disponible
- [ ] T5.12: Verificar WhatsApp message incluye detalle de combo correctamente
- [ ] T5.13: Verificar cash-pending y rl6-pending muestran combo correctamente
- [ ] T5.14: Crear combo desde Creador IA → verificar se puede configurar como combo con selecciones

---

## Archivos Afectados

### mi3/backend (nuevo)
- `database/migrations/2026_04_22_*_create_combo_components_table.php`
- `app/Models/ComboComponent.php`
- `app/Services/Recipe/ComboService.php`
- `app/Http/Controllers/Admin/ComboController.php`
- `routes/api.php`

### mi3/frontend (nuevo)
- `app/admin/recetas/combos/page.tsx`
- `components/admin/sections/RecetasSection.tsx` (agregar tab)

### app3 (refactorizar)
- `api/get_combos.php` → leer de combo_components
- `src/components/modals/ComboModal.jsx` → eliminar comboMapping

### caja3 (refactorizar)
- `api/get_combos.php` → sync con app3
- `src/components/modals/ComboModal.jsx` → eliminar comboMapping
- `api/confirm_transfer_payment.php` → usar combo_components
- `api/process_sale_inventory.php` → usar combo_components

### BD
- Nueva: `combo_components`
- Migrar: `combos` + `combo_items` + `combo_selections` → `combo_components`
- Limpiar: `product_recipes` de combos
- Deprecar: `combos`, `combo_items`, `combo_selections`

---

## Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|-----------|
| Órdenes históricas con combo_data JSON | Baja | Alto | No tocar — JSON es autocontenido |
| Tablas legacy referenciadas en código viejo | Media | Medio | Deprecar, no eliminar. grep exhaustivo |
| Combo Familiar solo tiene 1 bebida 1.5L | Baja | Bajo | Agregar más opciones 1.5L en migración |
| Typo "Haburguesa Cásica" | Baja | Bajo | Corregir nombre en products durante migración |
| Doble descuento inventario | Media | Alto | Eliminar product_recipes de combos ANTES de deploy |
| ComboModal en app3/caja3 divergen | Media | Medio | Refactorizar ambos en la misma sesión |
| Combo sin opciones disponibles (todas desactivadas) | Baja | Medio | UI muestra "Sin opciones" + no permite agregar al carrito |
