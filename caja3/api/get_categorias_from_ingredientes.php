<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener categorías únicas de la tabla ingredientes
$sql = "SELECT DISTINCT categoria FROM ingredientes WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
$result = $conn->query($sql);

$categorias = [];
$id = 1;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = [
            'id' => $id++,
            'name' => $row['categoria'],
            'type' => 'ingrediente'
        ];
    }
}

// Si no hay categorías, añadir algunas por defecto
if (empty($categorias)) {
    $categorias = [
        ['id' => 1, 'name' => 'Panes', 'type' => 'ingrediente'],
        ['id' => 2, 'name' => 'Carnes', 'type' => 'ingrediente'],
        ['id' => 3, 'name' => 'Vegetales', 'type' => 'ingrediente'],
        ['id' => 4, 'name' => 'Salsas', 'type' => 'ingrediente'],
        ['id' => 5, 'name' => 'Quesos', 'type' => 'ingrediente'],
        ['id' => 6, 'name' => 'Otros', 'type' => 'ingrediente']
    ];
}

echo json_encode($categorias);

$conn->close();
?>