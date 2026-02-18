<?php
// Usar configuración de sesión MySQL
require_once __DIR__ . '/../session_config.php';

// session_destroy() automáticamente llama a MySQLSessionHandler::destroy()
// que borra la fila de php_sessions
session_destroy();

header('Location: https://app.laruta11.cl/?logout=success');
exit();
?>