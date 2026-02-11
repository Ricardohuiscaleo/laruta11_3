<?php
header('Content-Type: application/json');

// Simular los datos que TUU envía en la URL de éxito
$tuu_data = [
    'x_account_id' => '50395671',
    'x_amount' => '500.0',
    'x_currency' => 'CLP',
    'x_reference' => 'R11-1756866099-8132',
    'x_result' => 'completed',
    'x_timestamp' => '2025-09-02T22:26:56Z',
    'x_message' => 'Transaccion aprobada',
    'x_signature' => '24b53c4a6880c51992c1bdf4eb46a2d9295455c2d0ddc7b0281e6c2586aabd46',
    'x_transaction_id' => 'TXN123456789' // Este es el que necesitamos capturar
];

echo json_encode([
    'success' => true,
    'message' => 'Datos que TUU envía en la URL de éxito',
    'tuu_data' => $tuu_data,
    'transaction_id_captured' => $tuu_data['x_transaction_id'],
    'url_example' => 'https://app.laruta11.cl/payment-success/?' . http_build_query($tuu_data)
]);
?>