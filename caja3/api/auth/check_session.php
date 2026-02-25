<?php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'https://app.laruta11.cl';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-cache, no-store, must-revalidate');

$response = [
    'authenticated' => false,
    'user' => null,
    'cashier' => null
];

// 1. Verificar Cliente (Login normal Google/etc)
if (isset($_SESSION['user_id'])) {
    $response['authenticated'] = true;
    $response['user'] = [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'foto_perfil' => $_SESSION['picture'] ?? ''
    ];
}

// 2. Verificar Cajera (Login interno caja)
if (isset($_SESSION['cashier'])) {
    $username = $_SESSION['cashier']['username'];

    // Intentar obtener detalles frescos de la BD
    try {
        $config_paths = [__DIR__ . '/../config.php', __DIR__ . '/../../config.php'];
        $config = null;
        foreach ($config_paths as $path) {
            if (file_exists($path)) {
                $config = require $path;
                break;
            }
        }

        if ($config) {
            $pdo = new PDO(
                "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
                $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            $stmt = $pdo->prepare("SELECT id, username, full_name, phone, email, role FROM cashiers WHERE username = ?");
            $stmt->execute([$username]);
            $cashier = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cashier) {
                $response['cashier'] = [
                    'user' => $cashier['username'],
                    'userId' => $cashier['id'],
                    'fullName' => $cashier['full_name'],
                    'phone' => $cashier['phone'],
                    'email' => $cashier['email'],
                    'role' => $cashier['role'],
                    'timestamp' => $_SESSION['cashier']['login_time'] * 1000 // ms for JS
                ];
                // Si solo hay cajera, marcamos como autenticado también para compatibilidad
                $response['authenticated'] = true;
            }
        }
    }
    catch (Exception $e) {
        // Fallback al dato de sesión si falla la BD
        $response['cashier'] = [
            'user' => $username,
            'timestamp' => $_SESSION['cashier']['login_time'] * 1000
        ];
    }
}

echo json_encode($response);
?>