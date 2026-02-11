-- SQL para verificar tablas necesarias en u958525313_app
-- Ejecutar en u958525313_app para verificar estructura

-- 1. Tabla usuarios (ya existe, verificar campos)
DESCRIBE usuarios;

-- 2. Verificar si existen estas tablas necesarias:
SHOW TABLES LIKE 'user_notifications';
SHOW TABLES LIKE 'user_locations'; 
SHOW TABLES LIKE 'tuu_orders';
SHOW TABLES LIKE 'tuu_pagos_online';

-- 3. Si no existen, crear las tablas faltantes:

-- Tabla de notificaciones de usuario
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    leida BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_leida TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_leida (leida),
    INDEX idx_fecha_creacion (fecha_creacion)
) COMMENT='Notificaciones para usuarios registrados';

-- Tabla de historial de ubicaciones
CREATE TABLE IF NOT EXISTS user_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    direccion TEXT,
    precision_metros INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) COMMENT='Historial de ubicaciones de usuarios';

-- Tabla de órdenes TUU (ya creada anteriormente)
CREATE TABLE IF NOT EXISTS tuu_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(100) UNIQUE COMMENT 'R11-XXXXX-XXXX',
    user_id INT NULL COMMENT 'ID del usuario registrado (NULL para anónimos, 4 = Ricardo)',
    amount DECIMAL(10,2) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    tuu_transaction_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) COMMENT='Órdenes TUU - incluye usuarios registrados y anónimos';

-- Tabla de pagos online de usuarios registrados (ya creada anteriormente)
CREATE TABLE IF NOT EXISTS tuu_pagos_online (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT 'ID del usuario (4 = Ricardo)',
    order_reference VARCHAR(100) COMMENT 'Referencia del pedido R11-XXXXX',
    amount DECIMAL(10,2) COMMENT 'Monto en CLP',
    payment_method ENUM('webpay', 'redcompra', 'onepay') DEFAULT 'webpay',
    tuu_transaction_id VARCHAR(255) COMMENT 'ID de transacción TUU',
    transbank_token VARCHAR(255) COMMENT 'Token de Webpay/Transbank',
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(50),
    webpay_response JSON COMMENT 'Respuesta completa de Webpay',
    tuu_callback_data JSON COMMENT 'Datos del callback TUU',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_order_reference (order_reference),
    INDEX idx_tuu_transaction_id (tuu_transaction_id),
    INDEX idx_created_at (created_at)
) COMMENT='Registro de pagos online procesados via TUU/Webpay de usuarios registrados';