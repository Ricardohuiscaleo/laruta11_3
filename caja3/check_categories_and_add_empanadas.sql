-- Ver categorías existentes
SELECT * FROM categories ORDER BY id;

-- Ver subcategorías existentes
SELECT * FROM subcategorias ORDER BY category_id, orden;

-- Agregar subcategoría Empanadas (ajustar category_id según corresponda)
INSERT INTO subcategorias (nombre, category_id, activo, orden) 
VALUES ('Empanadas', 1, 1, 10);