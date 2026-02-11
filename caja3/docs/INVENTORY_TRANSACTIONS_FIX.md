# 🔧 Fix: Mostrar Consumo de Ingredientes en Reportes de Pagos

## 📋 Problema

Las órdenes con método de pago `rl6_credit` NO mostraban el consumo de ingredientes en la página de reportes de pagos (`/admin/pagos-tuu`), mientras que otros métodos de pago (efectivo, tarjeta, transfer, pedidosya) SÍ lo mostraban correctamente.

### Síntomas
- ✅ Órdenes con `payment_method = 'cash'` → Muestra ingredientes
- ✅ Órdenes con `payment_method = 'card'` → Muestra ingredientes  
- ✅ Órdenes con `payment_method = 'pedidosya'` → Muestra ingredientes
- ❌ Órdenes con `payment_method = 'rl6_credit'` → NO muestra ingredientes

### Evidencia en Base de Datos
```sql
-- Las transacciones SÍ existen en la BD
SELECT * FROM inventory_transactions 
WHERE order_reference = 'T11-1769276709-1798' 
ORDER BY id DESC;

-- Resultado: 7 filas con ingredientes descontados
```

---

## 🔍 Diagnóstico

### 1. Verificación del API Response
```json
// Orden RL6 Credit (ANTES del fix)
{
  "payment_method": "rl6_credit",
  "items": [{
    "product_name": "Cheeseburger (200g)",
    "inventory_transactions": []  // ❌ VACÍO
  }]
}

// Orden Cash (funcionando)
{
  "payment_method": "cash",
  "items": [{
    "product_name": "Dr Pepper",
    "inventory_transactions": [...]  // ✅ CON DATOS
  }]
}
```

### 2. Causa Raíz Identificada

**Problema en APIs PHP**:
- `api/tuu/get_shift_transactions.php`
- `api/tuu/get_from_mysql.php`

Ambos APIs buscaban transacciones de inventario usando el campo **INCORRECTO**:

```php
// ❌ CÓDIGO INCORRECTO
WHERE it.order_item_id = ?  // Este campo NO existe en inventory_transactions
```

Debían buscar por:

```php
// ✅ CÓDIGO CORRECTO
WHERE it.order_reference = ?  // Este es el campo correcto
```

### 3. Problema Secundario en Frontend

El objeto `paymentMethods` en JavaScript NO incluía `rl6_credit`:

```javascript
// ❌ ANTES
const paymentMethods = {
  'cash': { icon: '💵', label: 'Efectivo', ... },
  'card': { icon: '💳', label: 'Tarjeta', ... },
  'pedidosya': { icon: '🛵', label: 'PedidosYA', ... }
  // rl6_credit NO estaba aquí
};
```

---

## ✅ Solución Implementada

### Archivos Modificados: **3**

#### 1. `api/tuu/get_shift_transactions.php`

**Cambio**: Buscar transacciones por `order_reference` y asignarlas al primer item.

```php
// Obtener TODAS las transacciones de inventario de la orden (una sola vez)
$trans_sql = "
    SELECT 
        it.id,
        it.ingredient_id,
        it.product_id,
        it.quantity,
        it.previous_stock,
        it.new_stock,
        COALESCE(i.name, p.name) as item_name,
        COALESCE(it.unit, i.unit, 'unidad') as unit,
        CASE WHEN it.ingredient_id IS NOT NULL THEN 'ingredient' ELSE 'product' END as item_type
    FROM inventory_transactions it
    LEFT JOIN ingredients i ON it.ingredient_id = i.id
    LEFT JOIN products p ON it.product_id = p.id
    WHERE it.order_reference = ?  -- ✅ Campo correcto
    ORDER BY it.id ASC
";

$trans_stmt = $pdo->prepare($trans_sql);
$trans_stmt->execute([$transaction['order_reference']]);
$all_inventory_transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Asignar inventory_transactions solo al PRIMER item
foreach ($items as $index => &$item) {
    if ($index === 0 && count($all_inventory_transactions) > 0) {
        $item['inventory_transactions'] = $all_inventory_transactions;
    } else {
        $item['inventory_transactions'] = [];
    }
}
```

#### 2. `api/tuu/get_from_mysql.php`

**Cambio**: Mismo fix que en `get_shift_transactions.php`.

```php
// Obtener TODAS las transacciones de inventario de la orden (una sola vez)
$trans_sql = "
    SELECT 
        it.id,
        it.ingredient_id,
        it.product_id,
        it.quantity,
        it.previous_stock,
        it.new_stock,
        COALESCE(i.name, p.name) as item_name,
        COALESCE(it.unit, i.unit, 'unidad') as unit,
        CASE WHEN it.ingredient_id IS NOT NULL THEN 'ingredient' ELSE 'product' END as item_type
    FROM inventory_transactions it
    LEFT JOIN ingredients i ON it.ingredient_id = i.id
    LEFT JOIN products p ON it.product_id = p.id
    WHERE it.order_reference = ?  -- ✅ Campo correcto
    ORDER BY it.id ASC
";

$trans_stmt = $pdo->prepare($trans_sql);
$trans_stmt->execute([$transaction['order_reference']]);
$all_inventory_transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Asignar inventory_transactions solo al PRIMER item
foreach ($items as $index => &$item) {
    if ($index === 0 && count($all_inventory_transactions) > 0) {
        $item['inventory_transactions'] = $all_inventory_transactions;
    } else {
        $item['inventory_transactions'] = [];
    }
}
```

#### 3. `src/pages/admin/pagos-tuu.astro`

**Cambio**: Agregar `rl6_credit` al objeto `paymentMethods` en **3 ubicaciones**.

```javascript
// Ubicación 1: Función principal loadTUUReports() - Línea ~321
const paymentMethods = {
  'card': { icon: 'credit-card', label: 'Tarjetas', sales: 0, cost: 0, orders: 0 },
  'transfer': { icon: 'landmark', label: 'Transfer', sales: 0, cost: 0, orders: 0 },
  'cash': { icon: 'banknote', label: 'Efectivo', sales: 0, cost: 0, orders: 0 },
  'webpay': { icon: 'credit-card', label: 'Webpay', sales: 0, cost: 0, orders: 0 },
  'pedidosya': { icon: 'bike', label: 'PedidosYA', sales: 0, cost: 0, orders: 0 },
  'rl6_credit': { icon: 'credit-card', label: 'Crédito RL6', sales: 0, cost: 0, orders: 0 }  // ✅ AGREGADO
};

// Ubicación 2: Función processShiftData() - Línea ~926
const paymentMethods = {
  'card': { icon: '💳', label: 'Tarjetas', sales: 0, cost: 0, orders: 0 },
  'transfer': { icon: '🏦', label: 'Transfer', sales: 0, cost: 0, orders: 0 },
  'cash': { icon: '💵', label: 'Efectivo', sales: 0, cost: 0, orders: 0 },
  'webpay': { icon: '💳', label: 'Webpay', sales: 0, cost: 0, orders: 0 },
  'pedidosya': { icon: '🛵', label: 'PedidosYA', sales: 0, cost: 0, orders: 0 },
  'rl6_credit': { icon: '💳', label: 'Crédito RL6', sales: 0, cost: 0, orders: 0 }  // ✅ AGREGADO
};

// Ubicación 3: Función updateStatsUI() - Línea ~1011
const paymentMethods = {
  'card': { icon: '💳', label: 'Tarjetas', sales: 0, cost: 0, orders: 0 },
  'transfer': { icon: '🏦', label: 'Transfer', sales: 0, cost: 0, orders: 0 },
  'cash': { icon: '💵', label: 'Efectivo', sales: 0, cost: 0, orders: 0 },
  'webpay': { icon: '💳', label: 'Webpay', sales: 0, cost: 0, orders: 0 },
  'pedidosya': { icon: '🛵', label: 'PedidosYA', sales: 0, cost: 0, orders: 0 },
  'rl6_credit': { icon: '💳', label: 'Crédito RL6', sales: 0, cost: 0, orders: 0 }  // ✅ AGREGADO
};
```

---

## 🎯 Resultado

### Antes del Fix
```
T11-1769276931-7396 (RL6 Credit)
Cheeseburger (200g) x1
$8.180
Consumo: 1 x Cheeseburger (200g)
0 ⚠️  // ❌ Sin desglose de ingredientes
```

### Después del Fix
```
T11-1769276931-7396 (RL6 Credit)
Cheeseburger (200g) x1
$8.180
Consumo de ingredientes:  // ✅ Desglose completo
Pan Artesano Brioche: 1 unidad
  3 | -1 | 2 ⚠
Caja Sandwich: 1 unidad
  6 | -1 | 5 ⚠
Bolsa Delivey Baja: 1 unidad
  39 | -1 | 38 ✓
Queso Cheddar: 3 unidad
  19 | -3 | 16 ✓
Hamburguesa R11 200gr: 1 unidad
  30 | -1 | 29 ✓
Tocino Laminado (50gr): 2 unidad
  -4 | -2 | -6 ⚠
Sweet Relish: 30.00 g
  40.0 | -30.0 | 10.0 ⚠
```

---

## 📚 Lecciones Aprendidas

### 1. Estructura de `inventory_transactions`

La tabla `inventory_transactions` usa `order_reference` (no `order_item_id`) para relacionar transacciones con órdenes:

```sql
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT,
    product_id INT,
    order_reference VARCHAR(50),  -- ✅ Campo correcto para JOIN
    order_item_id INT,            -- ❌ Este campo NO se usa
    quantity DECIMAL(10,3),
    previous_stock DECIMAL(10,3),
    new_stock DECIMAL(10,3),
    unit VARCHAR(20),
    transaction_type ENUM('sale', 'refund', 'adjustment'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. Patrón de Asignación de Transacciones

Las transacciones de inventario son **por orden completa**, no por item individual. Por eso se asignan solo al **primer item**:

```php
// ✅ PATRÓN CORRECTO
foreach ($items as $index => &$item) {
    if ($index === 0) {
        // Primer item: asignar todas las transacciones de la orden
        $item['inventory_transactions'] = $all_inventory_transactions;
    } else {
        // Otros items: array vacío
        $item['inventory_transactions'] = [];
    }
}
```

### 3. Consistencia en Frontend

Cuando se agrega un nuevo método de pago, debe incluirse en **TODOS** los objetos `paymentMethods` del frontend para evitar que las órdenes se salteen en el procesamiento.

---

## 🔄 Casos de Uso Similares

### Agregar Nuevo Método de Pago

Si necesitas agregar un nuevo método de pago (ej: `mercadopago`):

**1. Backend**: No requiere cambios (ya funciona con cualquier `payment_method`)

**2. Frontend**: Agregar en `pagos-tuu.astro` en las 3 ubicaciones:

```javascript
const paymentMethods = {
  'cash': { icon: '💵', label: 'Efectivo', sales: 0, cost: 0, orders: 0 },
  'card': { icon: '💳', label: 'Tarjeta', sales: 0, cost: 0, orders: 0 },
  'transfer': { icon: '🏦', label: 'Transfer', sales: 0, cost: 0, orders: 0 },
  'webpay': { icon: '💳', label: 'Webpay', sales: 0, cost: 0, orders: 0 },
  'pedidosya': { icon: '🛵', label: 'PedidosYA', sales: 0, cost: 0, orders: 0 },
  'rl6_credit': { icon: '💳', label: 'Crédito RL6', sales: 0, cost: 0, orders: 0 },
  'mercadopago': { icon: '💳', label: 'MercadoPago', sales: 0, cost: 0, orders: 0 }  // ✅ NUEVO
};
```

### Crear Nuevo Reporte con Inventario

Si necesitas crear un nuevo reporte que muestre consumo de ingredientes:

```php
// 1. Obtener órdenes
$orders = obtenerOrdenes();

// 2. Para cada orden, obtener transacciones de inventario
foreach ($orders as &$order) {
    // Obtener items
    $items = obtenerItems($order['id']);
    
    // Obtener transacciones por order_reference
    $trans_sql = "
        SELECT 
            it.*,
            COALESCE(i.name, p.name) as item_name,
            COALESCE(it.unit, i.unit, 'unidad') as unit,
            CASE WHEN it.ingredient_id IS NOT NULL THEN 'ingredient' ELSE 'product' END as item_type
        FROM inventory_transactions it
        LEFT JOIN ingredients i ON it.ingredient_id = i.id
        LEFT JOIN products p ON it.product_id = p.id
        WHERE it.order_reference = ?  -- ✅ Usar order_reference
        ORDER BY it.id ASC
    ";
    
    $stmt = $pdo->prepare($trans_sql);
    $stmt->execute([$order['order_number']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asignar al primer item
    if (count($items) > 0 && count($transactions) > 0) {
        $items[0]['inventory_transactions'] = $transactions;
    }
    
    $order['items'] = $items;
}
```

---

## ⚠️ Errores Comunes a Evitar

### ❌ Error 1: Buscar por `order_item_id`
```php
// INCORRECTO
WHERE it.order_item_id = ?
```

### ❌ Error 2: Asignar transacciones a cada item
```php
// INCORRECTO - Duplica las transacciones
foreach ($items as &$item) {
    $item['inventory_transactions'] = $all_transactions;
}
```

### ❌ Error 3: Olvidar agregar método de pago en frontend
```javascript
// INCORRECTO - Falta el nuevo método
const paymentMethods = {
  'cash': {...},
  'card': {...}
  // rl6_credit NO está aquí → órdenes se saltean
};
```

---

## 📊 Verificación del Fix

### Query SQL para Verificar
```sql
-- Verificar que las transacciones existen
SELECT 
    o.order_number,
    o.payment_method,
    COUNT(it.id) as num_transactions
FROM tuu_orders o
LEFT JOIN inventory_transactions it ON it.order_reference = o.order_number
WHERE o.payment_method = 'rl6_credit'
  AND o.payment_status = 'paid'
GROUP BY o.order_number, o.payment_method;
```

### Test en Frontend
1. Ir a `/admin/pagos-tuu`
2. Seleccionar período que incluya órdenes RL6
3. Verificar que se muestre "Consumo de ingredientes:" con desglose completo
4. Verificar que aparezca en "Desglose por Método de Pago"

---

**Fecha**: Enero 2025  
**Severidad Original**: 🔴 Alta (pérdida de visibilidad de inventario)  
**Estado**: ✅ Resuelto
