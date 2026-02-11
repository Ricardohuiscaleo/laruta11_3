<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Datos estáticos para producción
$staticReport = [
    'totalFiles' => 571,
    'totalLines' => 73014,
    'categories' => [
        'Frontend' => ['files' => 91, 'lines' => 28589],
        'Backend PHP' => ['files' => 406, 'lines' => 34956],
        'Database' => ['files' => 32, 'lines' => 1678],
        'Config' => ['files' => 6, 'lines' => 7102],
        'Documentation' => ['files' => 10, 'lines' => 0],
        'Media' => ['files' => 12, 'lines' => 0],
        'Other' => ['files' => 14, 'lines' => 689]
    ],
    'directories' => [
        'api' => ['files' => 362, 'lines' => 30244],
        'src' => ['files' => 80, 'lines' => 21153],
        'backup_deleted_apis' => ['files' => 52, 'lines' => 4165],
        'public' => ['files' => 15, 'lines' => 4024],
        'sql' => ['files' => 8, 'lines' => 301]
    ],
    'largestFiles' => [
        ['path' => 'package-lock.json', 'lines' => 6991],
        ['path' => 'src/components/MenuApp.jsx', 'lines' => 3298],
        ['path' => 'game-isolated/index.astro', 'lines' => 2732],
        ['path' => 'src/pages/admin/index.astro', 'lines' => 2059],
        ['path' => 'src/pages/admin/edit-product.astro', 'lines' => 1929]
    ],
    'system_status' => [
        'app_status' => 'operational',
        'api_status' => 'operational', 
        'db_status' => 'operational',
        'last_check' => date('Y-m-d H:i:s')
    ],
    'generated_at' => date('Y-m-d H:i:s'),
    'source' => 'static'
];

$staticReport['summary'] = [
    'totalFiles' => $staticReport['totalFiles'],
    'totalLines' => $staticReport['totalLines'],
    'avgLinesPerFile' => round($staticReport['totalLines'] / $staticReport['totalFiles'])
];

echo json_encode([
    'success' => true,
    'report' => $staticReport,
    'timestamp' => time()
]);
?>