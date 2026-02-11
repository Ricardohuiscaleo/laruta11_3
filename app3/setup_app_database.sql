-- =====================================================
-- RUTA11 APP - ESTRUCTURA COMPLETA DE BASE DE DATOS
-- Base de datos: u958525313_app
-- =====================================================

-- CATEGORÍAS DE PRODUCTOS
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
);

-- PRODUCTOS
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2) DEFAULT 0,
    image_url VARCHAR(255),
    sku VARCHAR(50) UNIQUE,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    has_variants BOOLEAN DEFAULT FALSE,
    preparation_time INT DEFAULT 10,
    calories INT,
    allergens TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_sku (sku),
    INDEX idx_stock (stock_quantity)
);

-- INGREDIENTES
CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    cost_per_unit DECIMAL(10,4) NOT NULL,
    current_stock DECIMAL(10,2) DEFAULT 0,
    min_stock_level DECIMAL(10,2) DEFAULT 1,
    supplier VARCHAR(100),
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_stock (current_stock),
    INDEX idx_expiry (expiry_date)
);

-- RECETAS
CREATE TABLE product_recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_ingredient (product_id, ingredient_id),
    INDEX idx_product (product_id),
    INDEX idx_ingredient (ingredient_id)
);

-- CLIENTES
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    loyalty_points INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

-- ÓRDENES
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT,
    customer_name VARCHAR(150),
    customer_phone VARCHAR(20),
    order_type ENUM('dine_in', 'takeaway', 'delivery', 'web') NOT NULL,
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    delivery_address TEXT,
    pos_device VARCHAR(20),
    cart_type VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status)
);

-- ITEMS DE ÓRDENES
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
);

-- PAGOS TUU
CREATE TABLE tuu_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    order_number VARCHAR(50),
    idempotency_key VARCHAR(36) NOT NULL UNIQUE,
    pos_device VARCHAR(20) DEFAULT 'pos1',
    cart_type VARCHAR(30) DEFAULT 'web',
    device_serial VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Pending',
    amount INT NOT NULL,
    description TEXT,
    tuu_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_idempotency (idempotency_key),
    INDEX idx_status (status)
);

-- MOVIMIENTOS INVENTARIO
CREATE TABLE inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(10,4),
    reference_type ENUM('purchase', 'sale', 'adjustment') NOT NULL,
    reference_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    INDEX idx_ingredient (ingredient_id),
    INDEX idx_type (movement_type)
);

-- USUARIOS ADMIN
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('admin', 'manager', 'cashier') DEFAULT 'cashier',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- CONFIGURACIÓN
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (config_key)
);

-- DATOS INICIALES
INSERT INTO categories (name, description, sort_order) VALUES
('Completos', 'Completos tradicionales y especiales', 1),
('Papas', 'Papas fritas y preparaciones', 2),
('Carnes', 'Carnes a la parrilla', 3),
('Bebidas', 'Bebidas frías y calientes', 4);

INSERT INTO products (category_id, name, description, price, sku, stock_quantity) VALUES
(1, 'Completo Italiano', 'Completo con palta, tomate y mayo', 2500, 'COMP-ITA', 50),
(1, 'Completo Talquino', 'Completo especial de la casa', 3000, 'COMP-TAL', 30),
(2, 'Papas Ruta 11', 'Papas fritas especiales', 2000, 'PAP-R11', 40),
(2, 'Salchipapas', 'Papas con salchicha', 3500, 'SALCHI', 25),
(3, 'Tomahawk Provoleta', 'Carne con provoleta', 8000, 'TOMA-PRO', 15),
(3, 'Tomahawk Full Ruta 11', 'Tomahawk completo', 12000, 'TOMA-FULL', 10);

INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@laruta11.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin');

INSERT INTO system_config (config_key, config_value, description) VALUES
('restaurant_name', 'La Ruta 11', 'Nombre del restaurante'),
('tax_rate', '19', 'Tasa de IVA en porcentaje'),
('delivery_fee', '1500', 'Costo de delivery');