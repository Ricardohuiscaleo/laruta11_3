-- Agregar todas las columnas faltantes a tuu_orders
ALTER TABLE tuu_orders 
ADD COLUMN user_id INT NULL AFTER order_number,
ADD COLUMN amount DECIMAL(10,2) NOT NULL AFTER user_id,
ADD COLUMN customer_name VARCHAR(255) NOT NULL AFTER amount,
ADD COLUMN customer_email VARCHAR(255) NULL AFTER customer_name,
ADD COLUMN customer_phone VARCHAR(50) NULL AFTER customer_email,
ADD COLUMN status VARCHAR(50) DEFAULT 'pending' AFTER customer_phone,
ADD INDEX idx_user_id (user_id);