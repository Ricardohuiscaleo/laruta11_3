<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $user_id = $_POST['user_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $is_active = $_POST['is_active'] ?? 1;
    $is_militar_rl6 = $_POST['is_militar_rl6'] ?? 0;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'ID de usuario requerido']);
        exit;
    }
    
    // Actualizar información básica
    $sql = "UPDATE usuarios SET 
            name = :name,
            email = :email,
            phone = :phone,
            is_active = :is_active,
            updated_at = NOW()
            WHERE id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'is_active' => $is_active,
        'user_id' => $user_id
    ]);
    
    // Si es militar RL6, actualizar campos adicionales
    if ($is_militar_rl6 == 1) {
        $rut_militar = $_POST['rut_militar'] ?? '';
        $rango_militar = $_POST['rango_militar'] ?? '';
        $unidad_militar = $_POST['unidad_militar'] ?? '';
        $limite_credito = $_POST['limite_credito'] ?? 50000;
        $credito_aprobado = $_POST['credito_aprobado'] ?? 0;
        
        // Verificar si ya tiene columnas RL6
        $checkSql = "SHOW COLUMNS FROM usuarios LIKE 'rut_militar'";
        $checkStmt = $pdo->query($checkSql);
        
        if ($checkStmt->rowCount() > 0) {
            // Actualizar campos RL6
            $sqlRL6 = "UPDATE usuarios SET 
                      rut_militar = :rut_militar,
                      rango_militar = :rango_militar,
                      unidad_militar = :unidad_militar,
                      limite_credito = :limite_credito,
                      credito_aprobado = :credito_aprobado";
            
            // Si se aprueba el crédito por primera vez, guardar fecha
            if ($credito_aprobado == 1) {
                $sqlRL6 .= ", fecha_aprobacion_credito = COALESCE(fecha_aprobacion_credito, NOW()),
                            credito_disponible = :limite_credito";
            }
            
            $sqlRL6 .= " WHERE id = :user_id";
            
            $stmtRL6 = $pdo->prepare($sqlRL6);
            $params = [
                'rut_militar' => $rut_militar,
                'rango_militar' => $rango_militar,
                'unidad_militar' => $unidad_militar,
                'limite_credito' => $limite_credito,
                'credito_aprobado' => $credito_aprobado,
                'user_id' => $user_id
            ];
            
            if ($credito_aprobado == 1) {
                $params['limite_credito'] = $limite_credito;
            }
            
            $stmtRL6->execute($params);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
