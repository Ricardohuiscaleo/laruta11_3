# Sistema de Combos - Tareas Backend Pendientes

## 🎯 Objetivo

Implementar la lógica backend para descuento de inventario y cálculo de stock de combos.

---

## 📋 Tareas Prioritarias

### 1. ⏳ Modificar `process_sale_inventory.php`

**Ubicación**: `/api/process_sale_inventory.php`

**Objetivo**: Descontar inventario cuando se vende un combo.

**Lógica a implementar**:

```php
<?php
// Procesar items de la orden
foreach ($order_items as $item) {
    $item_type = $item['item_type'] ?? 'product';
    
    if ($item_type === 'combo') {
        // NUEVO: Procesar combo
        processComboInventory($item, $order_id);
    } else {
        // EXISTENTE: Procesar producto normal
        processProductInventory($item, $order_id);
    }
}

function processComboInventory($item, $order_id) {
    global $conn;
    
    // Parsear combo_data
    $combo_data = json_decode($item['combo_data'], true);
    if (!$combo_data) {
        error_log("Error: combo_data inválido para item " . $item['id']);
        return;
    }
    
    $quantity = $item['quantity'];
    
    // 1. Descontar fixed_items (productos fijos del combo)
    if (isset($combo_data['fixed_items'])) {
        foreach ($combo_data['fixed_items'] as $fixed) {
            $product_id = $fixed['product_id'];
            $fixed_quantity = $fixed['quantity'] * $quantity;
            
            // Descontar ingredientes de la receta del producto
            deductIngredientsFromRecipe($product_id, $fixed_quantity, $order_id);
        }
    }
    
    // 2. Descontar selections (bebidas, salsas, etc.)
    if (isset($combo_data['selections'])) {
        foreach ($combo_data['selections'] as $group => $items) {
            if (is_array($items)) {
                // Selecciones múltiples (ej: 2 bebidas)
                foreach ($items as $selection) {
                    $product_id = $selection['id'];
                    deductProductStock($product_id, $quantity, $order_id);
                }
            } else {
                // Selección única
                $product_id = $items['id'];
                deductProductStock($product_id, $quantity, $order_id);
            }
        }
    }
    
    error_log("Combo inventory processed: " . $item['product_name'] . " x" . $quantity);
}

function deductIngredientsFromRecipe($product_id, $quantity, $order_id) {
    global $conn;
    
    // Obtener receta del producto
    $stmt = $conn->prepare("
        SELECT ingredient_id, quantity_needed 
        FROM recetas 
        WHERE product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $ingredient_id = $row['ingredient_id'];
        $quantity_needed = $row['quantity_needed'] * $quantity;
        
        // Descontar del inventario
        $update = $conn->prepare("
            UPDATE ingredientes 
            SET stock = stock - ? 
            WHERE id = ? AND stock >= ?
        ");
        $update->bind_param("dii", $quantity_needed, $ingredient_id, $quantity_needed);
        $update->execute();
        
        if ($update->affected_rows === 0) {
            error_log("Warning: Insufficient stock for ingredient " . $ingredient_id);
        }
        
        // Registrar movimiento
        $log = $conn->prepare("
            INSERT INTO inventory_movements 
            (ingredient_id, quantity, movement_type, order_id, created_at) 
            VALUES (?, ?, 'sale', ?, NOW())
        ");
        $log->bind_param("idi", $ingredient_id, $quantity_needed, $order_id);
        $log->execute();
    }
}

function deductProductStock($product_id, $quantity, $order_id) {
    global $conn;
    
    // Descontar stock del producto (para bebidas, salsas, etc.)
    $stmt = $conn->prepare("
        UPDATE productos 
        SET stock = stock - ? 
        WHERE id = ? AND stock >= ?
    ");
    $stmt->bind_param("iii", $quantity, $product_id, $quantity);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        error_log("Warning: Insufficient stock for product " . $product_id);
    }
    
    // Registrar movimiento
    $log = $conn->prepare("
        INSERT INTO product_movements 
        (product_id, quantity, movement_type, order_id, created_at) 
        VALUES (?, ?, 'sale', ?, NOW())
    ");
    $log->bind_param("iii", $product_id, $quantity, $order_id);
    $log->execute();
}
?>
```

**Testing**:
```bash
# Crear orden de prueba con combo
curl -X POST http://localhost/api/create_order.php \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{
      "product_id": 198,
      "product_name": "Combo Dupla",
      "quantity": 1,
      "item_type": "combo",
      "combo_data": {
        "fixed_items": [...],
        "selections": {...}
      }
    }]
  }'

# Verificar descuento de inventario
mysql> SELECT * FROM ingredientes WHERE id IN (1,2,3);
mysql> SELECT * FROM productos WHERE id IN (120,121);
mysql> SELECT * FROM inventory_movements ORDER BY created_at DESC LIMIT 10;
```

---

### 2. ⏳ Crear/Modificar `get_combos.php`

**Ubicación**: `/api/get_combos.php`

**Objetivo**: Obtener combos con cálculo de stock disponible.

**Implementación**:

```php
<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$combo_id = $_GET['combo_id'] ?? null;

try {
    if ($combo_id) {
        // Obtener combo específico
        $combo = getComboById($combo_id);
        echo json_encode([
            'success' => true,
            'combos' => [$combo],
            'debug' => [
                'combo_id_requested' => $combo_id,
                'combos_found' => $combo ? 1 : 0
            ]
        ]);
    } else {
        // Obtener todos los combos activos
        $combos = getAllCombos();
        echo json_encode([
            'success' => true,
            'combos' => $combos
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getComboById($combo_id) {
    global $conn;
    
    // Obtener datos básicos del combo
    $stmt = $conn->prepare("
        SELECT id, name, description, price, image_url, category_id, active 
        FROM combos 
        WHERE id = ? AND active = 1
    ");
    $stmt->bind_param("i", $combo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $combo = $result->fetch_assoc();
    
    if (!$combo) {
        return null;
    }
    
    // Obtener fixed_items
    $combo['fixed_items'] = getComboFixedItems($combo_id);
    
    // Obtener selection_groups
    $combo['selection_groups'] = getComboSelectionGroups($combo_id);
    
    // Calcular stock disponible
    $combo['stock_available'] = calculateComboStock($combo);
    
    return $combo;
}

function getComboFixedItems($combo_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            ci.product_id,
            ci.quantity,
            p.name as product_name,
            p.image as image_url
        FROM combo_items ci
        JOIN productos p ON ci.product_id = p.id
        WHERE ci.combo_id = ? AND ci.is_selectable = 0
        ORDER BY ci.id
    ");
    $stmt->bind_param("i", $combo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return $items;
}

function getComboSelectionGroups($combo_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            cs.selection_group,
            cs.product_id,
            cs.additional_price,
            cs.max_selections,
            p.name as product_name,
            p.image as image_url
        FROM combo_selections cs
        JOIN productos p ON cs.product_id = p.id
        WHERE cs.combo_id = ?
        ORDER BY cs.selection_group, cs.id
    ");
    $stmt->bind_param("i", $combo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $group = $row['selection_group'];
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }
        $groups[$group][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'additional_price' => $row['additional_price'],
            'max_selections' => $row['max_selections'],
            'image_url' => $row['image_url']
        ];
    }
    
    return $groups;
}

function calculateComboStock($combo) {
    global $conn;
    
    $min_stock = PHP_INT_MAX;
    
    // 1. Stock basado en fixed_items (ingredientes)
    foreach ($combo['fixed_items'] as $item) {
        $product_id = $item['product_id'];
        $required_qty = $item['quantity'];
        
        // Calcular stock basado en ingredientes del producto
        $stock = calculateProductStockByIngredients($product_id);
        $available = floor($stock / $required_qty);
        $min_stock = min($min_stock, $available);
    }
    
    // 2. Stock basado en selections (productos directos)
    foreach ($combo['selection_groups'] as $group => $options) {
        $group_stock = 0;
        foreach ($options as $option) {
            $stmt = $conn->prepare("SELECT stock FROM productos WHERE id = ?");
            $stmt->bind_param("i", $option['product_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $group_stock += $row['stock'] ?? 0;
        }
        $min_stock = min($min_stock, $group_stock);
    }
    
    return max(0, $min_stock);
}

function calculateProductStockByIngredients($product_id) {
    global $conn;
    
    // Obtener receta del producto
    $stmt = $conn->prepare("
        SELECT 
            r.ingredient_id,
            r.quantity_needed,
            i.stock
        FROM recetas r
        JOIN ingredientes i ON r.ingredient_id = i.id
        WHERE r.product_id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $min_stock = PHP_INT_MAX;
    while ($row = $result->fetch_assoc()) {
        $available = floor($row['stock'] / $row['quantity_needed']);
        $min_stock = min($min_stock, $available);
    }
    
    return $min_stock === PHP_INT_MAX ? 0 : $min_stock;
}

function getAllCombos() {
    global $conn;
    
    $stmt = $conn->query("
        SELECT id, name, description, price, image_url, category_id, active 
        FROM combos 
        WHERE active = 1
        ORDER BY name
    ");
    
    $combos = [];
    while ($row = $stmt->fetch_assoc()) {
        $combo = $row;
        $combo['fixed_items'] = getComboFixedItems($combo['id']);
        $combo['selection_groups'] = getComboSelectionGroups($combo['id']);
        $combo['stock_available'] = calculateComboStock($combo);
        $combos[] = $combo;
    }
    
    return $combos;
}
?>
```

**Testing**:
```bash
# Obtener combo específico
curl http://localhost/api/get_combos.php?combo_id=4

# Obtener todos los combos
curl http://localhost/api/get_combos.php

# Verificar stock calculado
# Debe retornar stock_available basado en ingredientes y productos
```

---

### 3. ⏳ Crear Tablas de Base de Datos (si no existen)

**Archivo**: `/api/setup_combo_tables.php`

```php
<?php
require_once 'config.php';

// Tabla de combos
$conn->query("
    CREATE TABLE IF NOT EXISTS combos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(500),
        category_id INT DEFAULT 8,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tabla de items fijos del combo
$conn->query("
    CREATE TABLE IF NOT EXISTS combo_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        combo_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT DEFAULT 1,
        is_selectable TINYINT(1) DEFAULT 0,
        selection_group VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tabla de opciones seleccionables
$conn->query("
    CREATE TABLE IF NOT EXISTS combo_selections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        combo_id INT NOT NULL,
        selection_group VARCHAR(50) NOT NULL,
        product_id INT NOT NULL,
        additional_price DECIMAL(10,2) DEFAULT 0,
        max_selections INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tabla de movimientos de inventario (si no existe)
$conn->query("
    CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_id INT NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        movement_type ENUM('purchase', 'sale', 'adjustment', 'waste') NOT NULL,
        order_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Tabla de movimientos de productos (si no existe)
$conn->query("
    CREATE TABLE IF NOT EXISTS product_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        movement_type ENUM('purchase', 'sale', 'adjustment', 'waste') NOT NULL,
        order_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES productos(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

echo json_encode([
    'success' => true,
    'message' => 'Tablas de combos creadas exitosamente'
]);
?>
```

**Ejecutar**:
```bash
curl http://localhost/api/setup_combo_tables.php
```

---

## 🧪 Plan de Testing

### Test 1: Descuento de Inventario
```sql
-- Antes de la venta
SELECT stock FROM ingredientes WHERE id IN (1,2,3);
SELECT stock FROM productos WHERE id = 120;

-- Crear orden con combo
-- (usar API create_order.php)

-- Después de la venta
SELECT stock FROM ingredientes WHERE id IN (1,2,3);
SELECT stock FROM productos WHERE id = 120;

-- Verificar movimientos
SELECT * FROM inventory_movements ORDER BY created_at DESC LIMIT 5;
SELECT * FROM product_movements ORDER BY created_at DESC LIMIT 5;
```

### Test 2: Cálculo de Stock
```bash
# Obtener combo con stock
curl http://localhost/api/get_combos.php?combo_id=4

# Verificar que stock_available sea correcto
# Debe ser el mínimo entre:
# - Stock de ingredientes / cantidad requerida
# - Stock de productos seleccionables
```

### Test 3: Stock Insuficiente
```sql
-- Reducir stock de un ingrediente
UPDATE ingredientes SET stock = 0 WHERE id = 1;

-- Intentar vender combo
-- Debe fallar o mostrar warning

-- Verificar logs
SELECT * FROM inventory_movements WHERE ingredient_id = 1;
```

---

## 📊 Checklist de Implementación

### Fase 1: Setup
- [ ] Ejecutar `setup_combo_tables.php`
- [ ] Verificar que tablas existan
- [ ] Insertar datos de prueba

### Fase 2: API get_combos.php
- [ ] Implementar `getComboById()`
- [ ] Implementar `calculateComboStock()`
- [ ] Implementar `calculateProductStockByIngredients()`
- [ ] Testing con combos reales

### Fase 3: Descuento de Inventario
- [ ] Modificar `process_sale_inventory.php`
- [ ] Implementar `processComboInventory()`
- [ ] Implementar `deductIngredientsFromRecipe()`
- [ ] Implementar `deductProductStock()`
- [ ] Testing con órdenes reales

### Fase 4: Testing Integral
- [ ] Test: Venta de combo simple
- [ ] Test: Venta de combo con selecciones múltiples
- [ ] Test: Stock insuficiente
- [ ] Test: Múltiples combos en una orden
- [ ] Test: Verificar movimientos de inventario

### Fase 5: Monitoreo
- [ ] Logs de errores
- [ ] Alertas de stock bajo
- [ ] Dashboard de inventario

---

## 🚨 Consideraciones Importantes

### 1. Transacciones
Usar transacciones para garantizar consistencia:
```php
$conn->begin_transaction();
try {
    processComboInventory($item, $order_id);
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error: " . $e->getMessage());
}
```

### 2. Stock Negativo
Prevenir stock negativo:
```sql
UPDATE ingredientes 
SET stock = stock - ? 
WHERE id = ? AND stock >= ?
```

### 3. Logs Detallados
Registrar todos los movimientos:
```php
error_log("Combo inventory: " . json_encode([
    'combo_id' => $combo_id,
    'quantity' => $quantity,
    'fixed_items' => $fixed_items,
    'selections' => $selections
]));
```

---

## 📞 Soporte

Para dudas o problemas:
1. Revisar logs: `/var/log/apache2/error.log`
2. Verificar tablas: `SHOW TABLES LIKE 'combo%'`
3. Consultar movimientos: `SELECT * FROM inventory_movements`

---

**Última actualización**: 2024
**Prioridad**: Alta
**Estimación**: 2-3 días de desarrollo + 1 día de testing
