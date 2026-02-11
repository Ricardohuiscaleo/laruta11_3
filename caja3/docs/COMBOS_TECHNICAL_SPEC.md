# Sistema de Combos - Especificación Técnica

## 📋 Resumen

Sistema completo de combos que permite crear, personalizar y gestionar combos con múltiples productos y selecciones (bebidas, salsas, etc.). Cada combo se trata como un item individual en el carrito, permitiendo múltiples instancias del mismo combo con diferentes personalizaciones.

---

## 🏗️ Arquitectura de Datos

### Estructura de un Combo en el Carrito

```javascript
{
  id: 198,                          // ID del producto combo
  name: "Combo Dupla",              // Nombre del combo
  price: 16770,                     // Precio del combo
  quantity: 1,                      // Siempre 1 (cada combo es un item separado)
  category_name: "Combos",          // Categoría
  cartItemId: "combo-1234567890-0.123", // ID único para el carrito
  
  // Productos fijos incluidos en el combo
  fixed_items: [
    {
      product_id: 45,
      product_name: "Hamburguesa Clásica",
      quantity: 1,
      image_url: "..."
    },
    {
      product_id: 67,
      product_name: "Ave Italiana",
      quantity: 1,
      image_url: "..."
    },
    {
      product_id: 89,
      product_name: "Papas Fritas Individual",
      quantity: 1,
      image_url: "..."
    }
  ],
  
  // Selecciones personalizables (bebidas, salsas, etc.)
  selections: {
    "Bebidas": [
      {
        id: 120,
        name: "Coca-Cola Lata 350ml",
        price: 0  // Precio adicional (0 = incluido)
      },
      {
        id: 120,
        name: "Coca-Cola Lata 350ml",
        price: 0
      }
    ]
  }
}
```

---

## 🔄 Flujo de Datos

### 1. Selección de Combo (MenuApp.jsx)

```javascript
// Usuario hace click en un combo
onClick={() => setComboModalProduct(product)}

// Se abre ComboModal con el producto
<ComboModal 
  combo={comboModalProduct}
  isOpen={!!comboModalProduct}
  onClose={() => setComboModalProduct(null)}
  quantity={1}
  onAddToCart={(comboWithSelections) => {
    vibrate(50);
    setCart(prevCart => [...prevCart, { 
      ...comboWithSelections, 
      quantity: 1,
      cartItemId: `combo-${Date.now()}-${Math.random()}`
    }]);
    setComboModalProduct(null);
  }}
/>
```

**Puntos clave:**
- `quantity` siempre es 1
- Cada combo agregado genera un `cartItemId` único
- No se buscan combos existentes para incrementar cantidad
- Cada combo es un item completamente independiente

---

### 2. Personalización de Combo (ComboModal.jsx)

#### Carga de Datos del Combo

```javascript
const loadComboData = async () => {
  // Mapeo de nombres a IDs reales
  const comboMapping = {
    'Combo Doble Mixta': 1,
    'Combo Completo': 2, 
    'Combo Gorda': 3,
    'Combo Dupla': 4
  };
  
  const realComboId = comboMapping[combo.name] || combo.id;
  
  const response = await fetch(`/api/get_combos.php?combo_id=${realComboId}`);
  const data = await response.json();
  
  if (data.success && data.combos.length > 0) {
    setComboData(data.combos[0]);
  }
};
```

#### Reseteo de Selecciones

```javascript
useEffect(() => {
  if (isOpen && combo) {
    setSelections({});  // ✅ Resetear selecciones al abrir
    loadComboData();
  }
}, [isOpen, combo]);
```

**Importante:** El reseteo de `selections` permite seleccionar el mismo combo múltiples veces seguidas sin conflictos.

#### Manejo de Selecciones Múltiples

```javascript
const handleSelectionChange = (groupName, productId, maxSelections, action) => {
  setSelections(prev => {
    if (maxSelections === 1) {
      // Selección única (radio button)
      return {
        ...prev,
        [groupName]: prev[groupName] === productId ? null : productId
      };
    } else {
      // Selección múltiple (botones +/-)
      const currentArray = Array.isArray(prev[groupName]) ? prev[groupName] : [];
      
      if (action === 'add' && currentArray.length < maxSelections) {
        return {
          ...prev,
          [groupName]: [...currentArray, productId]
        };
      } else if (action === 'remove') {
        const index = currentArray.indexOf(productId);
        if (index > -1) {
          const newArray = [...currentArray];
          newArray.splice(index, 1);
          return { ...prev, [groupName]: newArray };
        }
      }
    }
    return prev;
  });
};
```

**Ejemplo:** Combo Dupla con 2 bebidas
- `maxSelections = 2`
- Usuario presiona + en Coca-Cola → `selections.Bebidas = [120]`
- Usuario presiona + en Coca-Cola otra vez → `selections.Bebidas = [120, 120]`
- Usuario presiona + en Sprite → `selections.Bebidas = [120, 120, 135]` (bloqueado si max=2)

#### Validación y Agregado al Carrito

```javascript
const handleAddToCart = () => {
  // Validar que todas las selecciones requeridas estén completas
  const invalidGroups = [];
  Object.entries(comboData.selection_groups || {}).forEach(([groupName, options]) => {
    const maxSelections = options[0]?.max_selections || 1;
    const totalSelected = getTotalSelected(groupName);
    if (totalSelected !== maxSelections) {
      invalidGroups.push(`${groupName} (${totalSelected}/${maxSelections})`);
    }
  });
  
  if (invalidGroups.length > 0) {
    alert(`Por favor completa las selecciones:\n${invalidGroups.join('\n')}`);
    return;
  }
  
  // Construir objeto con detalles de selecciones
  const detailedSelections = {};
  Object.entries(selections).forEach(([groupName, selection]) => {
    const options = comboData.selection_groups?.[groupName];
    if (Array.isArray(selection)) {
      detailedSelections[groupName] = selection.map(productId => {
        const option = options?.find(o => o.product_id === productId);
        return option ? {
          id: option.product_id,
          name: option.product_name,
          price: option.additional_price || 0
        } : null;
      }).filter(Boolean);
    } else if (selection) {
      const option = options?.find(o => o.product_id === selection);
      if (option) {
        detailedSelections[groupName] = {
          id: option.product_id,
          name: option.product_name,
          price: option.additional_price || 0
        };
      }
    }
  });
  
  const comboWithSelections = {
    ...combo,
    selections: detailedSelections,
    fixed_items: comboData.fixed_items || [],
    quantity: 1
  };
  
  onAddToCart(comboWithSelections);
  onClose();
};
```

---

### 3. Visualización en Carrito (MenuApp.jsx)

```javascript
{cart.map((item, itemIndex) => {
  const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
  
  return (
    <div key={item.cartItemId}>
      <p className="font-semibold">{item.name}</p>
      <p className="text-orange-500">${item.price.toLocaleString('es-CL')}</p>
      
      {isCombo && item.selections && (
        <div className="mt-2 pt-2 border-t">
          <p className="text-xs font-medium text-gray-700">Incluye:</p>
          
          {/* Fixed items */}
          {item.fixed_items && item.fixed_items.map((fixedItem, idx) => (
            <p key={idx} className="text-xs text-gray-600">
              • {fixedItem.quantity || 1}x {fixedItem.product_name || fixedItem.name}
            </p>
          ))}
          
          {/* Selections */}
          {Object.entries(item.selections || {}).map(([group, selection]) => {
            if (Array.isArray(selection)) {
              return selection.map((sel, idx) => (
                <p key={`${group}-${idx}`} className="text-xs text-blue-600 font-medium">
                  • 1x {sel.name}
                </p>
              ));
            } else {
              return (
                <p key={group} className="text-xs text-blue-600 font-medium">
                  • 1x {selection.name}
                </p>
              );
            }
          })}
        </div>
      )}
    </div>
  );
})}
```

**Visualización esperada:**
```
Combo Dupla - $16.770
Incluye:
• 1x Hamburguesa Clásica
• 1x Ave Italiana
• 1x Papas Fritas Individual
• 1x Coca-Cola Lata 350ml
• 1x Coca-Cola Lata 350ml
```

---

### 4. Mensaje de WhatsApp (MenuApp.jsx)

```javascript
cart.forEach((item, index) => {
  const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
  message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\n`;
  
  if (isCombo && (item.fixed_items || item.selections)) {
    message += `   Incluye:\n`;
    
    // Fixed items
    if (item.fixed_items) {
      item.fixed_items.forEach(fixedItem => {
        message += `   • ${fixedItem.quantity || 1}x ${fixedItem.product_name || fixedItem.name}\n`;
      });
    }
    
    // Selections
    if (item.selections) {
      Object.entries(item.selections).forEach(([group, selection]) => {
        if (Array.isArray(selection)) {
          selection.forEach(sel => {
            message += `   • 1x ${sel.name}\n`;
          });
        } else if (selection) {
          message += `   • 1x ${selection.name}\n`;
        }
      });
    }
  }
});
```

**Mensaje generado:**
```
PEDIDO - LA RUTA 11

Cliente: Juan Pérez
Teléfono: +56912345678
Tipo: Delivery
Dirección: Av. Principal 123

PRODUCTOS:
1. Combo Dupla x1 - $16.770
   Incluye:
   • 1x Hamburguesa Clásica
   • 1x Ave Italiana
   • 1x Papas Fritas Individual
   • 1x Coca-Cola Lata 350ml
   • 1x Coca-Cola Lata 350ml

2. Combo Dupla x1 - $16.770
   Incluye:
   • 1x Hamburguesa Clásica
   • 1x Ave Italiana
   • 1x Papas Fritas Individual
   • 1x Sprite Lata 350ml
   • 1x Sprite Lata 350ml

Subtotal: $33.540
Delivery: $2.000
TOTAL: $35.540

Pedido realizado desde la app web.
```

---

### 5. Pantallas Pending (transfer-pending.astro, cash-pending.astro, etc.)

#### Función displayOrderItems

```javascript
function displayOrderItems(cart, total, deliveryFee) {
  let itemsHtml = '';
  let subtotal = 0;
  
  cart.forEach(item => {
    let itemTotal = item.price * item.quantity;
    
    // Agregar customizations (ingredientes extra)
    if (item.customizations && item.customizations.length > 0) {
      itemTotal += item.customizations.reduce((sum, c) => sum + (c.price * c.quantity), 0);
    }
    
    subtotal += itemTotal;
    
    let includesText = '';
    
    // Customizations (ingredientes extra)
    if (item.customizations && item.customizations.length > 0) {
      const customItems = item.customizations.map(c => 
        `${c.quantity}x ${c.name} (+$${(c.price * c.quantity).toLocaleString('es-CL')})`
      ).join(', ');
      includesText = `<div class="text-xs text-blue-600 mt-1">Incluye: ${customItems}</div>`;
    }
    
    const allIncludes = [];
    
    // Fixed items del combo
    if (item.fixed_items && item.fixed_items.length > 0) {
      item.fixed_items.forEach(f => {
        if (typeof f === 'string') {
          allIncludes.push(f);
        } else {
          allIncludes.push(`${f.quantity || 1}x ${f.product_name || f.name}`);
        }
      });
    }
    
    // Selections del combo (bebidas, salsas, etc.)
    if (item.selections && typeof item.selections === 'object') {
      Object.values(item.selections).forEach(categoryItems => {
        if (Array.isArray(categoryItems)) {
          categoryItems.forEach(s => {
            allIncludes.push(`1x ${s.name || s.product_name}`);
          });
        } else if (categoryItems && typeof categoryItems === 'object') {
          allIncludes.push(`1x ${categoryItems.name || categoryItems.product_name}`);
        }
      });
    }
    
    if (allIncludes.length > 0) {
      if (includesText) {
        includesText += `<div class="text-xs text-gray-500 mt-1">También: ${allIncludes.join(', ')}</div>`;
      } else {
        includesText = `<div class="text-xs text-gray-500 mt-1">Incluye: ${allIncludes.join(', ')}</div>`;
      }
    }
    
    itemsHtml += `
      <div class="border-b border-gray-200 pb-2 mb-3 last:border-b-0 last:mb-0">
        <div class="flex justify-between items-start">
          <div>
            <div class="font-medium text-gray-900">${item.name}</div>
            <div class="text-xs text-gray-500">Cantidad: ${item.quantity}</div>
            ${includesText}
          </div>
          <div class="font-semibold text-gray-900">$${itemTotal.toLocaleString('es-CL')}</div>
        </div>
      </div>
    `;
  });
  
  document.getElementById('order-items').innerHTML = itemsHtml;
  document.getElementById('subtotal').textContent = `$${subtotal.toLocaleString('es-CL')}`;
  document.getElementById('total').textContent = `$${total.toLocaleString('es-CL')}`;
  
  if (deliveryFee > 0) {
    document.getElementById('delivery-row').classList.remove('hidden');
    document.getElementById('delivery-row').classList.add('flex');
    document.getElementById('delivery-fee').textContent = `$${deliveryFee.toLocaleString('es-CL')}`;
  }
}
```

**Visualización en pending:**
```
🛒 Tu Pedido

Combo Dupla
Cantidad: 1
Incluye: 1x Hamburguesa Clásica, 1x Ave Italiana, 1x Papas Fritas Individual, 1x Coca-Cola Lata 350ml, 1x Coca-Cola Lata 350ml
$16.770

Combo Dupla
Cantidad: 1
Incluye: 1x Hamburguesa Clásica, 1x Ave Italiana, 1x Papas Fritas Individual, 1x Sprite Lata 350ml, 1x Sprite Lata 350ml
$16.770

Subtotal: $33.540
Delivery: $2.000
Total: $35.540
```

---

## 🗄️ Base de Datos

### Tablas Principales

#### `combos`
```sql
CREATE TABLE combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500),
    category_id INT DEFAULT 8,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

#### `combo_items` (productos fijos)
```sql
CREATE TABLE combo_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    is_selectable TINYINT(1) DEFAULT 0,
    selection_group VARCHAR(50),
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES productos(id)
);
```

#### `combo_selections` (opciones seleccionables)
```sql
CREATE TABLE combo_selections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    selection_group VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    additional_price DECIMAL(10,2) DEFAULT 0,
    max_selections INT DEFAULT 1,
    FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES productos(id)
);
```

### API Response Structure

#### GET `/api/get_combos.php?combo_id=4`

```json
{
  "success": true,
  "combos": [
    {
      "id": 4,
      "name": "Combo Dupla",
      "description": "La dupla perfecta...",
      "price": 16770,
      "image_url": "https://...",
      "fixed_items": [
        {
          "product_id": 45,
          "product_name": "Hamburguesa Clásica",
          "quantity": 1,
          "image_url": "https://..."
        },
        {
          "product_id": 67,
          "product_name": "Ave Italiana",
          "quantity": 1,
          "image_url": "https://..."
        },
        {
          "product_id": 89,
          "product_name": "Papas Fritas Individual",
          "quantity": 1,
          "image_url": "https://..."
        }
      ],
      "selection_groups": {
        "Bebidas": [
          {
            "product_id": 120,
            "product_name": "Coca-Cola Lata 350ml",
            "additional_price": 0,
            "max_selections": 2,
            "image_url": "https://..."
          },
          {
            "product_id": 121,
            "product_name": "Sprite Lata 350ml",
            "additional_price": 0,
            "max_selections": 2,
            "image_url": "https://..."
          }
        ]
      }
    }
  ]
}
```

---

## 📊 Comandas (Kitchen Display)

### Estructura de Order Items en DB

```json
{
  "items": [
    {
      "id": 198,
      "product_name": "Combo Dupla",
      "product_price": 16770,
      "quantity": 1,
      "item_type": "combo",
      "combo_data": {
        "fixed_items": [
          {
            "product_id": 45,
            "product_name": "Hamburguesa Clásica",
            "quantity": 1
          },
          {
            "product_id": 67,
            "product_name": "Ave Italiana",
            "quantity": 1
          },
          {
            "product_id": 89,
            "product_name": "Papas Fritas Individual",
            "quantity": 1
          }
        ],
        "selections": {
          "Bebidas": [
            {
              "id": 120,
              "name": "Coca-Cola Lata 350ml",
              "price": 0
            },
            {
              "id": 120,
              "name": "Coca-Cola Lata 350ml",
              "price": 0
            }
          ]
        }
      }
    }
  ]
}
```

### Implementación en Comandas (/src/pages/comandas/index.astro)

```javascript
// Detectar si es combo
const isCombo = item.item_type === 'combo';
let comboData = null;

if (item.combo_data) {
  try {
    comboData = typeof item.combo_data === 'string' 
      ? JSON.parse(item.combo_data) 
      : item.combo_data;
  } catch (e) {
    console.error('Error parsing combo_data:', e);
  }
}

// Renderizar fixed items
if (isCombo && comboData && comboData.fixed_items) {
  comboData.fixed_items.map(fixed => 
    // Multiplicar por item.quantity (siempre 1 para combos)
    `${item.quantity * fixed.quantity}x ${fixed.product_name}`
  );
}

// Renderizar selections
if (isCombo && comboData && comboData.selections) {
  Object.entries(comboData.selections).map(([group, selection]) => {
    if (Array.isArray(selection)) {
      return selection.map(sel => 
        `${item.quantity}x ${sel.name}`
      );
    } else if (selection && selection.name) {
      return `${item.quantity}x ${selection.name}`;
    }
  });
}
```

### Visualización en Comandas

**Tarjeta de Pedido con Combo:**

```
┌─────────────────────────────────────┐
│ R11-1234                            │
│ 🔥 Preparando • 5m 30s              │
├─────────────────────────────────────┤
│ 🍽️ PRODUCTOS                        │
│                                     │
│ 🎁 Combo Dupla                      │
│ x1  $16.770                         │
│                                     │
│ Incluye:                            │
│ • 1x Hamburguesa Clásica            │
│ • 1x Ave Italiana                   │
│ • 1x Papas Fritas Individual        │
│                                     │
│ Seleccionado:                       │
│ • 1x Coca-Cola Lata 350ml           │
│ • 1x Coca-Cola Lata 350ml           │
├─────────────────────────────────────┤
│ 👤 Juan Pérez                       │
│ +56912345678                        │
├─────────────────────────────────────┤
│ 🚚 Delivery                         │
│ 📍 Av. Principal 123                │
├─────────────────────────────────────┤
│ 💰 $18.770                          │
│ ✅ Pagado • 💳 Webpay               │
├─────────────────────────────────────┤
│ [🚴 DELIVERY]  [❌ ANULAR]          │
└─────────────────────────────────────┘
```

**Características:**
- ✅ Combos destacados con borde naranja y emoji 🎁
- ✅ Fixed items listados con cantidades correctas
- ✅ Selections agrupadas por categoría
- ✅ Multiplicación automática por `item.quantity`
- ✅ Visualización clara para cocina

---

## 🔑 Puntos Clave de Implementación

### ✅ DO's (Hacer)

1. **Siempre usar `quantity: 1`** para cada combo
2. **Generar `cartItemId` único** para cada combo agregado
3. **Resetear `selections`** al abrir el modal
4. **Validar selecciones completas** antes de agregar al carrito
5. **Mostrar `1x` en cada item** del combo (no multiplicar por quantity)
6. **Tratar cada combo como item independiente** en el carrito
7. **Preservar estructura de `fixed_items` y `selections`** en todo el flujo

### ❌ DON'Ts (No hacer)

1. **NO buscar combos existentes** para incrementar cantidad
2. **NO multiplicar `maxSelections` por quantity**
3. **NO reutilizar `cartItemId`** entre combos
4. **NO mostrar `item.quantity` en los sub-items** del combo
5. **NO agrupar combos** con las mismas selecciones
6. **NO permitir agregar combo** sin completar todas las selecciones

---

## 🧪 Casos de Prueba

### Test 1: Agregar Combo Simple
1. Abrir "Combo Doble Mixta"
2. Seleccionar 1 bebida (Coca-Cola)
3. Agregar al carrito
4. **Esperado:** 1 item en carrito con quantity=1

### Test 2: Agregar Mismo Combo 2 Veces
1. Agregar "Combo Dupla" con 2 Coca-Colas
2. Agregar "Combo Dupla" con 2 Sprites
3. **Esperado:** 2 items separados en carrito, cada uno con quantity=1

### Test 3: Combo con Selecciones Múltiples
1. Abrir "Combo Dupla"
2. Seleccionar 2 bebidas (1 Coca-Cola + 1 Sprite)
3. Agregar al carrito
4. **Esperado:** 1 item mostrando ambas bebidas como "1x Coca-Cola" y "1x Sprite"

### Test 4: Validación de Selecciones
1. Abrir "Combo Dupla" (requiere 2 bebidas)
2. Seleccionar solo 1 bebida
3. Intentar agregar
4. **Esperado:** Alert "Por favor completa las selecciones: Bebidas (1/2)"

### Test 5: Mensaje WhatsApp
1. Agregar 2 combos diferentes al carrito
2. Proceder a checkout
3. Generar mensaje WhatsApp
4. **Esperado:** Mensaje con cada combo listado por separado con sus items

### Test 6: Pantalla Pending
1. Completar orden con combos
2. Ir a transfer-pending o cash-pending
3. **Esperado:** Cada combo mostrado con "Incluye: 1x item1, 1x item2..."

---

## 📁 Archivos Modificados

### Frontend
- ✅ `/src/components/MenuApp.jsx` - Carrito y mensaje WhatsApp
- ✅ `/src/components/modals/ComboModal.jsx` - Modal de personalización
- ✅ `/src/pages/transfer-pending.astro` - Pantalla pending transferencia
- ✅ `/src/pages/cash-pending.astro` - Pantalla pending efectivo
- ✅ `/src/pages/card-pending.astro` - Pantalla pending tarjeta
- ✅ `/src/pages/pedidosya-pending.astro` - Pantalla pending PedidosYA
- ✅ `/src/pages/comandas/index.astro` - Sistema de comandas

### Backend
- ⏳ `/api/get_combos.php` - Obtener datos de combos
- ⏳ `/api/create_order.php` - Crear orden con combos
- ⏳ `/api/get_transfer_order.php` - Obtener orden para pending
- ⏳ `/api/process_sale_inventory.php` - Descuento de inventario

---

## 🚀 Estado de Implementación

### ✅ Completado (Frontend + Backend)
1. ✅ Visualización correcta en carrito
2. ✅ Mensaje de WhatsApp estructurado
3. ✅ Pantallas pending (transfer, cash, card, pedidosya)
4. ✅ Visualización en comandas (kitchen display)
5. ✅ Modal de personalización con validaciones
6. ✅ Reseteo de selecciones entre aperturas
7. ✅ Items separados en carrito (no agrupados)
8. ✅ **Descuento de inventario para combos** (Backend existente)
9. ✅ **Cálculo de stock basado en ingredientes** (Backend existente)
10. ✅ **Sistema de recetas y costos** (Backend existente)

### ⏳ Test realizados
1. ⏳ Testing end-to-end completo
2. ⏳ Validación de integración frontend-backend
3. ⏳ Documentación de flujo completo

---

## archivos y db en caso de debug

Para sistema de combos, revisar:
1. Console logs en ComboModal (carga de datos)
2. Estructura de `cart` en localStorage
3. Response de `/api/get_combos.php`
4. Estructura de `order_items` en base de datos

---

**Última actualización:** 2025
**Versión:** 1.0
**Estado:** En produccion.
