# Sistema de Combos - Próximos Pasos

## 📋 Resumen

Este documento detalla los pasos pendientes para completar la implementación del sistema de combos en tu app "ruta11app", basado en la especificación técnica de tu app hermana "caja".

---

## ✅ Ya Implementado

- ✅ ComboModal con validaciones y reseteo de selecciones
- ✅ Manejo de combos en carrito (items independientes con cartItemId único)
- ✅ Visualización de combos en CartModal
- ✅ API backend para obtener datos de combos

---

## 🚀 Pendiente de Implementación

### 1. **Mensaje de WhatsApp con Combos**

**Archivo:** `src/components/MenuApp.jsx`

**Ubicación:** Dentro del botón "Terminar Pedido (WhatsApp)" en el modal de checkout

**Código a actualizar:**

```javascript
// ANTES (línea ~1850)
cart.forEach((item, index) => {
  message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\\n`;
});

// DESPUÉS
cart.forEach((item, index) => {
  const isCombo = item.type === 'combo' || item.category_name === 'Combos' || item.selections;
  message += `${index + 1}. ${item.name} x${item.quantity} - $${(item.price * item.quantity).toLocaleString('es-CL')}\\n`;
  
  if (isCombo && (item.fixed_items || item.selections)) {
    message += `   Incluye:\\n`;
    
    // Fixed items
    if (item.fixed_items) {
      item.fixed_items.forEach(fixedItem => {
        message += `   • ${fixedItem.quantity || 1}x ${fixedItem.product_name || fixedItem.name}\\n`;
      });
    }
    
    // Selections
    if (item.selections) {
      Object.entries(item.selections).forEach(([group, selection]) => {
        if (Array.isArray(selection)) {
          selection.forEach(sel => {
            message += `   • 1x ${sel.name}\\n`;
          });
        } else if (selection) {
          message += `   • 1x ${selection.name}\\n`;
        }
      });
    }
  }
});
```

**Resultado esperado:**
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

Subtotal: $16.770
Delivery: $2.000
TOTAL: $18.770
```

---

### 2. **Pantallas Pending (transfer-pending, cash-pending, etc.)**

**Archivos a modificar:**
- `src/pages/transfer-pending.astro`
- `src/pages/cash-pending.astro`
- `src/pages/card-pending.astro`
- `src/pages/pedidosya-pending.astro` (si existe)

**Función a actualizar:** `displayOrderItems(cart, total, deliveryFee)`

**Código a agregar:**

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

**Resultado esperado:**
```
🛒 Tu Pedido

Combo Dupla
Cantidad: 1
Incluye: 1x Hamburguesa Clásica, 1x Ave Italiana, 1x Papas Fritas Individual, 1x Coca-Cola Lata 350ml, 1x Coca-Cola Lata 350ml
$16.770

Subtotal: $16.770
Delivery: $2.000
Total: $18.770
```

---

### 3. **Sistema de Comandas (Kitchen Display)**

**Archivo:** `src/pages/comandas/index.astro`

**Código a agregar en la renderización de items:**

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

**Visualización esperada:**
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
└─────────────────────────────────────┘
```

---

### 4. **Descuento de Inventario**

**Archivo:** `api/process_sale_inventory.php`

**Lógica a implementar:**

```php
// Para cada item del pedido
foreach ($order_items as $item) {
    // Detectar si es combo
    $isCombo = isset($item['item_type']) && $item['item_type'] === 'combo';
    
    if ($isCombo && isset($item['combo_data'])) {
        $comboData = is_string($item['combo_data']) 
            ? json_decode($item['combo_data'], true) 
            : $item['combo_data'];
        
        // Descontar fixed items
        if (isset($comboData['fixed_items'])) {
            foreach ($comboData['fixed_items'] as $fixedItem) {
                // Descontar ingredientes según receta del producto
                descontarIngredientesDeReceta($fixedItem['product_id'], $item['quantity'] * $fixedItem['quantity']);
            }
        }
        
        // Descontar selections (bebidas, salsas, etc.)
        if (isset($comboData['selections'])) {
            foreach ($comboData['selections'] as $group => $selection) {
                if (is_array($selection)) {
                    foreach ($selection as $sel) {
                        // Descontar producto directamente
                        descontarProducto($sel['id'], $item['quantity']);
                    }
                } else if (is_object($selection)) {
                    descontarProducto($selection['id'], $item['quantity']);
                }
            }
        }
    } else {
        // Producto normal
        descontarIngredientesDeReceta($item['product_id'], $item['quantity']);
    }
}
```

---

### 5. **Guardar Combos en Órdenes**

**Archivo:** `api/create_order.php` o similar

**Estructura de order_items en DB:**

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

---

## 📊 Prioridad de Implementación

### Alta Prioridad
1. ✅ **Mensaje de WhatsApp** - Los clientes necesitan ver los detalles del combo
2. ✅ **Pantallas Pending** - Visualización correcta en confirmación de pago

### Media Prioridad
3. ⏳ **Sistema de Comandas** - Cocina necesita ver qué preparar
4. ⏳ **Guardar Combos en Órdenes** - Persistencia de datos

### Baja Prioridad
5. ⏳ **Descuento de Inventario** - Puede implementarse después

---

## 🧪 Testing Recomendado

### Test End-to-End
1. Agregar combo al carrito
2. Personalizar selecciones
3. Proceder a checkout
4. Enviar por WhatsApp
5. Verificar mensaje recibido
6. Completar pago
7. Verificar pantalla pending
8. Verificar comandas (si aplica)

### Test de Validación
1. Intentar agregar combo sin completar selecciones → Debe mostrar error
2. Agregar mismo combo 2 veces con diferentes selecciones → Deben ser 2 items separados
3. Cerrar y reabrir modal → Selecciones deben resetearse

---

## 📁 Archivos a Modificar (Resumen)

### Frontend
1. `src/components/MenuApp.jsx` - Mensaje WhatsApp
2. `src/pages/transfer-pending.astro` - Pantalla pending
3. `src/pages/cash-pending.astro` - Pantalla pending
4. `src/pages/card-pending.astro` - Pantalla pending
5. `src/pages/comandas/index.astro` - Sistema de comandas

### Backend
6. `api/create_order.php` - Guardar combos en órdenes
7. `api/process_sale_inventory.php` - Descuento de inventario

---

## 📞 Soporte

Si tienes dudas durante la implementación:
1. Revisa `COMBOS_TECHNICAL_SPEC.md` de la app hermana "caja"
2. Revisa `COMBOS_IMPLEMENTATION_STATUS.md` para ver lo ya implementado
3. Consulta los console logs en el navegador
4. Verifica la estructura de datos en localStorage

---

**Última actualización:** 2024
**Versión:** 1.0
**Estado:** 📝 Guía de Implementación
