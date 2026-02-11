# 🍽️ Sistema de Combos - Estado Actual

## 📋 Resumen

El sistema de combos está **implementado y funcional** con tablas dedicadas y flujo completo de creación, venta y descuento de inventario.

---

## 🗄️ Estructura de Base de Datos

### Tablas Principales

```sql
-- Tabla principal de combos
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    category_id INT DEFAULT 8,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Items fijos del combo (ej: completo + papas)
CREATE TABLE combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    is_selectable TINYINT(1) DEFAULT 0,
    selection_group VARCHAR(50)
);

-- Opciones seleccionables (ej: bebidas)
CREATE TABLE combo_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    selection_group VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    additional_price DECIMAL(10,2) DEFAULT 0,
    max_selections INT DEFAULT 1
);
```

---

## 🎯 Flujo Completo de Combos

### 1️⃣ Crear Combo (Admin)

**URL**: `/admin/combos` → Click "Crear Combo"

**Pasos**:
1. Ir a `/admin/combos`
2. Click en "+ Crear Combo"
3. Redirige a `/admin/edit-product?category_id=8`
4. Llenar formulario:
   - Nombre del combo
   - Descripción
   - Precio
   - Imagen

**Estructura del Combo**:

```javascript
{
  "name": "Combo Completo Familiar",
  "description": "Completo + Papas + Bebida",
  "price": 5990,
  "image_url": "https://...",
  "fixed_items": [
    {
      "product_id": 1,  // Completo Tradicional
      "quantity": 1
    },
    {
      "product_id": 15, // Papas Medianas
      "quantity": 1
    }
  ],
  "selection_groups": {
    "bebida": {
      "max_selections": 1,
      "options": [
        { "product_id": 20, "additional_price": 0 },  // Coca-Cola
        { "product_id": 21, "additional_price": 0 },  // Sprite
        { "product_id": 22, "additional_price": 0 }   // Fanta
      ]
    }
  }
}
```

**APIs Involucradas**:
- `POST /api/save_combo.php` - Guarda el combo
- `GET /api/get_combos.php` - Lista combos
- `POST /api/delete_combo.php` - Elimina combo

---

### 2️⃣ Vender Combo (Caja/App)

**Flujo en Caja**:

```
1. Cliente selecciona combo
   ↓
2. Sistema muestra:
   - Items fijos (completo + papas)
   - Selector de bebida
   ↓
3. Cliente elige bebida
   ↓
4. Se agrega al carrito con estructura:
   {
     "id": 5,
     "name": "Combo Completo Familiar",
     "price": 5990,
     "quantity": 1,
     "type": "combo",
     "combo_id": 5,
     "fixed_items": [...],
     "selections": {
       "bebida": { "id": 20, "name": "Coca-Cola" }
     }
   }
   ↓
5. create_order.php guarda en tuu_order_items:
   - item_type = 'combo'
   - combo_data = JSON con fixed_items + selections
```

---

### 3️⃣ Descuento de Inventario

**Cuando se confirma el pago** (`confirm_transfer_payment.php`):

```php
// 1. Detecta que es combo
if ($item['item_type'] === 'combo') {
    $combo_data = json_decode($item['combo_data'], true);
    
    // 2. Descuenta receta del combo principal
    deductProduct($pdo, $combo_id, $quantity);
    
    // 3. Descuenta bebida seleccionada
    foreach ($combo_data['selections'] as $selection) {
        deductProduct($pdo, $selection['id'], $quantity);
    }
}
```

**Ejemplo Real**:

Si vendes 1x "Combo Completo Familiar" con Coca-Cola:

1. **Descuenta ingredientes del Completo**:
   - Pan: 1 unidad
   - Vienesa: 1 unidad
   - Tomate: 50g
   - Palta: 30g
   - Mayo: 20g

2. **Descuenta ingredientes de Papas**:
   - Papas: 150g
   - Aceite: 10ml
   - Sal: 2g

3. **Descuenta producto Coca-Cola**:
   - Coca-Cola 350ml: 1 unidad

4. **Registra en inventory_transactions**:
   - 8 transacciones (ingredientes)
   - 1 transacción (bebida)
   - Total: 9 registros con trazabilidad completa

---

## 📊 Cómo Funciona el Inventario de Combos

### Concepto Clave

**Los combos NO tienen inventario propio**. El stock se calcula dinámicamente basado en:

1. **Ingredientes de productos con receta** (completo, papas)
2. **Stock de productos seleccionables** (bebidas)

### Ejemplo de Cálculo de Stock

```
Combo: Completo + Papas + Bebida

Stock disponible = MIN(
  stock_completo,      // 50 (basado en ingredientes)
  stock_papas,         // 30 (basado en ingredientes)
  stock_bebidas        // 20 (stock directo de productos)
)

Stock del combo = 20 unidades
```

---

## 🔧 APIs Disponibles

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/get_combos.php` | GET | Lista todos los combos activos |
| `/api/get_combos.php?combo_id=5` | GET | Obtiene un combo específico |
| `/api/save_combo.php` | POST | Crea o actualiza combo |
| `/api/delete_combo.php` | POST | Elimina combo (soft delete) |
| `/api/setup_combo_tables.php` | GET | Crea tablas de combos |

---

## 🎨 Interfaz de Usuario

### Admin

**URL**: `/admin/combos`

**Funciones**:
- ✅ Ver lista de combos
- ✅ Crear nuevo combo
- ✅ Editar combo existente
- ✅ Eliminar combo
- ✅ Ver productos incluidos
- ✅ Ver grupos de selección

### Caja/App

**Integración**:
- Los combos aparecen como productos normales
- Al seleccionar, se abre modal de personalización
- Cliente elige opciones seleccionables
- Se agrega al carrito con selecciones

---

## 📝 Ejemplo Completo: Crear Combo Paso a Paso

### Paso 1: Crear Combo en Admin

```javascript
// POST /api/save_combo.php
{
  "name": "Combo Dupla",
  "description": "2 Completos + 2 Bebidas",
  "price": 8990,
  "image_url": "https://laruta11-images.s3.amazonaws.com/combos/dupla.jpg",
  "fixed_items": [
    { "product_id": 1, "quantity": 2 }  // 2 Completos
  ],
  "selection_groups": {
    "bebidas": {
      "max_selections": 2,  // Puede elegir 2 bebidas
      "options": [
        { "product_id": 20, "additional_price": 0 },
        { "product_id": 21, "additional_price": 0 },
        { "product_id": 22, "additional_price": 0 }
      ]
    }
  }
}
```

### Paso 2: Cliente Compra en Caja

```javascript
// Carrito
{
  "id": 10,
  "name": "Combo Dupla",
  "price": 8990,
  "quantity": 1,
  "type": "combo",
  "combo_id": 10,
  "selections": {
    "bebidas": [
      { "id": 20, "name": "Coca-Cola" },
      { "id": 21, "name": "Sprite" }
    ]
  }
}
```

### Paso 3: Confirmar Pago

```
confirm_transfer_payment.php ejecuta:

1. Descuenta 2x Completo (ingredientes):
   - Pan: 2 unidades
   - Vienesa: 2 unidades
   - Tomate: 100g
   - etc.

2. Descuenta bebidas seleccionadas:
   - Coca-Cola: 1 unidad
   - Sprite: 1 unidad

3. Registra 12+ transacciones en inventory_transactions
```

---

## ✅ Estado Actual

| Componente | Estado | Notas |
|------------|--------|-------|
| Tablas BD | ✅ Creadas | combos, combo_items, combo_selections |
| APIs | ✅ Funcionales | CRUD completo |
| Admin UI | ✅ Funcional | /admin/combos |
| Caja/App | ✅ Integrado | Selector de opciones |
| Inventario | ✅ Funcional | Descuento automático con trazabilidad |
| Transacciones | ✅ Registradas | inventory_transactions completo |

---

## 🚀 Próximos Pasos (Opcional)

1. **Mejorar UI de creación de combos** - Interfaz más visual
2. **Límites de stock** - Alertas cuando ingredientes están bajos
3. **Combos promocionales** - Descuentos por tiempo limitado
4. **Analytics** - Combos más vendidos

---

## 📞 Soporte

Si necesitas crear un combo nuevo:

1. Ve a `/admin/combos`
2. Click "+ Crear Combo"
3. Llena el formulario
4. Agrega productos fijos
5. Configura opciones seleccionables
6. Guarda

**El sistema se encarga automáticamente de**:
- ✅ Calcular stock disponible
- ✅ Descontar inventario al vender
- ✅ Registrar transacciones
- ✅ Mostrar en caja/app
