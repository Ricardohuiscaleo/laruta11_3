<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {

    // ── PERSONAL ──────────────────────────────────────────────
    case 'save_personal': {
            $nombre = trim($input['nombre'] ?? '');
            $rol_input = $input['rol'] ?? 'cajero';
            $rol = is_array($rol_input) ? implode(',', $rol_input) : $rol_input;
            $s_cajero = floatval($input['sueldo_base_cajero'] ?? 0);
            $s_planchero = floatval($input['sueldo_base_planchero'] ?? 0);
            $s_admin = floatval($input['sueldo_base_admin'] ?? 0);
            $s_seguridad = floatval($input['sueldo_base_seguridad'] ?? 0);
            $activo = intval($input['activo'] ?? 1);
            if (!$nombre) {
                echo json_encode(['success' => false, 'error' => 'Nombre requerido']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO personal (nombre, rol, sueldo_base_cajero, sueldo_base_planchero, sueldo_base_admin, sueldo_base_seguridad, activo) VALUES (?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssddddi', $nombre, $rol, $s_cajero, $s_planchero, $s_admin, $s_seguridad, $activo);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($conn)]);
            break;
        }

    case 'update_personal': {
            $id = intval($input['id'] ?? 0);
            $nombre = trim($input['nombre'] ?? '');
            $rol_input = $input['rol'] ?? 'cajero';
            $rol = is_array($rol_input) ? implode(',', $rol_input) : $rol_input;
            $s_cajero = floatval($input['sueldo_base_cajero'] ?? 0);
            $s_planchero = floatval($input['sueldo_base_planchero'] ?? 0);
            $s_admin = floatval($input['sueldo_base_admin'] ?? 0);
            $s_seguridad = floatval($input['sueldo_base_seguridad'] ?? 0);
            $activo = intval($input['activo'] ?? 1);
            if (!$id || !$nombre) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "UPDATE personal SET nombre=?, rol=?, sueldo_base_cajero=?, sueldo_base_planchero=?, sueldo_base_admin=?, sueldo_base_seguridad=?, activo=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssddddii', $nombre, $rol, $s_cajero, $s_planchero, $s_admin, $s_seguridad, $activo, $id);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true]);
            break;
        }

    // ── TURNOS ────────────────────────────────────────────────
    case 'save_turno': {
            $personal_id = intval($input['personal_id'] ?? 0);
            $fecha = $input['fecha'] ?? '';
            $tipo = $input['tipo'] ?? 'normal';
            $reemplazado_por = $input['reemplazado_por'] ? intval($input['reemplazado_por']) : null;
            $monto = floatval($input['monto_reemplazo'] ?? 20000);
            $pago_por = $input['pago_por'] ?? 'empresa';
            if (!$personal_id || !$fecha) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO turnos (personal_id, fecha, tipo, reemplazado_por, monto_reemplazo, pago_por) VALUES (?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'issids', $personal_id, $fecha, $tipo, $reemplazado_por, $monto, $pago_por);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($conn)]);
            break;
        }

    case 'delete_turno': {
            $id = intval($input['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "DELETE FROM turnos WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true]);
            break;
        }

    // ── PRESUPUESTO ───────────────────────────────────────────
    case 'save_presupuesto': {
            $mes = $input['mes'] ?? '';
            $monto = floatval($input['monto'] ?? 0);
            $centro_costo = $input['centro_costo'] ?? 'ruta11';
            if (!$mes || !$monto) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
                exit;
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO presupuesto_nomina (mes, monto, centro_costo) VALUES (?,?,?) ON DUPLICATE KEY UPDATE monto=?");
            mysqli_stmt_bind_param($stmt, 'sdsd', $mes, $monto, $centro_costo, $monto);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true]);
            break;
        }

    default:
        echo json_encode(['success' => false, 'error' => 'Acción desconocida: ' . $action]);
}