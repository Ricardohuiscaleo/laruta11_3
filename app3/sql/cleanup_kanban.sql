-- Limpiar duplicados en kanban_columns y kanban_cards

-- 1. Eliminar columnas duplicadas, manteniendo solo las primeras 5
DELETE FROM kanban_columns WHERE id > 5;

-- 2. Resetear AUTO_INCREMENT
ALTER TABLE kanban_columns AUTO_INCREMENT = 6;

-- 3. Limpiar kanban_cards que referencien columnas eliminadas
DELETE FROM kanban_cards WHERE column_id > 5;

-- 4. Verificar que solo queden 5 columnas
SELECT * FROM kanban_columns ORDER BY position;

-- 5. Verificar tarjetas restantes
SELECT 
    kc.id,
    kc.user_id,
    kc.position,
    kc.column_id,
    col.name as column_name
FROM kanban_cards kc
JOIN kanban_columns col ON kc.column_id = col.id
ORDER BY kc.column_id, kc.card_position;