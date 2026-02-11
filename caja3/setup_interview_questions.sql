-- Crear tabla de preguntas de entrevista
CREATE TABLE IF NOT EXISTS interview_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position VARCHAR(100) NOT NULL,
    question_type ENUM('yesno', 'open') NOT NULL,
    question_text TEXT NOT NULL,
    question_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_position (position),
    INDEX idx_type (question_type),
    INDEX idx_active (is_active)
);

-- Crear tabla de entrevistas
CREATE TABLE IF NOT EXISTS interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id VARCHAR(255) NOT NULL,
    position VARCHAR(100) NOT NULL,
    interview_date DATETIME NOT NULL,
    status ENUM('draft', 'completed', 'callback_scheduled') DEFAULT 'draft',
    yes_no_answers JSON,
    open_answers JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_candidate (candidate_id),
    INDEX idx_status (status)
);

-- Insertar preguntas para CAJERO
INSERT INTO interview_questions (position, question_type, question_text, question_order) VALUES
-- Preguntas Sí/No para Cajero
('cajero', 'yesno', '¿Acepta trabajar con aplicación móvil de KPIs que mide su rendimiento diario?', 1),
('cajero', 'yesno', '¿Está de acuerdo con sistema de bonificaciones basado en ventas y atención al cliente?', 2),
('cajero', 'yesno', '¿Acepta que los clientes califiquen el servicio en Google Maps y redes sociales?', 3),
('cajero', 'yesno', '¿Está dispuesto/a a cumplir metas de ventas diarias específicas?', 4),
('cajero', 'yesno', '¿Acepta promocionar y vender productos especiales del día?', 5),
('cajero', 'yesno', '¿Está de acuerdo con el rango salarial discutido (incluye base + bonos)?', 6),
('cajero', 'yesno', '¿Acepta trabajar fines de semana, feriados y horarios peak?', 7),
('cajero', 'yesno', '¿Tiene disponibilidad para turnos rotativos (mañana, tarde, noche)?', 8),
('cajero', 'yesno', '¿Acepta capacitación continua en nuevos productos y técnicas de venta?', 9),
('cajero', 'yesno', '¿Está cómodo/a manejando dinero en efectivo y sistemas de pago digital?', 10),
('cajero', 'yesno', '¿Acepta todos los términos y condiciones laborales explicados?', 11),

-- Preguntas Abiertas para Cajero
('cajero', 'open', '¿Qué te motiva a trabajar en el área de atención al cliente en La Ruta 11?', 1),
('cajero', 'open', 'Describe cómo resolverías un conflicto con un cliente insatisfecho', 2),
('cajero', 'open', '¿Cuál es tu experiencia previa en ventas, caja o atención al público?', 3),

-- Preguntas Sí/No para Maestro Sanguchero
('maestro_sanguchero', 'yesno', '¿Acepta trabajar con aplicación móvil que registra tiempos y calidad de preparación?', 1),
('maestro_sanguchero', 'yesno', '¿Está de acuerdo con bonificaciones por productividad y calidad del producto?', 2),
('maestro_sanguchero', 'yesno', '¿Acepta que los clientes califiquen la comida en Google Maps y delivery apps?', 3),
('maestro_sanguchero', 'yesno', '¿Está dispuesto/a a cumplir metas de producción y tiempos de entrega?', 4),
('maestro_sanguchero', 'yesno', '¿Acepta preparar productos especiales y nuevas recetas del menú?', 5),
('maestro_sanguchero', 'yesno', '¿Está de acuerdo con el rango salarial discutido (incluye base + bonos)?', 6),
('maestro_sanguchero', 'yesno', '¿Acepta trabajar en horarios de alta demanda y rush de pedidos?', 7),
('maestro_sanguchero', 'yesno', '¿Tiene disponibilidad para turnos de cocina en diferentes horarios?', 8),
('maestro_sanguchero', 'yesno', '¿Acepta capacitación constante en nuevas técnicas culinarias?', 9),
('maestro_sanguchero', 'yesno', '¿Está dispuesto/a a mantener estándares estrictos de higiene y calidad?', 10),
('maestro_sanguchero', 'yesno', '¿Acepta todos los términos y condiciones laborales explicados?', 11),

-- Preguntas Abiertas para Maestro Sanguchero
('maestro_sanguchero', 'open', '¿Qué experiencia tienes en cocina rápida o preparación de sandwiches/completos?', 1),
('maestro_sanguchero', 'open', '¿Cómo organizas tu trabajo cuando hay muchos pedidos simultáneos?', 2),
('maestro_sanguchero', 'open', '¿Por qué te interesa específicamente el puesto de maestro sanguchero?', 3);