<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/**
 * Google Reviews Proxy with Caching
 * Fetches reviews for La Ruta 11 from Google Places API
 */

// Configuration
$apiKey = 'AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A';
$placeId = 'ChIJx1qbNL6pWpERZwHfDe5eN1o';
$cacheFile = __DIR__ . '/cache_google_reviews.json';
$cacheTime = 86400; // 24 hours

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $cacheData = file_get_contents($cacheFile);
    if ($cacheData) {
        echo $cacheData;
        exit;
    }
}

// Fetch from Google
$fields = 'name,rating,reviews,user_ratings_total';
$url = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$placeId}&fields={$fields}&key={$apiKey}&language=es";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch from Google API']);
    exit;
}

$data = json_decode($response, true);

if (isset($data['status']) && $data['status'] === 'OK') {
    $result = [
        'success' => true,
        'name' => $data['result']['name'] ?? '',
        'rating' => $data['result']['rating'] ?? 0,
        'total_ratings' => $data['result']['user_ratings_total'] ?? 0,
        'reviews' => []
    ];

    if (isset($data['result']['reviews'])) {
        foreach ($data['result']['reviews'] as $review) {
            $result['reviews'][] = [
                'author' => $review['author_name'],
                'rating' => $review['rating'],
                'text' => $review['text'],
                'time_description' => $review['relative_time_description'],
                'profile_photo' => $review['profile_photo_url'] ?? null
            ];
        }
    }

    $finalJson = json_encode($result);
    file_put_contents($cacheFile, $finalJson);
    echo $finalJson;
}
else {
    echo json_encode([
        'success' => false,
        'error' => $data['error_message'] ?? ($data['status'] ?? 'Unknown error from Google')
    ]);
}
?>
