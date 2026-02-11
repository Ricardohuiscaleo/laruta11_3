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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $names = ['Francisca Morales', 'Ignacio Silva', 'Catalina Herrera', 'Tomás González', 'Javiera López', 'Nicolás Torres', 'Constanza Castro', 'Felipe Vargas', 'Martina Rojas', 'Agustín Soto', 'Florencia Muñoz', 'Maximiliano Peña', 'Isadora Contreras', 'Santiago Martínez', 'Esperanza Rivera'];
    
    $positiveComments = [
        '¡Increíble hamburguesa! La mejor de Arica sin duda',
        'Espectacular sabor, la carne jugosa y el pan perfecto',
        'Una delicia total, volveré por más',
        '10/10 - Superó todas mis expectativas',
        'La hamburguesa más sabrosa que he probado',
        'Excelente calidad, ingredientes frescos y muy rica',
        'Perfecta combinación de sabores, me encantó',
        'Buenísima! La recomiendo 100%',
        'Increíble sabor casero, como debe ser',
        'Una obra de arte culinaria, felicitaciones',
        'Deliciosa y abundante, excelente precio',
        'La mejor hamburguesa de la ciudad, sin competencia',
        'Sabor único y auténtico, volveré pronto',
        'Excelente preparación, se nota la calidad',
        'Simplemente perfecta, no cambien nada',
        'Increíble experiencia gastronómica',
        'La hamburguesa de mis sueños hecha realidad',
        'Calidad premium a precio justo',
        'Sabores que explotan en el paladar',
        'Una delicia que no puedo dejar de recomendar'
    ];
    
    // Productos de hamburguesas que necesitan más reseñas
    $hamburguesaProducts = [10, 11, 12, 13];
    
    $insertedReviews = 0;
    
    foreach ($hamburguesaProducts as $productId) {
        $reviewsCount = rand(6, 10); // 6-10 reseñas por hamburguesa
        
        for ($i = 0; $i < $reviewsCount; $i++) {
            $name = $names[array_rand($names)];
            $rating = rand(4, 5); // Solo 4 y 5 estrellas (80% positivas)
            $comment = $positiveComments[array_rand($positiveComments)];
            
            // Fechas aleatorias de los últimos 7 días
            $daysAgo = rand(1, 7);
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
            
            $stmt = $pdo->prepare("
                INSERT INTO reviews (product_id, customer_name, rating, comment, created_at, is_approved) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$productId, $name, $rating, $comment, $createdAt]);
            $insertedReviews++;
        }
    }
    
    // Agregar algunas reseñas más a productos existentes para balancear
    $existingProducts = [1, 4, 6, 9]; // Productos que ya tienen reseñas
    
    foreach ($existingProducts as $productId) {
        $reviewsCount = rand(3, 5); // 3-5 reseñas adicionales
        
        for ($i = 0; $i < $reviewsCount; $i++) {
            $name = $names[array_rand($names)];
            $rating = rand(4, 5); // Solo positivas
            $comment = $positiveComments[array_rand($positiveComments)];
            
            $daysAgo = rand(1, 7);
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
        'message' => "Se agregaron {$insertedReviews} reseñas positivas",
        'reviews_added' => $insertedReviews,
        'hamburguesas_updated' => count($hamburguesaProducts),
        'note' => 'Mayoría de reseñas son 4-5 estrellas para mantener buena reputación'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>