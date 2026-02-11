<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Lista de archivos que deberían existir
$required_files = [
    '../config.php',
    'get_productos.php',
    'get_ingredientes.php',
    'get_recetas.php',
    'delete_ingrediente.php',
    'test_api.php'
];

// Verificar cada archivo
$results = [];
foreach ($required_files as $file) {
    $results[$file] = [
        'exists' => file_exists($file),
        'readable' => is_readable($file),
        'size' => file_exists($file) ? filesize($file) : 0,
        'path' => realpath($file) ?: 'No encontrado'
    ];
}

// Información del servidor
$server_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
    'current_dir' => __DIR__,
    'api_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
];

// Devolver resultados
echo json_encode([
    'files' => $results,
    'server_info' => $server_info
]);
?>