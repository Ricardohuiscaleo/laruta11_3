<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Verificar si la tabla categorias existe
$tableExists = false;
$result = $conn->query("SHOW TABLES LIKE 'categorias'");
if ($result && $result->num_rows > 0) {
    $tableExists = true;
}

// Si la tabla no existe, crearla
if (!$tableExists) {
    $sql = "CREATE TABLE categorias (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Tabla categorias creada correctamente']);
        
        // Insertar categorías por defecto
        $defaultCategories = [
            ['name' => 'Panes', 'type' => 'ingrediente'],
            ['name' => 'Carnes', 'type' => 'ingrediente'],
            ['name' => 'Vegetales', 'type' => 'ingrediente'],
            ['name' => 'Salsas', 'type' => 'ingrediente'],
            ['name' => 'Quesos', 'type' => 'ingrediente'],
            ['name' => 'Bebidas', 'type' => 'producto'],
            ['name' => 'Sándwiches', 'type' => 'producto']
        ];
        
        $stmt = $conn->prepare("INSERT INTO categorias (name, type) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $type);
        
        foreach ($defaultCategories as $category) {
            $name = $category['name'];
            $type = $category['type'];
            $stmt->execute();
        }
        
        $stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Categorías por defecto insertadas correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al crear la tabla categorias: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'La tabla categorias ya existe']);
}

$conn->close();
?>