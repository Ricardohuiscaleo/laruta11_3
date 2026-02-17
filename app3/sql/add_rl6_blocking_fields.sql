-- Agregar campos para sistema de bloqueos de crédito RL6

-- Campo para bloquear crédito por falta de pago
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS credito_bloqueado TINYINT(1) DEFAULT 0 
COMMENT 'Bloqueado por falta de pago (1=bloqueado, 0=activo)';

-- Campo para registrar fecha del último pago
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS fecha_ultimo_pago DATE NULL 
COMMENT 'Fecha del último pago de crédito RL6';

-- Índice para consultas de bloqueo
ALTER TABLE usuarios 
ADD INDEX IF NOT EXISTS idx_credito_bloqueado (credito_bloqueado);

-- Índice para consultas de fecha de pago
ALTER TABLE usuarios 
ADD INDEX IF NOT EXISTS idx_fecha_ultimo_pago (fecha_ultimo_pago);
