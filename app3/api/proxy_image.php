<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_GET['url'])) {
    http_response_code(400);
    exit('URL requerida');
}

$imageUrl = $_GET['url'];

// Validar que sea una URL de imagen válida
if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('URL inválida');
}

// Obtener la imagen
$imageData = @file_get_contents($imageUrl);

if ($imageData === false) {
    http_response_code(404);
    exit('Imagen no encontrada');
}

// Detectar tipo de contenido
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->buffer($imageData);

// Validar que sea una imagen
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
    http_response_code(400);
    exit('No es una imagen válida');
}

// Enviar headers apropiados
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($imageData));
header('Cache-Control: public, max-age=3600');

echo $imageData;
?>