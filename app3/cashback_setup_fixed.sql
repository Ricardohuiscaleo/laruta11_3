-- ============================================
-- SISTEMA DE CASHBACK - SQL CORREGIDO
-- Base de datos: u958525313_app
-- Tabla de usuarios: usuarios (no users)
-- ============================================

-- 1. Crear tabla de billetera de usuario
CREATE TABLE IF NOT EXISTS user_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    total_earned DECIMAL(10,2) DEFAULT 0.00,
    total_used DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_wallet (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Crear tabla de transacciones de billetera
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('earned', 'used', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description VARCHAR(500),
    order_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_transactions (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Agregar columnas de nivel de cashback a tabla usuarios
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS cashback_level_bronze TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS cashback_level_silver TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS cashback_level_gold TINYINT(1) DEFAULT 0;

-- 4. Verificar estructura
SELECT 'Tablas creadas exitosamente' AS status;
