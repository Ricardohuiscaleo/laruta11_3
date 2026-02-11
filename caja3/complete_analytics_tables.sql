-- Script SQL completo para crear tablas faltantes de analytics
-- Base de datos: u958525313_app

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

-- Tabla básica de productos para referencia
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(8,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar datos de ejemplo para visitas
INSERT IGNORE INTO site_visits (ip_address, page_url, visit_date, device_type, browser) VALUES
('192.168.1.1', 'https://app.laruta11.cl', CURDATE(), 'mobile', 'Chrome'),
('192.168.1.2', 'https://app.laruta11.cl', CURDATE(), 'desktop', 'Firefox'),
('192.168.1.3', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'mobile', 'Safari'),
('192.168.1.4', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'tablet', 'Chrome'),
('192.168.1.5', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'mobile', 'Safari');

-- Insertar productos básicos
INSERT IGNORE INTO products (id, name, description, price) VALUES
(1, 'Tomahawk Full Ruta 11', 'Corte Premium Tomahawk + papa + provoleta', 22000.00),
(2, 'Completo Talquino', 'Vienesa, tomate, palta, mayo', 2300.00),
(3, 'Papas Ruta 11', 'Papa con tocino, queso cheddar', 3500.00),
(4, 'Churrasco Vacuno', '3 filetes de vacuno, pan frica', 6000.00),
(5, 'Hamburguesa Ruta 11', 'Doble carne, provoleta, tocino', 7200.00);

-- Insertar pedidos de ejemplo
INSERT IGNORE INTO user_orders (user_id, order_number, total_amount, status, order_date) VALUES
(1, 'R11-001', 22000.00, 'delivered', CURDATE()),
(2, 'R11-002', 8800.00, 'delivered', CURDATE()),
(3, 'R11-003', 15000.00, 'pending', CURDATE()),
(1, 'R11-004', 9500.00, 'delivered', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(2, 'R11-005', 12300.00, 'delivered', DATE_SUB(CURDATE(), INTERVAL 2 DAY));

-- Insertar items de pedidos
INSERT IGNORE INTO user_order_items (order_id, product_id, product_name, quantity, unit_price, total_price) VALUES
(1, 1, 'Tomahawk Full Ruta 11', 1, 22000.00, 22000.00),
(2, 2, 'Completo Talquino', 2, 2300.00, 4600.00),
(2, 5, 'Hamburguesa Ruta 11', 1, 7200.00, 7200.00),
(3, 3, 'Papas Ruta 11', 2, 3500.00, 7000.00),
(3, 4, 'Churrasco Vacuno', 1, 6000.00, 6000.00);

-- Insertar métricas del día actual
INSERT INTO daily_metrics (metric_date, unique_visitors, total_visits, new_users, total_orders, total_revenue) 
VALUES (CURDATE(), 25, 35, 5, 3, 45800.00)
ON DUPLICATE KEY UPDATE 
    unique_visitors = VALUES(unique_visitors),
    total_visits = VALUES(total_visits),
    new_users = VALUES(new_users),
    total_orders = VALUES(total_orders),
    total_revenue = VALUES(total_revenue);