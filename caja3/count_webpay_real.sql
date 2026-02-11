-- Contar webpay REAL (con signature) en Octubre 2025
SELECT 
    COUNT(*) as total_ordenes,
    SUM(total_amount) as monto_total
FROM tuu_orders
WHERE payment_method = 'webpay'
AND tuu_signature IS NOT NULL 
AND tuu_signature != ''
AND MONTH(created_at) = 10 
AND YEAR(created_at) = 2025;

-- Ver detalle
SELECT 
    order_number,
    total_amount,
    created_at,
    LEFT(tuu_signature, 20) as signature_preview
FROM tuu_orders
WHERE payment_method = 'webpay'
AND tuu_signature IS NOT NULL 
AND tuu_signature != ''
AND MONTH(created_at) = 10 
AND YEAR(created_at) = 2025
ORDER BY created_at;
