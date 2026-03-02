<?php
header('Content-Type: text/plain');

echo "🔍 Debug del sistema de sync\n";
echo "============================\n\n";

// 1. Verificar permisos de escritura
$logFile = __DIR__ . '/cron_log.txt';
echo "📁 Directorio: " . __DIR__ . "\n";
echo "📝 Archivo log: " . $logFile . "\n";
echo "✍️ Permisos escritura: " . (is_writable(__DIR__) ? "SÍ" : "NO") . "\n\n";

// 2. Intentar escribir log
try {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Test manual\n", FILE_APPEND);
    echo "✅ Log test escrito correctamente\n";
} catch (Exception $e) {
    echo "❌ Error escribiendo log: " . $e->getMessage() . "\n";
}

// 3. Verificar config
echo "\n🔧 Verificando configuración...\n";
$config = null;
for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    if (file_exists(__DIR__ . '/' . $configPath)) {
        $config = require_once __DIR__ . '/' . $configPath;
        echo "✅ Config encontrado en nivel $i\n";
        break;
    }
}

if (!$config) {
    echo "❌ Config no encontrado\n";
    exit;
}

// 4. Verificar conexión DB
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    echo "✅ Conexión DB exitosa\n";
} catch (Exception $e) {
    echo "❌ Error DB: " . $e->getMessage() . "\n";
}

// 5. Verificar API Key
echo "🔑 API Key configurada: " . (isset($config['tuu_api_key']) ? "SÍ" : "NO") . "\n";

// 6. Ejecutar sync manual
echo "\n🚀 Ejecutando sync manual...\n";
try {
    include __DIR__ . '/simple_sync.php';
    echo "✅ Sync ejecutado sin errores\n";
} catch (Exception $e) {
    echo "❌ Error en sync: " . $e->getMessage() . "\n";
}

// 7. Verificar resultados
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tuu_pos_transactions WHERE DATE(payment_date_time) = ?");
$stmt->execute([$today]);
$result = $stmt->fetch();
echo "📊 Transacciones hoy: " . $result['count'] . "\n";

// 8. Mostrar log si existe
if (file_exists($logFile)) {
    echo "\n📝 Contenido del log:\n";
    echo file_get_contents($logFile);
}
