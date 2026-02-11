<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Cargar config
    $config = require_once __DIR__ . '/../../../../../config.php';

    if (!isset($_GET['code'])) {
        throw new Exception('No se recibió código de autorización');
    }

    // Intercambiar código por token
    $token_data = [
        'client_id' => $config['ruta11_google_client_id'],
        'client_secret' => $config['ruta11_google_client_secret'],
        'redirect_uri' => 'https://app.laruta11.cl/api/auth/google/app_callback.php',
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $token_response = curl_exec($ch);
    
    if (curl_error($ch)) {
        throw new Exception('Error cURL: ' . curl_error($ch));
    }
    curl_close($ch);

    $token_info = json_decode($token_response, true);

    if (!isset($token_info['access_token'])) {
        throw new Exception('Error obteniendo token: ' . $token_response);
    }

    // Obtener información del usuario
    $user_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
    $user_response = file_get_contents($user_url);
    $user_info = json_decode($user_response, true);

    if (!$user_info || !isset($user_info['id'])) {
        throw new Exception('Error obteniendo datos del usuario');
    }

    // Conectar SOLO a app DB
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    if (!$conn) {
        throw new Exception('Error de conexión a BD: ' . mysqli_connect_error());
    }

    mysqli_set_charset($conn, 'utf8');

    // Verificar si usuario existe
    $google_id = mysqli_real_escape_string($conn, $user_info['id']);
    $email = mysqli_real_escape_string($conn, $user_info['email']);
    $nombre = mysqli_real_escape_string($conn, $user_info['name']);
    $foto_perfil = mysqli_real_escape_string($conn, $user_info['picture']);

    $check_user = "SELECT * FROM usuarios WHERE google_id = '$google_id'";
    $result = mysqli_query($conn, $check_user);

    if (!$result) {
        throw new Exception('Error en consulta: ' . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) > 0) {
        // Usuario existe, actualizar
        $update_user = "UPDATE usuarios SET ultimo_acceso = NOW(), foto_perfil = '$foto_perfil' WHERE google_id = '$google_id'";
        if (!mysqli_query($conn, $update_user)) {
            throw new Exception('Error actualizando usuario: ' . mysqli_error($conn));
        }
        $user = mysqli_fetch_assoc($result);
    } else {
        // Crear nuevo usuario
        $insert_user = "INSERT INTO usuarios (google_id, email, nombre, foto_perfil, fecha_registro) VALUES ('$google_id', '$email', '$nombre', '$foto_perfil', NOW())";
        if (!mysqli_query($conn, $insert_user)) {
            throw new Exception('Error creando usuario: ' . mysqli_error($conn));
        }
        $user_id = mysqli_insert_id($conn);
        $user = ['id' => $user_id, 'google_id' => $google_id, 'email' => $email, 'nombre' => $nombre, 'foto_perfil' => $foto_perfil];
    }

    // Crear sesión
    session_start();
    $_SESSION['user'] = $user;

    mysqli_close($conn);

    // Redirigir de vuelta a la app
    header('Location: https://app.laruta11.cl/?login=success');
    exit();

} catch (Exception $e) {
    error_log('Google OAuth App Error: ' . $e->getMessage());
    header('Location: https://app.laruta11.cl/?login=error&msg=' . urlencode($e->getMessage()));
    exit();
}
?>