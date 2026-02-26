<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
$config = null;
foreach ([__DIR__ . '/../../public/config.php', __DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../../config.php', __DIR__ . '/../../../../config.php'] as $p) {
    if (file_exists($p)) {
        $config = require_once $p;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}
$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

$mes = $_GET['mes'] ?? date('Y-m');
$inicio = $mes . '-01';
$fin = date('Y-m-t', strtotime($inicio));

$stmt = mysqli_prepare($conn, "
    SELECT t.*, r.nombre as reemplazante_nombre
    FROM turnos t
    LEFT JOIN personal r ON r.id = t.reemplazado_por
    WHERE t.fecha BETWEEN ? AND ?
    ORDER BY t.fecha, t.personal_id
");
mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fin);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = [];
$turnos_existentes = []; // Keep track of manually added security shifts to avoid duplicates
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = $row;
    if ($row['tipo'] === 'seguridad') {
        $turnos_existentes[$row['fecha'] . '_' . $row['personal_id']] = true;
    }
}

// Generación Dinámica de Turnos de Seguridad (4x4)
$res_personal = mysqli_query($conn, "SELECT id, nombre FROM personal WHERE FIND_IN_SET('seguridad', rol) > 0 OR nombre IN ('Ricardo', 'Claudio')");
$ricardo_id = null;
$claudio_id = null;
while ($p_row = mysqli_fetch_assoc($res_personal)) {
    if (strtolower(trim($p_row['nombre'])) === 'ricardo')
        $ricardo_id = $p_row['id'];
    if (strtolower(trim($p_row['nombre'])) === 'claudio')
        $claudio_id = $p_row['id'];
}

if ($ricardo_id && $claudio_id) {
    $baseDate = new DateTime("2026-03-26"); // Ricardo empieza su ciclo de 4
    $startDate = new DateTime($inicio);
    $endDate = new DateTime($fin);

    $current = clone $startDate;
    while ($current <= $endDate) {
        $diff = $current->diff($baseDate);
        $days = (int)$diff->format("%r%a");
        $pos = (($days % 8) + 8) % 8;

        $pId = ($pos < 4) ? $ricardo_id : $claudio_id;
        $fecha_str = $current->format('Y-m-d');

        // Solo agregar si no fue insertado manualmente en la base de datos (permite sobreescribir)
        if (!isset($turnos_existentes[$fecha_str . '_' . $pId])) {
            $data[] = [
                'id' => 'dyn_' . $fecha_str . '_' . $pId, // ID virtual
                'personal_id' => $pId,
                'fecha' => $fecha_str,
                'tipo' => 'seguridad',
                'reemplazado_por' => null,
                'reemplazante_nombre' => null,
                'monto_reemplazo' => 20000, // asumiendo un default
                'pago_por' => 'empresa',
                'is_dynamic' => true
            ];
        }

        $current->modify('+1 day');
    }
}

// Generación Dinámica de Turnos La Ruta 11 (4x4 — 2 ciclos independientes)
// Ciclo 1: Cajero — Camila (pos 0-3) / Neit (pos 4-7), base: 2026-02-09
// Ciclo 2: Planchero — Andrés (pos 0-3) / Gabriel (pos 4-7), base: 2026-02-15
$res_ruta = mysqli_query($conn, "SELECT id, nombre FROM personal WHERE nombre IN ('Camila', 'Neit', 'Andrés', 'Gabriel')");
$ruta_ids = [];
while ($p_row = mysqli_fetch_assoc($res_ruta)) {
    $nombre_lower = strtolower(trim($p_row['nombre']));
    $ruta_ids[$nombre_lower] = $p_row['id'];
}

// Track existing normal shifts to avoid duplicates
$turnos_normal_existentes = [];
foreach ($data as $t) {
    if ($t['tipo'] === 'normal') {
        $turnos_normal_existentes[$t['fecha'] . '_' . $t['personal_id']] = true;
    }
}

$ciclos_ruta11 = [
    ['base' => '2026-02-09', 'a' => 'camila', 'b' => 'neit'],
    ['base' => '2026-02-15', 'a' => 'andrés', 'b' => 'gabriel'],
];

foreach ($ciclos_ruta11 as $ciclo) {
    $idA = $ruta_ids[$ciclo['a']] ?? null;
    $idB = $ruta_ids[$ciclo['b']] ?? null;
    if (!$idA || !$idB)
        continue;

    $baseCiclo = new DateTime($ciclo['base']);
    $startDate = new DateTime($inicio);
    $endDate = new DateTime($fin);

    $current = clone $startDate;
    while ($current <= $endDate) {
        $diff = $current->diff($baseCiclo);
        $days = (int)$diff->format("%r%a");
        $pos = (($days % 8) + 8) % 8;

        $pId = ($pos < 4) ? $idA : $idB;
        $fecha_str = $current->format('Y-m-d');

        if (!isset($turnos_normal_existentes[$fecha_str . '_' . $pId])) {
            $data[] = [
                'id' => 'dyn_ruta_' . $fecha_str . '_' . $pId,
                'personal_id' => $pId,
                'fecha' => $fecha_str,
                'tipo' => 'normal',
                'reemplazado_por' => null,
                'reemplazante_nombre' => null,
                'monto_reemplazo' => null,
                'pago_por' => 'empresa',
                'is_dynamic' => true
            ];
        }

        $current->modify('+1 day');
    }
}

// Ordenar de nuevo por fecha para que se mezclen bien con los turnos manuales
usort($data, function ($a, $b) {
    if ($a['fecha'] === $b['fecha']) {
        return $a['personal_id'] <=> $b['personal_id'];
    }
    return strcmp($a['fecha'], $b['fecha']);
});

echo json_encode(['success' => true, 'data' => $data]);