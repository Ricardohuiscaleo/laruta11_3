# 🎯 APPS DEL SISTEMA - LA RUTA 11

## 📱 Tres Aplicaciones Diferentes

---

## 1️⃣ APP CLIENTE (Web Pública)

### **URL**: `/` (index) - `www.laruta11.cl`

### **Usuario**: Cliente final (desde su casa/celular)

### **Funcionalidad**:
- Ver menú de productos
- Agregar al carrito
- Hacer pedido online
- Seleccionar delivery o retiro

### **Métodos de Pago**:
- 🏦 **Transferencia bancaria** (pendiente confirmación)
- 🌐 **Pago online TUU/Webpay** (automático)

### **Flujo**:
```
Cliente → Hace pedido → Paga online/transfer → 
Pedido llega "pendiente" → Cocina confirma → Prepara
```

### **Características**:
- ✅ Acceso público (cualquiera puede entrar)
- ✅ Sin login requerido
- ✅ Pedidos remotos
- ✅ Comunicación por WhatsApp
- ✅ Páginas de confirmación (transfer-pending, card-pending)

---

## 2️⃣ APP CAJA (POS - Point of Sale)

### **URL**: `/caja` - **ESTA APP**

### **Usuario**: CAJERA del local (empleada)

### **Funcionalidad**:
- Tomar pedidos de clientes PRESENTES en el local
- Registrar productos
- Cobrar en el momento
- Enviar pedidos directo a cocina

### **Métodos de Pago**:
- 💵 **Efectivo** (pago inmediato)
- 💳 **Tarjeta física** (POS del local)
- 🏦 **Transferencia presencial** (cliente muestra comprobante)

### **Flujo**:
```
Cliente en local → Cajera toma pedido → Cliente paga → 
Cajera registra "pagado" → Pedido va DIRECTO a cocina
```

### **Características**:
- ✅ Requiere login (solo cajera)
- ✅ Cliente está PRESENTE físicamente
- ✅ Pago inmediato verificado
- ✅ Sin confirmación pendiente
- ✅ Pedidos van directo a cocina

### **Diferencias con App Cliente**:
| Aspecto | App Cliente | App Caja |
|---------|-------------|----------|
| Usuario | Cliente remoto | Cajera |
| Ubicación | Cualquier lugar | Local físico |
| Pago | Pendiente | Inmediato |
| Confirmación | Requiere | No requiere |
| Estado inicial | `pending` | `sent_to_kitchen` |

---

## 3️⃣ APP COMANDAS (Cocina)

### **URL**: `/comandas`

### **Usuario**: Cocinero/Cocina

### **Funcionalidad**:
- Ver TODOS los pedidos (remotos + presenciales)
- Confirmar pagos pendientes (de app cliente)
- Actualizar estados de preparación
- Gestionar entregas

### **Pedidos que ve**:
- 🟡 **Pendientes** (de app cliente, esperando confirmación)
- 🟢 **En cocina** (de app caja, ya pagados)
- 🔵 **Preparando** (en proceso)
- ✅ **Listos** (para entregar)

### **Flujo**:
```
Ve pedidos → Confirma pagos pendientes → 
Prepara todos → Actualiza estados → Entrega
```

### **Características**:
- ✅ Requiere login (solo cocina)
- ✅ Unifica ambos canales (remoto + presencial)
- ✅ Botones de confirmación de pago
- ✅ Control de estados
- ✅ Actualización en tiempo real (5 seg)

---

## 🔄 Flujo Completo del Sistema

### **Escenario 1: Cliente Remoto**
```
Cliente en casa
    ↓
App Cliente (/)
    ↓
Paga online/transfer
    ↓
Pedido → Comandas (pendiente)
    ↓
Cocina confirma pago
    ↓
Prepara pedido
    ↓
Delivery/Retiro
```

### **Escenario 2: Cliente Presencial**
```
Cliente en local
    ↓
Cajera en App Caja (/caja)
    ↓
Cliente paga (efectivo/tarjeta/transfer)
    ↓
Cajera registra "pagado"
    ↓
Pedido → Comandas (directo a cocina)
    ↓
Cocina prepara
    ↓
Entrega inmediata
```

---

## 🎯 Resumen de Diferencias

| Característica | App Cliente | App Caja | Comandas |
|----------------|-------------|----------|----------|
| **URL** | `/` | `/caja` | `/comandas` |
| **Usuario** | Cliente | Cajera | Cocina |
| **Login** | No | Sí | Sí |
| **Ubicación** | Remoto | Local | Local |
| **Pago** | Online/Transfer | Efectivo/Tarjeta | N/A |
| **Estado inicial** | `pending` | `sent_to_kitchen` | N/A |
| **Confirmación** | Requiere | No requiere | Confirma |
| **Propósito** | Pedidos online | Pedidos presenciales | Preparación |

---

## 💡 ¿Por qué dos apps de pedidos?

### **App Cliente (/)** 
- Para clientes que NO están en el local
- Pedidos desde casa, trabajo, calle
- Pago pendiente de verificación
- Delivery o retiro programado

### **App Caja (/caja)**
- Para clientes que SÍ están en el local
- Atención presencial por cajera
- Pago inmediato verificado
- Entrega inmediata

---

## 🚀 Ventajas del Sistema Dual

✅ **Omnicanal**: Atiende clientes remotos y presenciales  
✅ **Eficiencia**: Pedidos presenciales van directo a cocina  
✅ **Control**: Cajera verifica pagos presenciales  
✅ **Flexibilidad**: Múltiples métodos de pago  
✅ **Unificado**: Comandas ve todo en un solo lugar  

---

## 📊 Estadísticas Típicas

**Distribución de pedidos:**
- 60% App Cliente (remoto)
- 30% App Caja (presencial)
- 10% PedidosYA (integración)

**Métodos de pago más usados:**
- 40% Transferencia (app cliente)
- 30% Efectivo (app caja)
- 20% Tarjeta (app caja)
- 10% Pago online (app cliente)
