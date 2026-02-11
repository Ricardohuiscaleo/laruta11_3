-- Agregar columna delivery_fee a tuu_orders
ALTER TABLE tuu_orders 
ADD COLUMN delivery_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Costo de delivery desde food_trucks.tarifa_delivery';

-- Agregar índice para consultas por delivery_fee
ALTER TABLE tuu_orders 
ADD INDEX idx_delivery_fee (delivery_fee);