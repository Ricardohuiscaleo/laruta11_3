-- SQL para ejecutar manualmente en u958525313_app
-- Tabla para órdenes TUU (complementa tuu_pagos_online)
-- IMPORTANTE: user_id hace referencia a usuarios.id (ej: 4 = Ricardo)

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