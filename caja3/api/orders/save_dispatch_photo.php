<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = null;
foreach ([__DIR__ . '/../../config.php', __DIR__ . '/../../../config.php'] as $p) {
    if (file_exists($p)) {
        $config = require_once $p;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    if (!isset($_FILES['photo']) || !isset($_POST['order_id'])) {
        throw new Exception('photo y order_id requeridos');
    }

    $s3Manager = null;
    foreach ([__DIR__ . '/../S3Manager.php', __DIR__ . '/../../S3Manager.php'] as $p) {
        if (file_exists($p)) {
            require_once $p;
            $s3Manager = new S3Manager();
            break;
        }
    }
    if (!$s3Manager)
        throw new Exception('S3Manager no encontrado');

    $orderId = intval($_POST['order_id']);
    $fileName = 'despacho/pedido_' . $orderId . '_' . time() . '.jpg';
    $url = $s3Manager->uploadFile($_FILES['photo'], $fileName);

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    // Get current photos
    $stmt = $pdo->prepare("SELECT dispatch_photo_url FROM tuu_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    $photoUrl = $current['dispatch_photo_url'] ?? '';

    $photos = [];
    if (!empty($photoUrl)) {
        // Try to decode JSON
        $decoded = json_decode($photoUrl, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $photos = $decoded;
        }
        else {
            // Legacy single string URL
            $photos = [$photoUrl];
        }
    }

    // Add new photo
    $photos[] = $url;
    $jsonPhotos = json_encode($photos);

    $pdo->prepare("UPDATE tuu_orders SET dispatch_photo_url = ? WHERE id = ?")->execute([$jsonPhotos, $orderId]);

    // Build response
    $response = ['success' => true, 'url' => $url, 'all_photos' => $photos];

    // ── Verification IA (only when photo_type is present) ──
    $photoType = $_POST['photo_type'] ?? null;

    if ($photoType) {
        $verificationFallback = [
            'aprobado' => true,
            'puntaje' => 0,
            'feedback' => '⏳ Verificación no disponible',
        ];

        try {
            // Read uploaded image from S3 and convert to base64
            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                throw new Exception('No se pudo leer imagen desde S3 URL');
            }
            $imageBase64 = base64_encode($imageData);

            // Parse order items
            $orderItemsJson = $_POST['order_items'] ?? '[]';
            $orderItems = json_decode($orderItemsJson, true);
            if (!is_array($orderItems)) {
                $orderItems = [];
            }

            // Call GeminiService for verification
            require_once __DIR__ . '/../GeminiService.php';
            $gemini = new GeminiService();
            $verification = $gemini->verificarFotoDespacho($imageBase64, $orderItems, $photoType);

            // Insert result into dispatch_photo_feedback
            $userRetook = ($_POST['user_retook'] ?? 'false') === 'true' ? 1 : 0;

            $insertStmt = $pdo->prepare(
                "INSERT INTO dispatch_photo_feedback (order_id, photo_type, photo_url, ai_aprobado, ai_puntaje, ai_feedback, user_retook)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([
                $orderId,
                $photoType,
                $url,
                $verification['aprobado'] ? 1 : 0,
                $verification['puntaje'],
                $verification['feedback'],
                $userRetook,
            ]);

            $response['verification'] = $verification;
        } catch (\Throwable $e) {
            error_log("[save_dispatch_photo] Verification error: " . $e->getMessage());

            // Insert fallback into dispatch_photo_feedback
            try {
                $userRetook = ($_POST['user_retook'] ?? 'false') === 'true' ? 1 : 0;
                $insertStmt = $pdo->prepare(
                    "INSERT INTO dispatch_photo_feedback (order_id, photo_type, photo_url, ai_aprobado, ai_puntaje, ai_feedback, user_retook)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $insertStmt->execute([
                    $orderId,
                    $photoType,
                    $url,
                    1,
                    0,
                    $verificationFallback['feedback'],
                    $userRetook,
                ]);
            } catch (\Throwable $dbErr) {
                error_log("[save_dispatch_photo] Failed to insert fallback feedback: " . $dbErr->getMessage());
            }

            $response['verification'] = $verificationFallback;
        }
    }

    echo json_encode($response);
}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
