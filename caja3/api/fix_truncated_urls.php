<?php
header('Content-Type: application/json');

// Buscar config.php
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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar URLs truncadas (que no terminan en extensión válida)
    $stmt = $pdo->prepare("SELECT id, image_url FROM products WHERE image_url IS NOT NULL");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed = 0;
    foreach ($products as $product) {
        $urls = explode(',', $product['image_url']);
        $cleanUrls = [];
        
        foreach ($urls as $url) {
            $url = trim($url);
            // Si la URL está truncada (no termina en extensión válida)
            if (!empty($url) && !preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                // Intentar completar con .png si parece truncada
                if (strpos($url, 'tomahawk-papa.p') !== false) {
                    $url = str_replace('tomahawk-papa.p', 'tomahawk-papa.png', $url);
                }
                // Agregar otras correcciones según sea necesario
            }
            
            if (!empty($url)) {
                $cleanUrls[] = $url;
            }
        }
        
        $newImageUrl = implode(',', $cleanUrls);
        if ($newImageUrl !== $product['image_url']) {
            $updateStmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?");
            $updateStmt->execute([$newImageUrl, $product['id']]);
            $fixed++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "URLs corregidas: {$fixed} productos",
        'total_checked' => count($products)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error corrigiendo URLs: ' . $e->getMessage()
    ]);
}
?>