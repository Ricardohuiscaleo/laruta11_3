<?php
header('Content-Type: text/html; charset=UTF-8');

$config = require_once __DIR__ . '/../../config.php';

// Obtener user_id
$user_id = $_GET['user_id'] ?? 4;

// Conectar a base de datos
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die('Error de conexión a BD');
}

// Obtener datos del usuario y su crédito
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.limite_credito,
        u.credito_usado,
        u.grado_militar,
        u.unidad_trabajo
    FROM usuarios u
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die('Usuario no encontrado');
}

// Calcular valores
$credito_total = floatval($user['limite_credito']);
$credito_usado = floatval($user['credito_usado']);
$credito_disponible = $credito_total - $credito_usado;
$saldo_pagar = $credito_usado;

// Fecha de vencimiento
$fecha_vencimiento = date('d \d\e F, Y', strtotime('last day of next month'));

$conn->close();
?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Preview - Estado de Cuenta</title>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    
                    <tr>
                        <td style='background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); padding: 30px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>🍔 La Ruta 11</h1>
                            <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 16px;'>Estado de Cuenta - Crédito RL6</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 30px;'>
                            <h2 style='color: #333333; margin: 0 0 15px 0;'>Hola, <?= htmlspecialchars($user['nombre']) ?> 👋</h2>
                            <p style='color: #666666; line-height: 1.6; margin: 0;'>
                                Te enviamos el detalle de tu crédito La Ruta 11. Gracias por confiar en nosotros.
                            </p>
                            <p style='color: #666666; line-height: 1.6; margin: 10px 0 0 0; font-size: 14px;'>
                                <strong>Grado:</strong> <?= htmlspecialchars($user['grado_militar']) ?><br>
                                <strong>Unidad:</strong> <?= htmlspecialchars($user['unidad_trabajo']) ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; border-radius: 8px; overflow: hidden;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h3 style='color: #333333; margin: 0 0 15px 0; font-size: 18px;'>📊 Resumen de Cuenta</h3>
                                        
                                        <table width='100%' cellpadding='8' cellspacing='0'>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Crédito Total:</td>
                                                <td align='right' style='color: #333333; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$<?= number_format($credito_total, 0, ',', '.') ?></td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Consumido:</td>
                                                <td align='right' style='color: #333333; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$<?= number_format($credito_usado, 0, ',', '.') ?></td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Disponible:</td>
                                                <td align='right' style='color: #22c55e; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$<?= number_format($credito_disponible, 0, ',', '.') ?></td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; padding-top: 10px;'>Saldo a Pagar:</td>
                                                <td align='right' style='color: #ef4444; font-weight: bold; font-size: 20px; padding-top: 10px;'>$<?= number_format($saldo_pagar, 0, ',', '.') ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;'>
                                <p style='margin: 0; color: #856404;'>
                                    <strong>📅 Fecha de Vencimiento:</strong> <?= $fecha_vencimiento ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;' align='center'>
                            <a href='https://app.laruta11.cl/pagar-credito?user_id=<?= $user_id ?>&monto=<?= $saldo_pagar ?>' 
                               style='display: inline-block; background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                💳 Pagar con TUU (Webpay)
                            </a>
                            <p style='color: #999999; font-size: 12px; margin: 15px 0 0 0;'>
                                Pago seguro procesado por TUU.cl
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <h3 style='color: #333333; margin: 0 0 15px 0; font-size: 16px;'>💰 Otras formas de pago:</h3>
                            <ul style='color: #666666; line-height: 1.8; margin: 0; padding-left: 20px;'>
                                <li>Transferencia bancaria</li>
                                <li>Efectivo en nuestro local</li>
                                <li>Tarjeta de débito/crédito en local</li>
                            </ul>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; border-radius: 8px;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h4 style='color: #333333; margin: 0 0 10px 0; font-size: 14px;'>🏦 Datos para Transferencia:</h4>
                                        <p style='color: #666666; margin: 5px 0; font-size: 13px;'>
                                            <strong>Banco:</strong> Banco Estado<br>
                                            <strong>Cuenta Corriente:</strong> 12345678<br>
                                            <strong>RUT:</strong> 78.194.739-3<br>
                                            <strong>Titular:</strong> La Ruta 11 SpA
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                            <p style='color: #999999; margin: 0; font-size: 12px;'>
                                📍 Av. Principal 123, Santiago<br>
                                📞 +56 9 1234 5678 | 📧 contacto@laruta11.cl<br>
                                <a href='https://laruta11.cl' style='color: #ff6b35; text-decoration: none;'>www.laruta11.cl</a>
                            </p>
                            <p style='color: #cccccc; margin: 15px 0 0 0; font-size: 11px;'>
                                © 2026 La Ruta 11. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
