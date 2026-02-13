-- 🚀 INSERTAR DATOS DE COMBOS EXISTENTES
-- Copiar datos que ya están en combo_items y combo_selections a las tablas correctas

-- ============================================
-- OPCIÓN 1: Si las tablas están vacías, insertar todo
-- ============================================

-- 1. Insertar combo_items (73 registros)
INSERT INTO combo_items (id, combo_id, product_id, quantity, is_selectable, selection_group)
VALUES
(54, 211, 14, 4, 0, NULL),
(55, 211, 212, 1, 0, NULL),
(56, 212, 2, 1, 0, NULL),
(57, 212, 3, 1, 0, NULL),
(70, 3, 176, 1, 0, NULL),
(86, 4, 9, 1, 0, NULL),
(87, 4, 6, 1, 0, NULL),
(88, 4, 17, 1, 0, NULL),
(100, 233, 9, 1, 0, NULL),
(101, 233, 17, 1, 0, NULL),
(102, 234, 199, 1, 0, NULL),
(103, 234, 199, 1, 0, NULL),
(104, 2, 17, 1, 0, NULL),
(105, 2, 14, 1, 0, NULL),
(106, 1, 11, 1, 0, NULL),
(107, 1, 17, 1, 0, NULL);

-- 2. Insertar combo_selections (bebidas - 72 registros)
INSERT INTO combo_selections (id, combo_id, selection_group, product_id, additional_price, max_selections)
VALUES
-- Combo 211
(212, 211, 'Bebidas 1.5Lt', 64, 0.00, 1),
(213, 211, 'Bebidas 1.5Lt', 58, 0.00, 1),
-- Combo 212
(214, 212, 'Bebidas', 2, 0.00, 1),
-- Combo 3
(283, 3, 'Bebidas', 96, 0.00, 1),
(284, 3, 'Bebidas', 99, 0.00, 1),
(285, 3, 'Bebidas', 100, 0.00, 1),
(286, 3, 'Bebidas', 95, 0.00, 1),
(287, 3, 'Bebidas', 135, 0.00, 1),
(288, 3, 'Bebidas', 98, 0.00, 1),
(289, 3, 'Bebidas', 97, 0.00, 1),
(290, 3, 'Bebidas', 91, 0.00, 1),
(291, 3, 'Bebidas', 111, 0.00, 1),
(292, 3, 'Bebidas', 131, 0.00, 1),
(293, 3, 'Bebidas', 132, 0.00, 1),
(294, 3, 'Bebidas', 93, 0.00, 1),
(295, 3, 'Bebidas', 101, 0.00, 1),
(296, 3, 'Bebidas', 103, 0.00, 1),
-- Combo 4
(367, 4, 'Bebidas', 96, 0.00, 2),
(368, 4, 'Bebidas', 99, 0.00, 2),
(369, 4, 'Bebidas', 100, 0.00, 2),
(370, 4, 'Bebidas', 95, 0.00, 2),
(371, 4, 'Bebidas', 135, 0.00, 2),
(372, 4, 'Bebidas', 98, 0.00, 2),
(373, 4, 'Bebidas', 97, 0.00, 2),
(374, 4, 'Bebidas', 91, 0.00, 2),
(375, 4, 'Bebidas', 111, 0.00, 2),
(376, 4, 'Bebidas', 131, 0.00, 2),
(377, 4, 'Bebidas', 132, 0.00, 2),
(378, 4, 'Bebidas', 93, 0.00, 2),
(379, 4, 'Bebidas', 101, 0.00, 2),
(380, 4, 'Bebidas', 103, 0.00, 2),
-- Combo 233
(459, 233, 'Bebidas', 96, 0.00, 1),
(460, 233, 'Bebidas', 99, 0.00, 1),
(461, 233, 'Bebidas', 100, 0.00, 1),
(462, 233, 'Bebidas', 95, 0.00, 1),
(463, 233, 'Bebidas', 98, 0.00, 1),
(464, 233, 'Bebidas', 97, 0.00, 1),
(465, 233, 'Bebidas', 91, 0.00, 1),
(466, 233, 'Bebidas', 111, 0.00, 1),
(467, 233, 'Bebidas', 103, 0.00, 1),
-- Combo 234
(468, 234, 'Bebidas', 96, 0.00, 1),
(469, 234, 'Bebidas', 99, 0.00, 1),
(470, 234, 'Bebidas', 100, 0.00, 1),
(471, 234, 'Bebidas', 95, 0.00, 1),
(472, 234, 'Bebidas', 98, 0.00, 1),
(473, 234, 'Bebidas', 97, 0.00, 1),
(474, 234, 'Bebidas', 91, 0.00, 1),
(475, 234, 'Bebidas', 111, 0.00, 1),
(476, 234, 'Bebidas', 103, 0.00, 1),
-- Combo 2
(477, 2, 'Bebidas', 96, 0.00, 1),
(478, 2, 'Bebidas', 99, 0.00, 1),
(479, 2, 'Bebidas', 100, 0.00, 1),
(480, 2, 'Bebidas', 95, 0.00, 1),
(481, 2, 'Bebidas', 98, 0.00, 1),
(482, 2, 'Bebidas', 97, 0.00, 1),
(483, 2, 'Bebidas', 91, 0.00, 1),
(484, 2, 'Bebidas', 111, 0.00, 1),
(485, 2, 'Bebidas', 131, 0.00, 1),
(486, 2, 'Bebidas', 132, 0.00, 1),
(487, 2, 'Bebidas', 93, 0.00, 1),
(488, 2, 'Bebidas', 103, 0.00, 1),
-- Combo 1
(489, 1, 'Bebidas', 96, 0.00, 1),
(490, 1, 'Bebidas', 99, 0.00, 1),
(491, 1, 'Bebidas', 100, 0.00, 1),
(492, 1, 'Bebidas', 95, 0.00, 1),
(493, 1, 'Bebidas', 98, 0.00, 1),
(494, 1, 'Bebidas', 97, 0.00, 1),
(495, 1, 'Bebidas', 91, 0.00, 1),
(496, 1, 'Bebidas', 111, 0.00, 1),
(497, 1, 'Bebidas', 131, 0.00, 1),
(498, 1, 'Bebidas', 132, 0.00, 1),
(499, 1, 'Bebidas', 93, 0.00, 1),
(500, 1, 'Bebidas', 103, 0.00, 1);

-- ============================================
-- VERIFICACIÓN
-- ============================================

-- Ver cuántos registros se insertaron
SELECT 'combo_items' as tabla, COUNT(*) as total FROM combo_items
UNION ALL
SELECT 'combo_selections' as tabla, COUNT(*) as total FROM combo_selections;

-- Ver combos con sus relaciones
SELECT 
    c.id,
    c.name,
    COUNT(DISTINCT ci.id) as items,
    COUNT(DISTINCT cs.id) as bebidas
FROM combos c
LEFT JOIN combo_items ci ON c.id = ci.combo_id
LEFT JOIN combo_selections cs ON c.id = cs.combo_id
WHERE c.active = 1
GROUP BY c.id
ORDER BY c.id;
