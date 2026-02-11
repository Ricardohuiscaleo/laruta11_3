-- ✅ BASE DE DATOS YA CONFIGURADA CORRECTAMENTE

-- Campos agregados a tuu_orders:
-- ✅ customer_notes: Notas del cliente ("sin cebolla", "sin tomate")
-- ✅ special_instructions: Instrucciones especiales

-- Campo agregado a tuu_order_items:
-- ✅ item_type: Distingue productos de extras hardcodeados
--     'product': Productos normales de BD
--     'personalizar': Papas extra, cebolla extra, palta extra, queso extra, merkén
--     'extras': Entrega con escándalo, abrazo, bromas, canto, chiste, baile
--     'acompañamiento': Papas, jugos, bebidas, salsas del combo

-- PRÓXIMOS PASOS:
-- 1. Modificar CheckoutApp.jsx para agregar campo de notas
-- 2. Actualizar APIs para guardar extras con item_type
-- 3. Modificar payment-success.astro para incluir extras en WhatsApp