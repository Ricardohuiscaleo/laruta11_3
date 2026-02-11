-- Crear tabla para historial de notificaciones
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    notification_type ENUM('status_change', 'interview_scheduled', 'hired', 'rejected') NOT NULL,
    kanban_status_from ENUM('nuevo', 'revisando', 'entrevista', 'contratado', 'rechazado') NULL,
    kanban_status_to ENUM('nuevo', 'revisando', 'entrevista', 'contratado', 'rechazado') NOT NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at TIMESTAMP NULL,
    email_subject VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by VARCHAR(255) NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_email_sent (email_sent)
);

-- Crear tabla para templates de notificaciones
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    kanban_status ENUM('nuevo', 'revisando', 'entrevista', 'contratado', 'rechazado') NOT NULL,
    email_subject VARCHAR(255) NOT NULL,
    email_body TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar templates por defecto
INSERT IGNORE INTO notification_templates (template_name, kanban_status, email_subject, email_body) VALUES
('status_revisando', 'revisando', 'Tu postulación está siendo revisada - La Ruta 11', 
'Hola {nombre}, tu postulación para {posicion} está siendo revisada por nuestro equipo. Te contactaremos pronto.'),

('status_entrevista', 'entrevista', 'Te hemos seleccionado para entrevista - La Ruta 11', 
'¡Felicidades {nombre}! Has sido seleccionado/a para una entrevista para el puesto de {posicion}. Nos pondremos en contacto contigo para coordinar.'),

('status_contratado', 'contratado', '¡Bienvenido/a al equipo de La Ruta 11!', 
'¡Excelentes noticias {nombre}! Has sido seleccionado/a para el puesto de {posicion}. ¡Bienvenido/a al equipo de La Ruta 11!'),

('status_rechazado', 'rechazado', 'Resultado de tu postulación - La Ruta 11', 
'Hola {nombre}, agradecemos tu interés en La Ruta 11. En esta ocasión hemos decidido continuar con otros candidatos, pero te animamos a postular en futuras oportunidades.');