<?php
$urlProj = "http://localhost/api/get_smart_projection_shifts.php";
$urlCash = "http://localhost/api/personal/get_monthly_cashflow.php";

echo "Testing $urlProj...\n";
$dataProj = @file_get_contents($urlProj);
if($dataProj){
    $jsonProj = json_decode($dataProj, true);
    echo "Dashboard Sales Real: " . ($jsonProj['data']['totalReal'] ?? 'N/A') . "\n";
} else {
    echo "Failed to fetch $urlProj\n";
}

echo "Testing $urlCash...\n";
$dataCash = @file_get_contents($urlCash);
if($dataCash){
    $jsonCash = json_decode($dataCash, true);
    echo "Cashflow API Sales: " . ($jsonCash['data']['ventas'] ?? 'N/A') . "\n";
    echo "Cashflow API Sueldos: " . ($jsonCash['data']['sueldos'] ?? 'N/A') . "\n";
} else {
    echo "Failed to fetch $urlCash\n";
}
?>
