-- Insertar datos de ejemplo para analytics
-- Base de datos: u958525313_app

-- Insertar visitas de ejemplo
INSERT IGNORE INTO site_visits (ip_address, page_url, visit_date, device_type, browser) VALUES
('192.168.1.1', 'https://app.laruta11.cl', CURDATE(), 'mobile', 'Chrome'),
('192.168.1.2', 'https://app.laruta11.cl', CURDATE(), 'desktop', 'Firefox'),
('192.168.1.3', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'mobile', 'Safari'),
('192.168.1.4', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'tablet', 'Chrome'),
('192.168.1.5', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'mobile', 'Safari'),
('192.168.1.6', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'desktop', 'Edge'),
('192.168.1.7', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'mobile', 'Chrome'),
('192.168.1.8', 'https://app.laruta11.cl', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'tablet', 'Safari');

-- Insertar pedidos de ejemplo
INSERT IGNORE INTO user_orders (user_id, order_number, total_amount, status, order_date) VALUES
(1, 'R11-001', 22000.00, 'delivered', CURDATE()),
(2, 'R11-002', 8800.00, 'delivered', CURDATE()),
(3, 'R11-003', 15000.00, 'pending', CURDATE()),
(1, 'R11-004', 9500.00, 'delivered', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(2, 'R11-005', 12300.00, 'delivered', DATE_SUB(CURDATE(), INTERVAL 2 DAY));

-- Insertar items de pedidos
INSERT IGNORE INTO user_order_items (order_id, product_id, product_name, quantity, unit_price, total_price) VALUES
(1, 1, 'Tomahawk Full Ruta 11', 1, 22000.00, 22000.00),
(2, 2, 'Completo Talquino', 2, 2300.00, 4600.00),
(2, 5, 'Hamburguesa Ruta 11', 1, 7200.00, 7200.00),
(3, 3, 'Papas Ruta 11', 2, 3500.00, 7000.00),
(3, 4, 'Churrasco Vacuno', 1, 6000.00, 6000.00);

-- Actualizar métricas diarias
INSERT INTO daily_metrics (metric_date, unique_visitors, total_visits, new_users, total_orders, total_revenue) 
VALUES (CURDATE(), 25, 35, 5, 3, 45800.00)
ON DUPLICATE KEY UPDATE 
    unique_visitors = VALUES(unique_visitors),
    total_visits = VALUES(total_visits),
    new_users = VALUES(new_users),
    total_orders = VALUES(total_orders),
    total_revenue = VALUES(total_revenue);