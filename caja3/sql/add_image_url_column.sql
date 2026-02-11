-- Agregar columna image_url a la tabla concurso_registros
ALTER TABLE concurso_registros 
ADD COLUMN image_url TEXT NULL 
AFTER mayor_18;