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

// Función para verificar si una tabla existe
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

$response = [
    'success' => true,
    'actions' => []
];

// Verificar si la tabla ingredientes existe
if (!tableExists($conn, 'ingredientes')) {
    // Crear la tabla ingredientes
    $sql = "CREATE TABLE ingredientes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL,
        categoria VARCHAR(100) DEFAULT NULL,
        costo_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
        costo_neto DECIMAL(10,2) DEFAULT NULL,
        iva_incluido TINYINT(1) DEFAULT 1,
        costo_por_gramo DECIMAL(10,2) DEFAULT 0,
        unidad_nombre VARCHAR(20) DEFAULT 'kg',
        unidad_gramos INT(11) DEFAULT 1000,
        stock DECIMAL(10,2) DEFAULT 0,
        peso DECIMAL(10,2) DEFAULT 0,
        PRIMARY KEY (id)
    )";
    
    if ($conn->query($sql)) {
        $response['actions'][] = 'Tabla ingredientes creada correctamente';
    } else {
        $response['actions'][] = 'Error al crear tabla ingredientes: ' . $conn->error;
        $response['success'] = false;
    }
}

// Verificar si hay ingredientes en la tabla
$countQuery = $conn->query("SELECT COUNT(*) as count FROM ingredientes");
$count = 0;
if ($countQuery) {
    $countRow = $countQuery->fetch_assoc();
    $count = $countRow['count'];
}

// Si no hay ingredientes, insertar los ingredientes predeterminados
if ($count == 0) {
    $ingredientes = [
        ['Pan Marraqueta', 'Panes', 1100, 1, 1.1, 'kg', 100, 1000],
        ['Pan Frica', 'Panes', 2500, 1, 2.5, 'kg', 120, 1000],
        ['Pan de Completo', 'Panes', 2200, 1, 2.2, 'kg', 100, 1000],
        ['Churrasco (Posta)', 'Carnes', 9500, 1, 9.5, 'kg', 1000, 1000],
        ['Lomo de Cerdo', 'Carnes', 4700, 1, 4.7, 'kg', 1000, 1000],
        ['Carne Mechada', 'Carnes', 8780, 1, 8.8, 'kg', 1000, 998],
        ['Milanesa de Vacuno', 'Carnes', 5900, 1, 5.9, 'kg', 1000, 1000],
        ['Pechuga de Pollo', 'Aves', 5500, 1, 5.5, 'kg', 1000, 1000],
        ['Merluza (filete)', 'Pescados', 8000, 1, 8.0, 'kg', 1000, 1000],
        ['Vienesa (Sureña)', 'Embutidos', 3500, 1, 3.5, 'kg', 40, 1000],
        ['Queso Chanco/Gauda', 'Lácteos', 11110, 1, 11.1, 'kg', 1000, 1001],
        ['Huevo', 'Otros', 217, 1, 3.9, 'unidad', 55, 56],
        ['Palta Hass', 'Vegetales', 2500, 1, 2.5, 'kg', 1000, 1000],
        ['Tomate', 'Vegetales', 350, 1, 0.4, 'kg', 1000, 1000],
        ['Cebolla', 'Vegetales', 600, 1, 0.6, 'kg', 1000, 983],
        ['Porotos Verdes', 'Vegetales', 1500, 1, 1.5, 'kg', 1000, 1000],
        ['Ají Verde', 'Vegetales', 3070, 1, 3.1, 'kg', 1000, 990],
        ['Lechuga', 'Vegetales', 500, 1, 0.5, 'kg', 1000, 1000],
        ['Mayonesa', 'Salsas', 2870, 1, 2.9, 'kg', 1000, 990],
        ['Chucrut', 'Otros', 14690, 1, 14.7, 'kg', 1000, 999],
        ['Papas Fritas Cong.', 'Vegetales', 1120, 1, 1.1, 'kg', 1000, 1018],
        ['Jamón Sandwich', 'Embutidos', 7400, 1, 7.4, 'kg', 1000, 1000],
        ['Papas Hilo', 'Vegetales', 15000, 1, 15.0, 'kg', 1000, 1000],
        ['Salsa al Olivo', 'Salsas', 12500, 1, 12.5, 'kg', 1000, 1000],
        ['Choclo', 'Vegetales', 1000, 1, 1.0, 'kg', 1000, 1000],
        ['Aceitunas', 'Otros', 4000, 1, 4.0, 'kg', 1000, 1000]
    ];
    
    $insertCount = 0;
    foreach ($ingredientes as $index => $ingrediente) {
        $id = $index + 1; // Asegurar que el ID sea el correcto
        $nombre = $ingrediente[0];
        $categoria = $ingrediente[1];
        $costo_compra = $ingrediente[2];
        $iva_incluido = $ingrediente[3];
        $costo_por_gramo = $ingrediente[4];
        $unidad_nombre = $ingrediente[5];
        $unidad_gramos = $ingrediente[6];
        $peso = $ingrediente[7];
        
        $sql = "INSERT INTO ingredientes (id, nombre, categoria, costo_compra, iva_incluido, costo_por_gramo, unidad_nombre, unidad_gramos, stock, peso) 
                VALUES ($id, '$nombre', '$categoria', $costo_compra, $iva_incluido, $costo_por_gramo, '$unidad_nombre', $unidad_gramos, 0, $peso)";
        
        if ($conn->query($sql)) {
            $insertCount++;
        } else {
            $response['actions'][] = "Error al insertar ingrediente $nombre: " . $conn->error;
        }
    }
    
    $response['actions'][] = "Se insertaron $insertCount ingredientes";
} else {
    $response['actions'][] = "Ya existen $count ingredientes en la tabla";
}

// Verificar si hay problemas de coincidencia entre recetas e ingredientes
$checkCoincidencia = $conn->query("SELECT r.producto_id, r.ingrediente_id, 
                                  CASE WHEN i.id IS NULL THEN 0 ELSE 1 END as existe_ingrediente
                                  FROM recetas r
                                  LEFT JOIN ingredientes i ON r.ingrediente_id = i.id
                                  WHERE i.id IS NULL");

$ingredientesFaltantes = [];
if ($checkCoincidencia) {
    while ($row = $checkCoincidencia->fetch_assoc()) {
        $ingredientesFaltantes[] = $row;
    }
}

if (count($ingredientesFaltantes) > 0) {
    $response['actions'][] = "Se encontraron " . count($ingredientesFaltantes) . " ingredientes faltantes en recetas";
    
    // Corregir las recetas con ingredientes faltantes
    foreach ($ingredientesFaltantes as $faltante) {
        $productoId = $faltante['producto_id'];
        $ingredienteId = $faltante['ingrediente_id'];
        
        // Verificar si el ingrediente existe con otro ID
        $checkIngrediente = $conn->query("SELECT id FROM ingredientes WHERE id = $ingredienteId");
        if ($checkIngrediente && $checkIngrediente->num_rows > 0) {
            // El ingrediente existe, no debería estar en la lista de faltantes
            $response['actions'][] = "El ingrediente $ingredienteId existe pero aparece como faltante para el producto $productoId";
        } else {
            // El ingrediente no existe, usar un ingrediente de respaldo (Aceitunas, ID 26)
            $sql = "UPDATE recetas SET ingrediente_id = 26 WHERE producto_id = '$productoId' AND ingrediente_id = $ingredienteId";
            if ($conn->query($sql)) {
                $response['actions'][] = "Se actualizó el ingrediente $ingredienteId a 26 (Aceitunas) para el producto $productoId";
            } else {
                $response['actions'][] = "Error al actualizar el ingrediente $ingredienteId para el producto $productoId: " . $conn->error;
            }
        }
    }
} else {
    $response['actions'][] = "No se encontraron problemas de coincidencia entre recetas e ingredientes";
}

// Cerrar conexión
$conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
?>