# Sistema de Combos - Estado de Implementación

## 📋 Resumen

Se ha implementado el sistema de combos completo basado en la especificación técnica de la app hermana "caja". El sistema permite crear, personalizar y gestionar combos con múltiples productos y selecciones (bebidas, salsas, etc.).

---

## ✅ Implementación Completada

### 1. **Backend (Ya existente)**
- ✅ `api/get_combos.php` - Obtiene datos de combos con items fijos y grupos de selección
- ✅ Tablas de base de datos:
  - `combos` - Tabla principal de combos
  - `combo_items` - Productos fijos incluidos en el combo
  - `combo_selections` - Opciones seleccionables (bebidas, salsas, etc.)

### 2. **Frontend - ComboModal.jsx**
- ✅ **Reseteo de selecciones**: Al abrir el modal, las selecciones se resetean automáticamente
- ✅ **Validación completa**: No permite agregar al carrito sin completar todas las selecciones requeridas
- ✅ **Selecciones múltiples**: Soporta botones +/- para seleccionar múltiples items del mismo tipo
- ✅ **Selecciones únicas**: Soporta radio buttons para selecciones de 1 item
- ✅ **Visualización clara**: Muestra items fijos y opciones seleccionables con imágenes

### 3. **Frontend - MenuApp.jsx**
- ✅ **Items independientes**: Cada combo agregado es un item separado en el carrito
- ✅ **CartItemId único**: Cada combo tiene un `cartItemId` único generado con timestamp
- ✅ **No agrupación**: Los combos NO se agrupan aunque tengan las mismas selecciones
- ✅ **Quantity siempre 1**: Cada combo tiene quantity=1 (para agregar más, se crea otro item)

### 4. **Visualización en Carrito**
- ✅ **Detección de combos**: Identifica combos por `type === 'combo'`, `category_name === 'Combos'` o presencia de `selections`
- ✅ **Fixed items**: Muestra productos fijos incluidos en el combo
- ✅ **Selections**: Muestra selecciones personalizadas (bebidas, salsas, etc.)
- ✅ **Formato correcto**: Muestra "1x Producto" para cada item del combo

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

### 1. Usuario selecciona un combo
```javascript
// En MenuApp.jsx
onClick={() => setComboModalProduct(product)}
```

### 2. Se abre ComboModal
```javascript
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

### 3. Usuario personaliza el combo
- Selecciona bebidas (ej: 2x Coca-Cola)
- Selecciona salsas (ej: 1x Mayo)
- Sistema valida que todas las selecciones estén completas

### 4. Se agrega al carrito
- Cada combo es un item independiente
- No se buscan combos existentes para incrementar cantidad
- Cada combo tiene su propio `cartItemId` único

---

## 📊 Visualización en Carrito

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

### Test 5: Reseteo de Selecciones
1. Abrir "Combo Dupla"
2. Seleccionar 2 Coca-Colas
3. Cerrar modal sin agregar
4. Abrir "Combo Dupla" nuevamente
5. **Esperado:** Selecciones vacías (reseteo exitoso)

---

## 📁 Archivos Modificados

### Frontend
- ✅ `/src/components/modals/ComboModal.jsx` - Modal de personalización con validaciones
- ✅ `/src/components/MenuApp.jsx` - Manejo de combos en carrito

### Backend (Ya existente)
- ✅ `/api/get_combos.php` - Obtener datos de combos
- ✅ Base de datos con tablas `combos`, `combo_items`, `combo_selections`

---

## 🚀 Próximos Pasos

### Pendiente de Implementación

1. **Mensaje de WhatsApp**: Actualizar formato para incluir detalles de combos
2. **Pantallas Pending**: Actualizar visualización en transfer-pending, cash-pending, etc.
3. **Sistema de Comandas**: Integrar visualización de combos en comandas
4. **Descuento de Inventario**: Implementar descuento de ingredientes y productos seleccionados

### Archivos a Modificar

1. `src/pages/transfer-pending.astro` - Pantalla pending transferencia
2. `src/pages/cash-pending.astro` - Pantalla pending efectivo
3. `src/pages/card-pending.astro` - Pantalla pending tarjeta
4. `src/pages/comandas/index.astro` - Sistema de comandas
5. `api/process_sale_inventory.php` - Descuento de inventario

---

## 📞 Soporte

Para dudas o problemas con el sistema de combos, revisar:
1. Console logs en ComboModal (carga de datos)
2. Estructura de `cart` en localStorage
3. Response de `/api/get_combos.php`
4. Estructura de `order_items` en base de datos

---

**Última actualización:** 2024
**Versión:** 1.0
**Estado:** ✅ Implementación Frontend Completa
