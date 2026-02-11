-- SQL para ejecutar manualmente en u958525313_app
-- Tabla para registrar pagos TUU de usuarios registrados
-- IMPORTANTE: user_id hace referencia a usuarios.id (ej: Ricardo = 4)

CREATE TABLE IF NOT EXISTS tuu_pagos_online (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT 'ID del usuario de la tabla usuarios (ej: 4 = Ricardo)',
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