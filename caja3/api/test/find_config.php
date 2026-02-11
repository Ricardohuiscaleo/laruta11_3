<?php
// Script para encontrar config.php desde api/test/

echo "<h2>Buscando config.php desde api/test/</h2>";

// Rutas posibles para config.php
$possible_paths = [
    __DIR__ . '/../../config.php',           // ruta11app/config.php
    __DIR__ . '/../../../config.php',        // Tres niveles arriba
    __DIR__ . '/../../../../config.php',     // Cuatro niveles arriba
    __DIR__ . '/../../../../../config.php',  // Cinco niveles arriba
    __DIR__ . '/../config.php',              // api/config.php
    $_SERVER['DOCUMENT_ROOT'] . '/config.php', // public_html/config.php
    $_SERVER['DOCUMENT_ROOT'] . '/ruta11app/config.php', // public_html/ruta11app/config.php
    dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php' // Antes de public_html
];

echo "<h3>Rutas probadas:</h3>";
echo "<ul>";

foreach ($possible_paths as $path) {
    echo "<li>";
    echo "<strong>Ruta:</strong> " . $path . "<br>";
    
    if (file_exists($path)) {
        echo "<span style='color: green;'>✅ ENCONTRADO</span><br>";
        
        // Intentar incluir el archivo
        try {
            require_once $path;
            echo "<span style='color: blue;'>✅ INCLUIDO CORRECTAMENTE</span><br>";
            
            // Verificar conexión a BD
            if (isset($conn) && $conn) {
                echo "<span style='color: green;'>✅ CONEXIÓN BD: OK</span><br>";
            } else {
                echo "<span style='color: red;'>❌ CONEXIÓN BD: FALLO</span><br>";
            }
            
            // Verificar configuración
            if (isset($config) && is_array($config)) {
                echo "<span style='color: green;'>✅ CONFIG ARRAY: OK</span><br>";
                echo "<strong>Keys disponibles:</strong> " . implode(', ', array_keys($config)) . "<br>";
            } else {
                echo "<span style='color: red;'>❌ CONFIG ARRAY: NO ENCONTRADO</span><br>";
            }
            
            break; // Salir del loop si encontramos el archivo correcto
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ ERROR AL INCLUIR: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ NO EXISTE</span><br>";
    }
    echo "</li><br>";
}

echo "</ul>";

// Información adicional del sistema
echo "<h3>Información del Sistema:</h3>";
echo "<ul>";
echo "<li><strong>__DIR__:</strong> " . __DIR__ . "</li>";
echo "<li><strong>DOCUMENT_ROOT:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</li>";
echo "<li><strong>Script actual:</strong> " . __FILE__ . "</li>";
echo "<li><strong>Working directory:</strong> " . getcwd() . "</li>";
echo "<li><strong>dirname(DOCUMENT_ROOT):</strong> " . dirname($_SERVER['DOCUMENT_ROOT']) . "</li>";
echo "<li><strong>Servidor:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</li>";
echo "</ul>";

// Mostrar estructura de directorios
echo "<h3>Estructura de Directorios:</h3>";
function showDirectory($dir, $level = 0) {
    if ($level > 2) return; // Limitar profundidad
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $path = $dir . '/' . $item;
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        
        if (is_dir($path)) {
            echo $indent . "📁 " . $item . "<br>";
            if ($level < 2) showDirectory($path, $level + 1);
        } else {
            echo $indent . "📄 " . $item;
            if ($item == 'config.php') {
                echo " <span style='color: green;'>← ENCONTRADO!</span>";
            }
            echo "<br>";
        }
    }
}

echo "<strong>Desde raíz del proyecto:</strong><br>";
$root_dir = __DIR__ . '/../../';
if (is_dir($root_dir)) {
    showDirectory($root_dir);
} else {
    echo "No se puede acceder al directorio raíz";
}
?>