<?php
// Cargar variables de entorno desde .env
$envPaths = [
    __DIR__ . '/.env',           // Desarrollo
    __DIR__ . '/../../.env',     // Producción 2 niveles
    __DIR__ . '/../../../.env'   // Producción 3 niveles
];

foreach ($envPaths as $envPath) {
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
                putenv(trim($key) . '=' . trim($value));
            }
        }
        break; // Solo cargar el primer .env encontrado
    }
}
?>