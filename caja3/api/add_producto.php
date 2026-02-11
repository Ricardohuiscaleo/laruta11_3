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

$name = $_POST['name'] ?? null;
$description = $_POST['description'] ?? '';
$price = $_POST['price'] ?? null;
$cost_price = $_POST['cost_price'] ?? 0;
$stock_quantity = $_POST['stock_quantity'] ?? 0;
$min_stock_level = $_POST['min_stock_level'] ?? 5;
$category_id = $_POST['category_id'] ?? 1;
$sku = $_POST['sku'] ?? null;
$preparation_time = $_POST['preparation_time'] ?? 10;
$grams = $_POST['grams'] ?? 0;
$calories = $_POST['calories'] ?? null;
$is_active = $_POST['is_active'] ?? 1;
$allergens = $_POST['allergens'] ?? null;
$subcategory_id = $_POST['subcategory_id'] ?? null;

if (!$name || !$price) {
    echo json_encode(['success' => false, 'error' => 'Nombre y precio son requeridos']);
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
        echo json_encode(['success' => false, 'error' => 'Error subiendo imagen: ' . $e->getMessage()]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO products 
        (name, description, price, cost_price, stock_quantity, min_stock_level, category_id, 
         sku, preparation_time, grams, calories, is_active, allergens, subcategory_id, image_url, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $name, $description, $price, $cost_price, $stock_quantity, $min_stock_level,
        $category_id, $sku, $preparation_time, $grams, $calories, $is_active,
        $allergens, $subcategory_id, $image_url
    ]);
    
    $productId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Producto creado correctamente',
        'id' => $productId
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error creando producto: ' . $e->getMessage()]);
}
?>