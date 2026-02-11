<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

echo "<h1>Test de API para Dashboard</h1>";

// Función para probar una API
function testAPI($url, $name) {
    echo "<h2>Probando $name</h2>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>Status Code: $httpCode</p>";
    
    if ($httpCode == 200) {
        // Intentar decodificar como JSON
        $json = json_decode($response, true);
        if ($json !== null) {
            echo "<p style='color:green'>✓ Respuesta JSON válida</p>";
            echo "<details>";
            echo "<summary>Ver primeros 500 caracteres</summary>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "...</pre>";
            echo "</details>";
        } else {
            echo "<p style='color:red'>✗ Respuesta NO es JSON válido</p>";
            echo "<details>";
            echo "<summary>Ver primeros 500 caracteres</summary>";
            echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "...</pre>";
            echo "</details>";
        }
    } else {
        echo "<p style='color:red'>✗ Error en la solicitud</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
    
    echo "<hr>";
}

// Probar las APIs necesarias para el dashboard
$baseUrl = "http://localhost:80/api";

testAPI("$baseUrl/get_productos.php", "Productos");
testAPI("$baseUrl/get_ingredientes.php", "Ingredientes");
testAPI("$baseUrl/get_recetas.php", "Recetas (formato completo)");
testAPI("$baseUrl/get_recetas_simple.php", "Recetas (formato simple)");

echo "<p>Pruebas completadas.</p>";
?>