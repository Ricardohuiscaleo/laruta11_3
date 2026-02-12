<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    header('Location: /jobs/?error=db_connection');
    exit();
}

mysqli_set_charset($conn, 'utf8');

if (!isset($_GET['code'])) {
    header('Location: /jobs/?error=auth_failed');
    exit();
}

// Configuración desde config.php
$client_id = $config['ruta11_jobs_client_id'];
$client_secret = $config['ruta11_jobs_client_secret'];
$redirect_uri = $config['ruta11_jobs_redirect_uri'];

// Intercambiar código por token
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $_GET['code'],
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$token_response = curl_exec($ch);
curl_close($ch);

$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    header('Location: /jobs/?error=token_failed');
    exit();
}

// Obtener información del usuario
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_info['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$user_response = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_response, true);

if (!isset($user_info['email'])) {
    header('Location: /jobs/?error=user_failed');
    exit();
}

// Guardar o actualizar usuario en base de datos
try {
    $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE google_id = ?");
    mysqli_stmt_bind_param($stmt, "s", $user_info['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $user_id = $row['id'];
        // Solo actualizar foto_perfil (ultimo_acceso se actualiza automáticamente)
        $stmt = mysqli_prepare($conn, "UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $user_info['picture'], $user_id);
        mysqli_stmt_execute($stmt);
    } else {
        // Crear nuevo usuario (fecha_registro y ultimo_acceso usan defaults)
        $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (google_id, email, nombre, foto_perfil) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $user_info['id'], $user_info['email'], $user_info['name'], $user_info['picture']);
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
    }
    
    // Crear sesión específica para jobs
    $_SESSION['jobs_user_id'] = $user_id;
    $_SESSION['jobs_user_email'] = $user_info['email'];
    $_SESSION['jobs_user_name'] = $user_info['name'];
    $_SESSION['jobs_user_photo'] = $user_info['picture'];
    
    $redirect_url = $_GET['state'] ?? '/jobs/';
    header('Location: ' . $redirect_url . '?login=success');
    
} catch (Exception $e) {
    header('Location: /jobs/?error=db_failed');
}

mysqli_close($conn);
?>