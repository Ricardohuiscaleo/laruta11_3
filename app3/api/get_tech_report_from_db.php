<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 4 niveles hacia la raíz
$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php', 
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../../../config.php'
];

$configFound = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

try {
    // Obtener último informe técnico de la DB
    $stmt = $pdo->prepare("
        SELECT report_data, generated_at, deploy_version 
        FROM tech_reports 
        ORDER BY generated_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $report = json_decode($result['report_data'], true);
        $report['db_generated_at'] = $result['generated_at'];
        $report['deploy_version'] = $result['deploy_version'];
        $report['source'] = 'database';
        
        echo json_encode([
            'success' => true,
            'report' => $report,
            'timestamp' => time()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No hay informes técnicos en la base de datos'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>