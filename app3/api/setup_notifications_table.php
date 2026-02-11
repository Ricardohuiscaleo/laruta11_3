<?php
header('Content-Type: application/json');

$configPaths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'No se pudo encontrar config.php']);
    exit;
}

// Crear tabla en Supabase usando API REST
$supabaseUrl = $config['PUBLIC_SUPABASE_URL'];
$supabaseKey = $config['PUBLIC_SUPABASE_ANON_KEY'];

// SQL para crear la tabla
$createTableSQL = "
CREATE TABLE IF NOT EXISTS order_notifications (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    customer_name VARCHAR(100),
    status VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Crear índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_order_notifications_order_id ON order_notifications(order_id);
CREATE INDEX IF NOT EXISTS idx_order_notifications_created_at ON order_notifications(created_at);

-- Habilitar RLS (Row Level Security)
ALTER TABLE order_notifications ENABLE ROW LEVEL SECURITY;

-- Política para permitir insertar notificaciones
CREATE POLICY IF NOT EXISTS \"Allow insert notifications\" ON order_notifications
    FOR INSERT WITH CHECK (true);

-- Política para permitir leer notificaciones
CREATE POLICY IF NOT EXISTS \"Allow read notifications\" ON order_notifications
    FOR SELECT USING (true);
";

// Ejecutar SQL usando la API de Supabase
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $supabaseUrl . '/rest/v1/rpc/exec_sql');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sql' => $createTableSQL]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . $supabaseKey,
    'Authorization: Bearer ' . $supabaseKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode([
        'success' => true,
        'message' => 'Tabla order_notifications creada exitosamente en Supabase'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error creando tabla en Supabase',
        'http_code' => $httpCode,
        'response' => $response
    ]);
}
?>