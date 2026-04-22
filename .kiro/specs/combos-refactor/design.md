# Diseño: Refactorización de Combos

## Arquitectura

### Antes (actual)
```
products (id=187, category_id=8, "Combo Doble Mixta", price=$14,180)
  └── product_recipes (ingredientes crudos: pan, carne, mayo, queso...)
  
combos (id=1, "Combo Doble Mixta", price=$13,890)  ← DUPLICADO
  ├── combo_items (fixed: Hamburguesa Doble Mixta + Papas Individuales)
  └── combo_selections (selectable: 8 bebidas lata)

ComboModal.jsx:
  comboMapping = { 'Combo Doble Mixta': 1 }  ← HARDCODED
  fetch(`/api/get_combos.php?combo_id=${comboMapping[name]}`)
```

### Después (propuesto)
```
products (id=187, category_id=8, "Combo Doble Mixta", price=$14,180)
  └── combo_components
        ├── FIXED: child=11 (Hamburguesa Doble Mixta), qty=1
        ├── FIXED: child=17 (Papas Fritas Individuales), qty=1
        ├── SELECT "Bebidas": child=95 (Kem), child=96 (Bilz), ... max=1
        └── (cada child → products → product_recipes → ingredients)

ComboModal.jsx:
  fetch(`/api/get_combos.php?product_id=${product.id}`)  ← DIRECTO
  opciones filtradas por products.is_active=1
```

## Flujo de datos

### 1. Crear combo (mi3/admin)
```
Admin selecciona productos fijos + grupos de selección
  → POST /api/v1/admin/combos/{productId}
  → ComboService valida productos existen y están activos
  → INSERT combo_components (N filas)
  → Calcula cost_price = Σ(costo fijos) + promedio(costo seleccionables)
  → UPDATE products SET cost_price = X WHERE id = productId
```

### 2. Cliente compra combo (app3)
```
Cliente click combo → ComboModal abre
  → GET /api/get_combos.php?product_id=187
  → Query: combo_components JOIN products WHERE is_active=1
  → Muestra: fijos (check verde) + seleccionables (radio/counter)
  → Cliente elige bebida → "Agregar al carrito"
  → Cart item: { id: 187, type: 'combo', fixed_items: [...], selections: {...} }
```

### 3. Procesar venta (caja3/app3)
```
create_order.php:
  → Guarda combo_data JSON en tuu_order_items (autocontenido)
  → Calcula item_cost sumando costos de fixed_items + selections

process_sale_inventory.php / confirm_transfer_payment.php:
  → Lee combo_data.fixed_items → deductProduct(child_id, qty)
  → Lee combo_data.selections → deductProduct(selection_id, qty)
  → Cada deductProduct → product_recipes → ingredients (ya funciona)
  → NO toca product_recipes del combo padre (eliminadas en migración)
```

### 4. Toggle disponibilidad (caja3)
```
Admin desactiva Coca-Cola en caja3:
  → UPDATE products SET is_active=0 WHERE id=99
  → broadcast(StockActualizado)
  → Próximo GET get_combos.php filtra is_active=0
  → Coca-Cola desaparece de opciones en ComboModal
  → combo_components NO se modifica (Coca-Cola sigue configurada)

Admin reactiva Coca-Cola:
  → UPDATE products SET is_active=1 WHERE id=99
  → Coca-Cola reaparece automáticamente en todos los combos
```

## Query principal: get_combos.php refactorizado

```sql
SELECT 
  cc.id, cc.child_product_id, p.name AS product_name,
  p.price AS product_price, p.image_url, p.is_active,
  cc.quantity, cc.is_fixed, cc.selection_group,
  cc.max_selections, cc.price_adjustment, cc.sort_order
FROM combo_components cc
JOIN products p ON p.id = cc.child_product_id
WHERE cc.combo_product_id = ?
  AND p.is_active = 1
ORDER BY cc.is_fixed DESC, cc.selection_group, cc.sort_order, p.name
```

## Estructura de respuesta API

```json
{
  "success": true,
  "combo": {
    "id": 187,
    "name": "Combo Doble Mixta",
    "price": 14180,
    "fixed_items": [
      { "product_id": 11, "product_name": "Hamburguesa Doble Mixta (580g)", "quantity": 1, "image_url": "..." },
      { "product_id": 17, "product_name": "Papas Fritas Individuales", "quantity": 1, "image_url": "..." }
    ],
    "selection_groups": {
      "Bebidas": {
        "max_selections": 1,
        "options": [
          { "product_id": 95, "product_name": "Kem Lata 350ml", "price_adjustment": 0, "image_url": "..." },
          { "product_id": 99, "product_name": "Coca-Cola Lata 350ml", "price_adjustment": 0, "image_url": "..." }
        ]
      }
    }
  }
}
```

## Migración de datos

### Mapping combos.id → products.id
| combos.id | → products.id | Nombre |
|-----------|---------------|--------|
| 1 | 187 | Combo Doble Mixta |
| 2 | 188 | Combo Completo |
| 3 | 190 | Combo Gorda |
| 4 | 198 | Combo Dupla |
| 211 | 211 | Combo Completo Familiar |
| 233 | 233 | Combo Hamburguesa Clásica |
| 234 | 242 | Combo Salchipapa |

### Pasos migración
1. Crear tabla `combo_components`
2. INSERT fijos: combo_items WHERE is_selectable=0 → combo_components (is_fixed=1)
3. INSERT seleccionables: combo_selections → combo_components (is_fixed=0)
4. DELETE product_recipes WHERE product_id IN (combos category_id=8)
5. Fix typo: UPDATE products SET name='Combo Hamburguesa Clásica' WHERE id=233
6. Deprecar tablas legacy (no eliminar)

## UI mi3/admin — Editor de Combos

```
┌─────────────────────────────────────────────┐
│ 🍔 Combo Doble Mixta          Precio: $14,180│
│ Costo: $5,230  Margen: 63.1%                │
├─────────────────────────────────────────────┤
│ ✅ Items Fijos                               │
│  ├─ Hamburguesa Doble Mixta (580g) × 1      │
│  └─ Papas Fritas Individuales × 1           │
│  [+ Agregar producto fijo]                   │
├─────────────────────────────────────────────┤
│ 🔄 Grupo: Bebidas (elegir 1)                │
│  ├─ 🟢 Kem Lata 350ml          +$0         │
│  ├─ 🟢 Coca-Cola Lata 350ml    +$0         │
│  ├─ 🔴 Tomahawk Provoleta      [INACTIVO]  │
│  └─ ...                                     │
│  [+ Agregar opción] [Nuevo grupo]           │
└─────────────────────────────────────────────┘
```

## Compatibilidad hacia atrás

- `tuu_order_items.combo_data` JSON NO se modifica — autocontenido
- Comandas, ventas, cash-pending, rl6-pending leen de combo_data guardado
- `create_order.php` sigue guardando combo_data con fixed_items + selections
- Solo cambia la FUENTE de configuración (combo_components vs legacy tables)
