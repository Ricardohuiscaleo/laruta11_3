<?php
header('Content-Type: text/plain');

echo "🔍 Verificando configuración\n";
echo "============================\n\n";

for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    $fullPath = __DIR__ . '/' . $configPath;
    
    echo "Nivel $i: $fullPath\n";
    echo "Existe: " . (file_exists($fullPath) ? "SÍ" : "NO") . "\n";
    
    if (file_exists($fullPath)) {
        echo "Contenido:\n";
        echo "----------\n";
        $config = require $fullPath;
        
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                if (strpos($key, 'pass') !== false) {
                    echo "$key: [OCULTO]\n";
                } else {
                    echo "$key: $value\n";
                }
            }
        } else {
            echo "Config no es array: " . gettype($config) . "\n";
        }
        echo "\n";
        break;
    }
    echo "\n";
}
?>