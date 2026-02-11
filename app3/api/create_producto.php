<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false, 
        'error' => 'Método no permitido. Este endpoint requiere una solicitud POST con datos JSON.',
        'usage' => [
            'method' => 'POST',
            'content_type' => 'application/json',
            'body' => [
                'name' => 'Nombre del producto (requerido)',
                'price' => 'Precio del producto (requerido)',
                'category' => 'Categoría del producto (opcional)'
            ]
        ]
    ]));
}

require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Obtener datos del cuerpo de la solicitud
$data = json_decode(file_get_contents('php://input'), true);

// Verificar que se recibieron los datos necesarios
if (!isset($data['name']) || !isset($data['price'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Faltan datos requeridos: name y price son obligatorios']));
}

// Sanitizar los datos
$nombre = $conn->real_escape_string($data['name']); // Nombre del producto
$precio = intval($data['price']); // Precio del producto
$category = isset($data['category']) ? $conn->real_escape_string($data['category']) : ''; // Categoría (texto)
$imagen = ''; // Sin imagen por defecto
$activo = 1; // Activo por defecto

// Manejar la categoría_id
$categoria_id = 1; // Usar una categoría existente por defecto (ID 1)

// Si se proporciona una nueva categoría, intentar crearla primero
$newCategory = isset($data['newCategory']) && !empty($data['newCategory']) ? $conn->real_escape_string($data['newCategory']) : '';

if ($newCategory) {
    // Verificar si la categoría ya existe
    $check_category = "SELECT id FROM categorias WHERE nombre = '$newCategory'";
    $category_result = $conn->query($check_category);
    
    if ($category_result && $category_result->num_rows > 0) {
        // La categoría ya existe, usar su ID
        $category_row = $category_result->fetch_assoc();
        $categoria_id = $category_row['id'];
        $category = $newCategory; // Actualizar también el nombre de la categoría
    } else {
        // Intentar crear la nueva categoría
        try {
            $insert_category = "INSERT INTO categorias (nombre) VALUES ('$newCategory')";
            if ($conn->query($insert_category) === TRUE) {
                $categoria_id = $conn->insert_id;
                $category = $newCategory;
            }
        } catch (Exception $e) {
            // Si falla la creación de la categoría, usar la categoría por defecto
            // No lanzar error para permitir que el producto se cree de todos modos
        }
    }
} else if ($category) {
    // Si se seleccionó una categoría existente, obtener su ID
    $check_category = "SELECT id FROM categorias WHERE nombre = '$category'";
    $category_result = $conn->query($check_category);
    
    if ($category_result && $category_result->num_rows > 0) {
        $category_row = $category_result->fetch_assoc();
        $categoria_id = $category_row['id'];
    }
}

// Generar un ID único para el producto (formato: P-número)
$sql = "SELECT MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)) as max_id FROM productos WHERE id LIKE 'P-%'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$next_id = $row['max_id'] ? $row['max_id'] + 1 : 1;
$id = "P-" . $next_id;

// Verificar la estructura de la tabla
try {
    $check_table = "DESCRIBE productos";
    $table_result = $conn->query($check_table);
    $columns = [];
    while ($row = $table_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    // Adaptar la consulta SQL a las columnas existentes
    $fields = [];
    $values = [];
    
    // Campos obligatorios
    $fields[] = 'id';
    $values[] = "'$id'";
    
    // Verificar cada columna y añadirla si existe
    if (in_array('nombre', $columns)) {
        $fields[] = 'nombre';
        $values[] = "'$nombre'";
    } else if (in_array('name', $columns)) {
        $fields[] = 'name';
        $values[] = "'$nombre'";
    }
    
    if (in_array('precio', $columns)) {
        $fields[] = 'precio';
        $values[] = $precio;
    } else if (in_array('price', $columns)) {
        $fields[] = 'price';
        $values[] = $precio;
    }
    
    if (in_array('category', $columns)) {
        $fields[] = 'category';
        $values[] = "'$category'";
    }
    
    if (in_array('categoria_id', $columns)) {
        $fields[] = 'categoria_id';
        $values[] = $categoria_id;
    }
    
    if (in_array('imagen', $columns)) {
        $fields[] = 'imagen';
        $values[] = "'$imagen'";
    }
    
    if (in_array('activo', $columns)) {
        $fields[] = 'activo';
        $values[] = $activo;
    }
    
    // Construir la consulta SQL
    $fields_str = implode(', ', $fields);
    $values_str = implode(', ', $values);
    $sql = "INSERT INTO productos ($fields_str) VALUES ($values_str)";
    
    // Ejecutar la consulta
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Error al crear el producto: ' . $conn->error,
            'sql' => $sql
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error en el servidor: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Cerrar conexión
$conn->close();