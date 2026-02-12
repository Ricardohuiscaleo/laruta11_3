<?php
echo "=== AUDITORÍA DE USO DE CONFIG.PHP ===\n\n";

function findConfigUsage($dir) {
    $results = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            
            if (preg_match_all('/(require_once|require|include_once|include)\s+[\'"]?([^\'";\s]+config\.php)[\'"]?/i', $content, $matches)) {
                $relativePath = str_replace(__DIR__ . '/', '', $file->getPathname());
                $results[$relativePath] = [
                    'paths' => array_unique($matches[2]),
                    'methods' => array_unique($matches[1])
                ];
            }
        }
    }
    return $results;
}

echo "Escaneando directorio api/...\n";
$apiResults = findConfigUsage(__DIR__ . '/api');

echo "\n📊 RESUMEN:\n";
echo "Total de archivos que usan config: " . count($apiResults) . "\n\n";

$patterns = [];
foreach ($apiResults as $file => $data) {
    foreach ($data['paths'] as $path) {
        if (!isset($patterns[$path])) {
            $patterns[$path] = [];
        }
        $patterns[$path][] = $file;
    }
}

echo "📁 PATRONES DE RUTA ENCONTRADOS:\n\n";
foreach ($patterns as $pattern => $files) {
    echo "Patrón: $pattern\n";
    echo "Archivos: " . count($files) . "\n";
    echo "Ejemplos:\n";
    foreach (array_slice($files, 0, 3) as $file) {
        echo "  - $file\n";
    }
    echo "\n";
}

echo "=== FIN DE AUDITORÍA ===\n";
?>
