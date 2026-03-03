<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db_connect.php';

try {
    $pdo = require __DIR__ . '/../db_connect.php';

    // Auto-migración selectiva: asegurar que las columnas necesarias existen para el SELECT
    $check_cols = [
        'rut' => "VARCHAR(12) NULL",
        'grado_militar' => "VARCHAR(100) NULL",
        'unidad_trabajo' => "VARCHAR(255) NULL",
        'domicilio_particular' => "TEXT NULL",
        'es_militar_rl6' => "TINYINT(1) DEFAULT 0",
        'credito_aprobado' => "TINYINT(1) DEFAULT 0",
        'limite_credito' => "DECIMAL(10,2) DEFAULT 0.00",
        'credito_usado' => "DECIMAL(10,2) DEFAULT 0.00",
        'selfie_url' => "VARCHAR(500) NULL",
        'carnet_frontal_url' => "VARCHAR(500) NULL",
        'carnet_trasero_url' => "VARCHAR(500) NULL",
        'fecha_solicitud_rl6' => "TIMESTAMP NULL",
        'fecha_aprobacion_rl6' => "TIMESTAMP NULL",
        'credito_disponible' => "DECIMAL(10,2) DEFAULT 0.00"
    ];

    foreach ($check_cols as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN $col $definition");
        }
    }

    $sql = "
        SELECT 
            u.id,
            u.nombre as name,
            u.email,
            u.telefono as phone,
            u.fecha_registro as registration_date,
            u.activo as is_active,
            u.direccion as city,
            u.rut,
            u.grado_militar,
            u.unidad_trabajo,
            u.domicilio_particular,
            u.es_militar_rl6,
            u.credito_aprobado,
            u.limite_credito,
            u.credito_usado,
            u.credito_disponible,
            u.selfie_url,
            u.carnet_frontal_url,
            u.carnet_trasero_url,
            u.fecha_solicitud_rl6,
            u.fecha_aprobacion_rl6,
            COALESCE(o.order_count, 0) as total_orders,
            COALESCE(o.total_spent, 0) as total_spent,
            o.last_order_date,
            o.delivery_count,
            o.pickup_count,
            o.rewards_used,
            o.total_stamps_earned,
            o.stamps_consumed
        FROM usuarios u
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as order_count,
                SUM(product_price) as total_spent,
                MAX(created_at) as last_order_date,
                SUM(CASE WHEN delivery_type = 'delivery' THEN 1 ELSE 0 END) as delivery_count,
                SUM(CASE WHEN delivery_type = 'pickup' THEN 1 ELSE 0 END) as pickup_count,
                SUM(CASE WHEN reward_used IS NOT NULL THEN 1 ELSE 0 END) as rewards_used,
                FLOOR(SUM(product_price - COALESCE(delivery_fee, 0)) / 10000) as total_stamps_earned,
                COALESCE(SUM(reward_stamps_consumed), 0) as stamps_consumed
            FROM tuu_orders
            WHERE payment_status = 'paid'
            GROUP BY user_id
        ) o ON u.id = o.user_id
        ORDER BY u.id DESC
    ";

    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);

}
catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}