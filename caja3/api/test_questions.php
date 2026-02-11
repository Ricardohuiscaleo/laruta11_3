<?php
echo "<h2>Test API Questions</h2>";

$roles = ['planchero', 'cajero'];

foreach ($roles as $role) {
    echo "<h3>Role: $role</h3>";
    
    $url = "https://app.laruta11.cl/api/get_questions.php?role=$role";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<hr>";
}
?>