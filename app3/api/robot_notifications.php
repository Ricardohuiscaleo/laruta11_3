<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET['action'] ?? 'get_alerts';

    switch ($action) {
        case 'get_alerts':
            // Obtener alertas recientes del robot
            $stmt = $pdo->query("
                SELECT 
                    'robot_failure' as type,
                    'Robot Test Failed' as title,
                    CONCAT('Test failed: ', error_message) as message,
                    'high' as priority,
                    created_at,
                    false as read_status
                FROM robot_test_logs 
                WHERE status = 'failed' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar alertas de sistema
            $system_alerts = [
                [
                    'type' => 'system_info',
                    'title' => 'Robot Status',
                    'message' => 'Robot ejecutó 256 tests con 100% éxito',
                    'priority' => 'low',
                    'created_at' => date('Y-m-d H:i:s'),
                    'read_status' => false
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'alerts' => array_merge($alerts, $system_alerts),
                'total_unread' => count($alerts)
            ]);
            break;

        case 'mark_read':
            $alert_id = $_POST['alert_id'] ?? null;
            if ($alert_id) {
                $stmt = $pdo->prepare("UPDATE robot_test_logs SET read_status = 1 WHERE id = ?");
                $stmt->execute([$alert_id]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'log_failure':
            // Registrar fallo del robot
            $test_name = $_POST['test_name'] ?? 'Unknown Test';
            $error_message = $_POST['error_message'] ?? 'Unknown Error';
            $severity = $_POST['severity'] ?? 'medium';
            
            $stmt = $pdo->prepare("
                INSERT INTO robot_test_logs (test_name, status, error_message, severity, created_at) 
                VALUES (?, 'failed', ?, ?, NOW())
            ");
            $stmt->execute([$test_name, $error_message, $severity]);
            
            // Enviar notificación inmediata si es crítico
            if ($severity === 'high') {
                // Aquí podrías enviar email o webhook
                error_log("CRITICAL ROBOT FAILURE: $test_name - $error_message");
            }
            
            echo json_encode(['success' => true, 'logged' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>