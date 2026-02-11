<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $today = date('Y-m-d');
    
    // Estadísticas generales
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(CASE WHEN visit_date = '$today' THEN 1 END) as today_visits,
            COUNT(CASE WHEN is_participant = 1 THEN 1 END) as participants,
            COUNT(CASE WHEN has_paid = 1 THEN 1 END) as paid_participants
        FROM concurso_tracking
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular conversión
    $conversion_rate = $stats['total_visits'] > 0 
        ? round(($stats['participants'] / $stats['total_visits']) * 100, 1) 
        : 0;
    
    // Visitas por día (últimos 7 días)
    $stmt = $pdo->query("
        SELECT 
            visit_date as date,
            COUNT(*) as visits
        FROM concurso_tracking 
        WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY visit_date 
        ORDER BY visit_date ASC
    ");
    $daily_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Completar días faltantes con 0 visitas
    $complete_daily = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $found = false;
        foreach ($daily_visits as $day) {
            if ($day['date'] === $date) {
                $complete_daily[] = $day;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $complete_daily[] = ['date' => $date, 'visits' => 0];
        }
    }
    
    // Estadísticas por fuente
    $stmt = $pdo->query("
        SELECT 
            source,
            COUNT(*) as visits,
            COUNT(CASE WHEN visit_date = '$today' THEN 1 END) as today_visits,
            COUNT(CASE WHEN is_participant = 1 THEN 1 END) as participants,
            ROUND(
                CASE 
                    WHEN COUNT(*) > 0 THEN (COUNT(CASE WHEN is_participant = 1 THEN 1 END) / COUNT(*)) * 100
                    ELSE 0 
                END, 1
            ) as conversion_rate
        FROM concurso_tracking 
        GROUP BY source 
        ORDER BY visits DESC
    ");
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_visits' => (int)$stats['total_visits'],
            'today_visits' => (int)$stats['today_visits'],
            'participants' => (int)$stats['participants'],
            'paid_participants' => (int)$stats['paid_participants'],
            'conversion_rate' => $conversion_rate
        ],
        'daily_visits' => $complete_daily,
        'sources' => $sources
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>