<?php
// Configuración de cabeceras para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Establecer la zona horaria a Chile
date_default_timezone_set('America/Santiago');

// Obtener la fecha y hora actual del servidor
$fecha_actual = date('Y-m-d H:i:s');

// Formatear la fecha para mostrar (solo fecha, sin hora)
$fecha_formateada = date('l, j \d\e F \d\e Y');

// Devolver la respuesta
echo json_encode([
    "fecha" => $fecha_actual,
    "fecha_formateada" => $fecha_formateada,
    "timestamp" => time() * 1000 // Timestamp en milisegundos para JavaScript
]);
?>