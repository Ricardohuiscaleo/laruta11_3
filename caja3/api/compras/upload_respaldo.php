<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Cache-Control');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    // Debug: ver qué llega desde móvil
    error_log('=== UPLOAD DEBUG ===');
    error_log('FILES: ' . json_encode($_FILES));
    error_log('POST: ' . json_encode($_POST));
    error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    if (!isset($_FILES['image']) || !isset($_POST['compra_id'])) {
        throw new Exception('Imagen y compra_id requeridos. FILES=' . json_encode($_FILES) . ' POST=' . json_encode($_POST));
    }

    $s3ManagerPaths = [
        __DIR__ . '/../S3Manager.php',
        __DIR__ . '/../../S3Manager.php'
    ];
    
    $s3ManagerFound = false;
    foreach ($s3ManagerPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $s3ManagerFound = true;
            break;
        }
    }
    
    if (!$s3ManagerFound) {
        throw new Exception('S3Manager no encontrado');
    }
    
    $compra_id = $_POST['compra_id'];
    $s3Manager = new S3Manager();
    $file = $_FILES['image'];
    $fileName = 'compras/respaldo_' . $compra_id . '_' . time() . '.jpg';
    
    $imageUrl = $s3Manager->uploadFile($file, $fileName);
    
    // Actualizar BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("UPDATE compras SET imagen_respaldo = ? WHERE id = ?");
    $stmt->execute([$imageUrl, $compra_id]);
    
    echo json_encode([
        'success' => true,
        'url' => $imageUrl
    ]);
    
} catch (Exception $e) {
    $debugInfo = [
        'error' => $e->getMessage(),
        'FILES' => $_FILES,
        'POST' => $_POST,
        'SERVER' => [
            'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
            'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'not set'
        ]
    ];
    
    error_log('UPLOAD ERROR: ' . json_encode($debugInfo));
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debugInfo
    ]);
}
?>
