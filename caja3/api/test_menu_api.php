<?php
header('Content-Type: application/json');

// Probar el API de menú
$response = file_get_contents('http://localhost/api/get_menu_products.php');
$data = json_decode($response, true);

if ($data && $data['success']) {
    echo "✅ API funcionando correctamente\n\n";
    
    // Verificar productos en churrascos
    if (isset($data['menuData']['churrascos'])) {
        echo "🥪 CHURRASCOS:\n";
        foreach ($data['menuData']['churrascos'] as $subcategory => $products) {
            echo "  📂 {$subcategory}: " . count($products) . " productos\n";
            foreach ($products as $product) {
                echo "    - {$product['name']} (subcategory_name: " . ($product['subcategory_name'] ?? 'null') . ")\n";
            }
        }
    }
} else {
    echo "❌ Error en API: " . ($data['error'] ?? 'Unknown error') . "\n";
}
?>