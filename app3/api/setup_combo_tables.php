<?php
require_once 'config.php';

try {
    // Tabla principal de combos
    $sql1 = "CREATE TABLE IF NOT EXISTS combos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(500),
        category_id INT DEFAULT 8,
        active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    )";
    
    // Productos que componen cada combo
    $sql2 = "CREATE TABLE IF NOT EXISTS combo_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        combo_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT DEFAULT 1,
        is_selectable TINYINT(1) DEFAULT 0,
        selection_group VARCHAR(50),
        FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES productos(id)
    )";
    
    // Opciones seleccionables para grupos
    $sql3 = "CREATE TABLE IF NOT EXISTS combo_selections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        combo_id INT NOT NULL,
        selection_group VARCHAR(50) NOT NULL,
        product_id INT NOT NULL,
        additional_price DECIMAL(10,2) DEFAULT 0,
        FOREIGN KEY (combo_id) REFERENCES combos(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES productos(id)
    )";
    
    $pdo->exec($sql1);
    $pdo->exec($sql2);
    $pdo->exec($sql3);
    
    echo json_encode(['success' => true, 'message' => 'Tablas de combos creadas exitosamente']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>