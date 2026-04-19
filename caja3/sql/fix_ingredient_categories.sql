-- Fix encoding corrupto: LÃ¡cteos → Lácteos
UPDATE ingredients SET category = 'Lácteos' WHERE category = 'LÃ¡cteos';

-- Eliminar categoría legacy vacía: Ingredientes → NULL
UPDATE ingredients SET category = NULL WHERE category = 'Ingredientes';
