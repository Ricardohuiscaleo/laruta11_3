<?php
// Verificar versión del código en producción
echo "VERSION: v3 - Prepared statement con VALUES()\n";
echo "TIMESTAMP: " . date('Y-m-d H:i:s') . "\n";
echo "FILE: " . __FILE__ . "\n";

// Mostrar primeras líneas del callback
$callback_file = __DIR__ . '/callback.php';
if (file_exists($callback_file)) {
    $lines = file($callback_file);
    echo "\nPRIMERAS 5 LÍNEAS DE CALLBACK.PHP:\n";
    echo "=====================================\n";
    for ($i = 0; $i < min(5, count($lines)); $i++) {
        echo ($i + 1) . ": " . $lines[$i];
    }
    
    // Buscar si tiene el código nuevo
    $content = file_get_contents($callback_file);
    if (strpos($content, 'v3 - FINAL') !== false) {
        echo "\n✅ CÓDIGO V3 DETECTADO (prepared statement con VALUES())\n";
    } else if (strpos($content, 'real_escape_string') !== false) {
        echo "\n⚠️ CÓDIGO V2 DETECTADO (query directa)\n";
    } else if (strpos($content, 'bind_param') !== false) {
        echo "\n⚠️ CÓDIGO VIEJO DETECTADO (bind_param sin VALUES)\n";
    } else {
        echo "\n❓ NO SE PUDO DETERMINAR LA VERSIÓN\n";
    }
}
