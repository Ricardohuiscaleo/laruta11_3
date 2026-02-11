<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if (!$configPath) {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Obtener estado del torneo y participantes reales en una sola query
    $stmt = $pdo->prepare("
        SELECT 
            cs.tournament_data,
            cs.updated_at,
            GROUP_CONCAT(
                CONCAT(
                    'p', cr.id, '|',
                    COALESCE(cr.customer_name, cr.nombre), '|',
                    COALESCE(cr.image_url, '')
                ) SEPARATOR '||'
            ) as participants_data
        FROM concurso_state cs
        LEFT JOIN concurso_registros cr ON cr.payment_status = 'paid'
        WHERE cs.id = 1
        GROUP BY cs.id
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['tournament_data']) {
        $tournamentData = json_decode($result['tournament_data'], true);
        
        // Actualizar participantes con datos reales
        if ($result['participants_data'] && isset($tournamentData['participants'])) {
            $realParticipants = [];
            $participantsList = explode('||', $result['participants_data']);
            
            foreach ($participantsList as $pData) {
                if (empty($pData)) continue;
                $parts = explode('|', $pData);
                if (count($parts) >= 3) {
                    $realParticipants[$parts[0]] = [
                        'name' => $parts[1],
                        'image_url' => $parts[2]
                    ];
                }
            }
            
            // Actualizar nombres e imágenes reales
            foreach ($tournamentData['participants'] as &$participant) {
                if (isset($realParticipants[$participant['id']])) {
                    $participant['name'] = $realParticipants[$participant['id']]['name'];
                    $participant['image_url'] = $realParticipants[$participant['id']]['image_url'];
                }
            }
        }
        
        $tournamentData['last_updated'] = $result['updated_at'];
        echo json_encode($tournamentData);
    } else {
        // Si no hay estado, devolver participantes reales
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT('p', id) as id,
                COALESCE(customer_name, nombre) as name,
                ROW_NUMBER() OVER (ORDER BY fecha_registro) as seed,
                image_url
            FROM concurso_registros 
            WHERE payment_status = 'paid'
            ORDER BY fecha_registro ASC
            LIMIT 8
        ");
        $stmt->execute();
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $participantsMap = [];
        foreach ($participants as $p) {
            $participantsMap[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'seed' => (int)$p['seed'],
                'status' => 'active',
                'image_url' => $p['image_url']
            ];
        }

        echo json_encode([
            'participants' => $participantsMap,
            'matches' => [],
            'round' => 'Cuartos',
            'status' => 'preparing',
            'last_updated' => date('Y-m-d H:i:s')
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>