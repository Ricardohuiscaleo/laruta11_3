-- Agregar pedidosya_cash al ENUM de payment_method en tuu_orders
-- Permite registrar pedidos PedidosYA pagados en efectivo
-- Mantiene todos los valores existentes del ENUM
-- Requirement: 2.1

ALTER TABLE tuu_orders MODIFY COLUMN payment_method
  ENUM('webpay', 'transfer', 'card', 'cash', 'pedidosya', 'pedidosya_cash', 'rl6_credit', 'r11_credit')
  DEFAULT 'webpay';
