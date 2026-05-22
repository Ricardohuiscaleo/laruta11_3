ALTER TABLE email_logs
MODIFY COLUMN email_type ENUM(
    'payment_confirmation',
    'credit_statement',
    'payment_reminder',
    'credit_blocked',
    'sin_deuda',
    'recordatorio',
    'urgente',
    'moroso'
) NOT NULL;
