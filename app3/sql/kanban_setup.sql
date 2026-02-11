-- Kanban para Jobs Tracker
-- Base de datos: u958525313_usuariosruta11

-- 1. Tabla para columnas del Kanban
CREATE TABLE IF NOT EXISTS kanban_columns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    color VARCHAR(7) DEFAULT '#6B7280',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_position (position),
    UNIQUE KEY unique_name_position (name, position)
);

-- 2. Insertar columnas por defecto (solo si no existen)
INSERT IGNORE INTO kanban_columns (name, position, color) VALUES
('Nuevos', 0, '#3B82F6'),
('En Revisión', 1, '#F59E0B'),
('Entrevista', 2, '#8B5CF6'),
('Aprobados', 3, '#10B981'),
('Rechazados', 4, '#EF4444');

-- 3. Tabla para tarjetas del Kanban (candidatos)
CREATE TABLE IF NOT EXISTS kanban_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    position ENUM('maestro_sanguchero', 'cajero') NOT NULL,
    column_id INT NOT NULL,
    card_position INT NOT NULL DEFAULT 0,
    notes TEXT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to VARCHAR(100),
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (column_id) REFERENCES kanban_columns(id) ON DELETE CASCADE,
    INDEX idx_user_position (user_id, position),
    INDEX idx_column_position (column_id, card_position),
    UNIQUE KEY unique_user_position (user_id, position)
);

-- 4. Recrear tabla job_keywords con estructura correcta
DROP TABLE IF EXISTS job_keywords;
CREATE TABLE job_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position ENUM('cajero', 'maestro_sanguchero', 'both') NOT NULL,
    category VARCHAR(50) NOT NULL,
    words JSON NOT NULL,
    weight DECIMAL(3,1) NOT NULL,
    label VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Insertar keywords originales
INSERT INTO job_keywords (position, category, words, weight, label) VALUES
('both', 'autonomia', '["mi plan", "iniciativa", "responsabilidad", "organizo", "organizar", "adelanto", "adelantar", "mise en place", "revisión", "revisar", "stock"]', 1.5, 'Autonomía y Planificación'),
('both', 'comunicacion', '["comunico", "comunicar", "aviso", "avisar", "digo", "decir", "confirmo", "confirmar", "escucho", "escuchar", "coordino", "coordinar", "sincronizar", "señal", "acuerdo", "acordar"]', 2.5, 'Comunicación Efectiva'),
('both', 'equipo', '["compañera", "compañero", "juntos", "nosotros", "apoyo", "apoyar", "ayuda", "ayudar", "organizamos", "equipo"]', 2.0, 'Trabajo en Equipo'),
('both', 'higiene', '["limpieza", "limpiar", "desinfectar", "higiene", "sanitizar", "cadena de frío", "frescura", "impecable"]', 1.8, 'Higiene y Calidad'),
('both', 'calidad', '["calidad", "sabor", "presentación", "consistencia", "punto exacto", "detalle", "cliente feliz", "seguridad"]', 1.5, 'Foco en la Calidad'),
('both', 'presion', '["presión", "rápido", "eficiente", "calma", "calmado", "concentrado", "concentrar", "resolver", "solucionar", "hora punta", "sin caos", "crisis"]', 1.0, 'Manejo de Presión'),
('both', 'proactividad', '["idea", "ideas", "mejora", "mejorar", "mejoramos", "propongo", "proponer", "optimizar", "sugerencia", "sugerir", "nuevo", "eficiencia", "solución", "solucionar", "agregar", "crear", "vegetariano"]', 2.2, 'Proactividad y Mejora'),
('cajero', 'comunicacion', '["explico", "informo", "atención", "servicio", "cliente", "amable", "cortés"]', 2.5, 'Comunicación con Cliente'),
('cajero', 'equipo', '["maestro sanguchero", "cocina", "coordino con cocina"]', 2.0, 'Coordinación con Cocina'),
('cajero', 'presion', '["fila", "espera", "cola", "múltiples pedidos"]', 1.0, 'Manejo de Filas'),
('maestro_sanguchero', 'calidad', '["punto de cocción", "temperatura", "sazón", "textura"]', 1.5, 'Técnica Culinaria'),
('maestro_sanguchero', 'higiene', '["tabla de cortar", "contaminación cruzada", "temperatura segura"]', 1.8, 'Seguridad Alimentaria');

-- 6. Agregar columna de skills a job_applications (si no existe)
ALTER TABLE job_applications 
ADD COLUMN IF NOT EXISTS detected_skills JSON COMMENT 'Skills detectadas del análisis de keywords';

-- 7. Tabla de historial de movimientos en Kanban
CREATE TABLE IF NOT EXISTS kanban_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    from_column_id INT,
    to_column_id INT NOT NULL,
    moved_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES kanban_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (from_column_id) REFERENCES kanban_columns(id) ON DELETE SET NULL,
    FOREIGN KEY (to_column_id) REFERENCES kanban_columns(id) ON DELETE CASCADE,
    INDEX idx_card_date (card_id, created_at)
);

-- 8. Poblar kanban_cards con candidatos existentes
INSERT INTO kanban_cards (user_id, position, column_id, card_position)
SELECT 
    user_id,
    position,
    1 as column_id, -- Columna "Nuevos" por defecto
    ROW_NUMBER() OVER (PARTITION BY 1 ORDER BY MAX(created_at)) as card_position
FROM job_applications 
GROUP BY user_id, position
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;