<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Cargar config desde raíz
    $config_path = __DIR__ . '/../../../config.php';
    if (!file_exists($config_path)) {
        throw new Exception('Archivo config.php no encontrado en: ' . $config_path);
    }
    $config = require_once $config_path;

    if (!isset($_GET['code'])) {
        throw new Exception('No se recibió código de autorización');
    }

    // Verificar configuración
    if (!isset($config['ruta11_google_client_id']) || !isset($config['ruta11_google_client_secret'])) {
        throw new Exception('Configuración de Google OAuth incompleta');
    }

    // Intercambiar código por token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'client_id' => $config['ruta11_google_client_id'],
        'client_secret' => $config['ruta11_google_client_secret'],
        'redirect_uri' => $config['ruta11_google_redirect_uri'],
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
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
        // Debug info
        $debug_info = [
            'token_response' => $token_response,
            'curl_error' => curl_error($ch) ?? 'none',
            'token_data_sent' => $token_data,
            'parsed_response' => $token_info
        ];
        file_put_contents(__DIR__ . '/callback_debug.log', date('Y-m-d H:i:s') . " - Token Error: " . json_encode($debug_info) . "\n", FILE_APPEND);
        throw new Exception('Error obteniendo token: ' . $token_response);
    }

    // Obtener información del usuario
    $user_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
    $user_response = file_get_contents($user_url);
    $user_info = json_decode($user_response, true);

    if (!$user_info || !isset($user_info['id'])) {
        throw new Exception('Error obteniendo datos del usuario');
    }

    // Conectar solo a APP_DB
    $app_conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    if (!$app_conn) {
        throw new Exception('Error de conexión a BD: ' . mysqli_connect_error());
    }

    mysqli_set_charset($app_conn, 'utf8');

    // Verificar si usuario existe
    $google_id = mysqli_real_escape_string($app_conn, $user_info['id']);
    $email = mysqli_real_escape_string($app_conn, $user_info['email']);
    $nombre = mysqli_real_escape_string($app_conn, $user_info['name']);
    $foto_perfil = mysqli_real_escape_string($app_conn, $user_info['picture']);

    $check_user = "SELECT * FROM usuarios WHERE google_id = '$google_id'";
    $result = mysqli_query($app_conn, $check_user);

    if (!$result) {
        throw new Exception('Error en consulta: ' . mysqli_error($app_conn));
    }

    if (mysqli_num_rows($result) > 0) {
        // Usuario existe, actualizar
        $update_user = "UPDATE usuarios SET ultimo_acceso = NOW(), foto_perfil = '$foto_perfil' WHERE google_id = '$google_id'";
        mysqli_query($app_conn, $update_user);
        $user = mysqli_fetch_assoc($result);
    } else {
        // Crear nuevo usuario
        $insert_user = "INSERT INTO usuarios (google_id, email, nombre, foto_perfil, fecha_registro) VALUES ('$google_id', '$email', '$nombre', '$foto_perfil', NOW())";
        if (!mysqli_query($app_conn, $insert_user)) {
            throw new Exception('Error creando usuario: ' . mysqli_error($app_conn));
        }
        $user_id = mysqli_insert_id($app_conn);
        $user = ['id' => $user_id, 'google_id' => $google_id, 'email' => $email, 'nombre' => $nombre, 'foto_perfil' => $foto_perfil];
    }

    // Crear sesión persistente (30 días)
    ini_set('session.cookie_lifetime', 2592000); // 30 días en segundos
    ini_set('session.gc_maxlifetime', 2592000);
    session_start();
    $_SESSION['user'] = $user;
    
    // Renovar cookie de sesión para que persista
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), session_id(), time() + 2592000, '/', '', true, true);
    }

    mysqli_close($app_conn);

    // Redirigir de vuelta a la app
    header('Location: https://app.laruta11.cl/?login=success');
    exit();

} catch (Exception $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    error_log('Google OAuth Stack: ' . $e->getTraceAsString());
    header('Location: https://app.laruta11.cl/?login=error&msg=' . urlencode($e->getMessage()));
    exit();
}
?>