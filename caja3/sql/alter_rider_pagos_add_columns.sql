-- Add payment method and token columns to rider_pagos
ALTER TABLE rider_pagos 
  ADD COLUMN metodo_pago ENUM('transferencia','efectivo') DEFAULT 'transferencia' AFTER estado,
  ADD COLUMN token VARCHAR(64) DEFAULT NULL AFTER comprobante_url,
  ADD INDEX idx_token (token);
