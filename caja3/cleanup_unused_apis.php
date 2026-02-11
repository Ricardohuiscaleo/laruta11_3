<?php
// Script para identificar APIs no utilizadas en La Ruta 11

$usedApis = [
    // Auth
    'auth/check_session.php',
    'auth/get_profile.php', 
    'auth/update_profile.php',
    'auth/delete_account.php',
    'auth/logout.php',
    'auth/login.php',
    'auth/register.php',
    'auth/google/login.php',
    'auth/google/callback.php',
    
    // Location & Delivery
    'location/get_location.php',
    'location/geocode.php',
    'location/save_location.php',
    'location/check_delivery_zone.php',
    'location/get_nearby_products.php',
    'location/calculate_delivery_time.php',
    
    // Food Trucks
    'food_trucks/get_nearby.php',
    
    // Notifications
    'notifications/get_notifications.php',
    'notifications/mark_read.php',
    'notifications/mark_all_read.php',
    
    // Tracking
    'track_usage.php',
    'track_visit.php',
    
    // TUU Payments
    'tuu/create_payment_simple.php',
    'tuu/get_reports.php',
    'tuu/callback.php',
    'tuu/webhook.php',
    'tuu/check_status.php',
    
    // Admin
    'admin_dashboard.php',
    'products.php',
    'categories.php', 
    'check_admin_auth.php',
    'admin_auth.php',
    'admin_logout.php',
    'get_productos.php',
    'add_producto.php',
    'update_producto.php',
    'create_producto.php',
    'get_categories.php',
    'save_category.php',
    
    // Core functionality
    'api_status.php',
    'check_config.php',
    'setup_tables.php',
    
    // New Analytics APIs
    'app/get_analytics.php',
    'app/track_visit.php', 
    'app/setup_analytics_tables.php',
    'users/get_users.php',
    'users/get_user_detail.php'
];

$apiDir = __DIR__ . '/api';
$allFiles = [];

// Función recursiva para obtener todos los archivos PHP
function getAllPhpFiles($dir, &$files, $basePath = '') {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $dir . '/' . $item;
        $relativePath = $basePath ? $basePath . '/' . $item : $item;
        
        if (is_dir($fullPath)) {
            getAllPhpFiles($fullPath, $files, $relativePath);
        } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
            $files[] = $relativePath;
        }
    }
}

getAllPhpFiles($apiDir, $allFiles);

$unusedFiles = [];
$usedFiles = [];

foreach ($allFiles as $file) {
    if (in_array($file, $usedApis)) {
        $usedFiles[] = $file;
    } else {
        $unusedFiles[] = $file;
    }
}

echo "=== ANÁLISIS DE APIs LA RUTA 11 ===\n\n";
echo "📊 RESUMEN:\n";
echo "- Total archivos PHP: " . count($allFiles) . "\n";
echo "- APIs en uso: " . count($usedFiles) . "\n";
echo "- APIs no utilizadas: " . count($unusedFiles) . "\n\n";

echo "🟢 APIs EN USO (" . count($usedFiles) . "):\n";
foreach ($usedFiles as $file) {
    echo "  ✓ $file\n";
}

echo "\n🔴 APIs NO UTILIZADAS (" . count($unusedFiles) . "):\n";
foreach ($unusedFiles as $file) {
    echo "  ✗ $file\n";
}

// Categorizar archivos no utilizados
$categories = [
    'test' => [],
    'debug' => [],
    'setup' => [],
    'old_versions' => [],
    'duplicates' => [],
    'other' => []
];

foreach ($unusedFiles as $file) {
    $filename = basename($file);
    if (strpos($filename, 'test_') === 0 || strpos($filename, '_test') !== false) {
        $categories['test'][] = $file;
    } elseif (strpos($filename, 'debug_') === 0 || strpos($filename, '_debug') !== false) {
        $categories['debug'][] = $file;
    } elseif (strpos($filename, 'setup_') === 0 || strpos($filename, 'create_') === 0) {
        $categories['setup'][] = $file;
    } elseif (strpos($filename, '_v2') !== false || strpos($filename, '_v3') !== false || strpos($filename, '_update') !== false) {
        $categories['old_versions'][] = $file;
    } else {
        $categories['other'][] = $file;
    }
}

echo "\n📋 CATEGORIZACIÓN DE ARCHIVOS NO UTILIZADOS:\n\n";

foreach ($categories as $category => $files) {
    if (empty($files)) continue;
    
    $categoryNames = [
        'test' => '🧪 Archivos de Testing',
        'debug' => '🐛 Archivos de Debug', 
        'setup' => '⚙️ Scripts de Setup',
        'old_versions' => '📦 Versiones Antiguas',
        'duplicates' => '📋 Duplicados',
        'other' => '📄 Otros'
    ];
    
    echo $categoryNames[$category] . " (" . count($files) . "):\n";
    foreach ($files as $file) {
        echo "  - $file\n";
    }
    echo "\n";
}

// Generar script de limpieza
$cleanupScript = "#!/bin/bash\n";
$cleanupScript .= "# Script de limpieza de APIs no utilizadas\n";
$cleanupScript .= "# Generado automáticamente\n\n";
$cleanupScript .= "echo \"🧹 Limpiando APIs no utilizadas...\"\n\n";

$cleanupScript .= "# Crear backup\n";
$cleanupScript .= "mkdir -p backup_apis\n";
$cleanupScript .= "echo \"📦 Creando backup...\"\n\n";

foreach ($unusedFiles as $file) {
    $cleanupScript .= "# Backup y eliminar: $file\n";
    $cleanupScript .= "cp \"api/$file\" \"backup_apis/\" 2>/dev/null\n";
    $cleanupScript .= "rm \"api/$file\"\n";
}

$cleanupScript .= "\necho \"✅ Limpieza completada. Backup en: backup_apis/\"\n";
$cleanupScript .= "echo \"📊 Archivos eliminados: " . count($unusedFiles) . "\"\n";

file_put_contents(__DIR__ . '/cleanup_apis.sh', $cleanupScript);
chmod(__DIR__ . '/cleanup_apis.sh', 0755);

echo "💾 Script de limpieza generado: cleanup_apis.sh\n";
echo "⚠️  IMPORTANTE: Revisa la lista antes de ejecutar la limpieza\n";
echo "🔧 Para ejecutar: ./cleanup_apis.sh\n";
?>