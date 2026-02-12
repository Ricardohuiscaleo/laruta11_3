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

    $product_id = $_GET['product_id'] ?? null;
    
    if ($product_id) {
        // Obtener reseñas de un producto específico
        $stmt = $pdo->prepare("
            SELECT 
                id,
                customer_name,
                rating,
                comment,
                created_at
            FROM reviews 
            WHERE product_id = ? AND is_approved = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute([$product_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular estadísticas
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as five_stars,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as four_stars,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as three_stars,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as two_stars,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
            FROM reviews 
            WHERE product_id = ? AND is_approved = 1
        ");
        $stats_stmt->execute([$product_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'stats' => [
                'total' => (int)$stats['total_reviews'],
                'average' => round($stats['average_rating'], 1),
                'distribution' => [
                    5 => (int)$stats['five_stars'],
                    4 => (int)$stats['four_stars'],
                    3 => (int)$stats['three_stars'],
                    2 => (int)$stats['two_stars'],
                    1 => (int)$stats['one_star']
                ]
            ]
        ]);
    } else {
        // Obtener todas las reseñas recientes
        $stmt = $pdo->query("
            SELECT 
                r.id,
                r.product_id,
                p.name as product_name,
                r.customer_name,
                r.rating,
                r.comment,
                r.created_at
            FROM reviews r
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.is_approved = 1
            ORDER BY r.created_at DESC
            LIMIT 50
        ");
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>