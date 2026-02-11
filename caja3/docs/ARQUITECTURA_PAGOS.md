# 🏗️ ARQUITECTURA DE PAGOS - LA RUTA 11

## 🎯 Concepto del Sistema

**Sistema dual: App Cliente (online) + App Caja (presencial)**

### **Dos Aplicaciones Diferentes:**

1. **App Cliente (Web Pública)** - `/` (index)
   - Clientes hacen pedidos desde sus casas
   - Métodos: Transferencia bancaria, Pago online (TUU/Webpay)
   - Pedidos llegan como "pendientes" a comandas

2. **App Caja (POS)** - `/caja` (esta app)
   - Operada por CAJERA en el local físico
   - Cliente está presente en el local
   - Métodos: Efectivo, Tarjeta física (POS), Transferencia presencial
   - Pedidos van directo a cocina (ya pagados)

---

## 💡 Filosofía del Sistema

### **¿Por qué manual?**
1. **Costos**: Evitar comisiones de pasarelas de pago (2-4% por transacción)
2. **Simplicidad**: No requiere integración compleja con bancos
3. **Flexibilidad**: Acepta cualquier método de pago sin restricciones
4. **Control**: El negocio valida pagos antes de preparar pedidos

### **¿Cómo funciona?**
1. Cliente hace pedido en la app web
2. Sistema registra pedido como "pendiente de pago"
3. Cliente paga por su cuenta (transfer, tarjeta en local, efectivo)
4. Cocina/Caja confirma manualmente que recibió el pago
5. Pedido pasa a cocina para preparación

---

## 📊 Métodos de Pago

### **1. Transferencia Bancaria** 🏦

**A) Transferencia Remota (App Web Cliente)**
1. Cliente hace pedido online desde su casa
2. Ve datos bancarios en pantalla
3. Hace transferencia desde su banco
4. Envía comprobante por WhatsApp
5. **Registra en `tuu_orders` con `payment_method='transfer'`**
6. Cocina verifica y confirma en comandas

**B) Transferencia Presencial (App Caja)**
1. Cliente en local físico
2. Cajera toma pedido en App Caja
3. Cliente hace transfer y muestra comprobante
4. Cajera verifica comprobante
5. **Registra en `tuu_orders` con `payment_method='transfer'`**
6. Pedido va directo a cocina

**Ventajas:**
- Sin comisiones
- Comprobante automático
- Flexible (remoto o presencial)

### **2. Pago con Tarjeta** 💳

**A) Tarjeta Presencial (App Caja)**
1. Cliente en local físico
2. CAJERA toma pedido en App Caja
3. Cliente pasa tarjeta en POS físico
4. **Registra en `tuu_orders` con `payment_method='card'`**
5. Pedido va directo a cocina

**B) Tarjeta Remota (App Web Cliente)**
1. Cliente hace pedido online
2. Indica "pagaré con tarjeta en local"
3. **Registra en `tuu_orders` con `payment_method='card'`**
4. Va al local a pagar con tarjeta en POS
5. Cocina confirma en comandas

**Ventajas:**
- Comisión 0.94% (igual que Webpay)
- Seguridad del POS físico
- Sin integración compleja

### **3. Efectivo** 💵
**Flujo real del cliente:**
1. Cliente llega al local físico
2. CAJERA toma pedido en App Caja (/caja)
3. Cliente paga en efectivo
4. **Registra en `tuu_orders` con `payment_method='cash'`**
5. Pedido va directo a cocina

**Uso:**
- App Caja (operada por cajera)
- Cliente presente en local
- Pago inmediato

### **4. PedidosYA** 🛵
**Flujo real:**
1. Cliente hace pedido en plataforma PedidosYA
2. PedidosYA ya cobró al cliente
3. Pedido llega al local (tablet/app PedidosYA)
4. **CAJERA registra manualmente en App Caja**
5. Cajera click botón "Pago PedidosYA"
6. **Registra en `tuu_orders` con `payment_method='pedidosya'`**
7. Pedido va directo a cocina

**Uso:**
- Registro manual por cajera
- Pago ya procesado por PedidosYA
- NO hay integración automática

### **5. Webpay/TUU** 🌐
**Flujo real del cliente:**
1. Cliente hace pedido en App Web Cliente
2. Pago online automatizado con pasarela
3. **Registra en `tuu_orders` con `payment_method='webpay'`**
4. Confirmación automática
5. Pedido va directo a cocina

**Comisión:** 0.94% porcentual  
**Nota:** Tiene comisiones, por eso se prefieren transfer/efectivo cuando es posible

---

## 🗄️ Base de Datos

### **Tabla: `tuu_orders`**

**✅ TODOS los pedidos se registran en `tuu_orders`**

Cada pedido se guarda con su método de pago correspondiente:

**Desde App Caja (Presencial - Local Físico):**
- `payment_method='cash'` → Efectivo en local
- `payment_method='card'` → Tarjeta física en local (POS)
- `payment_method='transfer'` → Transferencia en local (cliente muestra comprobante)
- `payment_method='pedidosya'` → Pedidos de PedidosYA (registro manual)

**Desde App Cliente (Remoto - Online):**
- `payment_method='transfer'` → Transferencia online (vía WhatsApp)
- `payment_method='webpay'` → Pago online TUU/Webpay
- `payment_method='card'` → Tarjeta pendiente (pagará en local)

### **Campos Principales:**
```sql
order_number          VARCHAR   -- T11-timestamp-random
payment_method        ENUM      -- 'cash','card','transfer','webpay','pedidosya'
payment_status        ENUM      -- 'paid','unpaid'
order_status          ENUM      -- 'pending','sent_to_kitchen','preparing'...
customer_name         VARCHAR
customer_phone        VARCHAR
delivery_type         ENUM      -- 'pickup','delivery'
delivery_address      VARCHAR
installment_amount    DECIMAL   -- Total del pedido
delivery_fee          DECIMAL
```

---

## 🔄 Estados del Sistema

### **Estados de Pago**
```
unpaid (pendiente) → Esperando confirmación manual
paid (pagado)      → Confirmado manualmente o automático
```

### **Estados de Orden**
```
pending           → Esperando confirmación de pago
sent_to_kitchen   → Confirmado, en cocina
preparing         → Cocinando
ready             → Listo para entregar
out_for_delivery  → En camino (delivery)
delivered         → Entregado
cancelled         → Cancelado
```

---

## 🎭 Roles en el Sistema

### **Cliente Remoto (App Web Pública - `/`)**
- Hace pedido desde su casa/celular
- Selecciona: Transferencia o Pago Online
- Paga por su cuenta
- Envía comprobante por WhatsApp
- Pedido llega como "pendiente" a comandas

### **Cajera (App Caja - `/caja`)**
- Opera en el local físico
- Cliente está PRESENTE en el mostrador
- Toma pedido directamente
- Cobra en el momento:
  - 💵 Efectivo en local
  - 💳 Tarjeta en local (POS físico)
  - 🏦 Transferencia en local (muestra comprobante)
  - 🛵 PedidosYA (registro manual)
- Registra pedido como "pagado"
- Pedido va DIRECTO a cocina

### **Cocina (Comandas - `/comandas`)**
- Ve TODOS los pedidos (remotos + presenciales)
- Confirma pagos pendientes de app web
- Prepara pedidos confirmados
- Actualiza estados de preparación

---

## 💰 Comparación de Costos

### **Comisiones Reales del Sistema**

**Webpay/TUU (Pago Online):**
- Comisión: **0.94%** porcentual
- Recomendado para ventas < $9,300

**Tarjeta POS Local:**
- Comisión: **0.94%** porcentual
- Mismo costo que Webpay

**Transferencia/Efectivo:**
- Comisión: **$0** (sin costo)

---

### **Ejemplo: Venta de $10,000**

| Método | Comisión | Costo | Neto |
|--------|----------|-------|------|
| Webpay online | 0.94% | -$94 | $9,906 |
| Tarjeta POS local | 0.94% | -$94 | $9,906 |
| Transferencia | 0% | $0 | $10,000 |
| Efectivo | 0% | $0 | $10,000 |

**Ahorro usando Transfer/Efectivo**: $94 por pedido  
**Ahorro mensual (100 pedidos)**: $9,400

---

## 🔐 Seguridad

### **¿Es seguro?**
✅ **SÍ** - Porque:
1. No se procesan datos de tarjetas en el sistema
2. No se almacenan datos bancarios
3. Cliente paga directamente a su banco o POS físico
4. Confirmación manual evita fraudes
5. Comprobantes por WhatsApp como respaldo

### **¿Qué pasa si no pagan?**
- Pedido queda en "pending"
- NO se prepara hasta confirmar pago
- Se puede cancelar si no hay confirmación
- Sin pérdidas para el negocio

---

## 📱 Flujos Completos Reales

### **FLUJO A: Cliente Remoto (App Web)**

**1. Cliente en su casa (19:30)**
```
- Entra a www.laruta11.cl
- Agrega productos al carrito
- Va a checkout
- Llena datos (nombre, teléfono, dirección)
- Click "Pagar con Transferencia"
```

**2. Sistema registra en DB (19:30)**
```
- POST /api/create_order.php (desde app cliente)
- Guarda en tabla: tuu_orders
- Crea orden T11-1234567890-5678
- payment_method='transfer'
- payment_status='unpaid'
- order_status='pending'
```

**3. Cliente ve pantalla (19:31)**
```
- Datos bancarios del negocio
- Botón WhatsApp con detalles del pedido
- Instrucciones: "Envía comprobante por WhatsApp"
```

**4. Cliente paga (19:32)**
```
- Abre app de su banco
- Hace transferencia manual
- Captura pantalla del comprobante
- Envía por WhatsApp al negocio
```

**5. Cocina ve pedido (19:33)**
```
- Aparece en comandas con botón verde
- "🏦 Confirmar Pago Transferencia"
- Ve detalles del pedido
```

**6. Cocina verifica (19:34)**
```
- Revisa WhatsApp
- Ve comprobante de transferencia
- Verifica monto correcto
- Click en botón confirmar
```

**7. Sistema actualiza (19:34)**
```
- payment_status='paid'
- order_status='sent_to_kitchen'
- Pedido pasa a preparación
```

**8. Cocina prepara (19:35-19:50)**
```
- Prepara completo y papas
- Actualiza estado: preparing → ready
- Notifica cliente
```

**9. Entrega (19:55)**
```
- Delivery o retiro
- Estado: delivered
- Pedido completado
```

---

### **FLUJO B: Cliente Presencial (App Caja)**

**1. Cliente llega al local (20:00)**
```
- Cliente: "Quiero un completo y papas"
- Cajera abre App Caja (/caja)
```

**2. Cajera toma pedido (20:01)**
```
- Selecciona productos en pantalla
- Agrega al carrito
- Cliente dice: "Pago con tarjeta"
```

**3. Cajera cobra (20:02)**
```
- Pasa tarjeta en POS físico
- POS aprueba transacción
- Cajera en app: Click "Pago con Tarjeta"
```

**4. Sistema registra en DB (20:02)**
```
- POST /api/create_order.php
- Guarda en tabla: tuu_orders
- Crea orden T11-1234567890-9999
- payment_method='card'
- payment_status='paid' (YA PAGADO)
- order_status='sent_to_kitchen' (DIRECTO A COCINA)
```

**5. Cocina ve pedido (20:02)**
```
- Aparece INMEDIATAMENTE en comandas
- Sin botón de confirmación (ya está pagado)
- Estado: "👨‍🍳 En Cocina"
```

**6. Cocina prepara (20:03-20:15)**
```
- Prepara completo y papas
- Actualiza: preparing → ready
```

**7. Entrega (20:16)**
```
- Cajera entrega pedido al cliente
- Cliente se va con su comida
- Estado: delivered
```

---

## 🎯 Ventajas del Sistema

### **Para el Negocio**
✅ Sin comisiones de pasarelas  
✅ Control total de pagos  
✅ Flexibilidad en métodos  
✅ Comprobantes de respaldo  
✅ Menos costos operativos  

### **Para el Cliente**
✅ Paga con su método preferido  
✅ Sin crear cuentas en pasarelas  
✅ Comunicación directa (WhatsApp)  
✅ Comprobante de su banco  
✅ Seguridad de pago directo  

### **Para el Sistema**
✅ Código simple y mantenible  
✅ Sin integraciones complejas  
✅ Sin dependencias de terceros  
✅ Escalable y flexible  
✅ Fácil de auditar  

---

## 📦 Sistema de Inventarios Integrado

### **¿Por qué TODO se registra en `tuu_orders`?**

**Razón principal: CONTROL DE INVENTARIO CENTRALIZADO**

Al registrar TODOS los pedidos (presenciales y remotos) en una sola tabla, el sistema puede:

1. **Descontar ingredientes automáticamente**
   - Cada producto tiene receta con ingredientes
   - Al vender, se descuenta stock de ingredientes
   - Lectura desde `tuu_orders` → `tuu_order_items`

2. **Controlar stock de productos**
   - Productos terminados (bebidas, salsas, etc.)
   - Stock disponible en tiempo real
   - Alertas de stock bajo

3. **Trazabilidad completa**
   - Todos los pedidos en un solo lugar
   - Reportes unificados de ventas
   - Auditoría de inventario

4. **Evitar duplicación de lógica**
   - Un solo sistema de descuento de inventario
   - No importa si es efectivo, tarjeta o transfer
   - Mismo proceso para todos los métodos

---

### **Flujo de Inventario**

```
Pedido registrado en tuu_orders
     ↓
Leer productos del pedido (tuu_order_items)
     ↓
Por cada producto:
  - Obtener receta (ingredientes)
  - Descontar ingredientes del stock
  - Descontar productos terminados
     ↓
Inventario actualizado en tiempo real
```

---

## 💰 Sistema de Arqueo de Caja

### **¿Qué es el Arqueo?**
Resumen de ventas por método de pago durante un turno de trabajo.

### **Acceso**
- **URL**: `/arqueo`
- **Botón flotante**: "$ Ventas" en App Caja
- **Usuarios**: Cajera y administradores

### **Características**

**Detección Automática de Turnos:**
- Sistema detecta turno actual automáticamente
- Horarios por día de semana:
  - **Lunes-Jueves**: 18:00-01:00 (21:00-04:00 UTC)
  - **Viernes-Sábado**: 18:00-03:00 (21:00-06:00 UTC)
  - **Domingo**: 18:00-01:00 (21:00-04:00 UTC)

**Navegación de Turnos:**
- Botón "← Ayer": Ver turno anterior
- Botón "Hoy →": Volver a turno actual
- Siempre muestra turno de HOY por defecto

**Resumen por Método de Pago:**
- 💳 **Tarjetas**: Total + cantidad de pedidos
- 🏦 **Transfer**: Total + cantidad de pedidos
- 💵 **Efectivo**: Total + cantidad de pedidos
- 💳 **Webpay**: Total + cantidad de pedidos
- 🛵 **PedidosYA**: Total + cantidad de pedidos
- 📊 **TOTAL**: Suma general + total de pedidos

**Funciones Adicionales:**
- 📊 Ver Detalle de Ventas (lista completa de pedidos)
- 📱 Enviar Arqueo por WhatsApp (resumen formateado)
- ← Volver a Caja

### **API Backend**

**Endpoint**: `/api/get_sales_summary.php`

**Parámetros:**
- `?days_ago=0` → Turno actual (default)
- `?days_ago=1` → Turno de ayer
- `?days_ago=2` → Turno de hace 2 días

**Configuración de Performance:**
```php
set_time_limit(60);           // 60 segundos timeout PHP
ini_set('max_execution_time', 60);
```

**Frontend Timeout:**
```javascript
fetch(url, { 
    signal: AbortSignal.timeout(30000) // 30 segundos
})
```

**Query SQL Optimizada:**
```sql
SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(installment_amount) as total
FROM tuu_orders
WHERE created_at >= ? 
AND created_at < ?
AND payment_status = 'paid'
GROUP BY payment_method
```

**Conversión de Zona Horaria:**
- Base de datos: UTC
- Chile: UTC-3
- Conversión automática en queries

### **Ejemplo de Respuesta API**
```json
{
  "success": true,
  "summary": {
    "cash": {"count": 15, "total": 45000},
    "card": {"count": 20, "total": 80000},
    "transfer": {"count": 10, "total": 35000},
    "webpay": {"count": 5, "total": 20000},
    "pedidosya": {"count": 8, "total": 30000}
  },
  "total_general": 210000,
  "total_orders": 58,
  "shift_hours": "18:00-01:00",
  "shift_date": "15-01-2025"
}
```

### **Diseño Responsive**

**Grid 2x2 + Total:**
```
┌─────────────┬─────────────┐
│ 💳 Tarjetas │ 🏦 Transfer │
├─────────────┼─────────────┤
│ 💵 Efectivo │ 💳 Webpay   │
├─────────────┼─────────────┤
│ 🛵 PedidosYA              │
├───────────────────────────┤
│ 📊 TOTAL (ancho completo) │
└───────────────────────────┘
```

**Técnica de Responsive:**
```css
font-size: clamp(20px, 5vw, 24px);
padding: clamp(12px, 3vw, 16px);
gap: clamp(10px, 2.5vw, 15px);
```

### **Flujo de Uso**

**1. Cajera abre arqueo (22:00)**
```
- Click en botón flotante "$ Ventas"
- Sistema carga turno actual automáticamente
- Muestra: "Turno Actual: 18:00-01:00 (15-01-2025)"
```

**2. Ve resumen en tiempo real**
```
💳 Tarjetas:    $80,000 (20 pedidos)
🏦 Transfer:    $35,000 (10 pedidos)
💵 Efectivo:    $45,000 (15 pedidos)
💳 Webpay:      $20,000 (5 pedidos)
🛵 PedidosYA:   $30,000 (8 pedidos)
📊 TOTAL:       $210,000 (58 pedidos)
```

**3. Consulta turno anterior**
```
- Click "← Ayer"
- Sistema carga turno de ayer
- Muestra: "Turno hace 1 día: 18:00-01:00 (14-01-2025)"
```

**4. Envía reporte por WhatsApp**
```
- Click "📱 Enviar Arqueo por WhatsApp"
- Abre WhatsApp con mensaje formateado
- Envía a administrador o grupo
```

### **Ventajas del Sistema**

✅ **Automático**: Sin abrir/cerrar caja manualmente  
✅ **Tiempo Real**: Datos actualizados al instante  
✅ **Histórico**: Consulta turnos anteriores  
✅ **Completo**: Todos los métodos de pago  
✅ **Exportable**: Envío por WhatsApp  
✅ **Responsive**: Funciona en móvil y desktop  
✅ **Rápido**: Optimizado con timeouts extendidos  

---

## 🔍 Detalle de Ventas

### **Endpoint**: `/ventas-detalle.astro`

**Lista completa de pedidos del turno:**
- Nombre del cliente
- Teléfono
- Productos ordenados
- Método de pago (badge con color)
- Monto total

**Optimización:**
```sql
SELECT * FROM tuu_orders
WHERE created_at >= ? AND created_at < ?
ORDER BY created_at DESC
LIMIT 200  -- Previene timeout
```

**Badges de Métodos:**
- 💵 Efectivo (verde)
- 💳 Tarjeta (azul)
- 🏦 Transfer (morado)
- 🛵 PedidosYA (naranja)
- 💳 Webpay (índigo)

---

## 📊 Integración con Dashboard

El arqueo se integra con el dashboard principal para mostrar:
- Ventas del día en tiempo real
- Comparación con días anteriores
- Métodos de pago más usados
- Tendencias de ventas por turno

---

```
Pedido registrado en tuu_orders
    ↓
Sistema lee tuu_order_items
    ↓
Por cada producto:
  - Busca receta (ingredientes)
  - Descuenta ingredientes del stock
  - Descuenta productos terminados
    ↓
Inventario actualizado en tiempo real
```

---

### **Ejemplo Práctico**

**Pedido: 1 Completo Tradicional**

1. Cajera registra en App Caja → `tuu_orders`
2. Sistema lee receta del completo:
   - 1 pan (ingrediente)
   - 1 vienesa (ingrediente)
   - 50g tomate (ingrediente)
   - 30g palta (ingrediente)
   - 20ml mayo (ingrediente)
3. Sistema descuenta automáticamente:
   - Stock panes: 100 → 99
   - Stock vienesas: 80 → 79
   - Stock tomate: 5kg → 4.95kg
   - Stock palta: 3kg → 2.97kg
   - Stock mayo: 2L → 1.98L

**Sin registro centralizado:**
- Pedidos presenciales no descontarían inventario
- Stock incorrecto
- Pérdidas por falta de control

---

## 🚀 Conclusión

**Sistema DUAL con INVENTARIO CENTRALIZADO:**

### **App Web (Cliente Remoto)**
- Pedidos online desde cualquier lugar
- Pago pendiente de confirmación
- Comunicación por WhatsApp
- **Registra en `tuu_orders` → Descuenta inventario**

### **App Caja (Cliente Presencial)**
- Pedidos en el local físico
- Operada por CAJERA
- Pago inmediato (efectivo/tarjeta/transfer)
- **Registra en `tuu_orders` → Descuenta inventario**

### **Comandas (Cocina)**
- Unifica AMBOS flujos
- Confirma pagos pendientes (remotos)
- Prepara todos los pedidos
- **Lee `tuu_orders` → Control total**

### **Sistema de Inventario**
- Lee `tuu_orders` y `tuu_order_items`
- Descuenta ingredientes automáticamente
- Stock en tiempo real
- Trazabilidad completa

---

**Valor del sistema:**
- ✅ Presencia digital + atención presencial
- ✅ **Inventario centralizado y automatizado**
- ✅ **Control de ingredientes y productos**
- ✅ **Trazabilidad completa de ventas**
- ✅ Sin comisiones en pagos manuales
- ✅ Flexibilidad total de métodos
- ✅ Control de caja y cocina unificado
- ✅ Experiencia omnicanal

**Por eso TODO se registra manualmente en `tuu_orders`: para mantener el inventario actualizado y preciso.** 📦
