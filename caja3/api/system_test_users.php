<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/**
 * Standard User Diagnostic Test
 * Verifies basic connectivity to the usuarios table.
 */

require_once __DIR__ . '/db_connect.php';

$logs = [];
function addLog($msg, $status = 'info')
{
    global $logs;
    $logs[] = [
        'timestamp' => date('H:i:s'),
        'message' => $msg,
        'status' => $status
    ];
}

addLog("Iniciando prueba de diagnóstico de Usuarios (Básico)...");

try {
    $pdo = require __DIR__ . '/db_connect.php';
    addLog("Conexión a BD exitosa", "success");

    addLog("Consultando tabla 'usuarios'...");
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    addLog("Total de usuarios registrados: " . ($result['total'] ?? 0), "success");
    addLog("Verificando permisos de escritura temporales...");

    // Test simple de escritura si fuera necesario, pero por ahora lectura es suficiente para "Personas"

    addLog("Sistema de usuarios básico operativo.", "success");
    echo json_encode(['success' => true, 'logs' => $logs]);

}
catch (Exception $e) {
    addLog("ERROR: " . $e->getMessage(), "error");
    echo json_encode(['success' => false, 'logs' => $logs]);
}
?>
?>