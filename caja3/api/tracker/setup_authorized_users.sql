-- Crear tabla para usuarios autorizados del Jobs Tracker
CREATE TABLE IF NOT EXISTS tracker_authorized_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    nombre VARCHAR(255) NOT NULL,
    role ENUM('admin', 'viewer', 'manager') DEFAULT 'viewer',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_active (active)
);

-- Insertar usuarios autorizados iniciales
INSERT INTO tracker_authorized_users (email, nombre, role) VALUES
('ricardohuiscaleollafquen@gmail.com', 'Ricardo Huiscaleo', 'admin'),
('admin@laruta11.cl', 'Administrador La Ruta 11', 'admin'),
('rrhh@laruta11.cl', 'Recursos Humanos', 'manager'),
('gerencia@laruta11.cl', 'Gerencia', 'manager')
ON DUPLICATE KEY UPDATE 
    nombre = VALUES(nombre),
    role = VALUES(role),
    updated_at = CURRENT_TIMESTAMP;

-- Consulta para verificar usuarios autorizados
SELECT * FROM tracker_authorized_users WHERE active = TRUE ORDER BY role, nombre;