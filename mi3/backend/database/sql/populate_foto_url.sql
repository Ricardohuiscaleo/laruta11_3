-- Agregar columna foto_url a personal (si no existe por migración)
ALTER TABLE personal ADD COLUMN IF NOT EXISTS foto_url VARCHAR(500) NULL AFTER email;

-- Camila (user_id=162)
UPDATE personal SET foto_url = 'https://laruta11-images.s3.us-east-1.amazonaws.com/carnets-trabajadores/162_selfie_1775949561_17759495335086885619557444313874.jpg' WHERE user_id = 162;

-- Dafne (user_id=164)
UPDATE personal SET foto_url = 'https://laruta11-images.s3.us-east-1.amazonaws.com/carnets-trabajadores/164_selfie_1776040615_image.jpg' WHERE user_id = 164;

-- Andrés (user_id=165)
UPDATE personal SET foto_url = 'https://laruta11-images.s3.us-east-1.amazonaws.com/carnets-trabajadores/165_selfie_1775954188_1775954156543..jpg' WHERE user_id = 165;

-- Verificar
SELECT id, nombre, user_id, foto_url FROM personal WHERE user_id IN (162, 164, 165);
