-- Script SQL para crear tablas de analytics y métricas de usuarios
-- Para Dashboard de La Ruta 11

-- Tabla para tracking de visitas a la app
CREATE TABLE IF NOT EXISTS site_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    page_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500),
    session_id VARCHAR(100),
    visit_date DATE NOT NULL,
    visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    country VARCHAR(100),
    city VARCHAR(100),
    device_type ENUM('mobile', 'tablet', 'desktop') DEFAULT 'mobile',
    browser VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_visit_date (visit_date),
    INDEX idx_ip_date (ip_address, visit_date),
    INDEX idx_session (session_id)
);

-- Tabla para usuarios registrados de la app
CREATE TABLE IF NOT EXISTS app_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    name VARCHAR(255),
    last_name VARCHAR(255),
    birth_date DATE,
    gender ENUM('M', 'F', 'O'),
    address TEXT,
    city VARCHAR(100),
    region VARCHAR(100),
    postal_code VARCHAR(10),
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    favorite_category_id INT,
    preferred_payment_method VARCHAR(50),
    marketing_consent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_registration_date (registration_date),
    INDEX idx_city (city)
);

-- Tabla para pedidos de usuarios
CREATE TABLE IF NOT EXISTS user_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    delivery_type ENUM('pickup', 'delivery') DEFAULT 'pickup',
    delivery_address TEXT,
    delivery_fee DECIMAL(8,2) DEFAULT 0.00,
    estimated_time INT, -- minutos
    notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_date (order_date),
    INDEX idx_status (status)
);

-- Tabla para items de pedidos
CREATE TABLE IF NOT EXISTS user_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(8,2) NOT NULL,
    total_price DECIMAL(8,2) NOT NULL,
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES user_orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

-- Tabla para métricas diarias (cache)
CREATE TABLE IF NOT EXISTS daily_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL UNIQUE,
    unique_visitors INT DEFAULT 0,
    total_visits INT DEFAULT 0,
    new_users INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    avg_order_value DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_metric_date (metric_date)
);

-- Insertar datos de ejemplo para testing
INSERT IGNORE INTO app_users (email, name, last_name, phone, city, total_orders, total_spent) VALUES
('juan.perez@email.com', 'Juan', 'Pérez', '+56912345678', 'Santiago', 5, 25000.00),
('maria.gonzalez@email.com', 'María', 'González', '+56987654321', 'Valparaíso', 3, 18500.00),
('carlos.rodriguez@email.com', 'Carlos', 'Rodríguez', '+56911223344', 'Concepción', 8, 42000.00);

-- Insertar visitas de ejemplo
INSERT IGNORE INTO site_visits (ip_address, page_url, visit_date, device_type, browser) VALUES
('192.168.1.1', 'https://app.laruta11.cl', CURDATE(), 'mobile', 'Chrome'),
('192.168.1.2', 'https://app.laruta11.cl', CURDATE(), 'desktop', 'Firefox'),
('192.168.1.3', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'mobile', 'Safari');

-- Insertar métricas del día actual
INSERT INTO daily_metrics (metric_date, unique_visitors, total_visits, new_users, total_orders, total_revenue) 
VALUES (CURDATE(), 15, 23, 3, 8, 45000.00)
ON DUPLICATE KEY UPDATE 
    unique_visitors = VALUES(unique_visitors),
    total_visits = VALUES(total_visits),
    new_users = VALUES(new_users),
    total_orders = VALUES(total_orders),
    total_revenue = VALUES(total_revenue);