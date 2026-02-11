-- Script para actualizar tabla concurso_registros a nueva estructura TUU

-- 1. Agregar nuevas columnas TUU
ALTER TABLE concurso_registros 
ADD COLUMN order_number VARCHAR(50) AFTER id,
ADD COLUMN customer_name VARCHAR(255) AFTER order_number,
ADD COLUMN customer_phone VARCHAR(20) AFTER email,
ADD COLUMN tuu_payment_request_id INT NULL AFTER acepta_terminos,
ADD COLUMN tuu_idempotency_key VARCHAR(255) NULL AFTER tuu_payment_request_id,
ADD COLUMN tuu_device_used VARCHAR(50) NULL AFTER tuu_idempotency_key,
ADD COLUMN payment_status ENUM('unpaid', 'pending_payment', 'paid', 'failed') DEFAULT 'unpaid' AFTER tuu_device_used,
ADD COLUMN tuu_transaction_id VARCHAR(100) NULL AFTER payment_status,
ADD COLUMN tuu_amount DECIMAL(10,2) DEFAULT 5000.00 AFTER tuu_transaction_id,
ADD COLUMN tuu_timestamp VARCHAR(255) NULL AFTER tuu_amount,
ADD COLUMN tuu_message VARCHAR(255) NULL AFTER tuu_timestamp,
ADD COLUMN tuu_account_id VARCHAR(50) NULL AFTER tuu_message,
ADD COLUMN tuu_currency VARCHAR(10) DEFAULT 'CLP' AFTER tuu_account_id,
ADD COLUMN tuu_signature TEXT NULL AFTER tuu_currency,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER tuu_signature,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 2. Migrar datos existentes
UPDATE concurso_registros SET 
    order_number = CONCAT('CONCURSO_', DATE_FORMAT(NOW(), '%Y%m%d'), '_', LPAD(id, 3, '0')),
    customer_name = nombre,
    customer_phone = telefono,
    payment_status = CASE 
        WHEN estado_pago = 'pagado' THEN 'paid'
        WHEN estado_pago = 'fallido' THEN 'failed'
        ELSE 'unpaid'
    END,
    tuu_transaction_id = transaction_id,
    created_at = COALESCE(fecha_registro, NOW()),
    updated_at = COALESCE(fecha_pago, fecha_registro, NOW());

-- 3. Agregar índices
ALTER TABLE concurso_registros 
ADD UNIQUE KEY idx_order_number (order_number),
ADD INDEX idx_payment_status (payment_status),
ADD INDEX idx_tuu_payment_request_id (tuu_payment_request_id);

-- 4. Eliminar columnas antiguas (opcional - comentado por seguridad)
-- ALTER TABLE concurso_registros 
-- DROP COLUMN nombre,
-- DROP COLUMN telefono,
-- DROP COLUMN estado_pago,
-- DROP COLUMN transaction_id,
-- DROP COLUMN fecha_registro,
-- DROP COLUMN fecha_pago;