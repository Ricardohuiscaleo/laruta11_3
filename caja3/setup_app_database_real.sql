-- =====================================================
-- RUTA11 APP - BASE DE DATOS BASADA EN MenuApp.jsx
-- =====================================================

-- CATEGORÍAS SEGÚN MenuApp.jsx
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
    grams INT DEFAULT 0,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active),
    INDEX idx_sku (sku)
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

-- =====================================================
-- DATOS REALES BASADOS EN MenuApp.jsx
-- =====================================================

-- Categorías exactas del MenuApp.jsx
INSERT INTO categories (name, description, sort_order) VALUES
('La Ruta 11', 'Cortes premium y especialidades de la casa', 1),
('Sandwiches', 'Churrascos de carne y pollo', 2),
('Hamburguesas', 'Hamburguesas clásicas y especiales', 3),
('Completos', 'Completos tradicionales y al vapor', 4),
('Snacks', 'Papas, jugos, bebidas y salsas', 5);

-- Productos La Ruta 11 (Tomahawks)
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
(1, 'Tomahawk Papa', 'Corte Premium Tomahawk + papa tradicional o rústica + mayo a elección.', 17000, 'TOMA-PAPA', 20, 800, 1200, 350, 'https://laruta11-images.s3.amazonaws.com/menu/1755619388_toma-papa.png'),
(1, 'Tomahawk Provoleta', 'Corte Premium Tomahawk + provoleta fundida.', 20000, 'TOMA-PRO', 15, 750, 980, 280, 'https://laruta11-images.s3.amazonaws.com/menu/1755619385_toma-provoleta.png'),
(1, 'Tomahawk Full Ruta 11', 'Corte Premium Tomahawk + papa tradicional o rústica + provoleta + mayo a elección.', 22000, 'TOMA-FULL', 10, 950, 2500, 600, 'https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png');

-- Sandwiches (Churrascos)
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
(2, 'Churrasco Vacuno', '3 filetes de vacuno (120g aprox.), pan frica, tomate, palta, mayo Kraft.', 6000, 'CHURR-VAC', 30, 380, 850, 150, 'https://laruta11-images.s3.amazonaws.com/menu/1756125226_1-churrasco-vacuno.jpg'),
(2, 'Churrasco Queso (Vacuno)', '3 filetes de vacuno, queso mantecoso, pan frica, tomate, palta, mayo Kraft.', 6500, 'CHURR-Q-VAC', 25, 420, 760, 180, 'https://laruta11-images.s3.amazonaws.com/menu/1756125224_104-churrasco-queso--vacuno-.jpg'),
(2, 'Churrasco Pollo', '3 filetes de pollo (120g aprox.), pan frica, tomate, palta, mayo Kraft.', 5800, 'CHURR-POLLO', 35, 380, 650, 120, 'https://laruta11-images.s3.amazonaws.com/menu/1756125223_3-churrasco-pollo.jpg'),
(2, 'Churrasco Queso (Pollo)', '3 filetes de pollo, queso mantecoso, pan frica, tomate, palta, mayo Kraft.', 6500, 'CHURR-Q-POLLO', 30, 420, 550, 130, 'https://laruta11-images.s3.amazonaws.com/menu/1756125222_105-churrasco-queso--pollo-.jpg'),
(2, 'Churrasco Vegetariano', 'Carne vegetal, tomate, palta, lechuga, pan frica, mayo a elección.', 4800, 'CHURR-VEG', 20, 350, 920, 310, 'https://laruta11-images.s3.amazonaws.com/menu/1756125221_4-churrasco-vegetariano.jpg');

-- Hamburguesas
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
(3, 'Hamburguesa Clásica', 'Carne de vacuno, lechuga, tomate, cebolla, pepinillos, mayo y ketchup.', 4500, 'BURG-CLAS', 25, 350, 1200, 400, 'https://laruta11-images.s3.amazonaws.com/menu/1756125210_401-hamburguesa-cl-sica.jpg'),
(3, 'Hamburguesa con Queso', 'Carne de vacuno, queso cheddar, lechuga, tomate, cebolla, mayo y ketchup.', 5000, 'BURG-QUESO', 30, 380, 1500, 550, 'https://laruta11-images.s3.amazonaws.com/menu/1756125209_402-hamburguesa-con-queso.jpg'),
(3, 'Hamburguesa Doble', 'Doble carne de vacuno, queso, lechuga, tomate, cebolla, mayo y ketchup.', 6500, 'BURG-DOBLE', 20, 450, 900, 300, 'https://laruta11-images.s3.amazonaws.com/menu/1756125208_403-hamburguesa-doble.jpg'),
(3, 'Hamburguesa BBQ', 'Carne de vacuno, salsa BBQ, cebolla caramelizada, tocino, queso cheddar.', 5800, 'BURG-BBQ', 25, 420, 800, 280, 'https://laruta11-images.s3.amazonaws.com/menu/1756125207_404-hamburguesa-bbq.jpg'),
(3, 'Hamburguesa Ruta 11', 'Doble carne, queso provoleta, tocino, palta, tomate, salsa especial.', 7200, 'BURG-R11', 15, 500, 2000, 750, 'https://laruta11-images.s3.amazonaws.com/menu/1756125206_405-hamburguesa-ruta-11.jpg');

-- Completos
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
(4, 'Completo Tradicional', 'Vienesa receta sureña, tomate, palta, mayo Kraft.', 2000, 'COMP-TRAD', 50, 280, 1500, 450, 'https://laruta11-images.s3.amazonaws.com/menu/1755619377_Completo-italiano.png'),
(4, 'Completo Talquino', 'Vienesa receta sureña, tomate, palta, mayo Kraft, pan al vapor.', 2300, 'COMP-TALQ', 40, 290, 1100, 320, 'https://laruta11-images.s3.amazonaws.com/menu/1755619379_completo-talquino.png'),
(4, 'Completo Talquino Premium', 'Vienesa Premium, tomate, palta, mayo Kraft, pan al vapor.', 2700, 'COMP-TALQ-P', 30, 310, 1300, 400, 'https://laruta11-images.s3.amazonaws.com/menu/1756125220_107-completo-talquino-premium.jpg');

-- Snacks (Papas, jugos, bebidas, salsas)
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
(5, 'Papas Fritas', '300g aprox. tradicional o rústicas.', 2000, 'PAP-FRIT', 60, 300, 2200, 800, 'https://laruta11-images.s3.amazonaws.com/menu/1756125219_12-papas-fritas.jpg'),
(5, 'Salchipapas', 'Papa tradicional o rústica + vienesas receta sureña.', 2500, 'SALCHI', 40, 400, 1800, 650, 'https://laruta11-images.s3.amazonaws.com/menu/1755619380_salchi-papas.png'),
(5, 'Papas Provenzal', 'Papa tradicional o rústica con ajo, perejil y aceite de oliva.', 2500, 'PAP-PROV', 35, 320, 900, 250, 'https://laruta11-images.s3.amazonaws.com/menu/1756125217_109-papas-provenzal.jpg'),
(5, 'Papas Ruta 11', 'Papa tradicional o rústica con tocino, queso cheddar fundido + cebollín.', 3500, 'PAP-R11', 30, 450, 2800, 1100, 'https://laruta11-images.s3.amazonaws.com/menu/1755619383_papas-ruta11.png'),
(5, 'Jugo de Frutilla', 'Jugo natural hecho con frutillas frescas de temporada.', 2500, 'JUGO-FRUT', 25, 450, 700, 200, 'https://laruta11-images.s3.amazonaws.com/menu/1756125217_6-jugo-de-frutilla.jpg'),
(5, 'Jugo de Melón Tuna', 'Refrescante jugo de melón tuna, perfecto para un día de calor.', 2500, 'JUGO-MEL', 25, 450, 600, 150, 'https://laruta11-images.s3.amazonaws.com/menu/1756125216_7-jugo-de-mel-n-tuna.jpg'),
(5, 'Coca-Cola', 'Lata de 350cc.', 1500, 'COCA', 100, 350, 3000, 1500, 'https://laruta11-images.s3.amazonaws.com/menu/1756125215_8-coca-cola.jpg'),
(5, 'Sprite', 'Lata de 350cc.', 1500, 'SPRITE', 100, 350, 1500, 700, 'https://laruta11-images.s3.amazonaws.com/menu/1756125214_9-sprite.jpg'),
(5, 'Mayonesa de Ajo', 'La primera es gratis, adicional $500.', 0, 'MAYO-AJO', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125213_201-mayonesa-de-ajo.jpg'),
(5, 'Mayonesa de Aceituna', 'La primera es gratis, adicional $500.', 0, 'MAYO-ACEIT', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125212_202-mayonesa-de-aceituna.jpg'),
(5, 'Mayonesa de Albahaca', 'La primera es gratis, adicional $500.', 0, 'MAYO-ALB', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125211_203-mayonesa-de-albahaca.jpg');

-- Usuario admin
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@laruta11.cl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin');

-- Configuración
INSERT INTO system_config (config_key, config_value, description) VALUES
('restaurant_name', 'La Ruta 11', 'Nombre del restaurante'),
('tax_rate', '19', 'Tasa de IVA en porcentaje'),
('delivery_fee', '1500', 'Costo de delivery'),
('api_endpoints', '/api/products.php,/api/categories.php,/api/admin_dashboard.php', 'APIs disponibles');