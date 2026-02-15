# 🎖️ PLAN: Sistema de Cobro Crédito RL6 con Checkout Exclusivo

## 🎯 Objetivo
Implementar un sistema de cobro del crédito RL6 con checkout exclusivo que permita a los militares pagar su deuda mensual con Webpay y reembolso automático del crédito.

---

## 📋 Análisis del Sistema Actual

### ✅ Lo que ya existe:
- **Sección de Crédito RL6** en `ProfileModalModern.jsx` (línea 280-400)
- **API `get_credit.php`** - Obtiene datos del crédito
- **API `refund_credit.php`** - Reembolsa crédito por pedidos cancelados
- **Tabla `rl6_credit_transactions`** - Historial de transacciones
- **Tabla `tuu_orders`** - Órdenes con columnas `tuu_message` y `tuu_amount`
- **Sistema TUU Payment** - `create_payment_direct.php` para Webpay

### 🔍 Estructura de Datos Identificada:
```sql
-- usuarios (militares RL6)
es_militar_rl6 = 1
credito_aprobado = 1
limite_credito = 50000
credito_usado = 0-50000
grado_militar = "Cabo 2°"
unidad_trabajo = "Compañía de Abastecimiento"

-- tuu_orders (para verificar pagos)
tuu_message = "Transaccion aprobada"
tuu_amount = monto_pagado
user_id = id_cliente

-- rl6_credit_transactions (historial)
type = 'refund' | 'debit' | 'credit'
description = "Reembolso - Crédito pagado"
```

---

## 🚀 IMPLEMENTACIÓN

### **FASE 1: Botón "Pagar Crédito" en ProfileModal**

#### 1.1 Modificar `ProfileModalModern.jsx`
**Ubicación**: Después de la tarjeta de "Crédito Disponible" (línea ~350)

```jsx
{/* Botón Pagar Crédito - AGREGAR DESPUÉS DE LA TARJETA PRINCIPAL */}
{rl6Credit && rl6Credit.credit.credito_usado > 0 && (
  <Card className="p-4 bg-amber-900/20 border-amber-600">
    <div className="text-center">
      <h4 className="text-amber-300 font-bold mb-2">💳 Pagar Deuda del Mes</h4>
      <p className="text-slate-300 text-sm mb-3">
        Deuda actual: <span className="font-bold text-red-400">
          ${parseInt(rl6Credit.credit.credito_usado).toLocaleString('es-CL')}
        </span>
      </p>
      <button
        onClick={() => setShowPayCreditModal(true)}
        className="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center gap-2"
      >
        <CreditCard size={20} />
        Pagar con Webpay
      </button>
      <p className="text-xs text-slate-400 mt-2">
        Al pagar se reembolsará tu crédito automáticamente
      </p>
    </div>
  </Card>
)}
```

#### 1.2 Agregar Estado para Modal
```jsx
const [showPayCreditModal, setShowPayCreditModal] = useState(false);
```

---

### **FASE 2: Modal de Confirmación de Pago**

#### 2.1 Crear `PayCreditModal.jsx`
**Ubicación**: `app3/src/components/modals/PayCreditModal.jsx`

```jsx
import React, { useState } from 'react';
import { X, CreditCard, AlertTriangle, CheckCircle2 } from 'lucide-react';

const PayCreditModal = ({ 
  isOpen, 
  onClose, 
  creditData, 
  user,
  onPaymentSuccess 
}) => {
  const [isProcessing, setIsProcessing] = useState(false);
  
  if (!isOpen || !creditData) return null;
  
  const debtAmount = creditData.credit.credito_usado;
  
  const handlePayCredit = async () => {
    setIsProcessing(true);
    
    try {
      const paymentData = {
        amount: debtAmount,
        customer_name: user.nombre,
        customer_phone: user.telefono || '56912345678',
        customer_email: user.email,
        user_id: user.id,
        payment_type: 'rl6_credit_payment',
        credit_amount: debtAmount
      };
      
      const response = await fetch('/api/rl6/create_credit_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(paymentData)
      });
      
      const result = await response.json();
      
      if (result.success) {
        // Redirigir a Webpay
        window.location.href = result.payment_url;
      } else {
        alert('Error: ' + result.error);
        setIsProcessing(false);
      }
    } catch (error) {
      alert('Error de conexión');
      setIsProcessing(false);
    }
  };
  
  return (
    <>
      <div className="fixed inset-0 bg-black/50 z-50" onClick={onClose} />
      <div className="fixed inset-0 flex items-center justify-center z-50 p-4">
        <div className="bg-slate-800 rounded-xl border border-slate-700 w-full max-w-md">
          {/* Header */}
          <div className="flex justify-between items-center p-4 border-b border-slate-700">
            <h3 className="text-white font-bold flex items-center gap-2">
              <CreditCard className="text-amber-500" size={20} />
              Pagar Crédito RL6
            </h3>
            <button onClick={onClose} className="text-slate-400 hover:text-white">
              <X size={20} />
            </button>
          </div>
          
          {/* Content */}
          <div className="p-4 space-y-4">
            {/* Resumen */}
            <div className="bg-amber-900/20 border border-amber-600 rounded-lg p-4">
              <h4 className="text-amber-300 font-bold mb-2">📋 Resumen del Pago</h4>
              <div className="space-y-2 text-sm">
                <div className="flex justify-between">
                  <span className="text-slate-400">Deuda actual:</span>
                  <span className="text-red-400 font-bold">
                    ${parseInt(debtAmount).toLocaleString('es-CL')}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-400">Método de pago:</span>
                  <span className="text-white">Webpay</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-400">Crédito a reembolsar:</span>
                  <span className="text-green-400 font-bold">
                    +${parseInt(debtAmount).toLocaleString('es-CL')}
                  </span>
                </div>
              </div>
            </div>
            
            {/* Advertencia */}
            <div className="bg-blue-900/20 border border-blue-600 rounded-lg p-3">
              <div className="flex gap-2">
                <AlertTriangle size={16} className="text-blue-400 flex-shrink-0 mt-0.5" />
                <div className="text-xs text-blue-300">
                  <p className="font-medium mb-1">¿Qué pasará después del pago?</p>
                  <ul className="space-y-1 text-blue-200">
                    <li>✓ Se procesará el pago con Webpay</li>
                    <li>✓ Tu crédito se reembolsará automáticamente</li>
                    <li>✓ Podrás volver a usar tu límite completo</li>
                    <li>✓ Recibirás confirmación por email</li>
                  </ul>
                </div>
              </div>
            </div>
            
            {/* Botones */}
            <div className="flex gap-3">
              <button
                onClick={onClose}
                className="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-medium py-3 rounded-lg transition-colors"
              >
                Cancelar
              </button>
              <button
                onClick={handlePayCredit}
                disabled={isProcessing}
                className="flex-1 bg-amber-500 hover:bg-amber-600 disabled:bg-amber-500/50 text-white font-bold py-3 rounded-lg transition-colors flex items-center justify-center gap-2"
              >
                {isProcessing ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                    Procesando...
                  </>
                ) : (
                  <>
                    <CreditCard size={16} />
                    Pagar ${parseInt(debtAmount).toLocaleString('es-CL')}
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default PayCreditModal;
```

---

### **FASE 3: API de Pago de Crédito**

#### 3.1 Crear `create_credit_payment.php`
**Ubicación**: `app3/api/rl6/create_credit_payment.php`

```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $user_id = $input['user_id'];
    $credit_amount = $input['credit_amount'];
    $order_id = 'RL6-PAY-' . time() . '-' . rand(1000, 9999);
    
    // Validar que es militar RL6 con deuda
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("
        SELECT es_militar_rl6, credito_aprobado, credito_usado 
        FROM usuarios 
        WHERE id = ? AND es_militar_rl6 = 1 AND credito_aprobado = 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || $user['credito_usado'] <= 0) {
        throw new Exception('Usuario no válido o sin deuda');
    }
    
    if ($amount != $user['credito_usado']) {
        throw new Exception('Monto no coincide con la deuda actual');
    }
    
    // Guardar orden de pago de crédito
    $pdo->beginTransaction();
    
    $order_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone, 
        product_name, product_price, installment_amount, 
        payment_type, credit_payment_amount,
        status, payment_status, order_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'rl6_credit_payment', ?, 'pending', 'unpaid', 'pending')";
    
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([
        $order_id, $user_id, $customer_name, $customer_phone,
        'Pago Crédito RL6', $amount, $amount, $credit_amount
    ]);
    
    $pdo->commit();
    
    // CREAR PAGO TUU (igual que create_payment_direct.php)
    
    // PASO 1: Obtener Token TUU
    $url_base = 'https://core.payment.haulmer.com/api/v1/payment';
    $token_url = $url_base . '/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error obteniendo token TUU - HTTP $httpCode");
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido de TUU');
    }
    
    // DECODIFICAR JWT
    $jwt_parts = explode('.', $token_data['token']);
    if (count($jwt_parts) !== 3) {
        throw new Exception('Token JWT inválido');
    }
    
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    if (!isset($payload['secret_key']) || !isset($payload['account_id'])) {
        throw new Exception('Token JWT no contiene datos necesarios');
    }
    
    $secret_key = $payload['secret_key'];
    $account_id = $payload['account_id'];
    
    // PASO 2: Crear Transacción
    $transaction_data = [
        'platform' => 'ruta11app',
        'paymentMethod' => 'webpay',
        'x_account_id' => $account_id,
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $customer_email,
        'x_customer_first_name' => explode(' ', $customer_name)[0],
        'x_customer_last_name' => explode(' ', $customer_name)[1] ?? '',
        'x_customer_phone' => $customer_phone,
        'x_description' => 'Pago Crédito RL6 - La Ruta 11',
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta11 Foodtrucks',
        'x_url_callback' => 'https://app.laruta11.cl/api/rl6/callback_credit_payment.php',
        'x_url_cancel' => 'https://app.laruta11.cl/profile?payment_cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/profile?credit_paid=1',
        'secret' => '18756627',
        'dte_type' => 48
    ];
    
    // Generar firma HMAC SHA256
    ksort($transaction_data);
    $firmar = '';
    foreach ($transaction_data as $llave => $valor) {
        if (strpos($llave, 'x_') === 0) {
            $firmar .= $llave . $valor;
        }
    }
    $transaction_data['x_signature'] = hash_hmac('sha256', $firmar, $secret_key);
    
    // Agregar estructura DTE
    $transaction_data['dte'] = [
        'net_amount' => $amount,
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // PASO 3: Envío a TUU
    $ch = curl_init($url_base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($payment_httpCode !== 200) {
        error_log("TUU Payment Error - HTTP $payment_httpCode: $payment_response");
        throw new Exception("Error creando pago TUU - HTTP $payment_httpCode");
    }
    
    $webpay_url = trim($payment_response, '"');
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        error_log("TUU Payment Response: $payment_response");
        throw new Exception('URL de pago inválida recibida de TUU');
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("RL6 Credit Payment Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

---

### **FASE 4: Callback de Confirmación y Reembolso Automático**

#### 4.1 Crear `callback_credit_payment.php`
**Ubicación**: `app3/api/rl6/callback_credit_payment.php`

```php
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_reference = $input['x_reference'] ?? '';
    $tuu_amount = $input['x_amount'] ?? 0;
    $tuu_message = $input['x_response_reason_text'] ?? '';
    $tuu_status = $input['x_response'] ?? '';
    
    error_log("RL6 Credit Payment Callback: " . json_encode($input));
    
    if (empty($order_reference)) {
        throw new Exception('Referencia de orden vacía');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $pdo->beginTransaction();
    
    // 1. Actualizar orden en tuu_orders
    $stmt = $pdo->prepare("
        UPDATE tuu_orders 
        SET 
            tuu_message = ?, 
            tuu_amount = ?,
            payment_status = CASE WHEN ? = '1' THEN 'paid' ELSE 'failed' END,
            order_status = CASE WHEN ? = '1' THEN 'completed' ELSE 'failed' END,
            updated_at = NOW()
        WHERE order_number = ? AND payment_type = 'rl6_credit_payment'
    ");
    $stmt->execute([$tuu_message, $tuu_amount, $tuu_status, $tuu_status, $order_reference]);
    
    // 2. Si el pago fue exitoso, reembolsar crédito
    if ($tuu_status === '1' && $tuu_message === 'Transaccion aprobada') {
        
        // Obtener datos de la orden
        $stmt = $pdo->prepare("
            SELECT user_id, credit_payment_amount 
            FROM tuu_orders 
            WHERE order_number = ? AND payment_type = 'rl6_credit_payment'
        ");
        $stmt->execute([$order_reference]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Orden no encontrada');
        }
        
        $user_id = $order['user_id'];
        $credit_amount = $order['credit_payment_amount'];
        
        // 3. Reembolsar crédito (resetear credito_usado a 0)
        $stmt = $pdo->prepare("
            UPDATE usuarios 
            SET credito_usado = 0
            WHERE id = ? AND es_militar_rl6 = 1 AND credito_aprobado = 1
        ");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('No se pudo reembolsar el crédito');
        }
        
        // 4. Registrar transacción de reembolso
        $stmt = $pdo->prepare("
            INSERT INTO rl6_credit_transactions 
            (user_id, amount, type, description, order_id, created_at) 
            VALUES (?, ?, 'refund', 'Reembolso - Crédito pagado', ?, NOW())
        ");
        $stmt->execute([$user_id, $credit_amount, $order_reference]);
        
        error_log("RL6 Credit refunded successfully for user $user_id, amount: $credit_amount");
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Callback procesado correctamente',
        'refunded' => ($tuu_status === '1' && $tuu_message === 'Transaccion aprobada')
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("RL6 Credit Payment Callback Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

---

### **FASE 5: Modificaciones en Base de Datos**

#### 5.1 Queries SQL para ejecutar en Beekeeper Studio

```sql
-- 1. Agregar columnas a tuu_orders para pagos de crédito
ALTER TABLE tuu_orders 
ADD COLUMN IF NOT EXISTS payment_type VARCHAR(50) DEFAULT 'normal' COMMENT 'normal, rl6_credit_payment',
ADD COLUMN IF NOT EXISTS credit_payment_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Monto de crédito a reembolsar';

-- 2. Verificar estructura de rl6_credit_transactions
-- (Ya debería existir según el código revisado)
DESCRIBE rl6_credit_transactions;

-- 3. Crear índices para optimizar consultas
CREATE INDEX IF NOT EXISTS idx_tuu_orders_payment_type ON tuu_orders(payment_type);
CREATE INDEX IF NOT EXISTS idx_tuu_orders_user_payment ON tuu_orders(user_id, payment_type);
```

---

### **FASE 6: Integración en ProfileModal**

#### 6.1 Importar y usar PayCreditModal

```jsx
// En ProfileModalModern.jsx - AGREGAR IMPORT
import PayCreditModal from './PayCreditModal';

// AGREGAR AL FINAL DEL COMPONENTE, antes del cierre
{/* Modal de Pago de Crédito */}
<PayCreditModal
  isOpen={showPayCreditModal}
  onClose={() => setShowPayCreditModal(false)}
  creditData={rl6Credit}
  user={user}
  onPaymentSuccess={() => {
    setShowPayCreditModal(false);
    loadRL6Credit(); // Recargar datos
  }}
/>
```

---

## 🔄 FLUJO COMPLETO

### 1. **Usuario ve su deuda**
- En perfil → pestaña "Crédito"
- Ve: "Crédito Disponible $0" y "Usado $50.000"

### 2. **Usuario hace clic en "Pagar con Webpay"**
- Se abre `PayCreditModal`
- Muestra resumen del pago y explicación

### 3. **Usuario confirma pago**
- Se llama a `create_credit_payment.php`
- Se crea orden en `tuu_orders` con `payment_type = 'rl6_credit_payment'`
- Se redirige a Webpay

### 4. **Usuario paga en Webpay**
- TUU procesa el pago
- Llama a `callback_credit_payment.php`

### 5. **Reembolso automático**
- Si `tuu_message = 'Transaccion aprobada'`
- Se resetea `credito_usado = 0`
- Se registra transacción tipo `'refund'`
- Usuario recupera su crédito completo

### 6. **Usuario ve el resultado**
- Regresa al perfil con `?credit_paid=1`
- Ve "Crédito Disponible $50.000" y "Usado $0"
- Ve en historial: "Reembolso - Crédito pagado"

---

## 📊 EJEMPLO DE TRANSACCIÓN

### Antes del pago:
```
usuarios.credito_usado = 49770
usuarios.limite_credito = 50000
Crédito disponible = 230
```

### Después del pago exitoso:
```
usuarios.credito_usado = 0
usuarios.limite_credito = 50000
Crédito disponible = 50000

rl6_credit_transactions:
- amount: 49770
- type: 'refund'
- description: 'Reembolso - Crédito pagado'
- order_id: 'RL6-PAY-1234567890-5678'
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

### Archivos a crear:
- [ ] `app3/src/components/modals/PayCreditModal.jsx`
- [ ] `app3/api/rl6/create_credit_payment.php`
- [ ] `app3/api/rl6/callback_credit_payment.php`

### Archivos a modificar:
- [ ] `app3/src/components/modals/ProfileModalModern.jsx`
  - Agregar botón "Pagar Crédito"
  - Importar y usar PayCreditModal
  - Agregar estado showPayCreditModal

### Base de datos:
- [ ] Ejecutar queries SQL en Beekeeper Studio
- [ ] Verificar columnas en `tuu_orders`
- [ ] Verificar tabla `rl6_credit_transactions`

### Testing:
- [ ] Probar flujo completo con usuario militar RL6
- [ ] Verificar que el callback funciona correctamente
- [ ] Confirmar que el reembolso se registra bien
- [ ] Validar que el historial se muestra correctamente

---

## 🚨 CONSIDERACIONES IMPORTANTES

### Seguridad:
- ✅ Validar que solo militares RL6 aprobados puedan pagar
- ✅ Verificar que el monto coincida con la deuda actual
- ✅ Usar transacciones de BD para atomicidad

### UX:
- ✅ Modal explicativo antes del pago
- ✅ Feedback visual del proceso
- ✅ Redirección clara después del pago

### Monitoreo:
- ✅ Logs detallados en cada paso
- ✅ Error handling robusto
- ✅ Notificaciones por email (opcional)

---

**Fecha de creación**: 2026-02-12  
**Responsable**: Ricardo  
**Estado**: 📝 Listo para implementar  
**Prioridad**: 🔥 Alta
