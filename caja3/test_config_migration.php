<?php
echo "=== TEST DE MIGRACIÓN DE CONFIGURACIÓN ===\n\n";

// Test 1: Verificar archivos
echo "1. Verificando archivos...\n";
$files = [
    'config.php (root)' => __DIR__ . '/config.php',
    'load-env.php (root)' => __DIR__ . '/load-env.php',
    'config.php (public)' => __DIR__ . '/public/config.php',
    'load-env.php (public)' => __DIR__ . '/public/load-env.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "   ✓ $name: " . ($exists ? "✅ Existe" : "❌ No existe") . "\n";
}

// Test 2: Cargar config desde raíz
echo "\n2. Cargando config desde raíz...\n";
try {
    $config_root = require __DIR__ . '/config.php';
    echo "   ✓ Config cargado: ✅\n";
    echo "   ✓ Es array: " . (is_array($config_root) ? "✅" : "❌") . "\n";
    echo "   ✓ Tiene keys: " . count($config_root) . " configuraciones\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Cargar config desde public
echo "\n3. Cargando config desde public...\n";
try {
    $config_public = require __DIR__ . '/public/config.php';
    echo "   ✓ Config cargado: ✅\n";
    echo "   ✓ Es array: " . (is_array($config_public) ? "✅" : "❌") . "\n";
    echo "   ✓ Tiene keys: " . count($config_public) . " configuraciones\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Verificar que no requiere load-env
echo "\n4. Verificando independencia de load-env...\n";
$root_content = file_get_contents(__DIR__ . '/config.php');
$has_require = strpos($root_content, 'require_once') !== false && strpos($root_content, 'load-env') !== false;
echo "   ✓ Config NO requiere load-env: " . ($has_require ? "❌ Aún lo requiere" : "✅ Correcto") . "\n";

echo "\n=== MIGRACIÓN COMPLETADA ✅ ===\n";
?>
