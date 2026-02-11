<?php
// Sistema de emails para RL6
// Usa mail() nativo de PHP (funciona en la mayoría de servidores)

function sendRL6Email($to, $nombre, $rut, $grado, $unidad, $tipo = 'registro') {
    $from = 'noreply@laruta11.cl';
    $headers = "From: La Ruta 11 <$from>\r\n";
    $headers .= "Reply-To: contacto@laruta11.cl\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    if ($tipo === 'registro') {
        $subject = '✅ Solicitud RL6 Recibida - La Ruta 11';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 2px solid #f59e0b; border-radius: 10px;'>
                <h2 style='color: #f59e0b; text-align: center;'>🎖️ Solicitud RL6 Recibida</h2>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Hemos recibido tu solicitud de crédito RL6. Nuestro equipo está revisando tu información.</p>
                
                <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0; color: #1f2937;'>Resumen de tu solicitud:</h3>
                    <p><strong>RUT:</strong> $rut</p>
                    <p><strong>Grado:</strong> $grado</p>
                    <p><strong>Unidad:</strong> $unidad</p>
                    <p><strong>Estado:</strong> <span style='color: #f59e0b; font-weight: bold;'>EN REVISIÓN</span></p>
                </div>
                
                <p><strong>¿Qué sigue?</strong></p>
                <ul>
                    <li>Validaremos tu información en máximo 24 horas</li>
                    <li>Te contactaremos por email y teléfono</li>
                    <li>Si es aprobado, podrás usar tu crédito de inmediato</li>
                </ul>
                
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px;'>
                    La Ruta 11 - Sistema RL6<br>
                    <a href='https://wa.me/56936227422' style='color: #f59e0b;'>WhatsApp: +56 9 3622 7422</a>
                </p>
            </div>
        </body>
        </html>
        ";
    } elseif ($tipo === 'aprobado') {
        $limite = func_get_arg(5) ?? 50000;
        $subject = '🎉 Crédito RL6 Aprobado - La Ruta 11';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 2px solid #10b981; border-radius: 10px;'>
                <h2 style='color: #10b981; text-align: center;'>🎉 ¡Felicidades! Tu Crédito RL6 fue Aprobado</h2>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Excelentes noticias: tu solicitud de crédito RL6 ha sido <strong style='color: #10b981;'>APROBADA</strong>.</p>
                
                <div style='background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;'>
                    <h3 style='margin-top: 0; color: #065f46;'>Tu Límite de Crédito</h3>
                    <p style='font-size: 32px; font-weight: bold; color: #10b981; margin: 10px 0;'>$" . number_format($limite, 0, ',', '.') . "</p>
                    <p style='color: #065f46;'>Disponible de inmediato</p>
                </div>
                
                <p><strong>¿Cómo usar tu crédito?</strong></p>
                <ol>
                    <li>Cierra y vuelve a abrir la app (o presiona F5)</li>
                    <li>Ingresa a tu perfil → pestaña \"Crédito\"</li>
                    <li>Selecciona tus productos favoritos</li>
                    <li>En el checkout, elige \"Pagar con Crédito RL6\"</li>
                    <li>Paga el 21 de cada mes</li>
                </ol>
                
                <p style='background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b;'>
                    <strong>💡 Beneficio extra:</strong> Ganas cashback en cada compra que podrás usar en futuras órdenes.
                </p>
                
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px;'>
                    La Ruta 11 - Sistema RL6<br>
                    <a href='https://wa.me/56936227422' style='color: #f59e0b;'>WhatsApp: +56 9 3622 7422</a>
                </p>
            </div>
        </body>
        </html>
        ";
    } elseif ($tipo === 'rechazado') {
        $motivo = func_get_arg(5) ?? 'No se pudo validar la información proporcionada';
        $subject = '❌ Solicitud RL6 No Aprobada - La Ruta 11';
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 2px solid #ef4444; border-radius: 10px;'>
                <h2 style='color: #ef4444; text-align: center;'>Solicitud RL6 No Aprobada</h2>
                <p>Hola <strong>$nombre</strong>,</p>
                <p>Lamentamos informarte que tu solicitud de crédito RL6 no pudo ser aprobada en esta ocasión.</p>
                
                <div style='background: #fee2e2; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p><strong>Motivo:</strong> $motivo</p>
                </div>
                
                <p><strong>¿Qué puedes hacer?</strong></p>
                <ul>
                    <li>Contactarnos para más información</li>
                    <li>Verificar tus datos y volver a intentar</li>
                    <li>Seguir disfrutando de La Ruta 11 con otros métodos de pago</li>
                </ul>
                
                <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px;'>
                    La Ruta 11 - Sistema RL6<br>
                    <a href='https://wa.me/56936227422' style='color: #f59e0b;'>WhatsApp: +56 9 3622 7422</a>
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    return mail($to, $subject, $message, $headers);
}

// Uso:
// sendRL6Email('usuario@email.com', 'Juan Pérez', '12345678-9', 'Capitán', 'RL6', 'registro');
// sendRL6Email('usuario@email.com', 'Juan Pérez', '12345678-9', 'Capitán', 'RL6', 'aprobado', 50000);
// sendRL6Email('usuario@email.com', 'Juan Pérez', '12345678-9', 'Capitán', 'RL6', 'rechazado', 'Datos no válidos');
?>
