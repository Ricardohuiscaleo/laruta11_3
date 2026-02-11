-- Agregar columnas para cursos
ALTER TABLE job_applications 
ADD COLUMN curso_manipulador ENUM('si', 'no') DEFAULT NULL,
ADD COLUMN curso_cajero ENUM('si', 'no') DEFAULT NULL;