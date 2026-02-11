<?php
header("Content-Type: application/json");
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Eliminar todos los ingredientes excepto los primeros 26
$conn->query("DELETE FROM ingredientes WHERE id > 26");

// Asegurarse de que existan los ingredientes del 1 al 26
$ingredientes_base = [
    1 => "Pan Marraqueta",
    2 => "Pan Frica",
    3 => "Pan de Completo",
    4 => "Churrasco (Posta)",
    5 => "Lomo de Cerdo",
    6 => "Carne Mechada",
    7 => "Milanesa de Vacuno",
    8 => "Pechuga de Pollo",
    9 => "Merluza (filete)",
    10 => "Vienesa (Sureña)",
    11 => "Queso Chanco/Gauda",
    12 => "Huevo",
    13 => "Palta Hass",
    14 => "Tomate",
    15 => "Cebolla",
    16 => "Porotos Verdes",
    17 => "Ají Verde",
    18 => "Lechuga",
    19 => "Mayonesa",
    20 => "Chucrut",
    21 => "Papas Fritas Cong.",
    22 => "Jamón Sandwich",
    23 => "Papas Hilo",
    24 => "Salsa al Olivo",
    25 => "Choclo",
    26 => "Aceitunas"
];

$ingredientes_creados = 0;
$ingredientes_actualizados = 0;

foreach ($ingredientes_base as $id => $nombre) {
    // Verificar si el ingrediente existe
    $result = $conn->query("SELECT id FROM ingredientes WHERE id = $id");
    
    if ($result->num_rows > 0) {
        // Actualizar el ingrediente
        $stmt = $conn->prepare("UPDATE ingredientes SET nombre = ?, costo_compra = 1000, costo_por_gramo = 1 WHERE id = ?");
        $stmt->bind_param("si", $nombre, $id);
        $stmt->execute();
        $ingredientes_actualizados++;
    } else {
        // Crear el ingrediente
        $stmt = $conn->prepare("INSERT INTO ingredientes (id, nombre, costo_compra, costo_por_gramo, iva_incluido) VALUES (?, ?, 1000, 1, 1)");
        $stmt->bind_param("is", $id, $nombre);
        $stmt->execute();
        $ingredientes_creados++;
    }
}

echo json_encode([
    'success' => true,
    'ingredientes_creados' => $ingredientes_creados,
    'ingredientes_actualizados' => $ingredientes_actualizados,
    'mensaje' => 'Ingredientes base restablecidos correctamente'
]);

$conn->close();
?>