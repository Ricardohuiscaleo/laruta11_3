# 🔔 Sistema de Notificaciones para App Clientes

## 📋 Resumen

Adaptar el componente **MiniComandas** (usado en caja) para que los **clientes** puedan ver el estado de **SUS pedidos** con notificaciones de sonido cuando cambia el estado.

## 🎯 Concepto Base

El sistema de **caja** usa `MiniComandas.jsx` que:
- Hace polling a `/api/tuu/get_comandas_v2.php` cada 3 segundos
- Muestra TODOS los pedidos
- Permite confirmar pago, entregar, anular

Vamos a **adaptar esta misma lógica** para clientes, pero:
- Filtrando solo SUS pedidos
- Sin botones de acción (solo lectura)
- Con notificaciones de sonido cuando cambia el estado

---

## 🎯 Diferencias Clave: CAJA vs CLIENTES

| Aspecto | CAJA (`/caja`) | CLIENTES (`/`) |
|---------|----------------|----------------|
| **Objetivo** | Ver TODOS los pedidos nuevos | Ver solo MIS pedidos |
| **Filtro API** | Sin filtro (todos) | Por `customer_name` o `user_id` |
| **Acciones** | Confirmar pago, entregar, anular | Solo VER estado |
| **Trigger sonido** | Nuevo pedido creado | Cambio de estado de MI pedido |
| **Polling** | Cada 3 segundos | Cada 10 segundos |
| **Sonido** | `pedido.mp3` | `pedido.mp3` |

---

## 🏗️ Arquitectura

### **Sistema Actual (CAJA):**
```
MiniComandas.jsx
    ↓ (polling cada 3s)
/api/tuu/get_comandas_v2.php
    ↓
TODOS los pedidos
    ↓
Botones: Confirmar Pago, Entregar, Anular
```

### **Sistema Adaptado (CLIENTES):**
```
┌─────────────────────────────────────────────────────────────┐
│                    MenuApp (Clientes)                        │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Estados:                                             │   │
│  │  - audioEnabled (para sonido)                        │   │
│  │  - showAudioPopup (popup activación)                 │   │
│  └──────────────────────────────────────────────────────┘   │
│                           │                                  │
│                           ▼                                  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  MiniComandasCliente.jsx                              │   │
│  │  (ADAPTACIÓN de MiniComandas.jsx)                    │   │
│  │                                                        │   │
│  │  - Polling cada 10s (más lento)                      │   │
│  │  - Compara estados de pedidos                        │   │
│  │  - Reproduce pedido.mp3 si cambia estado             │   │
│  │  - Muestra notificación flotante                     │   │
│  │  - SIN botones de acción                             │   │
│  │  - Solo vista de lectura                             │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │  /api/tuu/get_comandas_v2.php         │
        │  (MISMA API pero con filtro)          │
        │                                        │
        │  ?customer_name=Juan+Pérez             │
        │                                        │
        │  Retorna solo pedidos del usuario      │
        └──────────────────────────────────────┘
```

---

## 📁 Archivos a Crear/Modificar

### **1. Nuevo Componente: `MiniComandasCliente.jsx`**

**Ubicación:** `/src/components/MiniComandasCliente.jsx`

**Función:** Adaptación de MiniComandas.jsx para clientes

**Basado en:** `MiniComandas.jsx` (caja)

**Cambios principales:**
- ✅ Filtra por `customer_name`
- ✅ Detecta cambios de estado y reproduce sonido
- ❌ Sin botones: Confirmar Pago, Entregar, Anular
- ✅ Solo vista de lectura
- ✅ Polling cada 10 segundos (vs 3 segundos en caja)

```javascript
import { useState, useEffect, useRef } from 'react';
import { Package, User, Phone, MessageSquare, Store, Truck, Clock } from 'lucide-react';

// ADAPTACIÓN DE MiniComandas.jsx PARA CLIENTES
export default function MiniComandasCliente({ 
  customerName, 
  audioEnabled, 
  onOrdersUpdate 
}) {
  const [orders, setOrders] = useState([]);
  const [showFloating, setShowFloating] = useState(false);
  const [newStatusOrder, setNewStatusOrder] = useState(null);
  const previousOrdersRef = useRef([]);

  const playSound = async () => {
    if (!audioEnabled) return;
    try {
      const audio = new Audio('/pedido.mp3');
      audio.volume = 1.0;
      await audio.play();
      console.log('✅ Sonido reproducido');
    } catch (error) {
      console.error('❌ Error reproduciendo sonido:', error);
    }
  };

  useEffect(() => {
    if (!customerName) return;

    const loadOrders = async () => {
      try {
        // MISMA API que caja, pero con filtro de customer_name
        const response = await fetch(
          `/api/tuu/get_comandas_v2.php?customer_name=${encodeURIComponent(customerName)}&t=${Date.now()}`,
          {
            headers: {
              'Cache-Control': 'no-cache, no-store, must-revalidate',
              'Pragma': 'no-cache',
              'Expires': '0'
            }
          }
        );
        const data = await response.json();
        
        if (data.success) {
          const freshOrders = data.orders || [];
          
          // Detectar cambios de estado
          if (previousOrdersRef.current.length > 0) {
            freshOrders.forEach(freshOrder => {
              const prevOrder = previousOrdersRef.current.find(
                o => o.order_number === freshOrder.order_number
              );
              
              // Si el estado cambió, reproducir sonido
              if (prevOrder && prevOrder.order_status !== freshOrder.order_status) {
                console.log('🔔 Cambio de estado detectado:', freshOrder.order_number);
                playSound();
                setNewStatusOrder(freshOrder);
                setShowFloating(true);
                setTimeout(() => setShowFloating(false), 5000);
              }
            });
          }
          
          setOrders(freshOrders);
          previousOrdersRef.current = freshOrders;
          
          // Actualizar contador de pedidos activos
          const activeCount = freshOrders.filter(
            o => o.order_status !== 'delivered' && o.order_status !== 'cancelled'
          ).length;
          
          if (onOrdersUpdate) {
            onOrdersUpdate(activeCount);
          }
        }
      } catch (error) {
        console.error('Error cargando pedidos:', error);
      }
    };

    const interval = setInterval(loadOrders, 10000); // Cada 10 segundos (vs 3s en caja)
    loadOrders();

    return () => clearInterval(interval);
  }, [customerName, audioEnabled]);

  // Renderizar pedidos (SIMPLIFICADO - sin botones de acción)
  const activeOrders = orders.filter(
    o => o.order_status !== 'delivered' && o.order_status !== 'cancelled'
  );

  return (
    <div className="bg-white rounded-lg shadow-sm">
      <div className="max-h-[600px] overflow-y-auto pb-4">
        {activeOrders.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            <Package size={48} className="mx-auto mb-2 opacity-50" />
            <p>No tienes pedidos activos</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {activeOrders.map(order => (
              <div key={order.id} className="p-4 bg-white border-l-4 border-orange-500">
                {/* Header */}
                <div className="flex items-center justify-between mb-3">
                  <span className="font-bold">{order.order_number}</span>
                  <span className="text-xs">
                    {getStatusIcon(order.order_status)} {getStatusText(order.order_status)}
                  </span>
                </div>

                {/* Productos */}
                <div className="bg-gray-50 rounded p-3 mb-3">
                  {order.items && order.items.map(item => (
                    <div key={item.id} className="mb-2">
                      <span className="font-medium text-sm">{item.product_name}</span>
                      <span className="text-xs text-gray-600"> x{item.quantity}</span>
                    </div>
                  ))}
                </div>

                {/* Info entrega */}
                {order.delivery_type === 'delivery' ? (
                  <div className="text-xs bg-blue-50 border border-blue-200 rounded p-2 mb-2">
                    <Truck size={12} className="inline mr-1" />
                    Delivery: {order.delivery_address}
                  </div>
                ) : (
                  <div className="text-xs flex items-center gap-2 mb-2">
                    <Store size={14} className="text-green-600" />
                    Retiro en local
                  </div>
                )}

                {/* Total */}
                <div className="font-bold text-green-600">
                  ${parseInt(order.installment_amount || 0).toLocaleString('es-CL')}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );

  const getStatusIcon = (status) => {
    switch (status) {
      case 'pending': return '📱';
      case 'sent_to_kitchen': return '👨‍🍳';
      case 'preparing': return '🔥';
      case 'ready': return '✅';
      case 'delivered': return '🎉';
      default: return '📦';
    }
  };

  const getStatusText = (status) => {
    switch (status) {
      case 'pending': return 'Pedido recibido';
      case 'sent_to_kitchen': return 'En cocina';
      case 'preparing': return 'Preparando tu pedido';
      case 'ready': return '¡Listo para retirar!';
      case 'delivered': return 'Entregado';
      default: return 'Procesando';
    }
  };

  if (!showFloating || !newStatusOrder) return null;

  return (
    <div className="fixed top-4 right-4 z-50 animate-slide-in">
      <div className="bg-white border-l-4 border-orange-500 rounded-lg shadow-lg p-4 max-w-sm">
        <div className="flex items-start">
          <div className="text-2xl mr-3">
            {getStatusIcon(newStatusOrder.order_status)}
          </div>
          <div className="flex-1">
            <div className="font-semibold text-gray-800 text-sm">
              {newStatusOrder.order_number}
            </div>
            <div className="text-gray-600 text-sm mt-1">
              {getStatusText(newStatusOrder.order_status)}
            </div>
          </div>
          <button
            onClick={() => setShowFloating(false)}
            className="text-gray-400 hover:text-gray-600 ml-2"
          >
            ×
          </button>
        </div>
      </div>
      
      <style jsx>{`
        @keyframes slideIn {
          from {
            transform: translateX(100%);
            opacity: 0;
          }
          to {
            transform: translateX(0);
            opacity: 1;
          }
        }
        .animate-slide-in {
          animation: slideIn 0.3s ease-out both;
        }
      `}</style>
    </div>
  );
}
```

---

### **2. Modificar API: `/api/tuu/get_comandas_v2.php`**

**Función:** Agregar filtro opcional por `customer_name`

**Cambio mínimo:** Agregar WHERE condicional

```php
// En /api/tuu/get_comandas_v2.php

// Obtener customer_name opcional
$customer_name = $_GET['customer_name'] ?? null;

// Construir query con filtro condicional
$where_clause = "WHERE 1=1";
if ($customer_name) {
    $where_clause .= " AND customer_name = :customer_name";
}

$sql = "
    SELECT 
        o.id,
        o.order_number,
        o.customer_name,
        o.customer_phone,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.delivery_type,
        o.delivery_address,
        o.installment_amount,
        o.customer_notes,
        o.created_at
    FROM tuu_orders o
    {$where_clause}
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
if ($customer_name) {
    $stmt->bindParam(':customer_name', $customer_name);
}
$stmt->execute();MIT 50
    ");
    
    $stmt->execute([$customer_name]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear pedidos
    $formatted_orders = array_map(function($order) use ($pdo) {
        // Obtener items del pedido
        $items_stmt = $pdo->prepare("
            SELECT product_name, quantity, product_price, item_type, combo_data
            FROM tuu_order_items
            WHERE order_id = ?
        ");
        $items_stmt->execute([$order['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'id' => intval($order['id']),
            'order_number' => $order['order_number'],
            'order_status' => $order['order_status'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'delivery_type' => $order['delivery_type'],
            'delivery_address' => $order['delivery_address'],
            'total' => floatval($order['total']),
            'customer_notes' => $order['customer_notes'],
            'created_at' => $order['created_at'],
            'items' => $items
        ];
    }, $orders);
    
    echo json_encode([
        'success' => true,
        'orders' => $formatted_orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
```

---

### **3. Modificar: `MenuApp.jsx`**

**Agregar estados y componente:**

```javascript
// En los imports
import MiniComandasCliente from './MiniComandasCliente.jsx';

// En los estados (ya existen audioEnabled y showAudioPopup)
const [myOrdersCount, setMyOrdersCount] = useState(0);

// Antes del return, agregar:
const customerName = user?.nombre || customerInfo.name;

// En el JSX, después del popup de audio:
{user && customerName && (
  <MiniComandasCliente 
    customerName={customerName}
    audioEnabled={audioEnabled}
    onOrdersUpdate={(count) => setActiveOrdersCount(count)}
  />
)}

// Nota: Reutiliza el mismo modal NotificationsModal que ya existe
```

---

## 🔄 Flujo Completo

```
1. Cliente hace pedido
   ↓
2. create_order.php guarda en tuu_orders
   - order_status: 'pending'
   - customer_name: 'Juan Pérez'
   ↓
3. CustomerOrderNotifications polling (cada 10s)
   - GET /api/get_customer_orders.php?customer_name=Juan+Pérez
   ↓
4. Personal cambia estado en comandas
   - update_order_status.php
   - order_status: 'pending' → 'ready'
   ↓
5. Siguiente polling detecta cambio
   - Compara: prevOrder.order_status !== freshOrder.order_status
   - playSound() → pedido.mp3
   - Muestra notificación flotante: "¡Listo para retirar!"
   ↓
6. Cliente ve notificación y escucha sonido
```

---

## 🎨 UI/UX para Clientes

### **Notificación Flotante:**
```
┌─────────────────────────────────────┐
│ 🔥  R11-1234567890                  │ ×
│     Preparando tu pedido             │
└─────────────────────────────────────┘
```

### **Badge en Campanita:**
```
🔔 (3)  ← Pedidos activos
```

### **Modal de Pedidos (al hacer click en campanita):**
```
┌──────────────────────────────────────┐
│  Mis Pedidos Activos            (3)  │
├──────────────────────────────────────┤
│  📱 R11-1234567890                   │
│     Hamburguesa Clásica x2           │
│     Estado: Preparando tu pedido 🔥  │
│     Total: $12.000                   │
│     Hace 5 minutos                   │
├──────────────────────────────────────┤
│  ✅ R11-1234567891                   │
│     Completo Italiano x1             │
│     Estado: ¡Listo para retirar!     │
│     Total: $5.500                    │
│     Hace 15 minutos                  │
└──────────────────────────────────────┘
```

---

## ✅ Checklist de Implementación

- [ ] Copiar `MiniComandas.jsx` → `MiniComandasCliente.jsx`
- [ ] Adaptar `MiniComandasCliente.jsx`:
  - [ ] Agregar detección de cambios de estado
  - [ ] Agregar reproducción de sonido
  - [ ] Eliminar botones de acción (Confirmar, Entregar, Anular)
  - [ ] Cambiar polling a 10 segundos
- [ ] Modificar `/api/tuu/get_comandas_v2.php`:
  - [ ] Agregar filtro opcional `?customer_name=`
- [ ] Modificar `MenuApp.jsx`:
  - [ ] Importar `MiniComandasCliente`
  - [ ] Renderizar componente con `customerName`
- [ ] Verificar que `pedido.mp3` existe en `/public`
- [ ] Probar popup de activación de audio
- [ ] Probar cambio de estado desde comandas
- [ ] Verificar que suena cuando cambia estado
- [ ] Verificar que solo muestra pedidos del usuario logueado

---

## 🔧 Testing

### **Caso 1: Usuario hace pedido**
1. Cliente hace pedido → Estado: `pending`
2. Esperar 10 segundos
3. Verificar que aparece en "Mis Pedidos"

### **Caso 2: Cambio de estado**
1. Personal cambia estado a `preparing` en comandas
2. Esperar máximo 10 segundos
3. Debe sonar `pedido.mp3`
4. Debe aparecer notificación flotante

### **Caso 3: Múltiples pedidos**
1. Cliente tiene 3 pedidos activos
2. Badge debe mostrar (3)
3. Solo debe sonar cuando cambia estado de alguno

---

## 📊 Cómo Adaptar MiniComandas

### **Paso 1: Copiar el archivo**
```bash
cp src/components/MiniComandas.jsx src/components/MiniComandasCliente.jsx
```

### **Paso 2: Modificar MiniComandasCliente.jsx**

**Cambios a realizar:**

1. **Agregar props de audio:**
```javascript
export default function MiniComandasCliente({ 
  customerName,      // NUEVO
  audioEnabled,      // NUEVO
  onOrdersUpdate 
}) {
```

2. **Cambiar URL de API:**
```javascript
// ANTES (caja):
const response = await fetch(`/api/tuu/get_comandas_v2.php?t=${Date.now()}`);

// DESPUÉS (clientes):
const response = await fetch(
  `/api/tuu/get_comandas_v2.php?customer_name=${encodeURIComponent(customerName)}&t=${Date.now()}`
);
```

3. **Agregar detección de cambios:**
```javascript
const previousOrdersRef = useRef([]);

const playSound = async () => {
  if (!audioEnabled) return;
  const audio = new Audio('/pedido.mp3');
  audio.volume = 1.0;
  await audio.play();
};

// En loadOrders, después de obtener freshOrders:
if (previousOrdersRef.current.length > 0) {
  freshOrders.forEach(freshOrder => {
    const prevOrder = previousOrdersRef.current.find(
      o => o.order_number === freshOrder.order_number
    );
    
    if (prevOrder && prevOrder.order_status !== freshOrder.order_status) {
      console.log('🔔 Cambio de estado:', freshOrder.order_number);
      playSound();
    }
  });
}

previousOrdersRef.current = freshOrders;
```

4. **Eliminar botones de acción:**
```javascript
// ELIMINAR estas funciones:
// - confirmPayment()
// - deliverOrder()
// - cancelOrder()

// ELIMINAR estos botones del JSX:
// - <button onClick={confirmPayment}>CONFIRMAR PAGO</button>
// - <button onClick={deliverOrder}>ENTREGAR</button>
// - <button onClick={cancelOrder}>ANULAR</button>
```

5. **Cambiar intervalo de polling:**
```javascript
// ANTES:
const interval = setInterval(loadOrders, 3000);

// DESPUÉS:
const interval = setInterval(loadOrders, 10000);
```

---

## 📊 Comparativa Final

| Aspecto | MiniComandas (Caja) | MiniComandasCliente |
| **Componente** | MiniComandas.jsx | CustomerOrderNotifications.jsx |
| **API** | get_comandas_v2.php | get_customer_orders.php |
| **Filtro** | Todos los pedidos | Solo del cliente |
| **Acciones** | Confirmar, entregar, anular | Solo ver |
| **Sonido** | No tiene | Sí (pedido.mp3) |
| **Polling** | 3 segundos | 10 segundos |
| **Trigger** | Nuevo pedido | Cambio de estado |

---

## 🎯 Resultado Final

El cliente tendrá:
- ✅ Popup para activar sonido al entrar
- ✅ Notificaciones con sonido cuando su pedido cambia de estado
- ✅ Badge con contador de pedidos activos
- ✅ Modal para ver detalles de sus pedidos
- ✅ Solo ve SUS pedidos (no todos como caja)
- ✅ Experiencia similar a comandas pero adaptada a cliente
