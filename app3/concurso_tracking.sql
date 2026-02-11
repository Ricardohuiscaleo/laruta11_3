-- Usar la base de datos correcta
USE u958525313_app;

-- Tabla para tracking del concurso
CREATE TABLE IF NOT EXISTS concurso_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) DEFAULT 'DIRECT',
    ip_address VARCHAR(45),
    user_agent TEXT,
    visit_date DATE NOT NULL,
    visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_participant TINYINT(1) DEFAULT 0,
    has_paid TINYINT(1) DEFAULT 0,
    INDEX idx_source (source),
    INDEX idx_visit_date (visit_date),
    INDEX idx_participant (is_participant)
);