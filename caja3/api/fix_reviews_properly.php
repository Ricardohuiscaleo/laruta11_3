<?php
header('Content-Type: text/plain; charset=utf-8');

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
    echo "❌ No se pudo encontrar config.php\n";
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🧹 LIMPIEZA TOTAL DE RESEÑAS\n\n";
    
    // 1. Eliminar TODAS las reseñas generadas automáticamente (mantener solo las reales)
    $realReviews = [1, 2, 3, 4]; // IDs de reseñas reales que queremos mantener
    $realIds = implode(',', $realReviews);
    
    $deleted = $pdo->exec("DELETE FROM reviews WHERE id NOT IN ($realIds)");
    echo "🗑️ Reseñas automáticas eliminadas: $deleted\n";
    
    // 2. Crear reseñas únicas y naturales para cada producto
    $uniqueReviews = [
        // Hamburguesas (productos 10, 11, 12, 13)
        [10, 'Catalina Herrera', 5, 'Esta hamburguesa está la raja, muy recomendada', '2025-09-06 19:30:00'],
        [10, 'Tomás González', 4, 'Buenísima, de las mejores que he probado', '2025-09-05 18:15:00'],
        [10, 'Javiera López', 5, 'Está súper rica, vuelvo seguro', '2025-09-04 20:45:00'],
        [10, 'Nicolás Torres', 4, 'Qué hamburguesa más bacán, me encantó', '2025-09-03 17:20:00'],
        
        [11, 'Constanza Castro', 5, 'Está terrible de buena, felicitaciones', '2025-09-06 16:10:00'],
        [11, 'Felipe Vargas', 4, 'Muy rica, superó mis expectativas', '2025-09-05 19:25:00'],
        [11, 'Martina Rojas', 5, 'La mejor hamburguesa de Arica, sin duda', '2025-09-04 21:30:00'],
        [11, 'Agustín Soto', 4, 'Está increíble, no me la esperaba tan buena', '2025-09-03 18:40:00'],
        
        [12, 'Florencia Muñoz', 5, 'Qué manjar, quedé muy contento', '2025-09-06 15:50:00'],
        [12, 'Maximiliano Peña', 4, 'Sabor espectacular, la recomiendo mucho', '2025-09-05 20:15:00'],
        [12, 'Isadora Contreras', 5, 'Está muy rica esta hamburguesa', '2025-09-04 17:35:00'],
        [12, 'Santiago Martínez', 4, 'Qué delicia, voy a venir más seguido', '2025-09-03 19:20:00'],
        
        [13, 'Esperanza Rivera', 5, 'Muy sabrosa, me gustó caleta', '2025-09-06 18:25:00'],
        [13, 'Francisco Silva', 4, 'Está terrible de buena, me encantó', '2025-09-05 16:40:00'],
        [13, 'Valentina Morales', 5, 'Qué hamburguesa más rica, la raja', '2025-09-04 20:10:00'],
        [13, 'Benjamín Soto', 4, 'Está demasiado buena, no puedo creerlo', '2025-09-03 21:15:00'],
        
        // Agregar algunas para otros productos populares
        [1, 'Carolina Mendoza', 5, 'El mejor tomahawk de Arica, increíble', '2025-09-06 20:30:00'],
        [1, 'Diego Morales', 4, 'Está la raja este tomahawk, vuelvo pronto', '2025-09-05 19:45:00'],
        
        [6, 'Fernanda Silva', 5, 'Ave italiana buenísima, muy recomendada', '2025-09-06 17:20:00'],
        [6, 'Rodrigo Castro', 4, 'Está súper rica, el pollo jugoso', '2025-09-05 18:30:00'],
        
        [9, 'Camila Torres', 5, 'Hamburguesa clásica perfecta, como debe ser', '2025-09-06 16:45:00'],
        [9, 'Matías Herrera', 4, 'Muy rica, sabor casero auténtico', '2025-09-05 20:20:00']
    ];
    
    $inserted = 0;
    foreach ($uniqueReviews as $review) {
        $stmt = $pdo->prepare("
            INSERT INTO reviews (product_id, customer_name, rating, comment, created_at, is_approved) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute($review);
        $inserted++;
    }
    
    echo "✅ Reseñas únicas agregadas: $inserted\n\n";
    
    // 3. Mostrar resumen final
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            COUNT(r.id) as review_count,
            ROUND(AVG(r.rating), 1) as avg_rating
        FROM products p
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.is_active = 1
        GROUP BY p.id
        HAVING review_count > 0
        ORDER BY p.id
    ");
    $summary = $stmt->fetchAll();
    
    echo "📋 RESUMEN FINAL:\n";
    foreach ($summary as $product) {
        echo "  ✅ {$product['name']}: {$product['review_count']} reseñas (⭐{$product['avg_rating']})\n";
    }
    
    echo "\n🎉 ¡Reseñas arregladas! Ahora son únicas y naturales.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>