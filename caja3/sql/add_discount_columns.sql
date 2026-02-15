-- Agregar columnas de descuento individuales a tuu_orders
ALTER TABLE tuu_orders 
ADD COLUMN IF NOT EXISTS discount_10 DECIMAL(10,2) DEFAULT 0 AFTER discount_amount,
ADD COLUMN IF NOT EXISTS discount_30 DECIMAL(10,2) DEFAULT 0 AFTER discount_10,
ADD COLUMN IF NOT EXISTS discount_birthday DECIMAL(10,2) DEFAULT 0 AFTER discount_30,
ADD COLUMN IF NOT EXISTS discount_pizza DECIMAL(10,2) DEFAULT 0 AFTER discount_birthday;
