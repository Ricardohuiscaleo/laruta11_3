-- ============================================================
-- Merma Smart: agregar peso_por_unidad y nombre_unidad_natural
-- a tabla ingredients para conversión automática en mermas
-- ============================================================

-- Nuevas columnas
ALTER TABLE ingredients 
  ADD COLUMN IF NOT EXISTS peso_por_unidad DECIMAL(10,4) DEFAULT NULL 
    COMMENT 'Peso/volumen de 1 unidad natural en la unidad base. Ej: 1 tomate=0.150 (kg)',
  ADD COLUMN IF NOT EXISTS nombre_unidad_natural VARCHAR(50) DEFAULT NULL 
    COMMENT 'Nombre singular para mostrar. Ej: tomate, pan, lata, bolsa';

-- ============================================================
-- Datos iniciales: pesos promedio reales de cocina chilena
-- Solo se actualizan ingredientes que tienen unit='kg' o 'g' o 'L'
-- y que se cuentan naturalmente por unidad
-- ============================================================

-- Vegetales (se cuentan por unidad, stock en kg)
UPDATE ingredients SET peso_por_unidad = 0.150, nombre_unidad_natural = 'tomate' WHERE name LIKE '%Tomate%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.300, nombre_unidad_natural = 'lechuga' WHERE name LIKE '%Lechuga%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.200, nombre_unidad_natural = 'cebolla' WHERE name LIKE '%Cebolla%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.200, nombre_unidad_natural = 'palta' WHERE name LIKE '%Palta%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.060, nombre_unidad_natural = 'huevo' WHERE name LIKE '%Huevo%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.080, nombre_unidad_natural = 'limón' WHERE name LIKE '%Lim%n%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.200, nombre_unidad_natural = 'bandeja' WHERE name LIKE '%Champiñ%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.300, nombre_unidad_natural = 'pepino' WHERE name LIKE '%Pepino%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.150, nombre_unidad_natural = 'pimentón' WHERE name LIKE '%Piment%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;
UPDATE ingredients SET peso_por_unidad = 0.080, nombre_unidad_natural = 'ají' WHERE name LIKE '%Aj%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;

-- Lácteos contables
UPDATE ingredients SET peso_por_unidad = 0.250, nombre_unidad_natural = 'lámina' WHERE name LIKE '%Queso Lámina%' AND unit IN ('kg','g') AND peso_por_unidad IS NULL;

-- Gas (se cuenta por balón)
UPDATE ingredients SET nombre_unidad_natural = 'balón' WHERE name LIKE '%Gas%' AND unit = 'kg' AND peso_por_unidad IS NULL;
