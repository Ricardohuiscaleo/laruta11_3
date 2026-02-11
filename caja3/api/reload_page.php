<?php
header('Content-Type: application/json');

// Simplemente devolver un mensaje de éxito para forzar la recarga de la página
echo json_encode([
    'success' => true,
    'message' => 'Recarga la página para ver los cambios actualizados',
    'reload' => true
]);
?>