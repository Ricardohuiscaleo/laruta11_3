# Push Notifications - La Ruta 11

## 📋 Resumen
Sistema de notificaciones push para PWA que funciona en Android y iOS (16.4+) con sonido y vibración.

## 🎯 Casos de Uso

### Para Cajeros (caja3)
- 🔔 **Nuevo pedido recibido** (sonoro + vibración)
- 💳 **Pago confirmado** (TUU/Webpay)
- ⚠️ **Pedido cancelado por cliente**
- 📊 **Resumen diario de ventas**

### Para Clientes (app3)
- ✅ **Pedido confirmado**
- 👨‍🍳 **Pedido en preparación**
- 🍔 **Pedido listo para recoger**
- 🚚 **Pedido en camino** (delivery)
- 📍 **Repartidor cerca** (geolocalización)
- 🎉 **Promociones especiales**

## 🔧 Implementación Técnica

### 1. Service Worker (sw.js)

```javascript
// Escuchar notificaciones push
self.addEventListener('push', event => {
  const data = event.data.json();
  
  const options = {
    body: data.body,
    icon: '/icon-192.png',
    badge: '/badge-72.png',
    vibrate: [200, 100, 200, 100, 200], // Patrón de vibración
    silent: false, // CON sonido
    requireInteraction: data.priority === 'high', // Requiere acción del usuario
    data: { 
      url: data.url,
      orderId: data.orderId 
    },
    actions: data.actions || [] // Botones de acción
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Manejar clicks en notificaciones
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view') {
    // Abrir pedido específico
    clients.openWindow(event.notification.data.url);
  } else if (event.action === 'dismiss') {
    // Solo cerrar
  } else {
    // Click en el cuerpo de la notificación
    clients.openWindow(event.notification.data.url);
  }
});
```

### 2. Frontend - Pedir Permisos

```javascript
// En MenuApp.jsx o componente principal
async function requestNotificationPermission() {
  if (!('Notification' in window)) {
    console.log('Este navegador no soporta notificaciones');
    return false;
  }
  
  if (Notification.permission === 'granted') {
    return true;
  }
  
  if (Notification.permission !== 'denied') {
    const permission = await Notification.requestPermission();
    return permission === 'granted';
  }
  
  return false;
}

// Suscribirse a push notifications
async function subscribeToPush() {
  const hasPermission = await requestNotificationPermission();
  if (!hasPermission) return;
  
  try {
    const registration = await navigator.serviceWorker.ready;
    
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
    });
    
    // Enviar subscription al servidor
    await fetch('/api/push/subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        subscription: subscription,
        user_type: 'cashier' // o 'customer'
      })
    });
    
    console.log('✅ Suscrito a notificaciones push');
  } catch (error) {
    console.error('Error al suscribirse:', error);
  }
}

// Convertir VAPID key
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');
  
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}
```

### 3. Backend - Generar VAPID Keys

```bash
# Instalar librería web-push
composer require minishlink/web-push
```

```php
<?php
// scripts/generate_vapid_keys.php
require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
?>
```

### 4. Backend - Guardar Subscriptions

```php
<?php
// api/push/subscribe.php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['cashier']) && !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$config = require __DIR__ . '/../../config.php';
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

$input = json_decode(file_get_contents('php://input'), true);
$subscription = json_encode($input['subscription']);
$user_type = $input['user_type']; // 'cashier' o 'customer'
$user_id = $_SESSION['cashier']['username'] ?? $_SESSION['user']['id'];

$stmt = mysqli_prepare($conn, 
    "INSERT INTO push_subscriptions (user_id, user_type, subscription, created_at) 
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE subscription = ?, updated_at = NOW()"
);

mysqli_stmt_bind_param($stmt, "ssss", $user_id, $user_type, $subscription, $subscription);
mysqli_stmt_execute($stmt);

echo json_encode(['success' => true]);
?>
```

### 5. Backend - Enviar Notificaciones

```php
<?php
// api/push/send_notification.php
require_once __DIR__ . '/../../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendPushNotification($userId, $userType, $title, $body, $url, $priority = 'normal') {
    $config = require __DIR__ . '/../../config.php';
    $conn = mysqli_connect(
        $config['ruta11_db_host'],
        $config['ruta11_db_user'],
        $config['ruta11_db_pass'],
        $config['ruta11_db_name']
    );
    
    // Obtener subscriptions del usuario
    $stmt = mysqli_prepare($conn, 
        "SELECT subscription FROM push_subscriptions 
         WHERE user_id = ? AND user_type = ? AND is_active = 1"
    );
    mysqli_stmt_bind_param($stmt, "ss", $userId, $userType);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:saboresdelaruta11@gmail.com',
            'publicKey' => getenv('VAPID_PUBLIC_KEY'),
            'privateKey' => getenv('VAPID_PRIVATE_KEY')
        ]
    ];
    
    $webPush = new WebPush($auth);
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'url' => $url,
        'priority' => $priority,
        'actions' => [
            ['action' => 'view', 'title' => 'Ver'],
            ['action' => 'dismiss', 'title' => 'Cerrar']
        ]
    ]);
    
    $sent = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $subscription = Subscription::create(json_decode($row['subscription'], true));
        $webPush->queueNotification($subscription, $payload);
        $sent++;
    }
    
    // Enviar todas las notificaciones
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            error_log("Push notification failed: " . $report->getReason());
        }
    }
    
    return $sent;
}
?>
```

### 6. Tabla de Base de Datos

```sql
CREATE TABLE push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    user_type ENUM('cashier', 'customer') NOT NULL,
    subscription TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id, user_type)
);
```

## 📱 Compatibilidad

| Plataforma | Soporte | Requisitos |
|------------|---------|------------|
| Android Chrome | ✅ SÍ | PWA instalada |
| Android Firefox | ✅ SÍ | PWA instalada |
| Android Samsung | ✅ SÍ | PWA instalada |
| iOS Safari | ✅ SÍ | PWA instalada + iOS 16.4+ |
| iOS Chrome/Firefox | ❌ NO | Usan motor Safari |
| Desktop Chrome | ✅ SÍ | - |
| Desktop Firefox | ✅ SÍ | - |
| Desktop Safari | ✅ SÍ | macOS 13+ |

## 🔊 Características de Sonido

- **Android**: Usa sonido de notificación del sistema (configurable por usuario)
- **iOS**: Usa sonido de notificación del sistema (no personalizable)
- **Desktop**: Usa sonido del navegador

## 🎨 Tipos de Notificaciones

### Alta Prioridad (requireInteraction: true)
- Nuevo pedido para cajeros
- Pago confirmado
- Pedido cancelado

### Prioridad Normal
- Pedido en preparación
- Pedido listo
- Promociones

### Baja Prioridad (silent: true)
- Actualizaciones de estado
- Sincronización de datos

## 🚀 Plan de Implementación

### Fase 1: Setup Básico
1. ✅ Generar VAPID keys
2. ✅ Crear tabla `push_subscriptions`
3. ✅ Instalar `minishlink/web-push`
4. ✅ Actualizar Service Worker

### Fase 2: Frontend
1. ✅ Agregar botón "Activar notificaciones"
2. ✅ Implementar `subscribeToPush()`
3. ✅ Guardar subscription en servidor

### Fase 3: Backend
1. ✅ Endpoint `/api/push/subscribe.php`
2. ✅ Función `sendPushNotification()`
3. ✅ Integrar con eventos de pedidos

### Fase 4: Testing
1. ✅ Probar en Android
2. ✅ Probar en iOS 16.4+
3. ✅ Probar en Desktop

### Fase 5: Producción
1. ✅ Monitorear tasa de entrega
2. ✅ Manejar subscriptions expiradas
3. ✅ Analytics de notificaciones

## 📊 Eventos a Notificar

### Cajeros (caja3)
```php
// Cuando llega un nuevo pedido
sendPushNotification(
    'cajera', 
    'cashier',
    '🔔 Nuevo Pedido',
    'Pedido #1234 - $15.990',
    '/comandas',
    'high'
);
```

### Clientes (app3)
```php
// Cuando el pedido está listo
sendPushNotification(
    $userId, 
    'customer',
    '✅ Pedido Listo',
    'Tu pedido #1234 está listo para recoger',
    '/orders',
    'normal'
);
```

## 🔐 Seguridad

- ✅ VAPID keys en variables de entorno
- ✅ Validar sesión antes de suscribir
- ✅ Limpiar subscriptions inactivas
- ✅ Rate limiting en envío de notificaciones

## 📈 Métricas a Monitorear

- Tasa de suscripción (% usuarios que activan)
- Tasa de entrega (% notificaciones recibidas)
- Tasa de interacción (% clicks en notificaciones)
- Subscriptions activas vs inactivas

## 🎯 Próximos Pasos

1. [ ] Generar VAPID keys
2. [ ] Crear tabla en base de datos
3. [ ] Instalar librería web-push
4. [ ] Implementar frontend en caja3
5. [ ] Implementar backend
6. [ ] Testing en dispositivos reales
7. [ ] Deploy a producción
