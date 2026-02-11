<?php
require_once '../config.php';

// Configuración para base de datos de usuarios
$user_conn = mysqli_connect('localhost', 'u958525313_usuariosruta11', 'Usuariosruta11', 'u958525313_usuariosruta11');

if($user_conn === false){
    die("ERROR: No se pudo conectar a la base de usuarios. " . mysqli_connect_error());
}

mysqli_set_charset($user_conn, "utf8");

// Tabla para reseñas de productos
$sql_reviews = "CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
)";

// Tabla para likes de productos
$sql_likes = "CREATE TABLE IF NOT EXISTS product_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product_like (user_id, product_id)
)";

// Tabla para vistas de productos
$sql_views = "CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    product_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_views (product_id),
    INDEX idx_user_views (user_id)
)";

// Ejecutar queries
$tables = [
    'product_reviews' => $sql_reviews,
    'product_likes' => $sql_likes,
    'product_views' => $sql_views
];

foreach ($tables as $table_name => $sql) {
    if (mysqli_query($user_conn, $sql)) {
        echo "✅ Tabla $table_name creada correctamente<br>";
    } else {
        echo "❌ Error creando tabla $table_name: " . mysqli_error($user_conn) . "<br>";
    }
}

mysqli_close($user_conn);
?>