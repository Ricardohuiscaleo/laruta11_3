CREATE TABLE job_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position ENUM('cajero', 'maestro_sanguchero', 'both') NOT NULL,
    category VARCHAR(50) NOT NULL,
    words JSON NOT NULL,
    weight DECIMAL(3,1) NOT NULL,
    label VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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