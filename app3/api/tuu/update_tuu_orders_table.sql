-- SQL para actualizar tabla tuu_orders con columnas para datos de TUU
-- Ejecutar en phpMyAdmin o cliente MySQL

ALTER TABLE tuu_orders 
ADD COLUMN tuu_transaction_id VARCHAR(100) NULL AFTER updated_at,
ADD COLUMN tuu_amount DECIMAL(10,2) NULL AFTER tuu_transaction_id,
ADD COLUMN tuu_timestamp VARCHAR(255) NULL AFTER tuu_amount,
ADD COLUMN tuu_message VARCHAR(255) NULL AFTER tuu_timestamp,
ADD COLUMN tuu_account_id VARCHAR(50) NULL AFTER tuu_message,
ADD COLUMN tuu_currency VARCHAR(10) NULL AFTER tuu_account_id,
ADD COLUMN tuu_signature TEXT NULL AFTER tuu_currency;