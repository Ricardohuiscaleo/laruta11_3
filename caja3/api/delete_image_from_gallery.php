<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

$product_id = $_POST['product_id'] ?? null;
$image_url = $_POST['image_url'] ?? null;

if (!$product_id || !$image_url) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit;
}

try {
    // Obtener imágenes actuales
    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $currentImages = $stmt->fetchColumn();
    
    if ($currentImages) {
        // Convertir a array, eliminar la imagen específica
        $imageArray = array_map('trim', explode(',', $currentImages));
        $imageArray = array_filter($imageArray, function($url) use ($image_url) {
            return $url !== $image_url;
        });
        
        // Convertir de vuelta a string
        $newImageUrls = implode(',', $imageArray);
        
        // Actualizar base de datos
        $stmt = $pdo->prepare("UPDATE products SET image_url = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newImageUrls, $product_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Imagen eliminada de la galería',
            'remaining_images' => $newImageUrls,
            'image_count' => count($imageArray)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontraron imágenes']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>