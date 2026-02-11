<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Incluir archivo de configuración
// Primero intentamos con la configuración local
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    // Si no existe, intentamos con la configuración global
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
        
        // Configurar la conexión a la base de datos usando los valores del config global
        $conn = mysqli_connect(
            $config['Calcularuta11_db_host'],
            $config['Calcularuta11_db_user'],
            $config['Calcularuta11_db_pass'],
            $config['Calcularuta11_db_name']
        );
        
        // Verificar la conexión
        if($conn === false){
            http_response_code(500);
            echo json_encode(["success" => false, "error" => "No se pudo conectar a la base de datos: " . mysqli_connect_error()]);
            exit;
        }
        
        // Configurar el conjunto de caracteres a utf8
        mysqli_set_charset($conn, "utf8");
    } else {
        http_response_code(500);
        echo json_encode(["error" => "No se encontró el archivo de configuración"]);
        exit;
    }
}

// Verificar si existe la tabla categorias
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'categorias'");
if (mysqli_num_rows($check_table) == 0) {
    // Crear la tabla categorias
    $create_table = "CREATE TABLE categorias (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nombre VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!mysqli_query($conn, $create_table)) {
        echo json_encode([
            "success" => false,
            "error" => "No se pudo crear la tabla categorias: " . mysqli_error($conn)
        ]);
        exit;
    }
    
    // Insertar categorías predefinidas
    $categorias_predefinidas = [
        "Sándwiches",
        "Hamburguesas",
        "Completos",
        "Vegetarianos",
        "Bebidas",
        "Jugos"
    ];
    
    foreach ($categorias_predefinidas as $cat) {
        $sql_insert = "INSERT INTO categorias (nombre) VALUES (?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("s", $cat);
        $stmt_insert->execute();
    }
}

// Obtener categorías
$categorias = [];
$sql_categorias = "SELECT id, nombre FROM categorias";
$result_categorias = $conn->query($sql_categorias);
if ($result_categorias) {
    while ($row = $result_categorias->fetch_assoc()) {
        $categorias[$row['id']] = $row['nombre'];
    }
}

// Verificar si hay productos con category pero sin categoria_id
$sql_check = "SELECT * FROM productos WHERE category IS NOT NULL AND category != '' AND (categoria_id IS NULL OR categoria_id = 0)";
$result_check = $conn->query($sql_check);
$productos_actualizados = [];

if ($result_check && $result_check->num_rows > 0) {
    // Hay productos que necesitan actualización
    while ($row = $result_check->fetch_assoc()) {
        $id = $row['id'];
        $category = $row['category'];
        
        // Buscar o crear la categoría
        $categoria_id = null;
        
        // Buscar la categoría por nombre
        $sql_find = "SELECT id FROM categorias WHERE nombre = ?";
        $stmt_find = $conn->prepare($sql_find);
        $stmt_find->bind_param("s", $category);
        $stmt_find->execute();
        $result_find = $stmt_find->get_result();
        
        if ($result_find && $result_find->num_rows > 0) {
            // La categoría existe
            $categoria_row = $result_find->fetch_assoc();
            $categoria_id = $categoria_row['id'];
        } else {
            // La categoría no existe, crearla
            $sql_insert = "INSERT INTO categorias (nombre) VALUES (?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("s", $category);
            
            if ($stmt_insert->execute()) {
                $categoria_id = $conn->insert_id;
            }
        }
        
        // Actualizar el producto con la categoría correcta
        if ($categoria_id) {
            $sql_update = "UPDATE productos SET categoria_id = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("is", $categoria_id, $id);
            
            if ($stmt_update->execute()) {
                $productos_actualizados[] = [
                    'id' => $id,
                    'nombre' => $row['nombre'],
                    'category_original' => $category,
                    'categoria_id_nuevo' => $categoria_id
                ];
            }
        }
    }
}

// Verificar si hay productos con categoria_id pero sin category
$sql_check2 = "SELECT p.*, c.nombre as categoria_nombre 
               FROM productos p 
               LEFT JOIN categorias c ON p.categoria_id = c.id 
               WHERE (p.category IS NULL OR p.category = '') AND p.categoria_id IS NOT NULL AND p.categoria_id > 0";
$result_check2 = $conn->query($sql_check2);
$productos_actualizados2 = [];

if ($result_check2 && $result_check2->num_rows > 0) {
    // Hay productos que necesitan actualización
    while ($row = $result_check2->fetch_assoc()) {
        $id = $row['id'];
        $categoria_id = $row['categoria_id'];
        $categoria_nombre = $row['categoria_nombre'];
        
        if ($categoria_nombre) {
            // Actualizar el campo category
            $sql_update = "UPDATE productos SET category = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ss", $categoria_nombre, $id);
            
            if ($stmt_update->execute()) {
                $productos_actualizados2[] = [
                    'id' => $id,
                    'nombre' => $row['nombre'],
                    'categoria_id' => $categoria_id,
                    'category_nuevo' => $categoria_nombre
                ];
            }
        }
    }
}

// Devolver resultados
echo json_encode([
    "success" => true,
    "categorias" => $categorias,
    "productos_actualizados_category_to_id" => $productos_actualizados,
    "productos_actualizados_id_to_category" => $productos_actualizados2,
    "mensaje" => "Proceso de corrección de categorías completado"
]);

$conn->close();
?>