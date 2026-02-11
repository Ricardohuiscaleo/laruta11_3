# 🎯 Sistema de Niveles y Premios - La Ruta 11

## 🔥 IMPORTANTE: Sistema Funciona en APP y CAJA

**✅ CONFIRMADO**: El sistema de niveles registra compras desde:
- **APP (TÚ)**: Clientes con `user_id` → Acumulan puntos/sellos/cashback
- **CAJA**: Ventas sin `user_id` (NULL) → Se registran sin errores

**Trigger `auto_generate_cashback`**:
- ✅ Valida `NEW.user_id IS NOT NULL` antes de ejecutar
- ✅ Solo procesa cashback para usuarios de APP
- ✅ Ignora órdenes de CAJA sin causar errores

**Ejemplo respuesta CAJA**:
```json
{
    "success": true,
    "message": "Pago por tarjeta confirmado exitosamente",
    "order_number": "T11-1764211178-1818",
    "payment_method": "card"
}
```

---

## 📊 Sistema de Niveles (1% Cashback)

**Conversión**: `$1.000 = 1 punto` • `100 puntos = 1 sello` • **1% de retorno**

### **Nivel Bronze** 🥉
- **Requisito**: 6 sellos (600 puntos = $600.000 gastados)
- **Valor por sello**: $1.000
- **Cashback**: $6.000 (automático)
- **Estado**: ✅ Implementado con TRIGGER

### **Nivel Silver** 🥈
- **Requisito**: 6 sellos ADICIONALES (1.200 puntos totales = $1.200.000 gastados)
- **Valor por sello**: $2.000
- **Cashback**: $12.000 (automático)
- **Estado**: ✅ Implementado con TRIGGER

### **Nivel Gold** 🥇
- **Requisito**: 6 sellos ADICIONALES (1.800 puntos totales = $1.800.000 gastados)
- **Valor por sello**: $3.000
- **Cashback**: $18.000 (automático)
- **Estado**: ✅ Implementado con TRIGGER

---

## 🎁 Sistema de Recompensas (Cupones Canjeables)

### **Recompensa 1: Delivery Gratis**
- **Requisito**: 2 sellos en el nivel actual
- **Tipo**: Cupón canjeable
- **Uso**: Se aplica en checkout (descuento en delivery_fee)
- **Productos**: N/A (es descuento en envío)

### **Recompensa 2: Papas + Bebida Gratis**
- **Requisito**: 4 sellos en el nivel actual
- **Tipo**: Cupón canjeable
- **Uso**: Se aplica en checkout (productos gratis)
- **Productos definidos**:
  - **Papas**: Cualquier producto de `category_id: 12, subcategory_id: 57`
  - **Bebidas**: 14 opciones disponibles (IDs: 91, 93, 95, 96, 97, 98, 99, 100, 101, 103, 111, 131, 132, 135)
  - Usuario elige en checkout qué bebida quiere (se muestra como "GRATIS")

### **Recompensa 3: Cashback**
- **Requisito**: 6 sellos en el nivel actual
- **Tipo**: Automático (ya implementado)
- **Uso**: Se agrega al wallet automáticamente

---

## 🔄 Progresión de Niveles

### **Ejemplo Usuario con 601 puntos ($601.000 gastados):**
```
Total puntos: 601
Total sellos: 6
Nivel actual: Bronze (completado)
Progreso Silver: 0/6 sellos (1 punto de 600 necesarios)
```

### **Ejemplo Usuario con 1.250 puntos ($1.250.000 gastados):**
```
Total puntos: 1.250
Total sellos: 12
Nivel actual: Silver (completado)
Progreso Gold: 0/6 sellos (50 puntos de 600 necesarios)
```

---

## 💾 Estructura de Base de Datos Necesaria

### **Tabla: user_coupons** ✅ CREADA
```sql
CREATE TABLE IF NOT EXISTS user_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    coupon_type ENUM('delivery_free', 'papas_bebida') NOT NULL,
    status ENUM('available', 'used') DEFAULT 'available',
    stamps_used INT NOT NULL COMMENT 'Sellos consumidos al crear cupón (2 o 4)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    order_id VARCHAR(50) NULL COMMENT 'order_number de tuu_orders',
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
```
**Estado**: ✅ Tabla creada en phpMyAdmin
**Nota**: Los cupones NO expiran (sin campo `expires_at`)

### **Campo en tuu_orders** ⚠️ VERIFICAR SI EXISTE
```sql
-- Agregar a tuu_orders si no existe:
ALTER TABLE tuu_orders 
ADD COLUMN reward_stamps_consumed INT DEFAULT 0 COMMENT 'Sellos consumidos en este pedido';
```
**Función**: Trackear cuántos sellos se usaron en cada pedido para calcular `available_stamps`

---

## 🎮 Flujo de Usuario - Canje de Premios

### **ProfileModalModern - Botones Informativos**
Los botones "Canjear" NO crean cupones, solo informan al usuario:

```javascript
const handleShowRedeemInfo = (rewardType) => {
    const messages = {
        delivery_free: '🎁 Para usar tu Delivery Gratis:\n\n1. Ve al checkout\n2. Selecciona "Delivery"\n3. Aplica tu premio disponible\n4. ¡El envío será GRATIS!',
        papas_bebida: '🎁 Para usar tu Papas + Bebida:\n\n1. Ve al checkout\n2. Busca "Premios Disponibles"\n3. Selecciona Papas y Bebida GRATIS\n4. ¡Agrégalos a tu pedido sin costo!'
    };
    
    alert(messages[rewardType]);
};
```

**Importante**: El cupón se crea automáticamente en checkout cuando el usuario lo aplica.

---

## 🛒 Aplicación en Checkout (AQUÍ SE CANJEA)

### **CheckoutApp.jsx - Verificar sellos disponibles**
```javascript
// Al cargar checkout, verificar sellos del usuario
const checkAvailableRewards = async () => {
    const response = await fetch(`/api/get_user_tuu_orders.php`, {
        method: 'POST',
        body: JSON.stringify({ user_id: user.id })
    });
    const data = await response.json();
    
    const currentLevelStamps = data.available_stamps % 6;
    
    // Mostrar premios disponibles según sellos
    if (currentLevelStamps >= 2) {
        setCanUseDeliveryFree(true);
    }
    if (currentLevelStamps >= 4) {
        setCanUsePapasBebida(true);
    }
};

// Al aplicar premio de delivery (AQUÍ SE CREA EL CUPÓN)
const applyDeliveryReward = async () => {
    // 1. Crear cupón
    const response = await fetch('/api/coupons/create_coupon.php', {
        method: 'POST',
        body: JSON.stringify({
            user_id: user.id,
            coupon_type: 'delivery_free'
        })
    });
    const data = await response.json();
    
    if (data.success) {
        // 2. Aplicar descuento
        setDeliveryFee(0);
        setAppliedCouponId(data.coupon_id);
    }
};

// Al aplicar premio de papas + bebida (AQUÍ SE CREA EL CUPÓN)
const applyPapasBebidaReward = async () => {
    // 1. Mostrar modal selector de papas y bebidas
    setShowPapasBebidaModal(true);
};

const confirmPapasBebidaSelection = async (selectedPapa, selectedBebida) => {
    // 1. Crear cupón
    const response = await fetch('/api/coupons/create_coupon.php', {
        method: 'POST',
        body: JSON.stringify({
            user_id: user.id,
            coupon_type: 'papas_bebida'
        })
    });
    const data = await response.json();
    
    if (data.success) {
        // 2. Agregar productos al carrito con precio 0 y flag de premio
        const papaItem = {
            ...selectedPapa,
            price: 0,
            original_price: selectedPapa.price,
            is_reward: true,
            reward_note: 'PREMIO - Papas + Bebida Gratis'
        };
        
        const bebidaItem = {
            ...selectedBebida,
            price: 0,
            original_price: selectedBebida.price,
            is_reward: true,
            reward_note: 'PREMIO - Papas + Bebida Gratis'
        };
        
        addToCart(papaItem);
        addToCart(bebidaItem);
        setAppliedCouponId(data.coupon_id);
        setShowPapasBebidaModal(false);
    }
};

// Al completar pedido, marcar cupón como usado
const completePurchase = async () => {
    // ... procesar pago ...
    
    if (appliedCouponId) {
        await fetch('/api/coupons/use_coupon.php', {
            method: 'POST',
            body: JSON.stringify({
                coupon_id: appliedCouponId,
                order_id: orderNumber
            })
        });
    }
};
```

---

## 📋 Desglose en "Tu Pedido" (Checkout)

### **Ejemplo SIN premios aplicados:**
```
Tu Pedido
Papas Fritas Individual
Cantidad: 1
$1.500

Bilz Lata 350ml
Cantidad: 1
$1.190

🎉 Descuento R11LOV:
-$269

Subtotal productos:
$2.421

Delivery:
$2.500

Extras delivery:
1x Broma caída delivery
$500

Total extras:
$500

💰 Usar Cashback
Disponible: $6.000
Solo aplica a productos
Usar todo

Aplicando: $0

Total:
$5.421
```

### **Ejemplo CON "Delivery Gratis" aplicado (2 sellos):**
```
Tu Pedido
Papas Fritas Individual
Cantidad: 1
$1.500

Bilz Lata 350ml
Cantidad: 1
$1.190

Subtotal productos:
$2.690

🎁 Premio: Delivery Gratis
-$2.500

Delivery:
$0  ✅

Extras delivery:
1x Broma caída delivery
$500

Total extras:
$500

💰 Usar Cashback
Disponible: $6.000
Solo aplica a productos
Usar todo

Aplicando: $0

Total:
$690
```

### **Ejemplo CON "Papas + Bebida Gratis" aplicado (4 sellos):**
```
Tu Pedido
Completo Tradicional
Cantidad: 1
$3.500

🎁 Papas Medianas (PREMIO)
Cantidad: 1
$0  ✅

🎁 Coca-Cola 500ml (PREMIO)
Cantidad: 1
$0  ✅

Subtotal productos:
$3.500

Delivery:
$2.500

💰 Usar Cashback
Disponible: $6.000
Solo aplica a productos
Usar todo

Aplicando: $3.500
Limpiar

💰 Cashback Aplicado:
-$3.500

Total:
$2.500
```

### **Ejemplo CON ambos premios + cashback:**
```
Tu Pedido
Completo Tradicional
Cantidad: 1
$3.500

🎁 Papas Medianas (PREMIO)
Cantidad: 1
$0  ✅

🎁 Coca-Cola 500ml (PREMIO)
Cantidad: 1
$0  ✅

Subtotal productos:
$3.500

🎁 Premio: Delivery Gratis
-$2.500

Delivery:
$0  ✅

💰 Usar Cashback
Disponible: $6.000
Solo aplica a productos
Usar todo

Aplicando: $3.500
Limpiar

💰 Cashback Aplicado:
-$3.500

Total:
$0  🎉
```

### **Ejemplo REAL: Bebida desde cinta + Premio Papas + Bebida:**
```
Tu Pedido
Hamburguesa Clásica
Cantidad: 1
$7.280

Bilz Lata 350ml
Cantidad: 1
$1.190

🎁 Papas Fritas Individual (PREMIO)
Cantidad: 1
$0  ✅

🎁 Coca-Cola 500ml (PREMIO)
Cantidad: 1
$0  ✅

🎉 Descuento R11LOV:
-$847

Subtotal productos:
$7.623

Delivery:
$2.500

Extras delivery:
1x Abrazo
$500
1x Broma caída delivery
$500

Total extras:
$1.000

💰 Usar Cashback
Disponible: $6.000
Solo aplica a productos
Usar todo

Aplicando: $6.000
Limpiar

💰 Cashback Aplicado:
-$6.000

Total:
$5.123
```
**Nota**: Usuario agregó Bilz desde cinta de bebidas, luego aplicó premio "Papas + Bebida" y seleccionó Papas Fritas + Coca-Cola GRATIS

---

## 🎨 UI de Cinta de Bebidas con Premio

### **Cinta ANTES de aplicar premio (4+ sellos disponibles):**
```
💡 ¿Agregar una bebida?
Complementa tu pedido

[Botón destacado] 🎁 Aplicar Premio: Papas + Bebida Gratis (4 sellos)

Agua con Gas Benedictino 500ml
$1.100
+ Agregar

Agua sin gas Benedictino 500ml
$1.100
+ Agregar

Bilz Lata 350ml
$1.190
-  1  +

Canada Dry Lata 350ml
$1.190
+ Agregar

Canada Dry Zero Lata 350ml
$1.190
+ Agregar

Coca-Cola 500ml
$1.500
+ Agregar
```

### **Cinta DESPUÉS de aplicar premio:**
```
💡 ¿Agregar una bebida?
Complementa tu pedido

Agua con Gas Benedictino 500ml
GRATIS  ← (fondo amarillo, texto negro)
+ Agregar

Agua sin gas Benedictino 500ml
GRATIS  ← (fondo amarillo, texto negro)
+ Agregar

Bilz Lata 350ml
$1.190
-  1  +

Canada Dry Lata 350ml
GRATIS  ← (fondo amarillo, texto negro)
+ Agregar

Canada Dry Zero Lata 350ml
GRATIS  ← (fondo amarillo, texto negro)
+ Agregar

Coca-Cola 500ml
GRATIS  ← (fondo amarillo, texto negro)
+ Agregar
```

### **Lógica de visualización:**
```javascript
// CheckoutApp.jsx - Cinta de bebidas
{availableDrinks.map(bebida => {
  const isRewardDrink = rewardBebidasIds.includes(bebida.id);
  const showAsGratis = papasBebidaRewardApplied && isRewardDrink;
  
  return (
    <div className="bebida-card">
      <img src={bebida.image_url} />
      <p>{bebida.name}</p>
      
      {showAsGratis ? (
        <span className="bg-yellow-400 text-black font-bold px-2 py-1 rounded">
          GRATIS
        </span>
      ) : (
        <span>${bebida.price.toLocaleString('es-CL')}</span>
      )}
      
      <button onClick={() => addBebidaToCart(bebida, showAsGratis)}>
        + Agregar
      </button>
    </div>
  );
})}
```

### **Papas también se muestran GRATIS:**
Cuando se aplica el premio, las papas (category 12/subcategory 57) también deben mostrarse con precio "GRATIS" en fondo amarillo.

---

## 🎯 Ubicación del Botón de Premio

**Posición**: Justo después del título "💡 ¿Agregar una bebida?" y ANTES de la lista de productos

**Diseño del botón**:
```html
<button className="w-full bg-gradient-to-r from-orange-500 to-red-600 text-white font-bold py-3 px-4 rounded-lg shadow-lg mb-4 flex items-center justify-center gap-2 hover:scale-105 transition-transform">
  🎁 Aplicar Premio: Papas + Bebida Gratis (4 sellos)
</button>
```

**Comportamiento**:
1. Solo se muestra si `availableStamps % 6 >= 4`
2. Al hacer click, abre modal selector de papas
3. Usuario selecciona papa → Se cierra modal
4. Bebidas en cinta cambian a "GRATIS"
5. Usuario selecciona bebida de la cinta → Se agrega con precio $0 + Coca-Cola GRATIS

---

## 📧 Pantallas Pending y Premios

### **Cash Pending (`/cash-pending`)**
```
💵 Pedido Registrado - Pago en Efectivo

Pedido: #TUU-20240115-001
Estado: Pendiente de confirmación

Detalle del Pedido:
- Hamburguesa Clásica x1: $7.280
- Bilz Lata 350ml x1: $1.190
- 🎁 Papas Fritas Individual (PREMIO) x1: $0
- 🎁 Coca-Cola 500ml (PREMIO) x1: $0

Descuentos:
- R11LOV: -$847
- Cashback: -$6.000

Delivery: $2.500
Extras: $1.000

Total a pagar: $5.123
Pagas con: $10.000
Vuelto: $4.877

Tu pedido será confirmado por WhatsApp.
```

### **Card Pending (`/card-pending`)**
```
💳 Pedido Registrado - Pago con Tarjeta

Pedido: #TUU-20240115-002
Estado: Pendiente de confirmación

Detalle del Pedido:
- Completo Tradicional x1: $3.500
- 🎁 Papas Medianas (PREMIO) x1: $0
- 🎁 Coca-Cola 500ml (PREMIO) x1: $0

Premios aplicados:
- 🎁 Delivery Gratis: -$2.500

Delivery: $0

Total a pagar con tarjeta: $3.500

Tu pedido será confirmado por WhatsApp.
Prepara tu tarjeta para el pago al recibir.
```

### **Transfer Pending (`/transfer-pending`)**
```
🏦 Pedido Registrado - Transferencia Bancaria

Pedido: #TUU-20240115-003
Estado: Pendiente de transferencia

Detalle del Pedido:
- Hamburguesa Clásica x1: $7.280
- 🎁 Papas Fritas Individual (PREMIO) x1: $0
- 🎁 Coca-Cola 500ml (PREMIO) x1: $0

Total a transferir: $7.280

Datos para transferencia:
Banco: Banco Estado
Cuenta: 12345678
RUT: 12.345.678-9
Nombre: La Ruta 11

Envía el comprobante por WhatsApp.
Tu pedido será confirmado al verificar el pago.
```

### **Mensaje WhatsApp con Premios**
```
*PEDIDO PENDIENTE - LA RUTA 11*

*Pedido:* #TUU-20240115-001
*Cliente:* Ricardo Huisca
*Estado:* Pendiente de confirmación
*Total:* $5.123
*Método:* Efectivo (paga con $10.000)

*PRODUCTOS:*
1. Hamburguesa Clásica x1 - $7.280
2. Bilz Lata 350ml x1 - $1.190
3. 🎁 Papas Fritas Individual (PREMIO) x1 - $0
4. 🎁 Coca-Cola 500ml (PREMIO) x1 - $0

*TIPO DE ENTREGA:* 🚴 Delivery
*DIRECCIÓN:* Av. Libertador Bernardo O'Higgins 123

*DESCUENTOS:*
- Código R11LOV: -$847
- Cashback usado: -$6.000

*DELIVERY:* $2.500
*EXTRAS:*
- 1x Abrazo: $500
- 1x Broma caída delivery: $500

Pedido realizado desde la app web.
Por favor confirmar recepción.
```

---

## ✅ Decisiones Tomadas

### **1. ¿Qué papas incluye el premio?**
- ✅ **Cualquier producto de categoría Papas** (`category_id: 12, subcategory_id: 57`)
- Usuario elige en checkout

### **2. ¿Qué bebida incluye el premio?**
- ✅ **Usuario elige entre 14 opciones**
- IDs disponibles: 91, 93, 95, 96, 97, 98, 99, 100, 101, 103, 111, 131, 132, 135
- Se muestran como "GRATIS" en checkout

### **3. ¿Los cupones expiran?**
- ✅ **NO expiran** (sin campo `expires_at` en tabla)
- Cupones permanentes hasta que se usen

### **4. ¿Cuántos cupones puede tener activos?**
- ✅ **Ilimitados**
- Usuario puede acumular múltiples cupones del mismo tipo
- Se usan uno a la vez en checkout

### **5. ¿Cómo se muestran en el desglose?**
- ✅ **Productos gratis**: Se agregan al carrito con `price: 0` y etiqueta "🎁 (PREMIO)"
- ✅ **Delivery gratis**: Se muestra línea "🎁 Premio: Delivery Gratis" con descuento `-$2.500`
- ✅ **Combinable con cashback**: Premios + cashback pueden usarse juntos
- ✅ **Visual claro**: Emojis 🎁 y ✅ para identificar premios aplicados

### **6. ¿Cómo funciona el inventario con premios?**
- ✅ **Productos de premio DESCUENTAN inventario** normalmente
- ✅ **Se registran en `cart_items` con `price: 0`** pero con `product_id` real
- ✅ **Campo `is_reward: true`** identifica que es premio (no afecta cálculo de puntos)
- ✅ **Campo `original_price`** guarda precio real del producto (para referencia)
- ✅ **Sistema de inventario** procesa igual que productos normales

### **7. ¿Cómo se muestran en pantallas pending?**
- ✅ **Productos de premio aparecen en el detalle** con precio $0
- ✅ **Se envían por WhatsApp** con nota "🎁 (PREMIO)"
- ✅ **Minicomandas** deben imprimir productos de premio normalmente
- ✅ **Cupón usado se registra** con `order_id` en `user_coupons`

### **8. ¿Cómo se muestra el botón de premio en checkout?**
- ✅ **Ubicación**: Después de "💡 ¿Agregar una bebida?" y ANTES de la lista de productos
- ✅ **Diseño**: Botón destacado naranja/rojo con gradiente
- ✅ **Texto**: "🎁 Aplicar Premio: Papas + Bebida Gratis (4 sellos)"
- ✅ **Condición**: Solo visible si `availableStamps % 6 >= 4`

### **9. ¿Cómo se muestran bebidas GRATIS en la cinta?**
- ✅ **Precio cambia a "GRATIS"** con fondo amarillo y texto negro
- ✅ **Solo bebidas elegibles** (14 IDs específicos) muestran GRATIS
- ✅ **Bebidas ya agregadas** (como Bilz) mantienen su precio normal
- ✅ **Al agregar bebida GRATIS** se agrega al carrito con `price: 0, is_reward: true`

---

## 🚀 Estado de Implementación

### ✅ **Completado**
1. ✅ **Productos definidos** (papas: category 12/57, bebidas: 14 IDs específicos)
2. ✅ **Tabla `user_coupons` creada** en base de datos
3. ✅ **API `create_coupon.php`** - Canjear recompensas y crear cupones
4. ✅ **API `get_user_coupons.php`** - Obtener cupones disponibles/usados
5. ✅ **API `use_coupon.php`** - Marcar cupón como usadoCanjear recompensas y crear cupones
4. ✅ **API `get_user_coupons.php`** - Obtener cupones disponibles/usados
5. ✅ **API `use_coupon.php`** - Marcar cupón como usado

### 🔄 **Pendiente**
6. ⏳ **Verificar campo `reward_stamps_consumed`** en tabla `tuu_orders` (probablemente no se usa)
7. ⏳ **Modificar ProfileModalModern.jsx** - Botones informativos (no crean cupones)
8. ⏳ **Modificar CheckoutApp.jsx** - Implementar UI y lógica completa:
   - ✅ Ya carga `availableRewards` desde API (línea 100-115)
   - ❌ Falta UI para mostrar premios disponibles
   - ❌ Falta botón "Aplicar Delivery Gratis"
   - ❌ Falta modal selector "Papas + Bebida" con productos reales
   - ❌ Falta llamar `create_coupon.php` al aplicar
   - ❌ Falta agregar productos con `price: 0, is_reward: true`
   - ❌ Falta llamar `use_coupon.php` al completar pedido
9. ⏳ **Modificar pantallas pending** - Mostrar productos de premio:
   - ❌ `/cash-pending` - Incluir productos con precio $0
   - ❌ `/card-pending` - Incluir productos con precio $0
   - ❌ `/transfer-pending` - Incluir productos con precio $0
   - ❌ Mensaje WhatsApp - Incluir línea "🎁 (PREMIO)"
10. ⏳ **Integrar minicomandas** - Sistema de impresión de tickets por estación:
    - ❌ Imprimir productos de premio normalmente
    - ❌ Marcar con nota "PREMIO" en ticket
    - ❌ Descontar inventario correctamente

---

## 🎯 Flujo Detallado: Premio "Papas + Bebida"

### **Paso 1: Usuario hace click en "Aplicar Premio"**
```javascript
// CheckoutApp.jsx
<button onClick={applyPapasBebidaReward}>
  🎁 Usar Papas + Bebida Gratis (4 sellos)
</button>
```

### **Paso 2: Se abre modal selector**
```javascript
// Modal muestra:
// - Lista de papas (category_id: 12, subcategory_id: 57)
// - Lista de bebidas (14 opciones específicas)
// - Botón "Confirmar Selección"
```

### **Paso 3: Usuario selecciona productos**
```javascript
// Usuario elige:
const selectedPapa = { id: 45, name: 'Papas Medianas', price: 2500, ... };
const selectedBebida = { id: 91, name: 'Coca-Cola 500ml', price: 1500, ... };
```

### **Paso 4: Se crea cupón y agregan productos**
```javascript
// 1. Crear cupón en BD
const coupon = await createCoupon(user.id, 'papas_bebida');

// 2. Agregar al carrito con precio 0
addToCart({
  ...selectedPapa,
  price: 0,
  original_price: 2500,
  is_reward: true,
  reward_note: 'PREMIO - Papas + Bebida Gratis',
  coupon_id: coupon.id
});

addToCart({
  ...selectedBebida,
  price: 0,
  original_price: 1500,
  is_reward: true,
  reward_note: 'PREMIO - Papas + Bebida Gratis',
  coupon_id: coupon.id
});
```

### **Paso 5: Se muestran en el desglose**
```
🎁 Papas Medianas (PREMIO)
Cantidad: 1
$0  ✅

🎁 Coca-Cola 500ml (PREMIO)
Cantidad: 1
$0  ✅
```

### **Paso 6: Al pagar, se descuenta inventario**
```javascript
// process_sale_inventory.php procesa normalmente:
// - Descuenta stock de Papas Medianas (product_id: 45)
// - Descuenta stock de Coca-Cola 500ml (product_id: 91)
// - Registra en cart_items con price: 0
// - NO suma al subtotal (price = 0)
// - NO genera puntos (is_reward = truee usa)
7. ⏳ **Modificar ProfileModalModern.jsx** - Botones informativos (no crean cupones)
8. ⏳ **Modificar CheckoutApp.jsx** - Implementar UI y lógica completa:
   - ✅ Ya carga `availableRewards` desde API (línea 100-115)
   - ❌ Falta UI para mostrar premios disponibles
   - ❌ Falta botón "Aplicar Delivery Gratis"
   - ❌ Falta modal selector "Papas + Bebida"
   - ❌ Falta llamar `create_coupon.php` al aplicar
   - ❌ Falta llamar `use_coupon.php` al completar pedido
9. ⏳ **Integrar minicomandas** - Sistema de impresión de tickets por estación

---

## 📁 Archivos Creados

### **APIs del Sistema de Cupones** (`/api/coupons/`)

#### **1. create_coupon.php**
- **Función**: Canjear recompensa y crear cupón
- **Validaciones**:
  - Verifica sellos disponibles en nivel actual (`available_stamps % 6`)
  - `delivery_free`: requiere 2 sellos disponibles
  - `papas_bebida`: requiere 4 sellos disponibles
  - Previene canje si no hay suficientes sellos
- **Proceso**:
  1. Calcula `available_stamps` desde `get_user_tuu_orders.php`
  2. Verifica `currentLevelStamps = available_stamps % 6`
  3. Valida requisitos (2 o 4 sellos)
  4. Crea cupón con `stamps_used` (2 o 4)
- **Respuesta**: `{success: true, coupon_id: X}` o error

#### **2. get_user_coupons.php**
- **Función**: Obtener cupones del usuario
- **Parámetros**: `user_id` (GET)
- **Respuesta**:
  ```json
  {
    "success": true,
    "coupons": {
      "delivery_free": {
        "available": 2,
        "used": 1,
        "total": 3
      },
      "papas_bebida": {
        "available": 1,
        "used": 0,
        "total": 1
      }
    },
    "available_coupons": [
      {
        "id": 5,
        "coupon_type": "delivery_free",
        "created_at": "2024-01-15 10:30:00"
      }
    ]
  }
  ```

#### **3. use_coupon.php**
- **Función**: Marcar cupón como usado
- **Parámetros**: `coupon_id`, `order_id` (POST)
- **Validaciones**:
  - Verifica que cupón existe
  - Verifica que está disponible (no usado)
  - Actualiza `status='used'`, `used_at`, `order_id`

---

## 🔧 Flujo Completo del Sistema

### **1. Usuario ve sus sellos en ProfileModalModern**
- Muestra sellos disponibles en nivel actual
- Botón "Canjear" es informativo (explica cómo usar en checkout)
- NO crea cupones aquí

### **2. Usuario va a Checkout**
- ✅ Checkout carga sellos disponibles vía `get_user_tuu_orders.php` (YA IMPLEMENTADO)
- ❌ Falta mostrar premios disponibles según sellos (UI PENDIENTE)
- ❌ Falta botones "Aplicar Delivery Gratis" / "Aplicar Papas + Bebida" (UI PENDIENTE)

### **3. Usuario aplica premio en Checkout**
- ❌ Click en "Aplicar Delivery Gratis" o "Aplicar Papas + Bebida" (PENDIENTE)
- ❌ Llamar `create_coupon.php` para crear cupón (PENDIENTE)
- ❌ Aplicar descuento/productos gratis en UI (PENDIENTE)
- ❌ Guardar `coupon_id` para marcar como usado después (PENDIENTE)

### **4. Usuario completa la compra**
- ❌ Al confirmar pedido, llamar `use_coupon.php` (PENDIENTE)
- ❌ Cupón se marca como `status='used'` (PENDIENTE)
- ❌ Se registra `order_id` y `used_at` (PENDIENTE)

---

## 🔢 Cálculo de Sellos Disponibles

### **Fórmula en `get_user_tuu_orders.php`**
```javascript
// Sellos ganados por compras
total_stamps = floor(total_points / 1000)

// Sellos consumidos en cupones
consumed_stamps = SUM(user_coupons.stamps_used WHERE user_id = X)

// Sellos disponibles para canjear
available_stamps = total_stamps - consumed_stamps

// Sellos en nivel actual (0-5)
currentLevelStamps = available_stamps % 6
```

### **Ejemplo Práctico**
```
Usuario tiene:
- 8.000 puntos = 8 sellos ganados
- 1 cupón delivery usado (2 sellos consumidos)
- available_stamps = 8 - 2 = 6 sellos
- currentLevelStamps = 6 % 6 = 0 sellos
- Nivel Bronze completado ✅
- Cashback $6.000 recibido ✅
- Ahora está en nivel Silver con 0/6 sellos
```

---

## 📋 Estado Real de CheckoutApp.jsx

### ✅ **Ya Implementado**
```javascript
// Líneas 100-115: Carga de rewards desde API
useEffect(() => {
  if (user) {
    fetch('/api/get_user_tuu_orders.php', {
      method: 'POST',
      body: JSON.stringify({ user_id: user.id })
    })
    .then(data => {
      const stamps = data.available_stamps || 0;
      const rewards = [];
      if (stamps >= 2) rewards.push({ id: 'delivery', name: 'Delivery Gratis', stamps: 2 });
      if (stamps >= 4) rewards.push({ id: 'combo', name: 'Papas + Bebida Gratis', stamps: 4 });
      setAvailableRewards(rewards);
    });
  }
}, [user]);
```

### ❌ **Falta Implementar**
1. **UI de Premios Disponibles** - Sección visual mostrando rewards
2. **Botón Delivery Gratis** - Aplicar descuento en delivery_fee
3. **Modal Papas + Bebida** - Selector de productos gratis
4. **Crear Cupón** - Llamar `create_coupon.php` al aplicar
5. **Marcar Usado** - Llamar `use_coupon.php` al pagar
6. **Integración Minicomandas** - Sistema de tickets por estaciónl Sistema

### **1. Usuario ve sus sellos en ProfileModalModern**
- Muestra sellos disponibles en nivel actual
- Botón "Canjear" es informativo (explica cómo usar en checkout)
- NO crea cupones aquí

### **2. Usuario va a Checkout**
- Checkout verifica sellos disponibles vía `get_user_tuu_orders.php`
- Muestra premios disponibles según sellos:
  - 2+ sellos → Mostrar "Usar Delivery Gratis"
  - 4+ sellos → Mostrar "Usar Papas + Bebida Gratis"

### **3. Usuario aplica premio en Checkout**
- Click en "Aplicar Delivery Gratis" o "Aplicar Papas + Bebida"
- **AQUÍ se crea el cupón** vía `create_coupon.php`
- API valida sellos disponibles y crea cupón
- Se aplica el descuento/productos gratis en UI
- Se guarda `coupon_id` para marcar como usado después

### **4. Usuario completa la compra**
- Al confirmar pedido, se llama `use_coupon.php`
- Cupón se marca como `status='used'`
- Se registra `order_id` (order_number) y `used_at`
- **IMPORTANTE**: El pedido en `tuu_orders` NO registra `reward_stamps_consumed` porque los sellos ya se consumieron al crear el cupón

---

## 🔢 Cálculo de Sellos Disponibles

### **Fórmula en `get_user_tuu_orders.php`**
```javascript
// Sellos ganados por compras
total_stamps = floor(total_points / 1000)

// Sellos consumidos en cupones
consumed_stamps = SUM(user_coupons.stamps_used WHERE user_id = X)

// Sellos disponibles para canjear
available_stamps = total_stamps - consumed_stamps

// Sellos en nivel actual (0-5)
currentLevelStamps = available_stamps % 6
```

### **Ejemplo Práctico**
```
Usuario tiene:
- 8.000 puntos = 8 sellos ganados
- 1 cupón delivery usado (2 sellos consumidos)
- available_stamps = 8 - 2 = 6 sellos
- currentLevelStamps = 6 % 6 = 0 sellos
- Nivel Bronze completado ✅
- Cashback $6.000 recibido ✅
- Ahora está en nivel Silver con 0/6 sellos
```ed'`
- Se registra `order_id` y `used_at`

### **Código Ejemplo - CheckoutApp.jsx**
```javascript
// Verificar sellos al cargar
const checkRewards = async () => {
    const res = await fetch('/api/get_user_tuu_orders.php', {
        method: 'POST',
        body: JSON.stringify({ user_id: user.id })
    });
    const data = await res.json();
    const stamps = data.available_stamps % 6;
    
    setCanUseDelivery(stamps >= 2);
    setCanUsePapas(stamps >= 4);
};

// Aplicar premio (crea cupón)
const applyReward = async (type) => {
    const res = await fetch('/api/coupons/create_coupon.php', {
        method: 'POST',
        body: JSON.stringify({ user_id: user.id, coupon_type: type })
    });
    const data = await res.json();
    
    if (data.success) {
        setAppliedCouponId(data.coupon_id);
        if (type === 'delivery_free') setDeliveryFee(0);
    }
};

// Completar compra (marca cupón usado)
const finalizePurchase = async () => {
    if (appliedCouponId) {
        await fetch('/api/coupons/use_coupon.php', {
            method: 'POST',
            body: JSON.stringify({ 
                coupon_id: appliedCouponId, 
                order_id: orderNumber 
            })
        });
    }
};
```DeliveryCoupon = (couponId) => {
    setDeliveryFee(0);
    setAppliedCouponId(couponId);
};

// 3. Aplicar cupón de papas + bebida
const applyPapasBebidaCoupon = (couponId) => {
    // Mostrar selector de papas y bebidas con precio $0
    setShowFreeProductSelector(true);
    setAppliedCouponId(couponId);
};

// 4. Al completar pedido, marcar cupón como usado
const completePurchase = async () => {
    // ... procesar pago ...
    
    if (appliedCouponId) {
        await fetch('/api/coupons/use_coupon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                coupon_id: appliedCouponId,
                order_id: orderNumber
            })
        });
    }
};
```


---

## 📝 Ejemplo Completo del Flujo

**Usuario**: Ricardo tiene 4 sellos disponibles

**Paso 1**: Agrega Hamburguesa ($7.280) al carrito
**Paso 2**: Ve cinta de bebidas y agrega Bilz ($1.190)
**Paso 3**: Ve botón "🎁 Usar Papas + Bebida Gratis (4 sellos)"
**Paso 4**: Click → Se abre modal con papas y bebidas
**Paso 5**: Selecciona "Papas Fritas Individual" y "Coca-Cola 500ml"
**Paso 6**: Confirma → Se crean 2 items con precio $0 en carrito
**Paso 7**: Aplica código R11LOV (-$847)
**Paso 8**: Usa cashback (-$6.000)
**Paso 9**: Agrega extras delivery ($1.000)
**Paso 10**: Total final: $5.123

**Desglose final**:
```
Hamburguesa Clásica: $7.280
Bilz Lata 350ml: $1.190
🎁 Papas Fritas Individual (PREMIO): $0
🎁 Coca-Cola 500ml (PREMIO): $0
Descuento R11LOV: -$847
Cashback: -$6.000
Delivery: $2.500
Extras: $1.000
Total: $5.123
```

**Inventario descontado**:
- Hamburguesa Clásica (stock -1)
- Bilz Lata 350ml (stock -1)
- Papas Fritas Individual (stock -1) ← PREMIO
- Coca-Cola 500ml (stock -1) ← PREMIO

**Puntos generados**: 
- Solo de productos pagados: $8.470 / 10 = 847 puntos
- Productos de premio NO generan puntos
