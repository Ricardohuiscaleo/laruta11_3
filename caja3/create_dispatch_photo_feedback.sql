-- Crear tabla para almacenar resultados de verificación IA de fotos de despacho
CREATE TABLE IF NOT EXISTS dispatch_photo_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Referencia al pedido
    order_id INT NOT NULL,
    
    -- Tipo de foto verificada
    photo_type ENUM('productos', 'bolsa') NOT NULL,
    
    -- URL de la foto en S3
    photo_url TEXT NOT NULL,
    
    -- Resultado de verificación IA
    ai_aprobado TINYINT(1) NOT NULL DEFAULT 1,
    ai_puntaje INT NOT NULL DEFAULT 0,
    ai_feedback TEXT,
    
    -- Indica si el usuario re-subió la foto después de eliminar la anterior
    user_retook TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_order_id (order_id),
    INDEX idx_photo_type (photo_type),
    
    -- Foreign key
    FOREIGN KEY (order_id) REFERENCES tuu_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
