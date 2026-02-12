<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$query = $_GET['query'] ?? 'route desert 11ch';

try {
    $url = "https://api.unsplash.com/search/photos?query=" . urlencode($query) . "&per_page=1&orientation=portrait";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Client-ID ' . $config['unsplash_access_key']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if (!empty($data['results'])) {
            $image = $data['results'][0];
            echo json_encode([
                'success' => true,
                'image_url' => $image['urls']['full'],
                'image_regular' => $image['urls']['regular'],
                'photographer' => $image['user']['name'],
                'photographer_url' => $image['user']['links']['html']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontraron imágenes']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error API Unsplash: ' . $httpCode]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>