-- Tabla para registros del concurso (máximo 8 participantes)
CREATE TABLE IF NOT EXISTS concurso_registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    customer_phone VARCHAR(20) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    acepta_terminos TINYINT(1) NOT NULL DEFAULT 1,
    
    -- Campos TUU compatibles con tuu_orders
    tuu_payment_request_id INT NULL,
    tuu_idempotency_key VARCHAR(255) NULL,
    tuu_device_used VARCHAR(50) NULL,
    payment_status ENUM('unpaid', 'pending_payment', 'paid', 'failed') DEFAULT 'unpaid',
    tuu_transaction_id VARCHAR(100) NULL,
    tuu_amount DECIMAL(10,2) DEFAULT 5000.00,
    tuu_timestamp VARCHAR(255) NULL,
    tuu_message VARCHAR(255) NULL,
    tuu_account_id VARCHAR(50) NULL,
    tuu_currency VARCHAR(10) DEFAULT 'CLP',
    tuu_signature TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_order_number (order_number),
    INDEX idx_email (email),
    INDEX idx_payment_status (payment_status),
    INDEX idx_tuu_payment_request_id (tuu_payment_request_id)
);

-- Trigger para limitar a 8 participantes máximo
DROP TRIGGER IF EXISTS limite_participantes;
DELIMITER //
CREATE TRIGGER limite_participantes 
BEFORE INSERT ON concurso_registros
FOR EACH ROW
BEGIN
    DECLARE total_registros INT;
    SELECT COUNT(*) INTO total_registros FROM concurso_registros WHERE payment_status = 'paid';
    IF total_registros >= 8 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Máximo 8 participantes permitidos';
    END IF;
END//
DELIMITER ;