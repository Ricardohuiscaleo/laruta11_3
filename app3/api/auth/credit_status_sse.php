<?php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$user_id || !$config) {
    http_response_code(400);
    exit();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) { exit(); }

$last_state = null;

while (true) {
    if (connection_aborted()) break;

    $stmt = mysqli_prepare($conn, "SELECT credito_aprobado, es_militar_rl6, credito_bloqueado FROM usuarios WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        $state = $row['credito_aprobado'] . '|' . $row['es_militar_rl6'] . '|' . $row['credito_bloqueado'];
        if ($state !== $last_state) {
            $last_state = $state;
            echo "data: " . json_encode([
                'credito_aprobado' => (int)$row['credito_aprobado'],
                'es_militar_rl6'   => (int)$row['es_militar_rl6'],
                'credito_bloqueado'=> (int)$row['credito_bloqueado'],
            ]) . "\n\n";
            ob_flush();
            flush();
        }
    }

    sleep(10);
}

mysqli_close($conn);
?>
