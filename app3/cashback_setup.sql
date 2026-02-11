-- ============================================
-- SISTEMA DE CASHBACK 10% - LA RUTA 11
-- Base de datos: u958525313_app
-- ============================================

-- 1. Tabla de saldo de usuarios
CREATE TABLE IF NOT EXISTS user_wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0 COMMENT 'Saldo disponible',
    total_earned DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ganado historico',
    total_used DECIMAL(10,2) DEFAULT 0 COMMENT 'Total usado historico',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla de transacciones de wallet
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('earned', 'used') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    order_id VARCHAR(50),
    description TEXT,
    balance_before DECIMAL(10,2),
    balance_after DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Columnas para rastrear cashback generado por nivel
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS cashback_level_bronze TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS cashback_level_silver TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS cashback_level_gold TINYINT(1) DEFAULT 0;

-- ============================================
-- VERIFICACIÓN
-- ============================================

-- Verificar que las tablas se crearon correctamente
SHOW TABLES LIKE '%wallet%';

-- Verificar estructura de user_wallet
DESCRIBE user_wallet;

-- Verificar estructura de wallet_transactions
DESCRIBE wallet_transactions;

-- Verificar columnas en users
SHOW COLUMNS FROM users LIKE 'cashback%';
