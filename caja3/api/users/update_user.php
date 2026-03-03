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
    $pdo = require __DIR__ . '/../db_connect.php';

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

    // Asegurar que updated_at existe (usando un método más compatible)
    $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'updated_at'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    // Actualizar información básica (usando nombres de columna reales: nombre, telefono, activo)
    $sql = "UPDATE usuarios SET 
            nombre = :name,
            email = :email,
            telefono = :phone,
            activo = :is_active,
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
        $rut = $_POST['rut'] ?? $_POST['rut_militar'] ?? '';
        $grado_militar = $_POST['grado_militar'] ?? $_POST['rango_militar'] ?? '';
        $unidad_trabajo = $_POST['unidad_trabajo'] ?? $_POST['unidad_militar'] ?? '';
        $domicilio_particular = $_POST['domicilio_particular'] ?? '';
        $limite_credito = $_POST['limite_credito'] ?? 50000;
        $credito_aprobado = $_POST['credito_aprobado'] ?? 0;

        // Nuevos campos RL6
        $selfie_url = $_POST['selfie_url'] ?? null;
        $carnet_frontal_url = $_POST['carnet_frontal_url'] ?? null;
        $carnet_trasero_url = $_POST['carnet_trasero_url'] ?? null;
        $credito_usado = $_POST['credito_usado'] ?? 0;

        // Auto-migración selectiva: asegurar que las columnas necesarias existen
        $check_cols = [
            'rut' => "VARCHAR(12) NULL",
            'grado_militar' => "VARCHAR(100) NULL",
            'unidad_trabajo' => "VARCHAR(255) NULL",
            'domicilio_particular' => "TEXT NULL",
            'es_militar_rl6' => "TINYINT(1) DEFAULT 0",
            'credito_aprobado' => "TINYINT(1) DEFAULT 0",
            'limite_credito' => "DECIMAL(10,2) DEFAULT 0.00",
            'credito_usado' => "DECIMAL(10,2) DEFAULT 0.00",
            'selfie_url' => "VARCHAR(500) NULL",
            'carnet_frontal_url' => "VARCHAR(500) NULL",
            'carnet_trasero_url' => "VARCHAR(500) NULL",
            'fecha_solicitud_rl6' => "TIMESTAMP NULL",
            'fecha_aprobacion_rl6' => "TIMESTAMP NULL",
            'fecha_aprobacion_credito' => "TIMESTAMP NULL",
            'credito_disponible' => "DECIMAL(10,2) DEFAULT 0.00"
        ];

        foreach ($check_cols as $col => $definition) {
            $check = $pdo->query("SHOW COLUMNS FROM usuarios LIKE '$col'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN $col $definition");
            }
        }

        // Actualizar campos RL6
        $sqlRL6 = "UPDATE usuarios SET 
                  es_militar_rl6 = 1,
                  rut = :rut,
                  grado_militar = :grado_militar,
                  unidad_trabajo = :unidad_trabajo,
                  domicilio_particular = :domicilio_particular,
                  limite_credito = :limite_credito,
                  credito_aprobado = :credito_aprobado,
                  credito_usado = :credito_usado";

        if ($selfie_url)
            $sqlRL6 .= ", selfie_url = :selfie_url";
        if ($carnet_frontal_url)
            $sqlRL6 .= ", carnet_frontal_url = :carnet_frontal_url";
        if ($carnet_trasero_url)
            $sqlRL6 .= ", carnet_trasero_url = :carnet_trasero_url";

        // Si se aprueba el crédito por primera vez, guardar fecha
        if ($credito_aprobado == 1) {
            $sqlRL6 .= ", fecha_aprobacion_credito = COALESCE(fecha_aprobacion_credito, NOW()),
                        fecha_aprobacion_rl6 = COALESCE(fecha_aprobacion_rl6, NOW()),
                        credito_disponible = :limite_credito - :credito_usado";
        }
        else {
            $sqlRL6 .= ", credito_disponible = :limite_credito - :credito_usado";
        }

        $sqlRL6 .= " WHERE id = :user_id";

        $stmtRL6 = $pdo->prepare($sqlRL6);
        $params = [
            'rut' => $rut,
            'grado_militar' => $grado_militar,
            'unidad_trabajo' => $unidad_trabajo,
            'domicilio_particular' => $domicilio_particular,
            'limite_credito' => $limite_credito,
            'credito_aprobado' => $credito_aprobado,
            'credito_usado' => $credito_usado,
            'user_id' => $user_id
        ];

        if ($selfie_url)
            $params['selfie_url'] = $selfie_url;
        if ($carnet_frontal_url)
            $params['carnet_frontal_url'] = $carnet_frontal_url;
        if ($carnet_trasero_url)
            $params['carnet_trasero_url'] = $carnet_trasero_url;

        $stmtRL6->execute($params);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Usuario actualizado correctamente'
    ]);

}
catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}