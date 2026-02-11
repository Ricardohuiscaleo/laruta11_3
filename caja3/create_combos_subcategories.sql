-- Crear subcategorías para la categoría Combos (ID 8)
-- Primero verificamos que existe la tabla subcategories

CREATE TABLE IF NOT EXISTS subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Insertar subcategorías para Combos (asumiendo que Combos tiene ID 8)
INSERT INTO subcategories (category_id, name, description, sort_order, is_active) VALUES
(8, 'Hamburguesas', 'Combos con hamburguesas', 1, 1),
(8, 'Churrascos', 'Combos con churrascos', 2, 1),
(8, 'Completos', 'Combos con completos', 3, 1);