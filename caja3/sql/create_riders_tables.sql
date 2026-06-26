-- Riders table (dedicated rider management)
CREATE TABLE IF NOT EXISTS riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    email VARCHAR(150),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rider payments (receipt tracking like compras)
CREATE TABLE IF NOT EXISTS rider_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    order_id INT,
    monto DECIMAL(10,2) NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('pendiente','pagado') DEFAULT 'pendiente',
    comprobante_url VARCHAR(500),
    notas TEXT,
    pagado_en TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES tuu_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert riders
INSERT INTO riders (nombre, telefono, activo) VALUES
('Karen', NULL, 1),
('Ariel', NULL, 1),
('Alba', NULL, 1),
('Eliana', NULL, 1),
('Javier', NULL, 1),
('Cecilia', NULL, 1),
('Jacqueline', NULL, 1),
('Luis', NULL, 1),
('Juan', NULL, 1),
('Francisca', NULL, 1),
('Vivix', NULL, 1),
('Beatriz', NULL, 1),
('Karina', NULL, 1),
('Marcelo', NULL, 1),
('Ricardo', NULL, 1),
('Yojhans', NULL, 1);
