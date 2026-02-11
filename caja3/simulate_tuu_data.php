<?php
// Simular datos de TUU para testing
echo "=== SIMULACIÓN DE DATOS TUU ===\n";

// Datos simulados basados en la respuesta real de TUU
$simulatedTuuData = [
    'code' => '200',
    'message' => 'Success',
    'content' => [
        [
            'sequenceNumber' => '600017824525',
            'amount' => 15000,
            'deviceSerial' => '6010B232541610747',
            'transactionDate' => '2025-01-15T10:30:00Z',
            'status' => 'completed',
            'commission' => 450,
            'paymentMethod' => 'credit_card',
            'cardType' => 'visa',
            'authorizationCode' => 'AUTH123456'
        ],
        [
            'sequenceNumber' => '600017824526',
            'amount' => 8500,
            'deviceSerial' => '6010B232541610747',
            'transactionDate' => '2025-01-15T11:15:00Z',
            'status' => 'completed',
            'commission' => 255,
            'paymentMethod' => 'debit_card',
            'cardType' => 'mastercard',
            'authorizationCode' => 'AUTH789012'
        ],
        [
            'sequenceNumber' => '600017824527',
            'amount' => 12000,
            'deviceSerial' => '6010B232541609909',
            'transactionDate' => '2025-01-15T12:00:00Z',
            'status' => 'completed',
            'commission' => 360,
            'paymentMethod' => 'credit_card',
            'cardType' => 'visa',
            'authorizationCode' => 'AUTH345678'
        ]
    ]
];

echo "Datos simulados de TUU:\n";
echo json_encode($simulatedTuuData, JSON_PRETTY_PRINT);

echo "\n\n=== ESTRUCTURA PARA tuu_payments ===\n";

foreach ($simulatedTuuData['content'] as $i => $transaction) {
    echo "\nRegistro " . ($i+1) . " para MySQL:\n";
    echo "INSERT INTO tuu_payments (\n";
    echo "  order_number, device_serial, status, amount,\n";
    echo "  description, tuu_response, created_at, updated_at\n";
    echo ") VALUES (\n";
    echo "  '{$transaction['sequenceNumber']}',\n";
    echo "  '{$transaction['deviceSerial']}',\n";
    echo "  'completed',\n";
    echo "  {$transaction['amount']},\n";
    echo "  'Transacción TUU importada',\n";
    echo "  '" . json_encode($transaction) . "',\n";
    echo "  '{$transaction['transactionDate']}',\n";
    echo "  NOW()\n";
    echo ");\n";
}

echo "\n=== DATOS LISTOS PARA INSERTAR ===\n";
?>