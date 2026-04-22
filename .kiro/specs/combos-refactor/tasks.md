# Tareas: Refactorización de Combos

## Fase 1: Backend mi3 — migración + API

### T1.1: Migración `combo_components` con datos legacy
- [x] Crear `mi3/backend/database/migrations/2026_04_22_100000_create_combo_components_table.php`
- [x] Tabla: id (INT AUTO_INCREMENT), combo_product_id (INT), child_product_id (INT), quantity (INT DEFAULT 1), is_fixed (TINYINT DEFAULT 1), selection_group (VARCHAR(50) NULL), max_selections (INT DEFAULT 1), price_adjustment (DECIMAL(10,2) DEFAULT 0), sort_order (INT DEFAULT 0), created_at (TIMESTAMP)
- [x] Índices: idx_combo (combo_product_id), idx_child (child_product_id)
- [x] Seed: migrar combo_items (is_selectable=0) → combo_components (is_fixed=1) usando mapping combos.id→products.id: {1→187, 2→188, 3→190, 4→198, 211→211, 233→233, 234→242}
- [x] Seed: migrar combo_selections → combo_components (is_fixed=0) con selection_group y max_selections
- [x] Limpiar: DELETE FROM product_recipes WHERE product_id IN (SELECT id FROM products WHERE category_id=8)
- [x] Fix: UPDATE products SET name='Combo Hamburguesa Clásica' WHERE id=233
- [x] down(): DROP TABLE combo_components
- **QA**: Ejecutar migración → `SELECT COUNT(*) FROM combo_components` = total migrado. Verificar product_recipes de combos = 0.

### T1.2: Modelo `ComboComponent`
- [x] Crear `mi3/backend/app/Models/ComboComponent.php`
- [x] Fillable: combo_product_id, child_product_id, quantity, is_fixed, selection_group, max_selections, price_adjustment, sort_order
- [x] Relaciones: combo() → belongsTo(Product, 'combo_product_id'), childProduct() → belongsTo(Product, 'child_product_id')
- [x] Casts: is_fixed → boolean, price_adjustment → float
- **QA**: `ComboComponent::where('combo_product_id', 187)->count()` retorna items del Combo Doble Mixta

### T1.3: `ComboService` — CRUD + costos
- [x] Crear `mi3/backend/app/Services/Recipe/ComboService.php`
- [x] `getComboList()`: combos (products category_id=8, is_active=1) con count componentes, costo, margen
- [x] `getComboDetail(int $productId)`: fixed_items + selection_groups filtrados por is_active=1
- [x] `saveComboComponents(int $productId, array $components)`: upsert en transacción
- [x] `calculateComboCost(int $productId)`: Σ(costo fijos) + promedio(seleccionables), UPDATE products.cost_price
- [x] `deleteComboComponents(int $productId)`: eliminar todos
- **QA**: `getComboDetail(187)` retorna 2 fixed + grupo "Bebidas". Tomahawk (id=2, inactive) NO aparece.

### T1.4: `ComboController` — endpoints REST
- [x] Crear `mi3/backend/app/Http/Controllers/Admin/ComboController.php`
- [x] `index()`: GET /admin/combos
- [x] `show(int $productId)`: GET /admin/combos/{productId}
- [x] `store(Request $request, int $productId)`: POST /admin/combos/{productId}
- [x] `destroy(int $productId)`: DELETE /admin/combos/{productId}
- [x] Validación estricta con try/catch + rollback
- **QA**: curl cada endpoint → respuestas correctas

### T1.5: Rutas en api.php
- [x] GET admin/combos, GET admin/combos/{productId}, POST admin/combos/{productId}, DELETE admin/combos/{productId}
- **QA**: `php artisan route:list --path=combos` muestra 4 rutas

### T1.6: Verificar product_recipes limpiadas
- [x] Incluido en migración T1.1
- **QA**: `SELECT COUNT(*) FROM product_recipes pr JOIN products p ON p.id=pr.product_id WHERE p.category_id=8` = 0

---

## Fase 2: Frontend mi3 — gestión de combos

### T2.1: Tab "Combos" en RecetasSection
- [x] Import lazy + tab en `RecetasSection.tsx` (icono Package)
- [x] Crear `mi3/frontend/app/admin/recetas/combos/page.tsx`
- **QA**: Tab visible, click carga página

### T2.2: Lista de combos
- [x] Fetch GET /admin/combos
- [x] Tabla: nombre, precio, costo, margen, # fijos, # seleccionables
- [x] Click → editor inline
- **QA**: 7 combos con datos correctos

### T2.3: Editor de combo
- [x] Sección "Items Fijos": lista editable + autocomplete productos
- [x] Sección "Grupos de Selección": por grupo, opciones con price_adjustment, max_selections
- [x] Botón "Nuevo Grupo", botón "Guardar"
- [x] Responsive mobile-first
- **QA**: Editar combo → agregar/eliminar opción → guardar → verificar BD

### T2.4: Toggle disponibilidad visual
- [x] Badge 🟢/🔴 según is_active del producto hijo
- [x] Inactivos grayed out
- **QA**: Desactivar producto → recargar → aparece inactivo

### T2.5: Cálculo costo y precio
- [x] Costo = Σ(fijos) + promedio(seleccionables)
- [x] Badge margen verde/amber
- **QA**: Cálculo manual coincide con mostrado

---

## Fase 3: Refactor app3

### T3.1: `get_combos.php` → combo_components
- [x] Reescribir: JOIN combo_components + products WHERE is_active=1
- [x] Aceptar `?product_id=` (nuevo) + `?combo_id=` (backward compat temporal)
- [x] Mismo formato JSON de respuesta
- **QA**: `curl ?product_id=187` retorna combo correcto. Tomahawk NO aparece.

### T3.2: `ComboModal.jsx` → eliminar comboMapping
- [x] Eliminar comboMapping hardcodeado
- [x] fetch con `combo.id` directo como product_id
- [x] Eliminar fallback búsqueda por nombre
- [x] Eliminar console.log debug
- **QA**: Cada combo abre correctamente. Sin console.log.

### T3.3: Soporte price_adjustment
- [x] Mostrar "+$X" si > 0, "Incluido" si = 0
- [x] Sumar al total en carrito
- **QA**: Monster +$1,000 → precio total correcto

---

## Fase 4: Refactor caja3

### T4.1: `get_combos.php` → sync con app3
- [x] Misma lógica que T3.1
- **QA**: Respuesta idéntica a app3

### T4.2: `ComboModal.jsx` → eliminar comboMapping
- [x] Mismo refactor que T3.2
- **QA**: Cada combo abre correctamente

### T4.3: `confirm_transfer_payment.php` → verificar inventario
- [x] Ya lee combo_data JSON autocontenido — verificar no necesita cambios
- **QA**: Vender combo transferencia → inventario hijos descontado

### T4.4: `process_sale_inventory.php` → verificar inventario
- [x] Ya lee combo_data JSON — verificar no necesita cambios
- **QA**: Vender combo efectivo → inventario correcto

---

## Fase 5: QA exhaustivo

### T5.1: Crear combo nuevo desde mi3
- [x] Crear "Combo Test": 1x Cheeseburger + 1x Papas Medianas + grupo Bebidas (5 latas)
- [x] Verificar en app3 y caja3: aparece, modal funciona, selección funciona
- [x] Eliminar después

### T5.2: Desactivar bebida → desaparece de combos
- [x] Desactivar Coca-Cola (id=99) desde caja3
- [x] app3: Combo Completo → Coca-Cola NO en opciones
- [x] caja3: Combo Completo → Coca-Cola NO en opciones
- [ ] mi3: editor muestra Coca-Cola gris "Inactivo"

### T5.3: Reactivar bebida → reaparece
- [x] Reactivar Coca-Cola
- [x] Verificar reaparece en app3, caja3, mi3

### T5.4: Inventario Combo Completo
- [x] Vender 1x con Kem seleccionada
- [x] Completo Italiano: ingredientes descontados (pan, montina, palta, tomate, mayo)
- [x] Papas: ingredientes descontados (papa, aceite)
- [ ] Kem: stock_quantity -1
- [x] Combo padre (188): SIN descuento de ingredientes crudos

### T5.5: Inventario Combo Dupla (2 bebidas)
- [ ] Vender con 2 bebidas diferentes
- [ ] Hamburguesa -1, Ave Italiana -1, Papas -1, Bebida1 -1, Bebida2 -1

### T5.6: Price adjustment
- [x] Monster +$1,000 en Combo Completo
- [x] app3: precio = $4,980 + $1,000 = $5,980
- [x] caja3: mismo

### T5.7: Combo Familiar
- [ ] 4x Completo + 1x Papas Medianas + 1x Bebida 1.5L
- [ ] Agregar más opciones 1.5L
- [ ] Inventario: 4 completos, 1 papas, 1 bebida

### T5.8: Órdenes históricas
- [x] Comandas, VentasDetalle, cash-pending, rl6-pending muestran combos antiguos correctamente

### T5.9: Comandas detalle
- [ ] Orden con combo → comanda muestra "Incluye:" correcto

### T5.10: Grupo sin opciones activas
- [x] Desactivar todas las bebidas lata → "Sin opciones disponibles" → botón deshabilitado

### T5.11: Producto fijo desactivado
- [x] Desactivar Papas Individuales → combos con papas fijas muestran warning

### T5.12: WhatsApp message
- [ ] Orden con combo → mensaje incluye detalle fijos + selecciones

### T5.13: Cash-pending y rl6-pending
- [ ] Combo pagado con efectivo/RL6 → detalle correcto en pending pages

### T5.14: Creador IA + Combos
- [ ] Generar "combo familiar" → guardar → configurar como combo en tab Combos
