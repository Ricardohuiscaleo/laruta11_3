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
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$product_id = $_POST['product_id'] ?? null;
$image_url = $_POST['image_url'] ?? null;

if (!$product_id || !$image_url) {
    echo json_encode(['success' => false, 'error' => 'ID de producto e imagen URL requeridos']);
    exit;
}

try {
    // Obtener imágenes existentes
    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $currentImages = $stmt->fetchColumn();
    
    // Agregar nueva imagen a las existentes
    if ($currentImages && !empty(trim($currentImages))) {
        $imageUrls = $currentImages . ',' . $image_url;
    } else {
        $imageUrls = $image_url;
    }
    
    // Actualizar con todas las imágenes
    $stmt = $pdo->prepare("UPDATE products SET image_url = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$imageUrls, $product_id]);
    
    $rowsAffected = $stmt->rowCount();
    $imageCount = count(explode(',', $imageUrls));
    
    echo json_encode([
        'success' => true,
        'message' => "URL de imagen guardada en MySQL (Total: {$imageCount} imágenes)",
        'rows_affected' => $rowsAffected,
        'image_url' => $image_url,
        'all_images' => $imageUrls,
        'image_count' => $imageCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error guardando URL: ' . $e->getMessage()]);
}
?>