-- Tabla para almacenar sesiones PHP en MySQL
-- Ejecutar en Beekeeper Studio en la base de datos de producción

CREATE TABLE IF NOT EXISTS php_sessions (
    session_id VARCHAR(128) NOT NULL PRIMARY KEY,
    session_data TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpiar sesiones expiradas (opcional, ejecutar manualmente o con cron)
-- DELETE FROM php_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY);
