<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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

$data = json_decode(file_get_contents('php://input'), true);

$tipo = $data['tipo'] ?? '';
$monto = floatval($data['monto'] ?? 0);
$motivo = $data['motivo'] ?? '';
$usuario = $data['usuario'] ?? 'Cajero';

if (!in_array($tipo, ['ingreso', 'retiro']) || $monto <= 0 || empty($motivo)) {
    die(json_encode(['success' => false, 'error' => 'Datos inválidos']));
}

// Obtener saldo actual
$sql = "SELECT saldo_nuevo FROM caja_movimientos ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
$saldo_anterior = 0;

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $saldo_anterior = floatval($row['saldo_nuevo']);
}

// Calcular nuevo saldo
$saldo_nuevo = $tipo === 'ingreso' ? $saldo_anterior + $monto : $saldo_anterior - $monto;

if ($saldo_nuevo < 0) {
    echo json_encode(['success' => false, 'error' => 'Saldo insuficiente']);
    exit;
}

// Insertar movimiento
$stmt = mysqli_prepare($conn, "INSERT INTO caja_movimientos (tipo, monto, motivo, saldo_anterior, saldo_nuevo, usuario) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sdsdds", $tipo, $monto, $motivo, $saldo_anterior, $saldo_nuevo, $usuario);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'saldo_nuevo' => $saldo_nuevo,
        'movimiento_id' => mysqli_insert_id($conn)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al registrar movimiento']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
