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

$product_id = $_POST['product_id'] ?? null;
$image_url = $_POST['image_url'] ?? null;

if (!$product_id || !$image_url) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit;
}

try {
    // Eliminar imagen de S3
    // Cargar S3Manager desde api/
    if (!file_exists(__DIR__ . '/S3Manager.php')) {
        echo json_encode(['success' => false, 'error' => 'No se pudo encontrar S3Manager.php']);
        exit;
    }
    require_once __DIR__ . '/S3Manager.php';
    $s3Manager = new S3Manager($config);
    
    // Extraer key de la URL
    $key = str_replace($config['s3_url'] . '/', '', $image_url);
    
    // Intentar eliminar de S3 (no es crítico si falla)
    try {
        $s3Manager->deleteFile($key);
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error eliminando imagen de S3: " . $e->getMessage());
    }
    
    // Actualizar base de datos
    $stmt = $pdo->prepare("UPDATE productos SET image_url = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$product_id]);
    
    echo json_encode(['success' => true, 'message' => 'Imagen eliminada correctamente']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error eliminando imagen: ' . $e->getMessage()]);
}
?>