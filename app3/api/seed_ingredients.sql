-- Seed de ingredientes para La Ruta 11
-- Usar tabla 'ingredients' existente con estructura mejorada

-- Agregar columna category si no existe
ALTER TABLE `ingredients` ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) DEFAULT NULL AFTER `name`;

INSERT INTO `ingredients` (`name`, `category`, `unit`, `cost_per_unit`, `current_stock`, `min_stock_level`, `supplier`, `is_active`) VALUES
('Pan Marraqueta', 'Panes', 'kg', 1.1000, 0.00, 5.00, 'Panadería Local', 1),
('Pan Frica', 'Panes', 'kg', 2.5000, 0.00, 3.00, 'Panadería Local', 1),
('Pan de Completo', 'Panes', 'unidad', 0.1500, 0.00, 20.00, 'Panadería Local', 1),
('Churrasco (Posta)', 'Carnes', 'kg', 9.5000, 0.00, 2.00, 'Carnicería Central', 1),
('Lomo de Cerdo', 'Carnes', 'kg', 4.7000, 0.00, 2.00, 'Carnicería Central', 1),
('Carne Mechada', 'Carnes', 'kg', 8.7800, 0.00, 1.50, 'Carnicería Central', 1),
('Milanesa de Vacuno', 'Carnes', 'kg', 5.9000, 0.00, 2.00, 'Carnicería Central', 1),
('Pechuga de Pollo', 'Aves', 'kg', 5.5000, 0.00, 2.00, 'Avícola San Juan', 1),
('Merluza (filete)', 'Pescados', 'kg', 8.0000, 0.00, 1.00, 'Pescadería del Puerto', 1),
('Vienesa (Sureña)', 'Embutidos', 'kg', 3.5000, 0.00, 2.00, 'Cecinas del Sur', 1),
('Queso Chanco/Gauda', 'Lácteos', 'kg', 11.1100, 0.00, 1.00, 'Lácteos Andinos', 1),
('Huevo', 'Lácteos', 'unidad', 0.2170, 0.00, 50.00, 'Granja Los Robles', 1),
('Palta Hass', 'Vegetales', 'kg', 2.5000, 0.00, 3.00, 'Frutería Central', 1),
('Tomate', 'Vegetales', 'kg', 0.3500, 0.00, 5.00, 'Verdulería Norte', 1),
('Cebolla', 'Vegetales', 'kg', 0.6000, 0.00, 5.00, 'Verdulería Norte', 1),
('Porotos Verdes', 'Vegetales', 'kg', 1.5000, 0.00, 2.00, 'Verdulería Norte', 1),
('Ají Verde', 'Vegetales', 'kg', 3.0700, 0.00, 1.00, 'Verdulería Norte', 1),
('Lechuga', 'Vegetales', 'unidad', 0.5000, 0.00, 10.00, 'Verdulería Norte', 1),
('Mayonesa', 'Salsas', 'kg', 2.8700, 0.00, 2.00, 'Distribuidora Alimentos', 1),
('Chucrut', 'Conservas', 'kg', 14.6900, 0.00, 0.50, 'Importadora Alemana', 1),
('Papas Fritas Cong.', 'Congelados', 'kg', 1.1200, 0.00, 5.00, 'Frozen Foods', 1),
('Jamón Sandwich', 'Embutidos', 'kg', 7.4000, 0.00, 1.00, 'Cecinas Premium', 1),
('Papas Hilo', 'Vegetales', 'kg', 15.0000, 0.00, 1.00, 'Procesados Gourmet', 1),
('Salsa al Olivo', 'Salsas', 'kg', 12.5000, 0.00, 1.00, 'Salsas Artesanales', 1),
('Choclo', 'Vegetales', 'kg', 1.0000, 0.00, 3.00, 'Verdulería Norte', 1),
('Aceite Vegetal', 'Aceites', 'litro', 1.8000, 0.00, 2.00, 'Distribuidora Alimentos', 1),
('Sal', 'Condimentos', 'kg', 0.5000, 0.00, 1.00, 'Distribuidora Alimentos', 1),
('Pimienta', 'Condimentos', 'kg', 8.0000, 0.00, 0.25, 'Especias del Mundo', 1),
('Orégano', 'Condimentos', 'kg', 12.0000, 0.00, 0.25, 'Especias del Mundo', 1),
('Ketchup', 'Salsas', 'kg', 2.2000, 0.00, 2.00, 'Distribuidora Alimentos', 1);

-- La tabla product_recipes ya existe con esta estructura:
-- id, product_id, ingredient_id, quantity, unit, created_at
-- No necesitamos crearla de nuevo