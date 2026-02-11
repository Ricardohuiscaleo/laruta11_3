-- Agregar campo shift_type a la tabla cash_register_sessions
ALTER TABLE cash_register_sessions 
ADD COLUMN shift_type ENUM('day', 'night') DEFAULT 'day' AFTER status;
