<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

// Rate Limiting: 5 registros por IP en 1 hora
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/rl6_rate_' . md5($ip) . '.txt';
$current_time = time();

if (file_exists($rate_limit_file)) {
    $attempts = json_decode(file_get_contents($rate_limit_file), true);
    $attempts = array_filter($attempts, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600; // 1 hora
    });
    
    if (count($attempts) >= 5) {
        echo json_encode(['success' => false, 'error' => 'Demasiados intentos. Intenta en 1 hora.']);
        exit;
    }
    $attempts[] = $current_time;
} else {
    $attempts = [$current_time];
}
file_put_contents($rate_limit_file, json_encode($attempts));

// Validar datos requeridos
$user_id = $_POST['user_id'] ?? null;
$rut = $_POST['rut'] ?? null;
$grado_militar = $_POST['grado_militar'] ?? null;
$unidad_trabajo = $_POST['unidad_trabajo'] ?? null;
$domicilio_particular = $_POST['domicilio_particular'] ?? null;

if (!$user_id || !$rut || !$grado_militar || !$unidad_trabajo || !$domicilio_particular) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos']);
    exit;
}

// Validar imágenes
if (!isset($_FILES['selfie']) || !isset($_FILES['carnet_frontal']) || !isset($_FILES['carnet_trasero'])) {
    echo json_encode(['success' => false, 'error' => 'Faltan imágenes requeridas']);
    exit;
}

// Subir imágenes a AWS S3
function uploadToS3($file, $type, $user_id) {
    $upload_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/upload_image.php';
    
    $ch = curl_init();
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    $data = ['image' => $cfile, 'folder' => 'carnets-militares'];
    
    curl_setopt($ch, CURLOPT_URL, $upload_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['success'] ? $result['url'] : null;
}

$selfie_url = uploadToS3($_FILES['selfie'], 'selfie', $user_id);
$carnet_frontal_url = uploadToS3($_FILES['carnet_frontal'], 'frontal', $user_id);
$carnet_trasero_url = uploadToS3($_FILES['carnet_trasero'], 'trasero', $user_id);

if (!$selfie_url || !$carnet_frontal_url || !$carnet_trasero_url) {
    echo json_encode(['success' => false, 'error' => 'Error al subir imágenes']);
    exit;
}

// Actualizar usuario en BD
try {
    $stmt = $conn->prepare("
        UPDATE usuarios SET 
            es_militar_rl6 = 1,
            rut = ?,
            grado_militar = ?,
            unidad_trabajo = ?,
            domicilio_particular = ?,
            selfie_url = ?,
            carnet_frontal_url = ?,
            carnet_trasero_url = ?,
            fecha_solicitud_rl6 = NOW(),
            credito_aprobado = 0,
            limite_credito = 0,
            credito_usado = 0
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "sssssssi",
        $rut,
        $grado_militar,
        $unidad_trabajo,
        $domicilio_particular,
        $selfie_url,
        $carnet_frontal_url,
        $carnet_trasero_url,
        $user_id
    );
    
    if ($stmt->execute()) {
        // Obtener email del usuario
        $stmt_user = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmt_user->bind_param("i", $user_id);
        $stmt_user->execute();
        $user_result = $stmt_user->get_result();
        $user_data = $user_result->fetch_assoc();
        
        // Enviar email de confirmación
        if ($user_data && $user_data['email']) {
            require_once __DIR__ . '/send_email.php';
            sendRL6Email(
                $user_data['email'],
                $user_data['nombre'],
                $rut,
                $grado_militar,
                $unidad_trabajo,
                'registro'
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Solicitud enviada exitosamente. Te contactaremos en 24 horas.',
            'data' => [
                'user_id' => $user_id,
                'rut' => $rut,
                'grado_militar' => $grado_militar,
                'unidad_trabajo' => $unidad_trabajo,
                'selfie_url' => $selfie_url,
                'carnet_frontal_url' => $carnet_frontal_url,
                'carnet_trasero_url' => $carnet_trasero_url
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar datos: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>
