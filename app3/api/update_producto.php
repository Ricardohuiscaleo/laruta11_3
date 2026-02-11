<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
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

$id = $_POST['id'] ?? null;
$name = $_POST['name'] ?? null;
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? null;
$cost_price = $_POST['cost_price'] ?? 0;
$stock_quantity = $_POST['stock_quantity'] ?? 0;
$min_stock_level = $_POST['min_stock_level'] ?? 5;
$category_id = $_POST['category_id'] ?? 1;
$subcategory_id = $_POST['subcategory_id'] ?? null;
$sku = $_POST['sku'] ?? null;
$preparation_time = $_POST['preparation_time'] ?? 10;
$grams = $_POST['grams'] ?? 0;
$calories = $_POST['calories'] ?? null;
$is_active = $_POST['is_active'] ?? 1;
$allergens = $_POST['allergens'] ?? null;

if (!$id || !$name || !$price) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit;
}

$image_url = null;

// Manejar subida de imagen a S3
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // Cargar S3Manager desde api/
    if (!file_exists(__DIR__ . '/S3Manager.php')) {
        echo json_encode(['success' => false, 'error' => 'No se pudo encontrar S3Manager.php']);
        exit;
    }
    require_once __DIR__ . '/S3Manager.php';
    
    try {
        $s3Manager = new S3Manager($config);
        $file = $_FILES['image'];
        $fileName = 'products/' . uniqid() . '_' . basename($file['name']);
        
        $image_url = $s3Manager->uploadFile($file, $fileName);
        
    } catch (Exception $e) {
        // Si es error HTTP 0 (conectividad), continuar sin imagen
        if (strpos($e->getMessage(), 'HTTP 0') !== false || strpos($e->getMessage(), 'conectar') !== false) {
            error_log('S3 no disponible, continuando sin imagen: ' . $e->getMessage());
            $image_url = null; // Continuar sin subir imagen
        } else {
            echo json_encode(['success' => false, 'error' => 'Error subiendo imagen: ' . $e->getMessage()]);
            exit;
        }
    }
}

try {
    // Si hay nueva imagen, agregarla a las existentes
    if ($image_url) {
        // Obtener imágenes existentes
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $currentImages = $stmt->fetchColumn();
        
        // Agregar nueva imagen a las existentes
        if ($currentImages && !empty(trim($currentImages))) {
            $finalImageUrls = $currentImages . ',' . $image_url;
        } else {
            $finalImageUrls = $image_url;
        }
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, price = ?, cost_price = ?, stock_quantity = ?, min_stock_level = ?, 
                category_id = ?, subcategory_id = ?, sku = ?, preparation_time = ?, grams = ?, calories = ?, is_active = ?, 
                allergens = ?, image_url = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $cost_price, $stock_quantity, $min_stock_level, 
                       $category_id, $subcategory_id, $sku, $preparation_time, $grams, $calories, $is_active, 
                       $allergens, $finalImageUrls, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, price = ?, cost_price = ?, stock_quantity = ?, min_stock_level = ?, 
                category_id = ?, subcategory_id = ?, sku = ?, preparation_time = ?, grams = ?, calories = ?, is_active = ?, 
                allergens = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $price, $cost_price, $stock_quantity, $min_stock_level, 
                       $category_id, $subcategory_id, $sku, $preparation_time, $grams, $calories, $is_active, 
                       $allergens, $id]);
    }
    
    $rowsAffected = $stmt->rowCount();
    
    // Contar imágenes totales
    $imageCount = 0;
    if ($image_url) {
        $imageCount = count(explode(',', $finalImageUrls));
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $image_url ? "Producto actualizado. Total: {$imageCount} imagen(es)" : 'Producto actualizado correctamente',
        'rows_affected' => $rowsAffected,
        'image_uploaded' => $image_url ? true : false,
        'image_url' => $image_url,
        'total_images' => $imageCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error actualizando producto: ' . $e->getMessage()]);
}
?>