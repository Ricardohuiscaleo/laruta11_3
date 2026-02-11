-- Crear tabla para almacenar el detalle de productos en cada pedido
CREATE TABLE IF NOT EXISTS tuu_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    order_reference VARCHAR(100) NOT NULL,
    product_id INT,
    product_name VARCHAR(255) NOT NULL,
    product_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    subtotal DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_order_reference (order_reference),
    INDEX idx_order_id (order_id),
    INDEX idx_product_id (product_id)
);

-- Agregar columna para indicar si el pedido tiene detalle de items
ALTER TABLE tuu_orders 
ADD COLUMN has_item_details BOOLEAN DEFAULT FALSE AFTER product_name;

-- Comentarios para documentación
-- Esta tabla almacena el detalle específico de cada producto en un pedido
-- order_reference: Referencia del pedido principal en tuu_orders
-- product_id: ID del producto (puede ser NULL si el producto fue eliminado)
-- product_name: Nombre del producto al momento de la compra
-- product_price: Precio del producto al momento de la compra
-- quantity: Cantidad comprada
-- subtotal: price * quantity