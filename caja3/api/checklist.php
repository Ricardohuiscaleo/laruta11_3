<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
        $conn = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

date_default_timezone_set('America/Santiago');

// Obtener action desde GET, POST o JSON body
$json_data = null;
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $json_data['action'] ?? null;
}

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'Action required']);
    exit;
}

// Router de actions
switch ($action) {
    case 'get_active':
        getActive($conn);
        break;
    case 'start':
        startChecklist($conn);
        break;
    case 'update_item':
        updateItem($conn);
        break;
    case 'complete':
        completeChecklist($conn);
        break;
    case 'get_history':
        getHistory($conn);
        break;
    case 'get_checklist_items':
        getChecklistItems($conn);
        break;
    case 'upload_photo':
        uploadPhoto($conn, $config);
        break;
    case 'delete_photo':
        deletePhoto($conn);
        break;
    case 'create_daily':
        createDaily($conn);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// ============= FUNCTIONS =============

function getActive($conn) {
    $type = $_GET['type'] ?? 'apertura';
    $current = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$current->format('H');
    
    // Determinar la fecha de búsqueda
    if ($type === 'cierre') {
        // Para cierre: si es antes de las 6 AM, buscar el checklist de HOY (que se programó ayer)
        // Si es después de las 6 AM, buscar el del día siguiente
        if ($currentHour < 6) {
            $date = $current->format('Y-m-d');
        } else {
            $tomorrow = clone $current;
            $tomorrow->modify('+1 day');
            $date = $tomorrow->format('Y-m-d');
        }
    } else {
        // Para apertura: siempre buscar el de hoy
        $date = $current->format('Y-m-d');
    }
    
    // Buscar checklist del día
    $stmt = $conn->prepare("
        SELECT * FROM checklists 
        WHERE type = ? AND scheduled_date = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$type, $date]);
    $checklist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checklist) {
        echo json_encode(['success' => false, 'error' => 'No hay checklist disponible para este tipo y fecha']);
        return;
    }
    
    // Actualizar status según tiempo
    $current_time = new DateTime('now', new DateTimeZone('America/Santiago'));
    $scheduled_datetime = new DateTime($checklist['scheduled_date'] . ' ' . $checklist['scheduled_time'], new DateTimeZone('America/Santiago'));
    
    $deadline = clone $scheduled_datetime;
    $deadline->modify('+1 hour');
    
    if ($checklist['status'] !== 'completed') {
        // Solo marcar como missed si pasó el deadline Y no tiene items completados
        if ($current_time > $deadline && $checklist['completed_items'] == 0) {
            $checklist['status'] = 'missed';
            $stmt = $conn->prepare("UPDATE checklists SET status = ? WHERE id = ?");
            $stmt->execute([$checklist['status'], $checklist['id']]);
        }
        // Si está dentro del horario programado, marcar como active
        elseif ($current_time >= $scheduled_datetime && $current_time <= $deadline) {
            $checklist['status'] = 'active';
            $stmt = $conn->prepare("UPDATE checklists SET status = ? WHERE id = ?");
            $stmt->execute([$checklist['status'], $checklist['id']]);
        }
        // Si es antes del horario, mantener como pending pero permitir iniciar
    }
    
    // Calcular tiempo restante
    $time_remaining = 0;
    if ($checklist['status'] === 'pending') {
        // Si está pendiente, mostrar tiempo hasta que se active
        if ($current_time < $scheduled_datetime) {
            $time_remaining = ($scheduled_datetime->getTimestamp() - $current_time->getTimestamp()) / 60;
        }
    } elseif ($checklist['status'] === 'active') {
        // Si está activo, mostrar tiempo hasta deadline
        if ($current_time < $deadline) {
            $time_remaining = ($deadline->getTimestamp() - $current_time->getTimestamp()) / 60;
        }
    }
    
    // Obtener items
    $stmt = $conn->prepare("SELECT * FROM checklist_items WHERE checklist_id = ? ORDER BY item_order");
    $stmt->execute([$checklist['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir is_completed y requires_photo a booleanos
    foreach ($items as &$item) {
        $item['is_completed'] = (bool)$item['is_completed'];
        $item['requires_photo'] = (bool)$item['requires_photo'];
    }
    unset($item);
    
    echo json_encode([
        'success' => true,
        'checklist' => array_merge($checklist, [
            'time_remaining_minutes' => max(0, round($time_remaining)),
            'items' => $items
        ])
    ]);
}

function startChecklist($conn) {
    global $json_data;
    $data = $json_data ?? json_decode(file_get_contents('php://input'), true);
    $checklist_id = $data['checklist_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $user_name = $data['user_name'] ?? null;
    
    if (!$checklist_id) {
        echo json_encode(['success' => false, 'error' => 'Checklist ID required']);
        return;
    }
    
    $stmt = $conn->prepare("
        UPDATE checklists 
        SET status = 'active', user_id = ?, user_name = ?
        WHERE id = ?
    ");
    $stmt->execute([$user_id, $user_name, $checklist_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Checklist iniciado'
    ]);
}

function updateItem($conn) {
    global $json_data;
    $data = $json_data ?? json_decode(file_get_contents('php://input'), true);
    $item_id = $data['item_id'] ?? null;
    $is_completed = $data['is_completed'] ?? false;
    $notes = $data['notes'] ?? null;
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'error' => 'Item ID required']);
        return;
    }
    
    // Actualizar item
    $completed_at = $is_completed ? date('Y-m-d H:i:s') : null;
    $stmt = $conn->prepare("
        UPDATE checklist_items 
        SET is_completed = ?, completed_at = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$is_completed, $completed_at, $notes, $item_id]);
    
    // Obtener checklist_id
    $stmt = $conn->prepare("SELECT checklist_id FROM checklist_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $checklist_id = $stmt->fetchColumn();
    
    // Recalcular progreso
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total, SUM(is_completed) as completed
        FROM checklist_items WHERE checklist_id = ?
    ");
    $stmt->execute([$checklist_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $percentage = ($progress['completed'] / $progress['total']) * 100;
    
    $stmt = $conn->prepare("
        UPDATE checklists 
        SET completed_items = ?, completion_percentage = ?
        WHERE id = ?
    ");
    $stmt->execute([$progress['completed'], $percentage, $checklist_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item actualizado',
        'progress' => [
            'completed_items' => (int)$progress['completed'],
            'total_items' => (int)$progress['total'],
            'percentage' => round($percentage, 2)
        ]
    ]);
}

function completeChecklist($conn) {
    global $json_data;
    $data = $json_data ?? json_decode(file_get_contents('php://input'), true);
    $checklist_id = $data['checklist_id'] ?? null;
    $notes = $data['notes'] ?? null;
    
    if (!$checklist_id) {
        echo json_encode(['success' => false, 'error' => 'Checklist ID required']);
        return;
    }
    
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE checklists 
        SET status = 'completed', completed_at = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$now, $notes, $checklist_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Checklist completado',
        'completed_at' => $now
    ]);
}

function getHistory($conn) {
    $type = $_GET['type'] ?? null;
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to = $_GET['to'] ?? date('Y-m-d', strtotime('+1 day'));
    $status = $_GET['status'] ?? null;
    
    $sql = "SELECT * FROM checklists WHERE scheduled_date BETWEEN ? AND ? AND total_items > 0";
    $params = [$from, $to];
    
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
    }
    
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY scheduled_date DESC, scheduled_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular stats
    $total = count($checklists);
    $completed = count(array_filter($checklists, fn($c) => $c['status'] === 'completed'));
    $missed = count(array_filter($checklists, fn($c) => $c['status'] === 'missed'));
    
    echo json_encode([
        'success' => true,
        'checklists' => $checklists,
        'stats' => [
            'total' => $total,
            'completed' => $completed,
            'missed' => $missed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ]
    ]);
}

function getChecklistItems($conn) {
    $checklist_id = $_GET['checklist_id'] ?? null;
    
    if (!$checklist_id) {
        echo json_encode(['success' => false, 'error' => 'Checklist ID required']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM checklist_items WHERE checklist_id = ? ORDER BY item_order");
    $stmt->execute([$checklist_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir is_completed y requires_photo a booleanos
    foreach ($items as &$item) {
        $item['is_completed'] = (bool)$item['is_completed'];
        $item['requires_photo'] = (bool)$item['requires_photo'];
    }
    unset($item);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
}

function uploadPhoto($conn, $config) {
    $item_id = $_POST['item_id'] ?? null;
    
    if (!$item_id || !isset($_FILES['photo'])) {
        echo json_encode(['success' => false, 'error' => 'Item ID and photo required']);
        return;
    }
    
    try {
        require_once __DIR__ . '/S3Manager.php';
        
        $s3Manager = new S3Manager();
        $file = $_FILES['photo'];
        $fileName = 'checklist/' . date('Y/m') . '/' . uniqid() . '_' . basename($file['name']);
        
        $imageUrl = $s3Manager->uploadFile($file, $fileName);
        
        $stmt = $conn->prepare("UPDATE checklist_items SET photo_url = ? WHERE id = ?");
        $stmt->execute([$imageUrl, $item_id]);
        
        echo json_encode([
            'success' => true,
            'photo_url' => $imageUrl,
            'message' => 'Foto subida a AWS S3'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Error subiendo foto: ' . $e->getMessage()]);
    }
}

function deletePhoto($conn) {
    global $json_data;
    $data = $json_data ?? json_decode(file_get_contents('php://input'), true);
    $item_id = $data['item_id'] ?? null;
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'error' => 'Item ID required']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE checklist_items SET photo_url = NULL WHERE id = ?");
    $stmt->execute([$item_id]);
    
    echo json_encode(['success' => true, 'message' => 'Foto eliminada']);
}

function createDaily($conn) {
    $current = new DateTime('now', new DateTimeZone('America/Santiago'));
    $date = $current->format('Y-m-d');
    $created = [];
    
    foreach (['apertura', 'cierre'] as $type) {
        // Para cierre, la fecha programada es el día siguiente
        $checklist_date = $date;
        if ($type === 'cierre') {
            $checklist_date = (new DateTime($date))->modify('+1 day')->format('Y-m-d');
        }
        
        // Verificar si ya existe
        $stmt = $conn->prepare("SELECT id FROM checklists WHERE type = ? AND scheduled_date = ? AND total_items > 0");
        $stmt->execute([$type, $checklist_date]);
        
        if ($stmt->fetch()) {
            continue;
        }
        
        $scheduled_time = $type === 'apertura' ? '18:00:00' : '00:45:00';
        
        // Obtener templates
        $templates = $conn->prepare("SELECT * FROM checklist_templates WHERE type = ? AND active = 1 ORDER BY item_order");
        $templates->execute([$type]);
        $items = $templates->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($items) === 0) {
            continue; // Skip si no hay templates
        }
        
        // Crear checklist
        $stmt = $conn->prepare("
            INSERT INTO checklists (type, scheduled_time, scheduled_date, total_items, completed_items, status, started_at)
            VALUES (?, ?, ?, ?, 0, 'pending', CONCAT(?, ' ', ?))
        ");
        $stmt->execute([$type, $scheduled_time, $checklist_date, count($items), $checklist_date, $scheduled_time]);
        $checklist_id = $conn->lastInsertId();
        
        // Crear items
        foreach ($items as $item) {
            $stmt = $conn->prepare("
                INSERT INTO checklist_items (checklist_id, item_order, description, requires_photo)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$checklist_id, $item['item_order'], $item['description'], $item['requires_photo']]);
        }
        
        $created[] = [
            'type' => $type,
            'scheduled_date' => $date,
            'scheduled_time' => $scheduled_time,
            'total_items' => count($items),
            'note' => $type === 'cierre' ? 'Se ejecutará el día siguiente a las 00:45' : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'created' => $created
    ]);
}
?>
