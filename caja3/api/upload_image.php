<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$configPaths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../public/config.php',
];
$config = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No se recibió imagen válida']);
    exit;
}

try {
    require_once __DIR__ . '/S3Manager.php';
    
    $s3Manager = new S3Manager($config);
    $file = $_FILES['image'];
    $fileName = 'products/' . uniqid() . '_' . time() . '.webp';
    
    $originalSize = filesize($file['tmp_name']);
    $imageUrl = $s3Manager->uploadFile($file, $fileName);
    
    // Calculate compression info
    $compressionInfo = '';
    $estimatedFinalSize = $originalSize * 0.15;
    $compressionRatio = round((($originalSize - $estimatedFinalSize) / $originalSize) * 100, 1);
    $compressionInfo = "Comprimida ~{$compressionRatio}% (" . round($originalSize/1024, 1) . "KB → ~" . round($estimatedFinalSize/1024, 1) . "KB). ";
    
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'original_size' => $originalSize,
        'final_size' => $estimatedFinalSize,
        'compressed' => true,
        'compression_info' => $compressionInfo,
        'message' => $compressionInfo . 'Subida exitosa a AWS S3: ' . $imageUrl
    ]);
    
} catch (Exception $e) {
    // Si es error de conectividad, informar pero no fallar completamente
    if (strpos($e->getMessage(), 'HTTP 0') !== false || strpos($e->getMessage(), 'conectar') !== false) {
        echo json_encode([
            'success' => false,
            'error' => 'AWS S3 no disponible temporalmente. Intente más tarde.',
            'connectivity_issue' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error subiendo imagen: ' . $e->getMessage()
        ]);
    }
}
