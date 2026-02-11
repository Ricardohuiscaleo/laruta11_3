-- =====================================================
-- ACTUALIZAR BASE DE DATOS EXISTENTE CON DATOS REALES
-- =====================================================

-- Limpiar datos existentes
DELETE FROM products;
DELETE FROM categories;

-- Reiniciar auto_increment
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE products AUTO_INCREMENT = 1;

-- Agregar columnas faltantes si no existen
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS grams INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS views INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS likes INT DEFAULT 0;

-- Insertar categorías reales
INSERT INTO categories (name, description, sort_order) VALUES
('La Ruta 11', 'Cortes premium y especialidades de la casa', 1),
('Sandwiches', 'Churrascos de carne y pollo', 2),
('Hamburguesas', 'Hamburguesas clásicas y especiales', 3),
('Completos', 'Completos tradicionales y al vapor', 4),
('Snacks', 'Papas, jugos, bebidas y salsas', 5);

-- Insertar productos reales
INSERT INTO products (category_id, name, description, price, sku, stock_quantity, grams, views, likes, image_url) VALUES
-- La Ruta 11 (Tomahawks)
(1, 'Tomahawk Papa', 'Corte Premium Tomahawk + papa tradicional o rústica + mayo a elección.', 17000, 'TOMA-PAPA', 20, 800, 1200, 350, 'https://laruta11-images.s3.amazonaws.com/menu/1755619388_toma-papa.png'),
(1, 'Tomahawk Provoleta', 'Corte Premium Tomahawk + provoleta fundida.', 20000, 'TOMA-PRO', 15, 750, 980, 280, 'https://laruta11-images.s3.amazonaws.com/menu/1755619385_toma-provoleta.png'),
(1, 'Tomahawk Full Ruta 11', 'Corte Premium Tomahawk + papa tradicional o rústica + provoleta + mayo a elección.', 22000, 'TOMA-FULL', 10, 950, 2500, 600, 'https://laruta11-images.s3.amazonaws.com/menu/1755574768_tomahawk-full-ig-portrait-1080-1350-2.png'),

-- Sandwiches (Churrascos)
(2, 'Churrasco Vacuno', '3 filetes de vacuno (120g aprox.), pan frica, tomate, palta, mayo Kraft.', 6000, 'CHURR-VAC', 30, 380, 850, 150, 'https://laruta11-images.s3.amazonaws.com/menu/1756125226_1-churrasco-vacuno.jpg'),
(2, 'Churrasco Queso (Vacuno)', '3 filetes de vacuno, queso mantecoso, pan frica, tomate, palta, mayo Kraft.', 6500, 'CHURR-Q-VAC', 25, 420, 760, 180, 'https://laruta11-images.s3.amazonaws.com/menu/1756125224_104-churrasco-queso--vacuno-.jpg'),
(2, 'Churrasco Pollo', '3 filetes de pollo (120g aprox.), pan frica, tomate, palta, mayo Kraft.', 5800, 'CHURR-POLLO', 35, 380, 650, 120, 'https://laruta11-images.s3.amazonaws.com/menu/1756125223_3-churrasco-pollo.jpg'),
(2, 'Churrasco Queso (Pollo)', '3 filetes de pollo, queso mantecoso, pan frica, tomate, palta, mayo Kraft.', 6500, 'CHURR-Q-POLLO', 30, 420, 550, 130, 'https://laruta11-images.s3.amazonaws.com/menu/1756125222_105-churrasco-queso--pollo-.jpg'),
(2, 'Churrasco Vegetariano', 'Carne vegetal, tomate, palta, lechuga, pan frica, mayo a elección.', 4800, 'CHURR-VEG', 20, 350, 920, 310, 'https://laruta11-images.s3.amazonaws.com/menu/1756125221_4-churrasco-vegetariano.jpg'),

-- Hamburguesas
(3, 'Hamburguesa Clásica', 'Carne de vacuno, lechuga, tomate, cebolla, pepinillos, mayo y ketchup.', 4500, 'BURG-CLAS', 25, 350, 1200, 400, 'https://laruta11-images.s3.amazonaws.com/menu/1756125210_401-hamburguesa-cl-sica.jpg'),
(3, 'Hamburguesa con Queso', 'Carne de vacuno, queso cheddar, lechuga, tomate, cebolla, mayo y ketchup.', 5000, 'BURG-QUESO', 30, 380, 1500, 550, 'https://laruta11-images.s3.amazonaws.com/menu/1756125209_402-hamburguesa-con-queso.jpg'),
(3, 'Hamburguesa Doble', 'Doble carne de vacuno, queso, lechuga, tomate, cebolla, mayo y ketchup.', 6500, 'BURG-DOBLE', 20, 450, 900, 300, 'https://laruta11-images.s3.amazonaws.com/menu/1756125208_403-hamburguesa-doble.jpg'),
(3, 'Hamburguesa BBQ', 'Carne de vacuno, salsa BBQ, cebolla caramelizada, tocino, queso cheddar.', 5800, 'BURG-BBQ', 25, 420, 800, 280, 'https://laruta11-images.s3.amazonaws.com/menu/1756125207_404-hamburguesa-bbq.jpg'),
(3, 'Hamburguesa Ruta 11', 'Doble carne, queso provoleta, tocino, palta, tomate, salsa especial.', 7200, 'BURG-R11', 15, 500, 2000, 750, 'https://laruta11-images.s3.amazonaws.com/menu/1756125206_405-hamburguesa-ruta-11.jpg'),

-- Completos
(4, 'Completo Tradicional', 'Vienesa receta sureña, tomate, palta, mayo Kraft.', 2000, 'COMP-TRAD', 50, 280, 1500, 450, 'https://laruta11-images.s3.amazonaws.com/menu/1755619377_Completo-italiano.png'),
(4, 'Completo Talquino', 'Vienesa receta sureña, tomate, palta, mayo Kraft, pan al vapor.', 2300, 'COMP-TALQ', 40, 290, 1100, 320, 'https://laruta11-images.s3.amazonaws.com/menu/1755619379_completo-talquino.png'),
(4, 'Completo Talquino Premium', 'Vienesa Premium, tomate, palta, mayo Kraft, pan al vapor.', 2700, 'COMP-TALQ-P', 30, 310, 1300, 400, 'https://laruta11-images.s3.amazonaws.com/menu/1756125220_107-completo-talquino-premium.jpg'),

-- Snacks
(5, 'Papas Fritas', '300g aprox. tradicional o rústicas.', 2000, 'PAP-FRIT', 60, 300, 2200, 800, 'https://laruta11-images.s3.amazonaws.com/menu/1756125219_12-papas-fritas.jpg'),
(5, 'Salchipapas', 'Papa tradicional o rústica + vienesas receta sureña.', 2500, 'SALCHI', 40, 400, 1800, 650, 'https://laruta11-images.s3.amazonaws.com/menu/1755619380_salchi-papas.png'),
(5, 'Papas Provenzal', 'Papa tradicional o rústica con ajo, perejil y aceite de oliva.', 2500, 'PAP-PROV', 35, 320, 900, 250, 'https://laruta11-images.s3.amazonaws.com/menu/1756125217_109-papas-provenzal.jpg'),
(5, 'Papas Ruta 11', 'Papa tradicional o rústica con tocino, queso cheddar fundido + cebollín.', 3500, 'PAP-R11', 30, 450, 2800, 1100, 'https://laruta11-images.s3.amazonaws.com/menu/1755619383_papas-ruta11.png'),
(5, 'Jugo de Frutilla', 'Jugo natural hecho con frutillas frescas de temporada.', 2500, 'JUGO-FRUT', 25, 450, 700, 200, 'https://laruta11-images.s3.amazonaws.com/menu/1756125217_6-jugo-de-frutilla.jpg'),
(5, 'Jugo de Melón Tuna', 'Refrescante jugo de melón tuna, perfecto para un día de calor.', 2500, 'JUGO-MEL', 25, 450, 600, 150, 'https://laruta11-images.s3.amazonaws.com/menu/1756125216_7-jugo-de-mel-n-tuna.jpg'),
(5, 'Coca-Cola', 'Lata de 350cc.', 1500, 'COCA', 100, 350, 3000, 1500, 'https://laruta11-images.s3.amazonaws.com/menu/1756125215_8-coca-cola.jpg'),
(5, 'Sprite', 'Lata de 350cc.', 1500, 'SPRITE', 100, 350, 1500, 700, 'https://laruta11-images.s3.amazonaws.com/menu/1756125214_9-sprite.jpg'),
(5, 'Mayonesa de Ajo', 'La primera es gratis, adicional $500.', 0, 'MAYO-AJO', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125213_201-mayonesa-de-ajo.jpg'),
(5, 'Mayonesa de Aceituna', 'La primera es gratis, adicional $500.', 0, 'MAYO-ACEIT', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125212_202-mayonesa-de-aceituna.jpg'),
(5, 'Mayonesa de Albahaca', 'La primera es gratis, adicional $500.', 0, 'MAYO-ALB', 50, 50, 0, 0, 'https://laruta11-images.s3.amazonaws.com/menu/1756125211_203-mayonesa-de-albahaca.jpg');