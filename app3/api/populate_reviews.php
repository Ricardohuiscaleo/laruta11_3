<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener productos existentes
    $stmt = $pdo->query("SELECT id FROM products LIMIT 20");
    $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($products)) {
        echo json_encode(['success' => false, 'error' => 'No hay productos para agregar reseñas']);
        exit;
    }
    
    $names = ['María González', 'Carlos Rodríguez', 'Ana López', 'Pedro Martínez', 'Sofía Silva', 'Diego Torres', 'Valentina Morales', 'Mateo Herrera', 'Camila Castro', 'Sebastián Vargas', 'Isidora Rojas', 'Benjamín Soto', 'Antonia Muñoz', 'Joaquín Peña', 'Emilia Contreras'];
    
    $comments = [
        'Excelente sabor, muy recomendado',
        'Delicioso, volveré a pedirlo',
        'Buena calidad y precio justo',
        'Me encantó, superó mis expectativas',
        'Muy sabroso y bien preparado',
        'Perfecto para compartir en familia',
        'Rico pero podría mejorar la presentación',
        'Buena porción y sabor auténtico',
        'Lo mejor que he probado en mucho tiempo',
        'Excelente atención y comida deliciosa',
        'Muy bueno, aunque un poco salado',
        'Increíble sabor, definitivamente vuelvo',
        'Buena relación calidad-precio',
        'Fresco y bien condimentado',
        'Me gustó mucho, muy recomendable',
        'Sabor casero, como debe ser',
        'Excelente preparación y presentación',
        'Muy rico, justo lo que esperaba',
        'Buena cantidad y sabor espectacular',
        'Lo recomiendo 100%, muy sabroso'
    ];
    
    $insertedReviews = 0;
    
    foreach ($products as $productId) {
        $reviewsCount = rand(3, 8); // 3-8 reseñas por producto
        
        for ($i = 0; $i < $reviewsCount; $i++) {
            $name = $names[array_rand($names)];
            $rating = rand(3, 5); // Solo ratings buenos (3-5 estrellas)
            $comment = $comments[array_rand($comments)];
            
            // Fechas aleatorias de los últimos 60 días
            $daysAgo = rand(1, 60);
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
            
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, customer_name, rating, comment, created_at, is_approved) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$productId, $name, $rating, $comment, $createdAt]);
            $insertedReviews++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Se agregaron {$insertedReviews} reseñas para " . count($products) . " productos",
        'reviews_added' => $insertedReviews,
        'products_updated' => count($products)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>