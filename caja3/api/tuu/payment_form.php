<?php
// Obtener parámetros
$order_number = $_GET['order'] ?? '';
$amount = $_GET['amount'] ?? 0;
$customer_name = $_GET['customer'] ?? '';
$customer_phone = $_GET['phone'] ?? '';
$description = $_GET['description'] ?? '';
$account_id = $_GET['account_id'] ?? '';
$secret_key = $_GET['secret_key'] ?? '';

if (!$order_number || !$amount || !$account_id || !$secret_key) {
    die('Parámetros faltantes');
}

// Datos del formulario (igual que el plugin WooCommerce)
$form_data = [
    'platform' => 'ruta11app',
    'paymentMethod' => 'webpay',
    'x_account_id' => $account_id,
    'x_amount' => round($amount),
    'x_currency' => 'CLP',
    'x_customer_email' => $customer_phone . '@ruta11.cl',
    'x_customer_first_name' => explode(' ', $customer_name)[0],
    'x_customer_last_name' => explode(' ', $customer_name)[1] ?? '',
    'x_customer_phone' => $customer_phone,
    'x_description' => $description,
    'x_reference' => $order_number,
    'x_shop_country' => 'CL',
    'x_shop_name' => 'Ruta 11',
    'x_url_callback' => 'https://app.laruta11.cl/api/tuu/webhook.php',
    'x_url_cancel' => 'https://app.laruta11.cl/checkout?cancelled=1',
    'x_url_complete' => 'https://app.laruta11.cl/payment-success',
    'secret' => '18756627',
    'dte_type' => 48
];

// URL de destino (igual que el plugin)
$action_url = 'https://core.payment.haulmer.com/api/v1/payment/init';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesando Pago - La Ruta 11</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: #f97316;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #f97316;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            margin-bottom: 20px;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #f97316;
            margin: 20px 0;
        }
        .btn {
            background: #f97316;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            background: #ea580c;
            transform: translateY(-2px);
        }
        .security {
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">R11</div>
        <h2>Procesando tu Pago</h2>
        <p>Serás redirigido a la pasarela segura de TUU</p>
        
        <div class="amount">$<?= number_format($amount, 0, ',', '.') ?></div>
        
        <div class="spinner"></div>
        
        <p><strong><?= htmlspecialchars($customer_name) ?></strong></p>
        <p><?= htmlspecialchars($description) ?></p>
        
        <form id="paymentForm" method="POST" action="<?= $action_url ?>">
            <?php foreach ($form_data as $key => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>
            <button type="submit" class="btn">Continuar al Pago</button>
        </form>
        
        <div class="security">
            🔒 Conexión segura SSL • Protegido por TUU
        </div>
    </div>

    <script>
        // Auto-submit después de 3 segundos
        setTimeout(function() {
            document.getElementById('paymentForm').submit();
        }, 3000);
    </script>
</body>
</html>