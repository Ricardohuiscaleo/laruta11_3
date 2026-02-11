<?php
// Debug callback - ver qué datos llegan de TUU
error_log("=== TUU CALLBACK DEBUG ===");
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));
error_log("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
error_log("QUERY_STRING: " . $_SERVER['QUERY_STRING']);

// Mostrar en pantalla también
echo "<h2>TUU Callback Debug</h2>";
echo "<h3>GET Parameters:</h3>";
echo "<pre>" . json_encode($_GET, JSON_PRETTY_PRINT) . "</pre>";
echo "<h3>POST Parameters:</h3>";
echo "<pre>" . json_encode($_POST, JSON_PRETTY_PRINT) . "</pre>";
echo "<h3>Full URL:</h3>";
echo "<pre>" . $_SERVER['REQUEST_URI'] . "</pre>";
?>