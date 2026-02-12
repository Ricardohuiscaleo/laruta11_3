<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: image/png');

try {
    $url = 'https://ruta11app.agenterag.com/jobs/';
    
    // Cargar imagen base
    $baseImage = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . '/Trabaja con Nosotros.png');
    if (!$baseImage) {
        throw new Exception('No se pudo cargar la imagen base');
    }
    
    // Obtener dimensiones de la imagen base
    $baseWidth = imagesx($baseImage);
    $baseHeight = imagesy($baseImage);
    
    // Generar QR usando API externa
    $qrSize = 300; // Tamaño del QR
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$qrSize}x{$qrSize}&format=png&margin=10&data=" . urlencode($url);
    
    // Descargar QR
    $qrData = file_get_contents($qrUrl);
    if (!$qrData) {
        throw new Exception('No se pudo generar el QR');
    }
    
    // Crear imagen del QR
    $qrImage = imagecreatefromstring($qrData);
    if (!$qrImage) {
        throw new Exception('No se pudo procesar el QR');
    }
    
    // Calcular posición para centrar el QR en el área blanca
    // Basado en la plantilla, el área blanca está aproximadamente en el centro
    $qrX = ($baseWidth - $qrSize) / 2 + 12;
    $qrY = ($baseHeight - $qrSize) / 2 - 8; // Ajustar un poco hacia arriba
    
    // Superponer el QR en la imagen base
    imagecopy($baseImage, $qrImage, $qrX, $qrY, 0, 0, $qrSize, $qrSize);
    
    // Limpiar memoria
    imagedestroy($qrImage);
    
    // Enviar imagen
    imagepng($baseImage);
    imagedestroy($baseImage);
    
} catch (Exception $e) {
    // En caso de error, enviar imagen base sin QR
    $baseImage = imagecreatefrompng($_SERVER['DOCUMENT_ROOT'] . '/Trabaja con Nosotros.png');
    if ($baseImage) {
        imagepng($baseImage);
        imagedestroy($baseImage);
    } else {
        // Crear imagen de error
        $errorImage = imagecreate(400, 300);
        $bg = imagecolorallocate($errorImage, 255, 255, 255);
        $text = imagecolorallocate($errorImage, 255, 0, 0);
        imagestring($errorImage, 5, 50, 140, 'Error generando poster', $text);
        imagepng($errorImage);
        imagedestroy($errorImage);
    }
}
?>