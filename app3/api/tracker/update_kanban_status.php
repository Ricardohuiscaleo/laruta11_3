<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$config = require_once __DIR__ . '/../../config.php';

$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $input['user_id'] ?? '';
$new_status = $input['new_status'] ?? '';
$send_notification = $input['send_notification'] ?? false;

if (empty($user_id) || empty($new_status)) {
    echo json_encode(['success' => false, 'error' => 'Datos requeridos faltantes']);
    exit();
}

try {
    mysqli_begin_transaction($conn);
    
    // Obtener estado actual
    $current_query = "SELECT kanban_status, email, nombre FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conn, $current_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    $old_status = $user['kanban_status'];
    
    // Actualizar estado en usuarios
    $update_query = "UPDATE usuarios SET kanban_status = ?, pending_notification = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    $pending = $send_notification ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "sii", $new_status, $pending, $user_id);
    mysqli_stmt_execute($stmt);
    
    // Mover tarjeta del Kanban a la columna correspondiente
    $status_to_column = [
        'nuevo' => 1,
        'revisando' => 2, 
        'entrevista' => 3,
        'contratado' => 4,
        'rechazado' => 5
    ];
    
    if (isset($status_to_column[$new_status])) {
        $column_id = $status_to_column[$new_status];
        $move_card_query = "UPDATE kanban_cards SET column_id = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $move_card_query);
        mysqli_stmt_bind_param($stmt, "ii", $column_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    
    // Registrar notificación
    $notification_id = null;
    if ($send_notification) {
        $status_names = [
            'nuevo' => 'Nuevo candidato',
            'revisando' => 'Postulación en revisión', 
            'entrevista' => 'Seleccionado para entrevista',
            'contratado' => 'Felicidades, has sido contratado',
            'rechazado' => 'Resultado de tu postulación'
        ];
        
        $titulo = $status_names[$new_status] ?? 'Actualización de estado';
        $mensaje = "Tu estado en el proceso de selección ha cambiado a: " . $new_status;
        
        // Enviar email primero
        $email_result = sendStatusNotification($user_id, $user['email'], $user['nombre'], $new_status, $conn);
        
        // Registrar notificación
        $history_query = "INSERT INTO order_notifications (user_id, tipo, titulo, mensaje) VALUES (?, 'sistema', ?, ?)";
        $stmt = mysqli_prepare($conn, $history_query);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $titulo, $mensaje);
        mysqli_stmt_execute($stmt);
        $notification_id = mysqli_insert_id($conn);
    }
    
    // El email ya se envió arriba, solo actualizar contador si fue exitoso
    if ($send_notification && $user['email'] && isset($email_result)) {
        
        if ($email_result['success'] && $notification_id) {
            // Notificación registrada correctamente
            
            // Actualizar contador de notificaciones
            $update_user = "UPDATE usuarios SET notification_count = notification_count + 1 WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_user);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
        }
    }
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Estado actualizado correctamente',
        'notification_sent' => $send_notification && isset($email_result) ? $email_result['success'] : false
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);

function sendStatusNotification($user_id, $email, $nombre, $status, $conn) {
    // Obtener template
    $template_query = "SELECT email_subject, email_body FROM notification_templates WHERE kanban_status = ? AND is_active = 1";
    $stmt = mysqli_prepare($conn, $template_query);
    mysqli_stmt_bind_param($stmt, "s", $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $template = mysqli_fetch_assoc($result);
    
    if (!$template) {
        return ['success' => false, 'error' => 'Template no encontrado'];
    }
    
    // Obtener el puesto desde job_applications
    $position_query = "SELECT position FROM job_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $position_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $job_app = mysqli_fetch_assoc($result);
    
    $position = $job_app ? $job_app['position'] : 'el puesto solicitado';
    
    // Personalizar template
    $subject = str_replace('{nombre}', $nombre, $template['email_subject']);
    $body = str_replace(['{nombre}', '{posicion}'], [$nombre, $position], $template['email_body']);
    
    // Enviar email usando Gmail API
    try {
        require_once __DIR__ . '/../auth/gmail/send_email.php';
        
        $email_data = [
            'to' => $email,
            'subject' => $subject,
            'body' => $body,
            'from_name' => 'La Ruta 11 - Empleos',
            'candidate_name' => $nombre
        ];
        
        $email_result = sendGmailEmail($email_data);
        
        return [
            'success' => $email_result['success'],
            'subject' => $subject,
            'error' => $email_result['error'] ?? null
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error enviando email: ' . $e->getMessage()
        ];
    }
}
?>