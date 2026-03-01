<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/**
 * Google Business Performance Statistics Proxy
 * Fetches private metrics using Service Account OAuth2
 */

// Configuration
$serviceAccountPath = __DIR__ . '/google_service_account.json';
$locationId = 'locations/5102592294647214981';

$cacheFile = __DIR__ . '/cache_google_performance.json';
$cacheTime = 172800; // 48 hours

// Check cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    $cacheData = file_get_contents($cacheFile);
    if ($cacheData) {
        echo $cacheData;
        exit;
    }
}

// Check if credentials exist
if (!file_exists($serviceAccountPath)) {
    echo json_encode(['success' => false, 'error' => 'Missing service account JSON file.']);
    exit;
}

try {
    // Generate OAuth2 Access Token
    $accessToken = getAccessToken($serviceAccountPath);

    // Fetch Metrics for the last 30 days
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-30 days'));

    $url = "https://businessprofileperformance.googleapis.com/v1/{$locationId}:fetchMultiDailyMetricsTimeSeries";

    $postData = [
        "dailyMetrics" => [
            "BUSINESS_IMPRESSIONS_DESKTOP_G_MAPS",
            "BUSINESS_IMPRESSIONS_MOBILE_G_MAPS",
            "CALL_CLICKS",
            "WEBSITE_CLICKS"
        ],
        "dailyRange" => [
            "startDate" => [
                "year" => (int)date('Y', strtotime($startDate)),
                "month" => (int)date('m', strtotime($startDate)),
                "day" => (int)date('d', strtotime($startDate))
            ],
            "endDate" => [
                "year" => (int)date('Y', strtotime($endDate)),
                "month" => (int)date('m', strtotime($endDate)),
                "day" => (int)date('d', strtotime($endDate))
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Google API Error (HTTP {$httpCode}): " . $response);
    }

    $data = json_decode($response, true);

    // Aggregate Data
    $totals = [
        'views' => 0,
        'calls' => 0,
        'clicks' => 0
    ];

    if (isset($data['multiDailyMetricTimeSeries'])) {
        foreach ($data['multiDailyMetricTimeSeries'] as $serie) {
            $metricName = $serie['dailyMetric'] ?? '';
            $values = $serie['dailyMetricTimeSeries']['dateValues'] ?? [];

            foreach ($values as $dv) {
                $val = (int)($dv['value'] ?? 0);
                if (strpos($metricName, 'BUSINESS_IMPRESSIONS') !== false) {
                    $totals['views'] += $val;
                }
                elseif ($metricName === 'CALL_CLICKS') {
                    $totals['calls'] += $val;
                }
                elseif ($metricName === 'WEBSITE_CLICKS') {
                    $totals['clicks'] += $val;
                }
            }
        }
    }

    $result = [
        'success' => true,
        'period' => 'last_30_days',
        'data' => $totals
    ];

    $finalJson = json_encode($result);
    file_put_contents($cacheFile, $finalJson);
    echo $finalJson;

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getAccessToken($jsonPath)
{
    $keyData = json_decode(file_get_contents($jsonPath), true);
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $now = time();
    $payload = base64_encode(json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/business.manage',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]));
    $signatureInput = $header . "." . $payload;
    openssl_sign($signatureInput, $signature, $keyData['private_key'], 'SHA256');
    $jwt = $signatureInput . "." . base64_encode($signature);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);
    return $data['access_token'] ?? null;
}
?>