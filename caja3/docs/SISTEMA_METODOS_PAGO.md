# 💳 Sistema de Métodos de Pago - La Ruta 11

## 📋 Resumen

Este documento explica cómo funciona el sistema de selección de métodos de pago en la aplicación de caja de La Ruta 11, incluyendo los archivos involucrados, el flujo de datos y el código utilizado.

---

## 🎯 Métodos de Pago Disponibles

El sistema soporta **4 métodos de pago**:

1. **💵 Efectivo (Cash)** - Pago en efectivo con cálculo de vuelto
2. **💳 Tarjeta (Card)** - Pago con tarjeta en POS físico
3. **🏦 Transferencia (Transfer)** - Pago por transferencia bancaria
4. **🛵 PedidosYA** - Pago a través de la plataforma PedidosYA

---

## 📁 Archivos Principales

### **Frontend**

| Archivo | Descripción |
|---------|-------------|
| `src/components/MenuApp.jsx` | Componente principal de la app de caja con checkout integrado |
| `src/components/CheckoutApp.jsx` | Componente dedicado de checkout (alternativo) |
| `src/pages/cash-pending.astro` | Página de confirmación para pagos en efectivo |
| `src/pages/card-pending.astro` | Página de confirmación para pagos con tarjeta |
| `src/pages/transfer-pending.astro` | Página de confirmación para transferencias |
| `src/pages/pedidosya-pending.astro` | Página de confirmación para PedidosYA |

### **Backend (APIs)**

| Archivo | Descripción |
|---------|-------------|
| `api/create_order.php` | API principal para crear órdenes con cualquier método de pago |
| `api/tuu/save_order_with_items.php` | Guarda órdenes en la tabla `tuu_orders` |
| `api/tuu/update_order_status.php` | Actualiza el estado de las órdenes |

---

## 🔄 Flujo Completo del Sistema

### **1. Usuario en Checkout**

```
MenuApp.jsx (showCheckout = true)
    ↓
Usuario completa datos:
  - Nombre
  - Teléfono
  - Tipo de entrega (Delivery/Retiro)
  - Dirección (si es delivery)
  - Notas adicionales
    ↓
Usuario selecciona método de pago
```

### **2. Selección de Método de Pago**

El usuario ve 4 botones en el checkout:

```jsx
<div className="grid grid-cols-4 gap-2 mb-3">
  <button onClick={() => handleCashPayment()}>💵 Efectivo</button>
  <button onClick={() => handleCardPayment()}>💳 Tarjeta</button>
  <button onClick={() => handleTransferPayment()}>🏦 Transfer.</button>
  <button onClick={() => handlePedidosYAPayment()}>🛵 PedidosYA</button>
</div>
```

**Ubicación en código:** `MenuApp.jsx` líneas ~2850-2880

---

## 💵 Método 1: EFECTIVO (Cash)

### **Flujo**

```
Usuario hace click en "Efectivo"
    ↓
setShowCashModal(true) - Abre modal de efectivo
    ↓
Modal muestra:
  - Total a pagar
  - Input para monto con el que paga
  - Botones rápidos: Monto Exacto, $5.000, $10.000, $20.000
    ↓
Usuario ingresa monto y hace click "Continuar"
    ↓
handleContinueCash() valida:
  - Monto no vacío
  - Monto >= Total
    ↓
Si monto > total:
  - Muestra pantalla de confirmación de vuelto
  - Usuario confirma
    ↓
processCashOrder() ejecuta:
  1. Agrega nota con monto y vuelto al pedido
  2. Llama a /api/create_order.php con payment_method: 'cash'
  3. Redirige a /cash-pending?order=ORDER_ID
```

### **Código Clave**

```javascript
// MenuApp.jsx - Línea ~2400
const handleCashPayment = () => {
  if (!customerInfo.name) {
    alert('Por favor completa tu nombre');
    return;
  }
  setShowCashModal(true);
  setCashAmount('');
  setCashStep('input');
};

// Validación y cálculo de vuelto
const handleContinueCash = () => {
  const numericAmount = parseInt(cashAmount.replace(/\./g, ''));
  
  if (!numericAmount || numericAmount === 0) {
    alert('⚠️ Debe ingresar un monto o seleccionar "Monto Exacto"');
    return;
  }
  
  if (numericAmount < finalTotal) {
    const faltante = finalTotal - numericAmount;
    alert(`⚠️ Monto insuficiente. Faltan $${faltante.toLocaleString('es-CL')}`);
    return;
  }
  
  if (numericAmount === finalTotal) {
    processCashOrder();
  } else {
    setCashStep('confirm'); // Mostrar pantalla de confirmación de vuelto
  }
};

// Procesar orden con efectivo
const processCashOrder = async () => {
  setIsProcessing(true);
  try {
    const numericAmount = parseInt(cashAmount.replace(/\./g, ''));
    const vuelto = numericAmount - finalTotal;
    
    // Agregar mensaje estructurado a las notas
    const paymentNote = `💵 EFECTIVO | Paga con: $${numericAmount.toLocaleString('es-CL')} | Vuelto: $${vuelto.toLocaleString('es-CL')}`;
    const finalNotes = customerInfo.customerNotes 
      ? `${customerInfo.customerNotes}\n\n${paymentNote}` 
      : paymentNote;
    
    const orderData = {
      amount: finalTotal,
      customer_name: customerInfo.name,
      customer_phone: customerInfo.phone,
      customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
      user_id: user?.id || null,
      cart_items: cart,
      delivery_fee: deliveryFee,
      customer_notes: finalNotes,
      delivery_type: customerInfo.deliveryType,
      delivery_address: customerInfo.address || null,
      payment_method: 'cash'
    };
    
    const response = await fetch('/api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    });
    
    const result = await response.json();
    if (result.success) {
      localStorage.removeItem('ruta11_cart');
      localStorage.removeItem('ruta11_cart_total');
      window.location.href = '/cash-pending?order=' + result.order_id;
    }
  } catch (error) {
    setIsProcessing(false);
    alert('Error al procesar el pedido: ' + error.message);
  }
};
```

### **Modal de Efectivo**

El modal tiene 2 pasos:

**Paso 1: Input de monto**
```jsx
<input
  type="text"
  value={cashAmount}
  onChange={handleCashInput}
  placeholder="0"
/>
<button onClick={setExactAmount}>Monto Exacto</button>
<button onClick={() => setQuickAmount(5000)}>$5.000</button>
```

**Paso 2: Confirmación de vuelto**
```jsx
<div>
  <p>Total: ${cartTotal}</p>
  <p>Paga con: ${cashAmount}</p>
  <p>Vuelto a entregar: ${vuelto}</p>
</div>
<button onClick={processCashOrder}>✓ Confirmar Vuelto</button>
```

---

## 💳 Método 2: TARJETA (Card)

### **Flujo**

```
Usuario hace click en "Tarjeta"
    ↓
Confirmación: "Has seleccionado TARJETA como método de pago. ¿Continuar?"
    ↓
handleCardPayment() ejecuta:
  1. Valida datos del cliente
  2. Llama a /api/create_order.php con payment_method: 'card'
  3. Redirige a /card-pending?order=ORDER_ID
```

### **Código Clave**

```javascript
// MenuApp.jsx - Línea ~2650
const handleCardPayment = async () => {
  if (!customerInfo.name || (customerInfo.deliveryType === 'delivery' && !customerInfo.address)) {
    return;
  }
  
  const confirmed = window.confirm('Has seleccionado TARJETA como método de pago. ¿Continuar?');
  if (!confirmed) return;
  
  try {
    const orderData = {
      amount: finalTotal,
      customer_name: customerInfo.name,
      customer_phone: customerInfo.phone,
      customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
      user_id: user?.id || null,
      cart_items: cart,
      delivery_fee: deliveryFee,
      customer_notes: customerInfo.customerNotes || null,
      delivery_type: customerInfo.deliveryType,
      delivery_address: customerInfo.address || null,
      payment_method: 'card'
    };
    
    const response = await fetch('/api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    });
    
    const result = await response.json();
    if (result.success) {
      localStorage.removeItem('ruta11_cart');
      localStorage.removeItem('ruta11_cart_total');
      window.location.href = '/card-pending?order=' + result.order_id;
    }
  } catch (error) {
    console.error('Error card:', error);
  }
};
```

---

## 🏦 Método 3: TRANSFERENCIA (Transfer)

### **Flujo**

```
Usuario hace click en "Transferencia"
    ↓
Confirmación: "Has seleccionado TRANSFERENCIA como método de pago. ¿Continuar?"
    ↓
handleTransferPayment() ejecuta:
  1. Valida datos del cliente
  2. Llama a /api/create_order.php con payment_method: 'transfer'
  3. Genera mensaje de WhatsApp con detalles del pedido
  4. Abre WhatsApp en nueva pestaña
  5. Redirige a /transfer-pending?order=ORDER_ID
```

### **Código Clave**

```javascript
// MenuApp.jsx - Línea ~2700
const handleTransferPayment = async () => {
  const confirmed = window.confirm('Has seleccionado TRANSFERENCIA como método de pago. ¿Continuar?');
  if (!confirmed) return;
  
  try {
    const orderData = {
      amount: finalTotal,
      customer_name: customerInfo.name,
      customer_phone: customerInfo.phone,
      customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
      user_id: user?.id || null,
      cart_items: cart,
      delivery_fee: deliveryFee,
      customer_notes: customerInfo.customerNotes || null,
      delivery_type: customerInfo.deliveryType,
      delivery_address: customerInfo.address || null,
      payment_method: 'transfer'
    };
    
    const response = await fetch('/api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    });
    
    const result = await response.json();
    if (result.success) {
      localStorage.removeItem('ruta11_cart');
      localStorage.removeItem('ruta11_cart_total');
      window.location.href = '/transfer-pending?order=' + result.order_id;
    }
  } catch (error) {
    console.error('Error transfer:', error);
  }
};
```

---

## 🛵 Método 4: PEDIDOSYA

### **Flujo**

```
Usuario hace click en "PedidosYA"
    ↓
Confirmación: "Has seleccionado PEDIDOSYA como método de pago. ¿Continuar?"
    ↓
handlePedidosYAPayment() ejecuta:
  1. Valida datos del cliente
  2. Llama a /api/create_order.php con payment_method: 'pedidosya'
  3. Redirige a /pedidosya-pending?order=ORDER_ID
```

### **Código Clave**

```javascript
// MenuApp.jsx - Línea ~2750
const handlePedidosYAPayment = async () => {
  const confirmed = window.confirm('Has seleccionado PEDIDOSYA como método de pago. ¿Continuar?');
  if (!confirmed) return;
  
  try {
    const orderData = {
      amount: finalTotal,
      customer_name: customerInfo.name,
      customer_phone: customerInfo.phone,
      customer_email: customerInfo.email || `${customerInfo.phone}@ruta11.cl`,
      user_id: user?.id || null,
      cart_items: cart,
      delivery_fee: deliveryFee,
      customer_notes: customerInfo.customerNotes || null,
      delivery_type: customerInfo.deliveryType,
      delivery_address: customerInfo.address || null,
      payment_method: 'pedidosya'
    };
    
    const response = await fetch('/api/create_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(orderData)
    });
    
    const result = await response.json();
    if (result.success) {
      localStorage.removeItem('ruta11_cart');
      localStorage.removeItem('ruta11_cart_total');
      window.location.href = '/pedidosya-pending?order=' + result.order_id;
    }
  } catch (error) {
    console.error('Error pedidosya:', error);
  }
};
```

---

## 🔧 API Backend: create_order.php

### **Estructura de Datos Enviada**

```json
{
  "amount": 15000,
  "customer_name": "Juan Pérez",
  "customer_phone": "+56912345678",
  "customer_email": "juan@example.com",
  "user_id": 123,
  "cart_items": [
    {
      "id": 1,
      "name": "Hamburguesa Clásica",
      "price": 5000,
      "quantity": 2,
      "customizations": [
        {
          "name": "Extra Queso",
          "price": 500,
          "quantity": 1
        }
      ]
    }
  ],
  "delivery_fee": 2000,
  "customer_notes": "Sin cebolla",
  "delivery_type": "delivery",
  "delivery_address": "Av. Principal 123",
  "payment_method": "cash"
}
```

### **Respuesta de la API**

```json
{
  "success": true,
  "order_id": "R11-1234567890",
  "message": "Orden creada exitosamente"
}
```

---

## 📊 Estados de Pago

Cada método de pago tiene estados específicos:

| Método | Estado Inicial | Estados Posibles |
|--------|---------------|------------------|
| **Efectivo** | `pending` | `pending` → `paid` → `preparing` → `ready` → `delivered` |
| **Tarjeta** | `pending` | `pending` → `paid` → `preparing` → `ready` → `delivered` |
| **Transferencia** | `pending` | `pending` → `paid` → `preparing` → `ready` → `delivered` |
| **PedidosYA** | `pending` | `pending` → `paid` → `preparing` → `ready` → `delivered` |

---

## 🎨 UI/UX de Métodos de Pago

### **Botones en Checkout**

```jsx
<div className="grid grid-cols-4 gap-2 mb-3">
  {/* EFECTIVO */}
  <button
    onClick={() => handleCashPayment()}
    disabled={!customerInfo.name}
    className="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-1 rounded-lg"
  >
    <Banknote size={16} />
    <span>Efectivo</span>
  </button>

  {/* TARJETA */}
  <button
    onClick={() => handleCardPayment()}
    disabled={!customerInfo.name}
    className="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-1 rounded-lg"
  >
    <CreditCard size={16} />
    <span>Tarjeta</span>
  </button>

  {/* TRANSFERENCIA */}
  <button
    onClick={() => handleTransferPayment()}
    disabled={!customerInfo.name}
    className="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-1 rounded-lg"
  >
    <Smartphone size={16} />
    <span>Transfer.</span>
  </button>

  {/* PEDIDOSYA */}
  <button
    onClick={() => handlePedidosYAPayment()}
    disabled={!customerInfo.name}
    className="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-1 rounded-lg"
  >
    <Bike size={16} />
    <span>PedidosYA</span>
  </button>
</div>
```

### **Colores por Método**

- **Efectivo**: Verde (`bg-green-500`)
- **Tarjeta**: Morado (`bg-purple-500`)
- **Transferencia**: Azul (`bg-blue-500`)
- **PedidosYA**: Naranja (`bg-orange-500`)

---

## 🔐 Validaciones

### **Validaciones Comunes (Todos los Métodos)**

```javascript
// Validar nombre del cliente
if (!customerInfo.name) {
  alert('Por favor completa tu nombre');
  return;
}

// Validar dirección si es delivery
if (customerInfo.deliveryType === 'delivery' && !customerInfo.address) {
  alert('Por favor ingresa la dirección de entrega');
  return;
}
```

### **Validaciones Específicas de Efectivo**

```javascript
// Validar monto ingresado
if (!numericAmount || numericAmount === 0) {
  alert('⚠️ Debe ingresar un monto o seleccionar "Monto Exacto"');
  return;
}

// Validar monto suficiente
if (numericAmount < finalTotal) {
  const faltante = finalTotal - numericAmount;
  alert(`⚠️ Monto insuficiente. Faltan $${faltante.toLocaleString('es-CL')}`);
  return;
}
```

---

## 📱 Páginas de Confirmación

Cada método de pago redirige a una página específica:

### **1. cash-pending.astro**
- Muestra orden pendiente de pago en efectivo
- Botón para confirmar pago recibido
- Muestra monto y vuelto calculado

### **2. card-pending.astro**
- Muestra orden pendiente de pago con tarjeta
- Botón para confirmar pago procesado en POS
- Instrucciones para pasar tarjeta

### **3. transfer-pending.astro**
- Muestra orden pendiente de transferencia
- Datos bancarios para transferir
- Botón para confirmar transferencia recibida

### **4. pedidosya-pending.astro**
- Muestra orden pendiente de PedidosYA
- Instrucciones para el repartidor
- Botón para confirmar pago recibido

---

## 🔄 Actualización de Estados

Para actualizar el estado de un pago:

```javascript
// Llamar a API de actualización
const response = await fetch('/api/tuu/update_order_status.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    order_number: 'R11-1234567890',
    status: 'paid'
  })
});
```

---

## 📝 Notas Importantes

1. **Efectivo es el único método con modal interactivo** - Los demás solo muestran confirmación
2. **Transferencia abre WhatsApp automáticamente** - Para facilitar comunicación
3. **Todos los métodos guardan en la misma tabla** - `tuu_orders`
4. **El campo `payment_method` diferencia el tipo** - `cash`, `card`, `transfer`, `pedidosya`
5. **Las notas del pedido incluyen información del pago** - Especialmente en efectivo (monto y vuelto)

---

## 🚀 Mejoras Futuras

- [ ] Integración con Webpay para pagos online
- [ ] QR de transferencia automático
- [ ] Confirmación automática de transferencias vía API bancaria
- [ ] Integración directa con API de PedidosYA
- [ ] Historial de métodos de pago preferidos por cliente
- [ ] Reportes por método de pago

---

**Última actualización:** Enero 2025
