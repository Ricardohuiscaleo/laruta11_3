-- ============================================================
-- Migración: Sistema de Crédito R11
-- Fecha: 2025
-- Descripción: Agrega campos y tablas necesarios para el
--   sistema de crédito R11 (trabajadores La Ruta 11).
-- ============================================================
-- IMPORTANTE: Ejecutar en la base de datos u958525313_app
-- Ejecutar con usuario que tenga permisos ALTER, CREATE, INDEX
-- ============================================================

-- ------------------------------------------------------------
-- 1.1 Campos R11 en tabla `usuarios`
-- ------------------------------------------------------------

ALTER TABLE usuarios ADD COLUMN es_credito_r11 TINYINT(1) DEFAULT 0
  COMMENT 'Es beneficiario del crédito R11 (1=sí, 0=no)';

ALTER TABLE usuarios ADD COLUMN credito_r11_aprobado TINYINT(1) DEFAULT 0
  COMMENT 'Crédito R11 aprobado por admin (1=aprobado, 0=pendiente)';

ALTER TABLE usuarios ADD COLUMN limite_credito_r11 DECIMAL(10,2) DEFAULT 0.00
  COMMENT 'Límite de crédito R11 asignado';

ALTER TABLE usuarios ADD COLUMN credito_r11_usado DECIMAL(10,2) DEFAULT 0.00
  COMMENT 'Crédito R11 consumido en el período actual';

ALTER TABLE usuarios ADD COLUMN credito_r11_bloqueado TINYINT(1) DEFAULT 0
  COMMENT 'Crédito R11 bloqueado por falta de pago (1=bloqueado, 0=activo)';

ALTER TABLE usuarios ADD COLUMN fecha_aprobacion_r11 TIMESTAMP NULL
  COMMENT 'Fecha en que se aprobó el crédito R11';

ALTER TABLE usuarios ADD COLUMN fecha_ultimo_pago_r11 DATE NULL
  COMMENT 'Fecha del último pago de crédito R11';

ALTER TABLE usuarios ADD COLUMN relacion_r11 VARCHAR(100) NULL
  COMMENT 'Relación con R11: trabajador, familiar, confianza, Planchero/a, Cajero/a, Rider, Otro';

-- Índices para consultas frecuentes
ALTER TABLE usuarios ADD INDEX idx_es_credito_r11 (es_credito_r11);
ALTER TABLE usuarios ADD INDEX idx_credito_r11_bloqueado (credito_r11_bloqueado);

-- ------------------------------------------------------------
-- 1.2 Tabla `r11_credit_transactions`
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS r11_credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'FK → usuarios.id',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Monto de la transacción',
    type ENUM('credit','debit','refund') NOT NULL COMMENT 'credit=ajuste, debit=compra, refund=pago/reintegro',
    description VARCHAR(255) COMMENT 'Descripción de la transacción',
    order_id VARCHAR(50) COMMENT 'Referencia a tuu_orders.order_number',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Transacciones de crédito R11 (independiente de RL6)';

-- ------------------------------------------------------------
-- 1.3 Campos R11 en tabla `tuu_orders` + ENUM payment_method
-- ------------------------------------------------------------

ALTER TABLE tuu_orders ADD COLUMN pagado_con_credito_r11 TINYINT(1) DEFAULT 0
  COMMENT 'Orden pagada con crédito R11 (1=sí, 0=no)';

ALTER TABLE tuu_orders ADD COLUMN monto_credito_r11 DECIMAL(10,2) DEFAULT 0.00
  COMMENT 'Monto pagado con crédito R11';

-- Agregar r11_credit al ENUM de payment_method
ALTER TABLE tuu_orders MODIFY COLUMN payment_method
  ENUM('webpay','transfer','card','cash','pedidosya','rl6_credit','r11_credit')
  DEFAULT 'webpay';

ALTER TABLE tuu_orders ADD INDEX idx_pagado_credito_r11 (pagado_con_credito_r11);

-- ------------------------------------------------------------
-- 1.4 Migración tabla `personal` — vincular con usuarios y R11
-- ------------------------------------------------------------

ALTER TABLE personal ADD COLUMN user_id INT NULL
  COMMENT 'FK → usuarios.id para vincular con cuenta de cliente';

ALTER TABLE personal ADD COLUMN rut VARCHAR(12) NULL
  COMMENT 'RUT chileno del trabajador (ej: 17638433-6)';

ALTER TABLE personal ADD COLUMN telefono VARCHAR(20) NULL
  COMMENT 'Teléfono del trabajador';

-- Agregar 'rider' al SET de roles
ALTER TABLE personal MODIFY COLUMN rol
  SET('administrador','cajero','planchero','delivery','seguridad','dueño','rider')
  NOT NULL DEFAULT 'cajero';

-- Índice para búsqueda por user_id
ALTER TABLE personal ADD INDEX idx_user_id (user_id);

-- ============================================================
-- FIN DE MIGRACIÓN R11
-- No se modifican datos existentes.
-- Los registros actuales en `personal` se vincularán
-- manualmente o cuando cada trabajador haga el registro R11.
-- ============================================================


-- ============================================================
-- Migración adicional: campo carnet_qr_data en usuarios
-- Almacena los datos completos del QR del carnet chileno
-- (RUN, type, serial, mrz, validated, validation_status)
-- ============================================================

ALTER TABLE usuarios ADD COLUMN carnet_qr_data JSON NULL
  COMMENT 'Datos del QR del carnet: RUN, type, serial, mrz, validated, validation_status';
