# 📄 Páginas PENDING - Detalle Completo

## 🎯 Resumen

Cada método de pago redirige a una página específica que muestra el estado del pedido y permite acciones adicionales.

---

## 💵 1. CASH-PENDING.ASTRO

### **URL:** `/cash-pending?order=ORDER_ID`

### **Propósito:**
Confirmación de pago en efectivo recibido y pedido enviado a cocina.

### **Diseño Visual:**
- ✅ **Icono:** Check verde en círculo verde claro
- ✅ **Título:** "¡Pedido Registrado!"
- ✅ **Subtítulo:** "Pago en efectivo recibido"

### **Secciones:**

#### **1. Estado del Pago (Verde)**
```
💵 Pago en Efectivo
✅ Pago recibido y confirmado
✅ Pedido enviado a cocina
Monto pagado: $15.000
```

#### **2. Datos del Cliente (Verde claro)**
```
👤 Datos del Cliente
Cliente: Juan Pérez
Teléfono: +56912345678
Tipo de entrega: Delivery
Dirección: Av. Principal 123
```

#### **3. Detalle del Pedido**
```
🛒 Tu Pedido

1. Hamburguesa Clásica
   Cantidad: 2
   Incluye: 1x Extra Queso (+$500)
   $11.000

Subtotal: $10.000
Delivery: $2.000
Total: $12.000
```

#### **4. Botones de Acción**
- 🟢 **"Notificar por WhatsApp"** - Abre WhatsApp con mensaje estructurado
- ⚫ **"Volver a Caja"** - Redirige a https://caja.laruta11.cl

### **Funcionalidad JavaScript:**
```javascript
// Carga datos del pedido desde API
loadOrderData() → fetch('/api/get_transfer_order.php?order_id=...')

// Muestra items con customizations y combos
displayOrderItems(cart, total, deliveryFee)

// Muestra datos del cliente
displayCustomerInfo(order)

// Genera mensaje WhatsApp estructurado
updateWhatsAppLink(order)
```

### **Mensaje WhatsApp Generado:**
```
*PEDIDO REGISTRADO - LA RUTA 11*

*Pedido:* T11-1234567890
*Cliente:* Juan Pérez
*Estado:* Pagado y en cocina
*Total:* $12.000
*Método:* Pago en Efectivo

*PRODUCTOS:*
1. Hamburguesa Clásica x2
   Incluye: 1x Extra Queso (+$500)
   Precio: $11.000

*Subtotal:* $10.000
*Delivery:* $2.000
*Total:* $12.000

*Pago en Efectivo*

Pedido realizado desde la app web.
```

---

## 💳 2. CARD-PENDING.ASTRO

### **URL:** `/card-pending?order=ORDER_ID`

### **Propósito:**
Pedido pendiente de confirmación de pago con tarjeta en POS físico.

### **Diseño Visual:**
- ⏳ **Icono:** Tarjeta morada en círculo morado claro
- ⏳ **Título:** "Pago Pendiente"
- ⏳ **Subtítulo:** "Tu pedido está esperando confirmación de pago con tarjeta"

### **Secciones:**

#### **1. Estado del Pago (Morado)**
```
💳 Pago con Tarjeta
Por favor realiza el pago con tarjeta de crédito o débito en el local
Monto a pagar: $15.000
```

#### **2. Datos del Cliente (Verde claro)**
```
👤 Datos del Cliente
Cliente: María González
Teléfono: +56987654321
Tipo de entrega: Retiro
```

#### **3. Detalle del Pedido**
```
🛒 Tu Pedido

1. Completo Italiano
   Cantidad: 3
   $9.000

Subtotal: $9.000
Total: $9.000
```

#### **4. Botones de Acción**
- 🟢 **"Continuar en WhatsApp"** - Abre WhatsApp con mensaje estructurado
- ⚫ **"Volver a Caja"** - Redirige a https://caja.laruta11.cl

### **Funcionalidad JavaScript:**
```javascript
// Genera mensaje WhatsApp desde DOM
generateWhatsAppMessage()

// Actualiza enlace WhatsApp
updateWhatsAppLink()

// Carga datos del pedido
loadOrderData()

// Muestra items con soporte para combos
displayOrderItems(cart, total, deliveryFee)

// Muestra datos del cliente
displayCustomerInfo(order)
```

### **Mensaje WhatsApp Generado:**
```
*PEDIDO PENDIENTE - LA RUTA 11*

*Pedido:* T11-1234567890
*Cliente:* María González
*Teléfono:* +56987654321
*Tipo de entrega:* Retiro
*Estado:* Pendiente de pago con tarjeta
*Total:* $9.000
*Método:* Pago con tarjeta

*PRODUCTOS:*
1. Completo Italiano
   Cantidad: 3
   Precio: $9.000

*Subtotal:* $9.000
*Total:* $9.000

*Pago con Tarjeta*

Pedido realizado desde la app web.
Por favor confirmar pago con tarjeta en el local.
```

---

## 🏦 3. TRANSFER-PENDING.ASTRO

### **URL:** `/transfer-pending?order=ORDER_ID`

### **Propósito:**
Pedido pendiente de confirmación de transferencia bancaria.

### **Diseño Visual:**
- ⏳ **Icono:** Reloj ámbar en círculo ámbar claro
- ⏳ **Título:** "Pago Pendiente"
- ⏳ **Subtítulo:** "Tu pedido está esperando confirmación de transferencia"

### **Secciones:**

#### **1. Datos Bancarios (Azul)**
```
💳 Datos para Transferencia
Titular: La Ruta once Spa
RUT: 78.194.739-3
Banco: Banco BCI
Cuenta Corriente: 97618110
Email: SABORESDELARUTA11@GMAIL.COM
```

#### **2. Datos del Cliente (Verde claro)**
```
👤 Datos del Cliente
Cliente: Pedro Ramírez
Teléfono: +56911223344
Tipo de entrega: Delivery
Dirección: Calle Falsa 123
```

#### **3. Detalle del Pedido**
```
🛒 Tu Pedido

1. Combo Hamburguesa
   Cantidad: 1
   Incluye: 1x Papas Medianas, 1x Coca-Cola
   $8.500

Subtotal: $7.000
Delivery: $1.500
Total: $8.500
```

#### **4. Botones de Acción**
- 🟢 **"Continuar en WhatsApp"** - Abre WhatsApp con mensaje estructurado
- ⚫ **"Volver a Caja"** - Redirige a https://caja.laruta11.cl

### **Funcionalidad JavaScript:**
```javascript
// Variable global para datos del pedido
let currentOrderData = null;

// Genera mensaje WhatsApp desde DOM
generateWhatsAppMessage()

// Actualiza enlace WhatsApp
updateWhatsAppLink()

// Carga datos del pedido
loadOrderData()

// Muestra items con soporte completo para combos
displayOrderItems(cart, total, deliveryFee)
  - Soporta customizations
  - Soporta fixed_items (productos fijos del combo)
  - Soporta selections (bebidas seleccionables)

// Muestra datos del cliente
displayCustomerInfo(order)
```

### **Mensaje WhatsApp Generado:**
```
PEDIDO PENDIENTE - LA RUTA 11

Pedido: T11-1234567890
Cliente: Pedro Ramírez
Teléfono: +56911223344
Tipo de entrega: Delivery
Dirección: Calle Falsa 123
Estado: Pendiente de transferencia
Total: $8.500
Método: Transferencia bancaria

PRODUCTOS:
1. Combo Hamburguesa
   Cantidad: 1
   Incluye: Papas Medianas, Coca-Cola
   Precio: $8.500

Subtotal: $7.000
Delivery: $1.500
Total: $8.500

Pago con Transferencia

Pedido realizado desde la app web.
Por favor confirmar recepcion del comprobante de transferencia.
```

---

## 🛵 4. PEDIDOSYA-PENDING.ASTRO

### **URL:** `/pedidosya-pending?order=ORDER_ID`

### **Propósito:**
Confirmación de pedido de PedidosYA registrado y enviado a cocina.

### **Diseño Visual:**
- ✅ **Icono:** Check naranja en círculo naranja claro
- ✅ **Título:** "¡Pedido Registrado!"
- ✅ **Subtítulo:** "Pedido de PedidosYA confirmado"

### **Secciones:**

#### **1. Estado del Pago (Naranja)**
```
🛵 Pedido PedidosYA
✅ Pago procesado por PedidosYA
✅ Pedido enviado a cocina
✅ Registrado en sistema
Monto: $12.000
```

#### **2. Datos del Cliente (Azul claro)**
```
👤 Datos del Cliente
Cliente: Ana Torres
Teléfono: +56955667788
Tipo de entrega: Delivery
Dirección: Av. Libertador 456
```

#### **3. Detalle del Pedido**
```
🛒 Tu Pedido

1. Salchipapas Grande
   Cantidad: 2
   $10.000

Subtotal: $10.000
Delivery: $2.000
Total: $12.000
```

#### **4. Botones de Acción**
- 🟢 **"Notificar por WhatsApp"** - Abre WhatsApp con mensaje estructurado
- ⚫ **"Volver a Caja"** - Redirige a https://caja.laruta11.cl

### **Funcionalidad JavaScript:**
```javascript
// Carga datos del pedido
loadOrderData()

// Muestra items con soporte para combos
displayOrderItems(cart, total, deliveryFee)

// Muestra datos del cliente
displayCustomerInfo(order)

// Genera mensaje WhatsApp con formato especial PedidosYA
updateWhatsAppLink(order)
```

### **Mensaje WhatsApp Generado:**
```
*PEDIDO REGISTRADO - LA RUTA 11*

*Pedido:* T11-1234567890
*Cliente:* Ana Torres
*Teléfono:* +56955667788
*Tipo de entrega:* Delivery
*Dirección:* Av. Libertador 456
*Estado:* Pagado por PedidosYA y en cocina
*Total:* $12.000
*Método:* PedidosYA

*PRODUCTOS:*
1. Salchipapas Grande
   Cantidad: 2
   $10.000

*Subtotal:* $10.000
*Delivery:* $2.000
*Total:* $12.000

*Pago por PedidosYA*

Pedido registrado desde App Caja.
```

---

## 🔄 API Compartida: get_transfer_order.php

Todas las páginas pending usan la misma API para cargar datos:

### **Endpoint:** `/api/get_transfer_order.php?order_id=ORDER_ID`

### **Respuesta:**
```json
{
  "success": true,
  "order": {
    "order_number": "T11-1234567890",
    "customer_name": "Juan Pérez",
    "customer_phone": "+56912345678",
    "customer_email": "juan@example.com",
    "delivery_type": "delivery",
    "delivery_address": "Av. Principal 123",
    "total": 12000,
    "delivery_fee": 2000,
    "payment_method": "cash",
    "items": [
      {
        "id": 1,
        "name": "Hamburguesa Clásica",
        "price": 5000,
        "quantity": 2,
        "customizations": [
          {
            "id": 10,
            "name": "Extra Queso",
            "price": 500,
            "quantity": 1
          }
        ],
        "fixed_items": [],
        "selections": {}
      }
    ]
  }
}
```

---

## 📊 Comparación de Páginas

| Aspecto | Cash | Card | Transfer | PedidosYA |
|---------|------|------|----------|-----------|
| **Estado** | ✅ Confirmado | ⏳ Pendiente | ⏳ Pendiente | ✅ Confirmado |
| **Color** | Verde | Morado | Ámbar | Naranja |
| **Icono** | Check | Tarjeta | Reloj | Check |
| **Datos bancarios** | ❌ | ❌ | ✅ | ❌ |
| **WhatsApp** | Notificar | Continuar | Continuar | Notificar |
| **Mensaje** | Pagado | Pendiente | Pendiente | Pagado |

---

## 🎨 Estilos Compartidos

Todas las páginas usan **Tailwind CSS inline** con clases utility:

```css
.bg-gray-50 - Fondo gris claro
.rounded-lg - Bordes redondeados
.shadow-xl - Sombra grande
.font-sans - Fuente sans-serif
.text-center - Texto centrado
.space-y-3 - Espaciado vertical
```

---

## 🔧 Funciones JavaScript Comunes

### **1. loadOrderData()**
```javascript
async function loadOrderData() {
  const response = await fetch(`/api/get_transfer_order.php?order_id=${orderId}`);
  const data = await response.json();
  
  if (data.success && data.order) {
    displayOrderItems(data.order.items, data.order.total, data.order.delivery_fee);
    displayCustomerInfo(data.order);
    updateWhatsAppLink(data.order);
  }
}
```

### **2. displayOrderItems()**
```javascript
function displayOrderItems(cart, total, deliveryFee) {
  let itemsHtml = '';
  let subtotal = 0;
  
  cart.forEach(item => {
    let itemTotal = item.price * item.quantity;
    
    // Agregar customizations
    if (item.customizations) {
      itemTotal += item.customizations.reduce((sum, c) => 
        sum + (c.price * c.quantity), 0
      );
    }
    
    subtotal += itemTotal;
    
    // Generar HTML del item
    itemsHtml += `<div>...</div>`;
  });
  
  document.getElementById('order-items').innerHTML = itemsHtml;
  document.getElementById('subtotal').textContent = `$${subtotal.toLocaleString('es-CL')}`;
  document.getElementById('total').textContent = `$${total.toLocaleString('es-CL')}`;
}
```

### **3. displayCustomerInfo()**
```javascript
function displayCustomerInfo(order) {
  document.getElementById('customer-name').textContent = order.customer_name;
  document.getElementById('customer-phone').textContent = order.customer_phone;
  document.getElementById('delivery-type').textContent = 
    order.delivery_type === 'delivery' ? 'Delivery' : 'Retiro';
  
  if (order.delivery_type === 'delivery' && order.delivery_address) {
    document.getElementById('delivery-address').textContent = order.delivery_address;
    document.getElementById('delivery-address-row').classList.remove('hidden');
  }
  
  document.getElementById('customer-info').classList.remove('hidden');
}
```

### **4. updateWhatsAppLink()**
```javascript
function updateWhatsAppLink(order) {
  const message = generateWhatsAppMessage();
  document.getElementById('whatsapp-link')
    .setAttribute('href', `https://wa.me/56936227422?text=${message}`);
}
```

---

## 📱 Responsive Design

Todas las páginas son **100% responsive**:

- ✅ **Mobile First** - Diseñadas para móviles
- ✅ **Max-width: 28rem** - Ancho máximo en desktop
- ✅ **Padding adaptativo** - `p-4` en móvil, `p-8` en contenido
- ✅ **Fuentes escalables** - Tamaños relativos
- ✅ **Botones táctiles** - Tamaño mínimo 44px

---

## 🚀 Mejoras Futuras

- [ ] Agregar QR de transferencia automático
- [ ] Polling para actualizar estado en tiempo real
- [ ] Notificaciones push cuando cambia el estado
- [ ] Historial de pedidos en la misma página
- [ ] Botón para cancelar pedido pendiente
- [ ] Timer de expiración para pagos pendientes

---

**Última actualización:** Enero 2025
