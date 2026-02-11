<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config_file = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config_file = $path;
        break;
    }
}

if (!$config_file) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $tuu_rut = $_POST['tuu_rut'] ?? '';
    $tuu_environment = $_POST['tuu_environment'] ?? 'development';
    $tuu_secret = $_POST['tuu_secret'] ?? '';
    
    // Validar RUT
    if (!preg_match('/^\d{8}-[\dkK]$/', $tuu_rut)) {
        throw new Exception('Formato de RUT inválido');
    }
    
    // Validar clave secreta
    if (strlen($tuu_secret) !== 80) {
        throw new Exception('La clave secreta debe tener 80 caracteres');
    }
    
    // Leer archivo config actual
    $config_content = file_get_contents($config_file);
    
    // Actualizar valores
    $config_content = preg_replace(
        "/'tuu_online_rut' => '[^']*'/",
        "'tuu_online_rut' => '$tuu_rut'",
        $config_content
    );
    
    $config_content = preg_replace(
        "/'tuu_online_env' => '[^']*'/",
        "'tuu_online_env' => '$tuu_environment'",
        $config_content
    );
    
    $config_content = preg_replace(
        "/'tuu_online_secret' => '[^']*'/",
        "'tuu_online_secret' => '$tuu_secret'",
        $config_content
    );
    
    // Guardar archivo
    if (file_put_contents($config_file, $config_content) === false) {
        throw new Exception('Error escribiendo archivo de configuración');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>