-- Crear tabla para pedidos TUU con datos del cliente y productos
CREATE TABLE IF NOT EXISTS tuu_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    
    -- Datos del cliente
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    table_number VARCHAR(10),
    
    -- Datos del producto
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    
    -- Datos de cuotas
    installments_total INT DEFAULT 1,
    installment_current INT DEFAULT 1,
    installment_amount DECIMAL(10,2) NOT NULL,
    
    -- Datos TUU
    tuu_payment_request_id INT,
    tuu_idempotency_key VARCHAR(255),
    tuu_device_used VARCHAR(50),
    
    -- Estado
    status ENUM('pending', 'sent_to_pos', 'completed', 'failed') DEFAULT 'pending',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_tuu_payment (tuu_payment_request_id),
    INDEX idx_created_at (created_at)
);