<?php
require_once 'config.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die(json_encode(['error' => $conn->connect_error]));

$result = $conn->query("SELECT id, tipo, monto FROM caja_movimientos ORDER BY fecha_movimiento ASC, id ASC");

$saldo = 0;
while ($row = $result->fetch_assoc()) {
    $saldo_anterior = $saldo;
    $saldo = $saldo + ($row['tipo'] === 'ingreso' ? $row['monto'] : -$row['monto']);
    
    $conn->query("UPDATE caja_movimientos SET saldo_anterior = $saldo_anterior, saldo_nuevo = $saldo WHERE id = {$row['id']}");
}

echo json_encode(['success' => true, 'message' => 'Saldos recalculados']);
$conn->close();
?>
