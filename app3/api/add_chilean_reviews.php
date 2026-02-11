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
    
    // Primero eliminar reseñas duplicadas recientes
    $pdo->exec("DELETE FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND id > 115");
    
    $names = ['Catalina Herrera', 'Tomás González', 'Javiera López', 'Nicolás Torres', 'Constanza Castro', 'Felipe Vargas', 'Martina Rojas', 'Agustín Soto', 'Florencia Muñoz', 'Maximiliano Peña', 'Isadora Contreras', 'Santiago Martínez', 'Esperanza Rivera', 'Francisco Silva', 'Valentina Morales'];
    
    $chileanComments = [
        'Esta hamburguesa está la raja, muy recomendada',
        'Buenísima, de las mejores que he probado',
        'Está súper rica, vuelvo seguro',
        'Qué hamburguesa más bacán, me encantó',
        'Está terrible de buena, felicitaciones',
        'Muy rica, superó mis expectativas',
        'La mejor hamburguesa de Arica, sin duda',
        'Está increíble, no me la esperaba tan buena',
        'Qué manjar, quedé muy contento',
        'Sabor espectacular, la recomiendo mucho',
        'Está muy rica esta hamburguesa',
        'Qué delicia, voy a venir más seguido',
        'Muy sabrosa, me gustó caleta',
        'Está terrible de buena, me encantó',
        'Qué hamburguesa más rica, la raja',
        'Está demasiado buena, no puedo creerlo',
        'Excelente hamburguesa, muy recomendable',
        'Qué rico, me dejó muy satisfecho',
        'Está la raja, vuelvo el fin de semana',
        'Muy rica, la recomiendo completamente',
        'Sabor increíble, me gustó mucho',
        'Está buenísima, superó expectativas',
        'La hamburguesa más rica que he probado',
        'Qué delicia, está muy sabrosa',
        'Me encantó, voy a traer a mi familia'
    ];
    
    // Productos de hamburguesas
    $hamburguesaProducts = [10, 11, 12, 13];
    
    $insertedReviews = 0;
    
    foreach ($hamburguesaProducts as $productId) {
        $reviewsCount = rand(4, 6); // 4-6 reseñas únicas por hamburguesa
        $usedComments = []; // Para evitar repetir comentarios en el mismo producto
        
        for ($i = 0; $i < $reviewsCount; $i++) {
            $name = $names[array_rand($names)];
            $rating = rand(4, 5); // Solo 4 y 5 estrellas
            
            // Seleccionar comentario único para este producto
            do {
                $comment = $chileanComments[array_rand($chileanComments)];
            } while (in_array($comment, $usedComments));
            $usedComments[] = $comment;
            
            // Fechas aleatorias de los últimos 7 días con horas diferentes
            $daysAgo = rand(1, 7);
            $hour = rand(10, 22); // Entre 10 AM y 10 PM
            $minute = rand(0, 59);
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days {$hour}:{$minute}:00"));
            
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
        'message' => "Se agregaron {$insertedReviews} reseñas chilenas únicas",
        'reviews_added' => $insertedReviews,
        'note' => 'Comentarios con modismos chilenos auténticos, sin repeticiones'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>