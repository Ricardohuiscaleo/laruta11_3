<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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

// Conexión mysqli
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexion']);
    exit;
}

mysqli_set_charset($conn, "utf8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

try {
    $ids = json_decode($_POST['ids'] ?? '[]', true);
    $adjustType = $_POST['adjust_type'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    
    if (empty($ids) || !is_array($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs requeridos']);
        exit;
    }
    
    if (!in_array($adjustType, ['add', 'subtract', 'set'])) {
        echo json_encode(['success' => false, 'error' => 'Tipo de ajuste invalido']);
        exit;
    }
    
    $ids = array_filter($ids, 'is_numeric');
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'IDs invalidos']);
        exit;
    }
    
    // Construir query según tipo de ajuste
    $placeholders = implode(',', array_map('intval', $ids));
    
    if ($adjustType === 'add') {
        $sql = "UPDATE products SET price = price + $amount WHERE id IN ($placeholders)";
    } elseif ($adjustType === 'subtract') {
        $sql = "UPDATE products SET price = GREATEST(0, price - $amount) WHERE id IN ($placeholders)";
    } else { // set
        $sql = "UPDATE products SET price = $amount WHERE id IN ($placeholders)";
    }
    
    if (mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        echo json_encode([
            'success' => true,
            'message' => 'Precios actualizados',
            'affected_rows' => $affected,
            'sql' => $sql,
            'ids' => $ids,
            'type' => $adjustType,
            'amount' => $amount
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Error al actualizar',
            'mysql_error' => mysqli_error($conn),
            'sql' => $sql
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}

mysqli_close($conn);
?>
