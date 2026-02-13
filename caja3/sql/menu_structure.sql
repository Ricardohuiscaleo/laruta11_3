-- ============================================
-- ESTRUCTURA DE MENÚ DINÁMICA
-- ============================================
-- Este archivo documenta la estructura de categorías del menú
-- que reemplaza el hardcoding en el frontend

-- ============================================
-- TABLAS
-- ============================================

CREATE TABLE menu_categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  slug VARCHAR(50) UNIQUE NOT NULL COMMENT 'Identificador único para el frontend',
  display_name VARCHAR(100) NOT NULL COMMENT 'Nombre que se muestra en la app',
  icon_type VARCHAR(50) NOT NULL COMMENT 'Tipo de icono: hamburger, sandwich, hotdog, fries, pizza, drink, combo',
  color VARCHAR(20) COMMENT 'Color en formato hex (#D2691E)',
  sort_order INT DEFAULT 0 COMMENT 'Orden de aparición en el menú',
  is_active TINYINT(1) DEFAULT 1 COMMENT '1=visible, 0=oculta',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE menu_subcategories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  menu_category_id INT NOT NULL COMMENT 'FK a menu_categories',
  display_name VARCHAR(100) NOT NULL COMMENT 'Nombre de la subcategoría',
  sort_order INT DEFAULT 0 COMMENT 'Orden dentro de la categoría',
  is_active TINYINT(1) DEFAULT 1 COMMENT '1=visible, 0=oculta',
  -- Mapeo a la estructura original de la DB
  category_id INT COMMENT 'ID de categories (tabla original)',
  subcategory_id INT COMMENT 'ID de subcategories (tabla original)',
  subcategory_ids JSON COMMENT 'Array de IDs cuando hay múltiples subcategorías',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (menu_category_id) REFERENCES menu_categories(id) ON DELETE CASCADE
);

-- ============================================
-- DATOS ACTUALES
-- ============================================

-- Hamburguesas 200g
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('hamburguesas', 'Hamburguesas\n(200g)', 'hamburger', '#D2691E', 1, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_id) 
VALUES (1, 'Clásicas', 1, 1, 3, 6);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_id) 
VALUES (1, 'Especiales', 2, 1, 3, 6);

-- Hamburguesas 100g
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('hamburguesas_100g', 'Hamburguesas\n(100g)', 'hamburger_small', '#D2691E', 2, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_id) 
VALUES (2, 'Clásicas', 1, 1, 3, 5);

-- Sandwiches
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('churrascos', 'Sandwiches', 'sandwich', '#FF6347', 3, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (3, 'Carne', 1, 1, 2);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (3, 'Pollo', 2, 1, 2);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (3, 'Vegetariano', 3, 1, 2);

-- Completos
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('completos', 'Completos', 'hotdog', '#FF4500', 4, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (4, 'Tradicionales', 1, 1, 4);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (4, 'Al Vapor', 2, 1, 4);

-- Papas
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('papas', 'Papas', 'fries', '#FFD700', 5, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_ids) 
VALUES (5, 'Papas Fritas', 1, 1, 12, '[9,57]');

-- Pizzas
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('pizzas', 'Pizzas', 'pizza', '#FF6347', 6, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_id) 
VALUES (6, 'Pizzas', 1, 1, 5, 60);

-- Bebidas
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('bebidas', 'Bebidas', 'drink', '#4299E1', 7, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id, subcategory_ids) 
VALUES (7, 'Bebidas', 1, 1, 5, '[11,10,28,27]');

-- Combos
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('combos', 'Combos', 'combo', '#FF6B35', 8, 1);

INSERT INTO menu_subcategories (menu_category_id, display_name, sort_order, is_active, category_id) 
VALUES (8, 'Combos', 1, 1, 8);

-- ============================================
-- QUERIES ÚTILES
-- ============================================

-- Ver todas las categorías con sus subcategorías
SELECT 
  mc.id,
  mc.slug,
  mc.display_name AS categoria,
  mc.is_active AS cat_activa,
  ms.display_name AS subcategoria,
  ms.is_active AS subcat_activa,
  ms.category_id,
  ms.subcategory_id,
  ms.subcategory_ids
FROM menu_categories mc
LEFT JOIN menu_subcategories ms ON mc.id = ms.menu_category_id
ORDER BY mc.sort_order, ms.sort_order;

-- Activar/desactivar una categoría
UPDATE menu_categories SET is_active = 0 WHERE slug = 'pizzas';
UPDATE menu_categories SET is_active = 1 WHERE slug = 'pizzas';

-- Cambiar orden de categorías
UPDATE menu_categories SET sort_order = 10 WHERE slug = 'combos';

-- Agregar nueva categoría
INSERT INTO menu_categories (slug, display_name, icon_type, color, sort_order, is_active) 
VALUES ('nueva_categoria', 'Nueva Categoría', 'icon_type', '#FF0000', 9, 1);
