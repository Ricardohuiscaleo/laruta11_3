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

// Fetch from Google Places API (New)
$url = "https://places.googleapis.com/v1/places/{$placeId}";
$fields = 'reviews,rating,userRatingCount,displayName';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-Goog-Api-Key: {$apiKey}",
    "X-Goog-FieldMask: {$fields}"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['success' => false, 'error' => "Failed to fetch from Google API (New). Code: {$httpCode}"]);
    exit;
}

$data = json_decode($response, true);

if (isset($data['name']) || isset($data['reviews'])) {
    $result = [
        'success' => true,
        'name' => $data['displayName']['text'] ?? '',
        'rating' => $data['rating'] ?? 0,
        'total_ratings' => $data['userRatingCount'] ?? 0,
        'reviews' => []
    ];

    if (isset($data['reviews'])) {
        foreach ($data['reviews'] as $review) {
            $result['reviews'][] = [
                'author' => $review['authorAttribution']['displayName'] ?? 'Anónimo',
                'rating' => $review['rating'] ?? 0,
                'text' => $review['text']['text'] ?? '',
                'time_description' => $review['relativePublishTimeDescription'] ?? '',
                'profile_photo' => $review['authorAttribution']['photoUri'] ?? null
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