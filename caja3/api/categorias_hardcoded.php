<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Devolver categorías hardcoded para evitar problemas con la base de datos
$categorias = [
    ['id' => 1, 'name' => 'Panes', 'type' => 'ingrediente'],
    ['id' => 2, 'name' => 'Carnes', 'type' => 'ingrediente'],
    ['id' => 3, 'name' => 'Vegetales', 'type' => 'ingrediente'],
    ['id' => 4, 'name' => 'Salsas', 'type' => 'ingrediente'],
    ['id' => 5, 'name' => 'Quesos', 'type' => 'ingrediente'],
    ['id' => 6, 'name' => 'Lácteos', 'type' => 'ingrediente'],
    ['id' => 7, 'name' => 'Embutidos', 'type' => 'ingrediente'],
    ['id' => 8, 'name' => 'Aves', 'type' => 'ingrediente'],
    ['id' => 9, 'name' => 'Pescados', 'type' => 'ingrediente'],
    ['id' => 10, 'name' => 'Otros', 'type' => 'ingrediente'],
    ['id' => 11, 'name' => 'Bebidas', 'type' => 'producto'],
    ['id' => 12, 'name' => 'Sándwiches', 'type' => 'producto']
];

echo json_encode($categorias);
?>