<?php
error_log('🔍 [DEBUG] === CHECK SESSION INICIO ===');
error_log('🔍 [DEBUG] User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
error_log('🔍 [DEBUG] Cookies recibidas: ' . json_encode($_COOKIE));

// Configurar sesión persistente (30 días)
ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
session_start();

error_log('🔍 [DEBUG] Session ID: ' . session_id());
error_log('🔍 [DEBUG] Session name: ' . session_name());
error_log('🔍 [DEBUG] Session data: ' . json_encode($_SESSION));

// Renovar cookie de sesión
if (isset($_COOKIE[session_name()])) {
    error_log('✅ [DEBUG] Cookie de sesión existe, renovando...');
    setcookie(session_name(), session_id(), time() + 2592000, '/', '', true, true);
} else {
    error_log('⚠️ [DEBUG] Cookie de sesión NO existe');
}

$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    error_log('✅ [DEBUG] Usuario en sesión: ' . $_SESSION['user']['nombre']);
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
    error_log('❌ [DEBUG] NO hay usuario en sesión');
    echo json_encode(['authenticated' => false]);
}
error_log('🔍 [DEBUG] === CHECK SESSION FIN ===');
?>