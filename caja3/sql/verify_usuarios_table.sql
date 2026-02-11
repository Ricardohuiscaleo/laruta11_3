-- Verificar estructura de tabla usuarios en u958525313_app
-- Ejecutar en u958525313_app

-- Ver estructura actual
DESCRIBE usuarios;

-- Verificar campos específicos que necesitan las APIs actualizadas:
-- Para update_profile.php:
SHOW COLUMNS FROM usuarios LIKE 'telefono';
SHOW COLUMNS FROM usuarios LIKE 'instagram';
SHOW COLUMNS FROM usuarios LIKE 'fecha_nacimiento';
SHOW COLUMNS FROM usuarios LIKE 'genero';
SHOW COLUMNS FROM usuarios LIKE 'direccion';

-- Para ubicación (save_location.php):
SHOW COLUMNS FROM usuarios LIKE 'latitud';
SHOW COLUMNS FROM usuarios LIKE 'longitud';
SHOW COLUMNS FROM usuarios LIKE 'direccion_actual';
SHOW COLUMNS FROM usuarios LIKE 'ubicacion_actualizada';

-- Si faltan campos, agregarlos:
-- ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(50) NULL;
-- ALTER TABLE usuarios ADD COLUMN instagram VARCHAR(100) NULL;
-- ALTER TABLE usuarios ADD COLUMN fecha_nacimiento DATE NULL;
-- ALTER TABLE usuarios ADD COLUMN genero ENUM('masculino', 'femenino', 'otro', 'prefiero_no_decir') NULL;
-- ALTER TABLE usuarios ADD COLUMN direccion TEXT NULL;
-- ALTER TABLE usuarios ADD COLUMN latitud DECIMAL(10, 8) NULL;
-- ALTER TABLE usuarios ADD COLUMN longitud DECIMAL(11, 8) NULL;
-- ALTER TABLE usuarios ADD COLUMN direccion_actual TEXT NULL;
-- ALTER TABLE usuarios ADD COLUMN ubicacion_actualizada TIMESTAMP NULL;