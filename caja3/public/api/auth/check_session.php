<?php
error_reporting(0);
ini_set('display_errors', '0');

// Usar configuración centralizada de sesión MySQL
require_once __DIR__ . '/../session_config.php';

$config = require __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    // Recargar datos completos del usuario desde la DB
    try {
        $conn = mysqli_connect(
            $config['app_db_host'],
            $config['app_db_user'],
            $config['app_db_pass'],
            $config['app_db_name']
        );
        
        if ($conn) {
            mysqli_set_charset($conn, 'utf8');
            $google_id = mysqli_real_escape_string($conn, $_SESSION['user']['google_id']);
            $query = "SELECT * FROM usuarios WHERE google_id = '$google_id'";
            $result = mysqli_query($conn, $query);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $user = mysqli_fetch_assoc($result);
                $_SESSION['user'] = $user; // Actualizar sesión con datos completos
                
                // Obtener stats del usuario
                $user_id = $user['id'];
                $stats_query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'paid' THEN (installment_amount - COALESCE(delivery_fee, 0)) ELSE 0 END) as total_spent
                FROM tuu_orders 
                WHERE user_id = $user_id";
                $stats_result = mysqli_query($conn, $stats_query);
                $stats = mysqli_fetch_assoc($stats_result);
                
                echo json_encode(['authenticated' => true, 'user' => $user, 'stats' => $stats]);
            } else {
                echo json_encode(['authenticated' => true, 'user' => $_SESSION['user']]);
            }
            
            mysqli_close($conn);
        } else {
            echo json_encode(['authenticated' => true, 'user' => $_SESSION['user']]);
        }
    } catch (Exception $e) {
        echo json_encode(['authenticated' => true, 'user' => $_SESSION['user']]);
    }
} else {
    echo json_encode(['authenticated' => false]);
}
?>