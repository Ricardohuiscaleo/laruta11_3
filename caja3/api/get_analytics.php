<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';
$config = require '../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Visitas hoy
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as visits FROM site_visits WHERE visit_date = CURDATE()");
    $stmt->execute();
    $visits_today = $stmt->fetchColumn();

    // Visitas esta semana
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as visits FROM site_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute();
    $visits_week = $stmt->fetchColumn();

    // Visitas este mes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as visits FROM site_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $visits_month = $stmt->fetchColumn();

    // Total usuarios registrados
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM app_users WHERE is_active = 1");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();

    // Ventas hoy
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as sales FROM user_orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'");
    $stmt->execute();
    $sales_today = $stmt->fetchColumn();

    // Total productos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $stmt->execute();
    $total_products = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'visits_today' => (int)$visits_today,
            'visits_week' => (int)$visits_week,
            'visits_month' => (int)$visits_month,
            'total_users' => (int)$total_users,
            'sales_today' => (float)$sales_today,
            'total_products' => (int)$total_products
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>