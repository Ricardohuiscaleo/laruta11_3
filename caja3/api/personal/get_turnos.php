<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$config = null;
foreach ([__DIR__.'/../../public/config.php', __DIR__.'/../config.php', __DIR__.'/../../config.php', __DIR__.'/../../../config.php', __DIR__.'/../../../../config.php'] as $p) {
    if (file_exists($p)) { $config = require_once $p; break; }
}
if (!$config) { echo json_encode(['success'=>false,'error'=>'Config no encontrado']); exit; }
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) { echo json_encode(['success'=>false,'error'=>'DB error']); exit; }

$mes = $_GET['mes'] ?? date('Y-m');
$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

// Ciclos 4x4 corridos desde fecha de inicio de cada persona
// personal_id => [fecha_inicio_ciclo, offset: 0=trabaja primero, 4=descansa primero]
$ciclos = [
    1 => ['inicio' => '2025-02-03', 'trabaja_primero' => true],  // Camila
    2 => ['inicio' => '2025-02-03', 'trabaja_primero' => false], // Neit (opuesto a Camila)
    3 => ['inicio' => '2025-02-01', 'trabaja_primero' => true],  // Andrés
    4 => ['inicio' => '2025-02-01', 'trabaja_primero' => false], // Gabriel (opuesto a Andrés)
];

// Obtener excepciones de la BD (reemplazos, días especiales) para este mes
$stmt = mysqli_prepare($conn, "SELECT * FROM turnos WHERE fecha BETWEEN ? AND ? AND tipo = 'reemplazo'");
mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fin);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$excepciones = [];
while ($row = mysqli_fetch_assoc($res)) {
    $excepciones[$row['personal_id'] . '_' . $row['fecha']] = $row;
}

// Obtener días excluidos (tabla opcional)
$excluidos = [];
if (mysqli_query($conn, "SHOW TABLES LIKE 'turnos_excluidos'") && mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE 'turnos_excluidos'")) > 0) {
    $stmt2 = mysqli_prepare($conn, "SELECT fecha FROM turnos_excluidos WHERE fecha BETWEEN ? AND ?");
    mysqli_stmt_bind_param($stmt2, 'ss', $inicio, $fin);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    while ($row = mysqli_fetch_assoc($res2)) $excluidos[] = $row['fecha'];
}

$data = [];
$current = new DateTime($inicio);
$end = new DateTime($fin);

while ($current <= $end) {
    $fecha = $current->format('Y-m-d');
    
    foreach ($ciclos as $personal_id => $ciclo) {
        $diff = (new DateTime($ciclo['inicio']))->diff($current)->days;
        $pos = $diff % 8;
        $trabaja = $ciclo['trabaja_primero'] ? ($pos < 4) : ($pos >= 4);
        
        $key = $personal_id . '_' . $fecha;
        
        if ($trabaja) {
            // Día normal de ciclo
            $data[] = array_merge(
                ['id' => $key, 'personal_id' => $personal_id, 'fecha' => $fecha, 'tipo' => 'normal', 'notas' => null],
                isset($excepciones[$key]) ? $excepciones[$key] : []
            );
        } elseif (isset($excepciones[$key])) {
            // Día de descanso pero tiene reemplazo registrado
            $data[] = $excepciones[$key];
        }
    }
    
    $current->modify('+1 day');
}

echo json_encode(['success' => true, 'data' => $data]);
