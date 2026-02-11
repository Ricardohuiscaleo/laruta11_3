<?php
// Script para eliminar archivos test, debug y setup

$filesToDelete = [
    // Testing files
    'auth/gmail/test_gmail.php',
    'auth/gmail/test_send.php',
    'auth/test_columns.php',
    'auth/test_register.php',
    'auth/test_session.php',
    'jobs/test_jobs_table.php',
    'jobs/test_keywords.php',
    'jobs/test_score_calculation.php',
    'test_api.php',
    'test_connection.php',
    'test_cors.php',
    'test_dashboard.php',
    'test_gemini.php',
    'test_job_demo.php',
    'test_jobs_table.php',
    'test_kanban_tables.php',
    'test_keywords.php',
    'test_s3.php',
    'test_update_costos.php',
    'test_ventas_v2.php',
    'tuu/test_connection.php',
    
    // Debug files
    'auth/debug_cookies.php',
    'auth/debug_register.php',
    'auth/debug_session.php',
    'debug_analisis.php',
    'debug_db_connection.php',
    'debug_ingredientes.php',
    'debug_metrics.php',
    'debug_order.php',
    'debug_proyeccion.php',
    'debug_recetas.php',
    'jobs/debug_keywords.php',
    'tracker/debug_kanban_status.php',
    
    // Setup files
    'auth/setup_manual_auth.php',
    'create_backup.php',
    'create_order.php',
    'create_productos_table.php',
    'setup_analytics_tables.php',
    'setup_app_db.php',
    'setup_categorias.php',
    'setup_dashboard_tables.php',
    'setup_ia_tables.php',
    'setup_orders_table.php',
    'setup_proyecciones_simple.php',
    'setup_proyecciones_v2.php',
    'setup_real_db.php',
    'setup_user_columns.php',
    'setup_user_tables.php',
    'setup_ventas_tables.php',
    'tracker/setup_questions_table.php',
    'tuu/create_payment.php',
    'tuu/create_payment_fallback.php',
    'tuu/create_payment_minimal.php',
    'tuu/create_payment_real.php',
    'tuu/create_payment_working.php'
];

$apiDir = __DIR__ . '/api';
$backupDir = __DIR__ . '/backup_deleted_apis';

// Crear directorio de backup
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$deleted = 0;
$notFound = 0;

echo "🧹 Eliminando archivos test, debug y setup...\n\n";

foreach ($filesToDelete as $file) {
    $fullPath = $apiDir . '/' . $file;
    
    if (file_exists($fullPath)) {
        // Crear backup
        $backupPath = $backupDir . '/' . basename($file);
        copy($fullPath, $backupPath);
        
        // Eliminar archivo
        unlink($fullPath);
        echo "✅ Eliminado: $file\n";
        $deleted++;
    } else {
        echo "❌ No encontrado: $file\n";
        $notFound++;
    }
}

echo "\n📊 RESUMEN:\n";
echo "- Archivos eliminados: $deleted\n";
echo "- Archivos no encontrados: $notFound\n";
echo "- Backup creado en: backup_deleted_apis/\n";
echo "\n✅ Limpieza completada!\n";
?>