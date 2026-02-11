-- Obtener ID de subcategoría bebidas
SET @bebidas_subcategory_id = (SELECT id FROM subcategories WHERE slug = 'bebidas' AND category_id = 5);

-- SCORE ENERGY VARIEDADES - LATAS 473ML
INSERT INTO products (category_id, subcategory_id, name, description, price, cost_price, sku, stock_quantity, is_active, preparation_time, grams) VALUES

-- Score Original (Guaraná Doble Shot)
(5, @bebidas_subcategory_id, 'Score Original 473ml', 'Bebida energética Score sabor guaraná doble shot en lata de 473ml', 2800, 1400, 'SCORE-ORIGINAL-473', 50, 1, 0, 473),

-- Score Gorilla
(5, @bebidas_subcategory_id, 'Score Gorilla 473ml', 'Bebida energética Score sabor Gorilla en lata de 473ml', 2800, 1400, 'SCORE-GORILLA-473', 50, 1, 0, 473),

-- Score Zero (Guaraná sin azúcar)
(5, @bebidas_subcategory_id, 'Score Zero 473ml', 'Bebida energética Score guaraná sin azúcar en lata de 473ml', 2800, 1400, 'SCORE-ZERO-473', 50, 1, 0, 473),

-- Score Bubble Gum
(5, @bebidas_subcategory_id, 'Score Bubble Gum 473ml', 'Bebida energética Score sabor chicle en lata de 473ml', 2900, 1450, 'SCORE-BUBBLEGUM-473', 40, 1, 0, 473),

-- Score Mango
(5, @bebidas_subcategory_id, 'Score Mango 473ml', 'Bebida energética Score sabor mango en lata de 473ml', 2900, 1450, 'SCORE-MANGO-473', 40, 1, 0, 473),

-- Score Fruit Punch
(5, @bebidas_subcategory_id, 'Score Fruit Punch 473ml', 'Bebida energética Score sabor ponche de frutas en lata de 473ml', 2900, 1450, 'SCORE-FRUITPUNCH-473', 40, 1, 0, 473);

-- Comentario: Precios ajustados para Score (más económico que Monster)
-- Score Original, Gorilla, Zero: $2,800
-- Score sabores especiales (Bubble Gum, Mango, Fruit Punch): $2,900