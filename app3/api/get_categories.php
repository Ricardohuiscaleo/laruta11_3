<?php
// Configurar encabezados para evitar caché
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

// Crear conexión usando la configuración de app
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Verificar si la tabla categories existe
$tableExists = false;
$result = $conn->query("SHOW TABLES LIKE 'categories'");
if ($result && $result->num_rows > 0) {
    $tableExists = true;
}

// Si la tabla no existe, crearla
if (!$tableExists) {
    $sql = "CREATE TABLE categories (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    )";
    
    if ($conn->query($sql) === TRUE) {
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
        
        foreach ($defaultCategories as $category) {
            $name = $category['name'];
            $type = $category['type'];
            $conn->query("INSERT INTO categories (name, type) VALUES ('$name', '$type')");
        }
    } else {
        die(json_encode(['success' => false, 'error' => 'Error al crear la tabla categories: ' . $conn->error]));
    }
}

// Consultar categorías
$sql = "SELECT * FROM categories ORDER BY name";
$result = $conn->query($sql);

$categorias = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}

echo json_encode($categorias);

$conn->close();
?>