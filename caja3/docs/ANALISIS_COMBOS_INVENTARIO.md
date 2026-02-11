# 🔍 Análisis: Sistema de Combos e Inventario

## 📊 Estado Actual del Sistema

### ✅ Implementación Actual (confirm_transfer_payment.php)

```php
if ($is_combo && !empty($item['combo_data'])) {
    // 1. Descuenta receta del COMBO (product_id del combo)
    deductProduct($pdo, $combo_product_id, $quantity);
    
    // 2. Descuenta SELECTIONS (bebidas elegidas)
    foreach ($combo_data['selections'] as $selection) {
        deductProduct($pdo, $selection['id'], $quantity);
    }
}
```

### 🎯 Lógica Correcta Implementada

**El sistema YA está implementado correctamente:**

1. **NO descuenta fixed_items individuales** ✅
2. **SÍ descuenta la receta del combo** ✅  
3. **SÍ descuenta solo las bebidas seleccionadas** ✅

---

## 🧩 Cómo Funciona (Ejemplo Real)

### Combo: "Completo + Papas + Bebida" (ID: 5)

#### Estructura en Base de Datos

**Tabla `combos`:**
```
id: 5
name: "Combo Completo Familiar"
price: 5990
```

**Tabla `combo_items` (fixed_items - NO se descontarán individualmente):**
```
combo_id: 5, product_id: 1 (Completo), quantity: 1
combo_id: 5, product_id: 15 (Papas), quantity: 1
```

**Tabla `combo_selections` (opciones seleccionables):**
```
combo_id: 5, selection_group: "bebida", product_id: 20 (Coca-Cola)
combo_id: 5, selection_group: "bebida", product_id: 21 (Sprite)
combo_id: 5, selection_group: "bebida", product_id: 22 (Fanta)
```

**Tabla `product_recipes` (receta del COMBO ID 5):**
```
product_id: 5, ingredient_id: 1 (Pan), quantity: 1
product_id: 5, ingredient_id: 2 (Vienesa), quantity: 1
product_id: 5, ingredient_id: 3 (Tomate), quantity: 50g
product_id: 5, ingredient_id: 10 (Papas), quantity: 150g
product_id: 5, ingredient_id: 11 (Aceite), quantity: 10ml
product_id: 5, ingredient_id: 20 (Bolsa Delivery), quantity: 1
product_id: 5, ingredient_id: 21 (Caja), quantity: 1
```

**NOTA IMPORTANTE**: La receta del combo SÍ incluye packaging (bolsas, cajas) porque es la receta COMPLETA del combo. Esto NO causa duplicación porque el sistema NO descuenta las recetas de los productos individuales (fixed_items), solo descuenta la receta del combo.

---

## 🔄 Flujo de Venta

### Cliente compra 1x "Combo Completo Familiar" con Coca-Cola

**1. Se guarda en `tuu_order_items`:**
```json
{
  "product_id": 5,
  "item_type": "combo",
  "quantity": 1,
  "combo_data": {
    "combo_id": 5,
    "fixed_items": [
      {"product_id": 1, "quantity": 1},
      {"product_id": 15, "quantity": 1}
    ],
    "selections": {
      "bebida": {"id": 20, "name": "Coca-Cola"}
    }
  }
}
```

**2. Al confirmar pago, `processInventoryDeduction()` ejecuta:**

```php
// Paso 1: Descuenta receta del COMBO (ID 5)
deductProduct($pdo, 5, 1);
// Esto descuenta:
// - Pan: 1 unidad
// - Vienesa: 1 unidad
// - Tomate: 50g
// - Papas: 150g
// - Aceite: 10ml
// Total: 5 ingredientes

// Paso 2: Descuenta bebida seleccionada (Coca-Cola ID 20)
deductProduct($pdo, 20, 1);
// Esto descuenta:
// - Coca-Cola: 1 unidad (stock directo)
```

**3. Resultado en `inventory_transactions`:**
- 5 transacciones de ingredientes del combo
- 1 transacción de la bebida
- **Total: 6 transacciones**

---

## ✅ Problema Resuelto: NO Hay Duplicación

### ❌ Problema Anterior (Hipotético)

Si el sistema descontara fixed_items individuales:

```php
// MAL - Esto causaría duplicación
foreach ($fixed_items as $item) {
    deductProduct($pdo, $item['product_id'], $item['quantity']);
}
// Descontaría:
// - Completo (ID 1) → Pan, Vienesa, Tomate, Mayo, Bolsa Delivery
// - Papas (ID 15) → Papas, Aceite, Sal, Bolsa Delivery
// Total: 8 ingredientes + 2 bolsas = DUPLICACIÓN
```

### ✅ Solución Actual (Implementada)

```php
// BIEN - Solo descuenta receta del combo
deductProduct($pdo, $combo_id, $quantity);
// Descuenta:
// - Receta del combo (ID 5) → Pan, Vienesa, Tomate, Papas, Aceite
// Total: 5 ingredientes SIN bolsas duplicadas
```

---

## 🎯 Ventajas del Sistema Actual

1. **Sin Duplicación** ✅
   - Cada combo tiene su propia receta optimizada
   - NO incluye items de packaging duplicados

2. **Flexibilidad** ✅
   - Puedes ajustar cantidades en la receta del combo
   - Ejemplo: Combo usa 100g papas vs Papas individuales usan 150g

3. **Trazabilidad** ✅
   - Cada transacción registra el combo_id
   - Puedes ver qué combos se vendieron

4. **Selections Independientes** ✅
   - Bebidas se descontan por separado
   - Permite tracking de bebidas más vendidas

---

## 📋 Cómo Crear un Combo Correctamente

### Paso 1: Crear el Combo en Admin

1. Ve a `/admin/combos`
2. Click "+ Crear Combo"
3. Llena datos básicos (nombre, precio, imagen)

### Paso 2: Configurar Fixed Items (Referencia Visual)

**IMPORTANTE**: Los fixed_items son SOLO para mostrar al cliente qué incluye el combo. NO se descontarán individualmente.

```javascript
{
  "fixed_items": [
    {"product_id": 1, "quantity": 1},  // Completo (solo visual)
    {"product_id": 15, "quantity": 1}  // Papas (solo visual)
  ]
}
```

### Paso 3: Crear Receta del Combo

**CRÍTICO**: Debes crear una receta en `product_recipes` para el combo con TODOS los ingredientes necesarios.

```sql
-- Ejemplo: Receta para Combo ID 233 (Hamburguesa + Papas + Bebida)
INSERT INTO product_recipes (product_id, ingredient_id, quantity, unit) VALUES
(233, 1, 100, 'g'),      -- Tomate
(233, 2, 30, 'g'),       -- Mayonesa
(233, 3, 1, 'unidad'),   -- Pan Artesano Brioche
(233, 4, 1, 'unidad'),   -- Queso Cheddar
(233, 5, 1, 'unidad'),   -- Hamburguesa R11 200gr
(233, 6, 500, 'g'),      -- Papa Cardenal
(233, 7, 2, 'unidad'),   -- Caja Sandwich
(233, 8, 1, 'unidad'),   -- Papel Mantequilla
(233, 9, 1, 'unidad');   -- Bolsa Delivery Baja
-- SÍ incluir packaging porque es la receta COMPLETA del combo
```

**IMPORTANTE**: La receta del combo SÍ debe incluir packaging (bolsas, cajas, papel) porque representa la receta COMPLETA del combo. NO causa duplicación porque el sistema NO descuenta las recetas de los productos individuales.

### Paso 4: Configurar Selections

```javascript
{
  "selection_groups": {
    "bebida": {
      "max_selections": 1,
      "options": [
        {"product_id": 20},  // Coca-Cola
        {"product_id": 21},  // Sprite
        {"product_id": 22}   // Fanta
      ]
    }
  }
}
```

---

## 🔍 Verificación del Sistema

### Query para Verificar Recetas de Combos

```sql
-- Ver recetas de combos (category_id = 8)
SELECT 
    p.id as combo_id,
    p.name as combo_name,
    i.name as ingrediente,
    pr.quantity,
    pr.unit
FROM products p
JOIN product_recipes pr ON p.id = pr.product_id
JOIN ingredients i ON pr.ingredient_id = i.id
WHERE p.category_id = 8
ORDER BY p.id, i.name;
```

### Query para Ver Transacciones de un Combo

```sql
-- Ver qué se descontó al vender un combo
SELECT 
    it.order_reference,
    i.name as ingrediente,
    it.quantity,
    it.unit,
    it.previous_stock,
    it.new_stock,
    it.created_at
FROM inventory_transactions it
LEFT JOIN ingredients i ON it.ingredient_id = i.id
WHERE it.order_reference = 'T11-XXXXX'
ORDER BY it.created_at;
```

---

## ⚠️ Posibles Problemas a Verificar

### 1. Combos Sin Receta

**Problema**: Si un combo NO tiene receta en `product_recipes`, el sistema intentará descontar stock directo del producto combo (que no existe).

**Solución**: Asegurar que TODOS los combos tengan receta.

```sql
-- Verificar combos sin receta
SELECT p.id, p.name
FROM products p
LEFT JOIN product_recipes pr ON p.id = pr.product_id
WHERE p.category_id = 8
AND pr.product_id IS NULL;
```

### 2. ✅ Recetas con Packaging (CORRECTO)

**Aclaración**: Las recetas de combos SÍ deben incluir packaging (bolsas, cajas, papel).

**Razón**: La receta del combo representa la receta COMPLETA del combo, no la suma de recetas individuales.

**Ejemplo Correcto - Combo ID 233**:
```
- Tomate: 100g
- Mayonesa: 30g
- Pan: 1 unidad
- Queso: 1 unidad
- Hamburguesa: 1 unidad
- Papas: 500g
- Caja Sandwich: 2 unidades ✅
- Papel Mantequilla: 1 unidad ✅
- Bolsa Delivery: 1 unidad ✅
```

**NO hay duplicación** porque:
1. Sistema descuenta receta del combo (1 bolsa)
2. Sistema NO descuenta recetas de productos individuales
3. Solo descuenta bebida seleccionada

```sql
-- Verificar recetas de combos (packaging es CORRECTO)
SELECT 
    p.name as combo,
    i.name as ingrediente,
    pr.quantity,
    pr.unit
FROM products p
JOIN product_recipes pr ON p.id = pr.product_id
JOIN ingredients i ON pr.ingredient_id = i.id
WHERE p.category_id = 8
ORDER BY p.id, i.name;
```

---

---

## ⚠️ PROBLEMA ACTUAL: Creación de Combos desde Admin

### 🔴 Hallazgo

**Fecha**: 25-nov-2025

**Problema Identificado**: Al crear un combo desde `/admin/combos`, el sistema crea el registro en la tabla `products` (con category_id=8) pero **NO lo crea en la tabla `combos`**.

**Impacto**:
- El combo aparece en el admin de productos
- El combo NO aparece en `/admin/combos`
- El frontend NO puede cargar el combo (busca en tabla `combos`)
- Error en consola: `"combos_found": 0`

**Ejemplo Real**:
- Combo "Hamburguesa Clásica" creado con ID 233
- Existe en `products` (ID 233, category_id=8)
- NO existe en `combos`
- Frontend falla al intentar cargarlo

**Workaround Actual**:
```sql
-- Migración manual requerida
INSERT INTO combos (id, name, description, price, image_url, category_id, active)
SELECT id, name, description, price, image_url, 8, 1
FROM products
WHERE id = 233;
```

**Causa Raíz**: 
El flujo de creación desde admin usa el editor de productos estándar que solo guarda en `products`. No hay integración automática con la tabla `combos`.

**Archivos Involucrados**:
- `/admin/combos` - Interfaz de gestión
- `api/save_combo.php` - API de guardado
- Posiblemente falta integración en el flujo de creación

**Solución Requerida**:
1. Modificar `save_combo.php` para crear en ambas tablas simultáneamente
2. O crear un flujo dedicado para combos que no use el editor de productos
3. O agregar trigger en BD para auto-crear en `combos` cuando se crea producto con category_id=8

**Estado**: 🔴 PENDIENTE DE RESOLVER

---

## ✅ Conclusión

**El sistema ESTÁ implementado correctamente:**

1. ✅ Descuenta receta del combo (sin duplicación)
2. ✅ Descuenta solo bebidas seleccionadas
3. ✅ NO descuenta fixed_items individuales
4. ✅ Registra transacciones con trazabilidad

**Acción requerida:**
- ⚠️ **CRÍTICO**: Resolver problema de creación de combos desde admin
- Verificar que todos los combos tengan recetas propias
- Asegurar que las recetas NO incluyan items de packaging duplicados
- Ejecutar queries de verificación arriba

**El código en `confirm_transfer_payment.php` es correcto y no requiere cambios.**
