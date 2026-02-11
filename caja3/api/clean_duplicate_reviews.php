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
    
    echo "=== LIMPIEZA DE RESEÑAS DUPLICADAS ===\n\n";
    
    // 1. Contar reseñas antes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
    $totalBefore = $stmt->fetch()['total'];
    echo "📊 Reseñas antes de limpiar: {$totalBefore}\n\n";
    
    // 2. Eliminar duplicados exactos (mismo producto, usuario, comentario)
    $duplicatesRemoved = $pdo->exec("
        DELETE r1 FROM reviews r1
        INNER JOIN reviews r2 
        WHERE r1.id > r2.id 
        AND r1.product_id = r2.product_id 
        AND r1.customer_name = r2.customer_name 
        AND r1.comment = r2.comment
    ");
    echo "🗑️ Duplicados exactos eliminados: {$duplicatesRemoved}\n";
    
    // 3. Eliminar reseñas con nombres raros o incompletos
    $weirdNamesRemoved = $pdo->exec("
        DELETE FROM reviews 
        WHERE customer_name LIKE '%El %' 
        OR customer_name LIKE '%La %'
        OR customer_name = 'Usuario Anónimo'
        OR customer_name LIKE '%Exquisita Hamburguesa%'
        OR LENGTH(customer_name) < 3
    ");
    echo "🧹 Nombres raros eliminados: {$weirdNamesRemoved}\n";
    
    // 4. Limitar reseñas por producto (máximo 8 por producto)
    $stmt = $pdo->query("
        SELECT product_id, COUNT(*) as count 
        FROM reviews 
        GROUP BY product_id 
        HAVING count > 8
    ");
    $productsWithTooMany = $stmt->fetchAll();
    
    $excessRemoved = 0;
    foreach ($productsWithTooMany as $product) {
        $productId = $product['product_id'];
        $excess = $product['count'] - 8;
        
        // Mantener las mejores reseñas (rating más alto y más recientes)
        $removed = $pdo->exec("
            DELETE FROM reviews 
            WHERE product_id = {$productId} 
            AND id NOT IN (
                SELECT * FROM (
                    SELECT id FROM reviews 
                    WHERE product_id = {$productId} 
                    ORDER BY rating DESC, created_at DESC 
                    LIMIT 8
                ) as keeper
            )
        ");
        $excessRemoved += $removed;
    }
    echo "📉 Reseñas excesivas eliminadas: {$excessRemoved}\n";
    
    // 5. Contar reseñas después
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews");
    $totalAfter = $stmt->fetch()['total'];
    echo "\n📊 Reseñas después de limpiar: {$totalAfter}\n";
    echo "✅ Total eliminadas: " . ($totalBefore - $totalAfter) . "\n\n";
    
    // 6. Mostrar resumen por producto
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
        ORDER BY p.id
    ");
    $summary = $stmt->fetchAll();
    
    echo "📋 RESUMEN POR PRODUCTO:\n";
    foreach ($summary as $product) {
        echo "  ID {$product['id']}: {$product['name']} - {$product['review_count']} reseñas (⭐{$product['avg_rating']})\n";
    }
    
    echo "\n✅ Limpieza completada exitosamente\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>