<?php
header('Content-Type: application/json');

// Log de datos recibidos
$input = json_decode(file_get_contents('php://input'), true);

$log = [
    'timestamp' => date('Y-m-d H:i:s'),
    'received_data' => $input,
    'fixed_items_count' => isset($input['fixed_items']) ? count($input['fixed_items']) : 0,
    'selection_groups_count' => isset($input['selection_groups']) ? count($input['selection_groups']) : 0,
    'fixed_items' => $input['fixed_items'] ?? [],
    'selection_groups' => $input['selection_groups'] ?? []
];

echo json_encode($log, JSON_PRETTY_PRINT);
?>