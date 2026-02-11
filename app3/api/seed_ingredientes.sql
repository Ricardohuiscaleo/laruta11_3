-- Seed de ingredientes para La Ruta 11
-- Ejecutar este script para poblar la tabla ingredientes con datos iniciales

INSERT INTO `ingredientes` (`id`, `nombre`, `categoria`, `costo_compra`, `iva_incluido`, `costo_por_gramo`, `unidad_nombre`, `unidad_gramos`, `stock`, `peso`) VALUES
(1, 'Pan Marraqueta', 'Panes', 1100.00, 1, 1.10, 'kg', 1000, 0.00, 1000.00),
(2, 'Pan Frica', 'Panes', 2500.00, 1, 2.50, 'kg', 1000, 0.00, 1000.00),
(3, 'Pan de Completo', 'Panes', 3000.00, 1, 3.00, 'kg', 1000, 0.00, 1000.00),
(4, 'Churrasco (Posta)', 'Carnes', 9500.00, 1, 9.50, 'kg', 1000, 0.00, 1000.00),
(5, 'Lomo de Cerdo', 'Carnes', 4700.00, 1, 4.70, 'kg', 1000, 0.00, 1000.00),
(6, 'Carne Mechada', 'Carnes', 8780.00, 1, 8.80, 'kg', 1000, 0.00, 1000.00),
(7, 'Milanesa de Vacuno', 'Carnes', 5900.00, 1, 5.90, 'kg', 1000, 0.00, 1000.00),
(8, 'Pechuga de Pollo', 'Aves', 5500.00, 1, 5.50, 'kg', 1000, 0.00, 1000.00),
(9, 'Merluza (filete)', 'Pescados', 8000.00, 1, 8.00, 'kg', 1000, 0.00, 1000.00),
(10, 'Vienesa (Sureña)', 'Embutidos', 3500.00, 1, 3.50, 'kg', 1000, 0.00, 1000.00),
(11, 'Queso Chanco/Gauda', 'Lácteos', 11110.00, 1, 11.10, 'kg', 1000, 0.00, 1000.00),
(12, 'Huevo', 'Otros', 217.00, 1, 3.90, 'unidad', 55, 0.00, 56.00),
(13, 'Palta Hass', 'Vegetales', 2500.00, 1, 2.50, 'kg', 1000, 0.00, 1000.00),
(14, 'Tomate', 'Vegetales', 350.00, 1, 0.40, 'kg', 1000, 0.00, 1000.00),
(15, 'Cebolla', 'Vegetales', 600.00, 1, 0.60, 'kg', 1000, 0.00, 983.00),
(16, 'Porotos Verdes', 'Vegetales', 1500.00, 1, 1.50, 'kg', 1000, 0.00, 1000.00),
(17, 'Ají Verde', 'Vegetales', 3070.00, 1, 3.10, 'kg', 1000, 0.00, 1000.00),
(18, 'Lechuga', 'Vegetales', 500.00, 1, 0.50, 'kg', 1000, 0.00, 1000.00),
(19, 'Mayonesa', 'Salsas', 2870.00, 1, 2.90, 'kg', 1000, 0.00, 1000.00),
(20, 'Chucrut', 'Otros', 14690.00, 1, 14.70, 'kg', 1000, 0.00, 1000.00),
(21, 'Papas Fritas Cong.', 'Vegetales', 1120.00, 1, 1.10, 'kg', 1000, 0.00, 1000.00),
(22, 'Jamón Sandwich', 'Embutidos', 7400.00, 1, 7.40, 'kg', 1000, 0.00, 1000.00),
(23, 'Papas Hilo', 'Vegetales', 15000.00, 1, 15.00, 'kg', 1000, 0.00, 1000.00),
(24, 'Salsa al Olivo', 'Salsas', 12500.00, 1, 12.50, 'kg', 1000, 0.00, 1000.00),
(25, 'Choclo', 'Vegetales', 1000.00, 1, 1.00, 'kg', 1000, 0.00, 1000.00);

-- Crear tabla recetas si no existe
CREATE TABLE IF NOT EXISTS `recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `ingrediente_id` int(11) NOT NULL,
  `gramos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `ingrediente_id` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;