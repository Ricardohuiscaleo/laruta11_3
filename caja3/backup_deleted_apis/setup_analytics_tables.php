<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';
$config = require '../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabla para tracking de visitas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            user_agent TEXT,
            page_url VARCHAR(500),
            referrer VARCHAR(500),
            visit_date DATE,
            visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            session_id VARCHAR(100),
            user_id INT NULL,
            INDEX idx_visit_date (visit_date),
            INDEX idx_user_id (user_id)
        )
    ");

    // Tabla para usuarios de la app (clientes)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            phone VARCHAR(20),
            password_hash VARCHAR(255),
            address TEXT,
            city VARCHAR(50),
            region VARCHAR(50),
            postal_code VARCHAR(10),
            birth_date DATE,
            gender ENUM('M', 'F', 'Other'),
            registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            email_verified BOOLEAN DEFAULT FALSE,
            phone_verified BOOLEAN DEFAULT FALSE,
            total_orders INT DEFAULT 0,
            total_spent DECIMAL(10,2) DEFAULT 0.00,
            favorite_products TEXT,
            preferences JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Tabla para pedidos de usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            order_number VARCHAR(50) UNIQUE,
            status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
            total_amount DECIMAL(10,2) NOT NULL,
            delivery_address TEXT,
            delivery_fee DECIMAL(8,2) DEFAULT 0.00,
            payment_method VARCHAR(50),
            payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
            notes TEXT,
            estimated_delivery_time TIMESTAMP NULL,
            delivered_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )
    ");

    // Tabla para items de pedidos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(200),
            quantity INT NOT NULL,
            unit_price DECIMAL(8,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            special_instructions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES user_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");

    echo json_encode(['success' => true, 'message' => 'Tablas de analytics y usuarios creadas correctamente']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>