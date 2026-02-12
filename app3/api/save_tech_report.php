<?php
// Guardar informe técnico en base de datos

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
    die(json_encode(['success' => false, 'error' => 'config.php no encontrado']));
}

function saveTechReportToDB($reportData) {
    global $pdo;
    
    try {
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tech_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_data JSON,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deploy_version VARCHAR(50)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO tech_reports (report_data, deploy_version) 
            VALUES (?, ?)
        ");
        
        $version = date('Y-m-d-H-i-s');
        $stmt->execute([json_encode($reportData), $version]);
        
        return ['success' => true, 'version' => $version];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Si se ejecuta directamente, generar y guardar
if (php_sapi_name() === 'cli') {
    $reportData = json_decode(file_get_contents(__DIR__ . '/tech-report-cache.json'), true);
    $result = saveTechReportToDB($reportData);
    echo json_encode($result);
}
?>