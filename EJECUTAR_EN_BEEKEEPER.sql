-- ============================================
-- SESIONES PHP EN MYSQL - EJECUTAR EN BEEKEEPER
-- ============================================

-- 1. CREAR TABLA DE SESIONES
CREATE TABLE IF NOT EXISTS php_sessions (
    session_id VARCHAR(128) NOT NULL PRIMARY KEY,
    session_data TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. VERIFICAR QUE SE CREÓ
SELECT COUNT(*) as total_sessions FROM php_sessions;

-- 3. LIMPIAR SESIONES EXPIRADAS (ejecutar manualmente cuando quieras)
DELETE FROM php_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY);
