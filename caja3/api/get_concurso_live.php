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

    // Obtener estado actual del torneo
    $stmt = $pdo->prepare("SELECT tournament_data, updated_at FROM concurso_state WHERE id = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $tournamentData = json_decode($result['tournament_data'], true);
        $tournamentData['last_updated'] = $result['updated_at'];
        echo json_encode($tournamentData);
    } else {
        // Si no hay estado guardado, devolver participantes reales del concurso
        $stmt = $pdo->prepare("
            SELECT 
                CONCAT('p', ROW_NUMBER() OVER (ORDER BY fecha_registro)) as id,
                COALESCE(customer_name, nombre) as name,
                ROW_NUMBER() OVER (ORDER BY fecha_registro) as seed,
                image_url
            FROM concurso_registros 
            WHERE payment_status = 'paid' OR estado_pago = 'pagado'
            ORDER BY fecha_registro ASC
            LIMIT 8
        ");
        $stmt->execute();
        $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convertir a formato esperado
        $participantsMap = [];
        foreach ($participants as $p) {
            $participantsMap[$p['id']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'seed' => (int)$p['seed'],
                'image_url' => $p['image_url']
            ];
        }

        echo json_encode([
            'participants' => $participantsMap,
            'rounds' => [],
            'champion' => null,
            'status' => 'waiting',
            'last_updated' => date('Y-m-d H:i:s')
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>