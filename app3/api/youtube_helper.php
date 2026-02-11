<?php
/**
 * Función robusta para extraer ID de video de YouTube y generar URL embed limpia
 * Maneja todos los formatos: watch, live, embed, youtu.be
 */
function get_clean_youtube_embed_url($input_url) {
    if (empty($input_url)) {
        return '';
    }
    
    // Limpiar la URL de espacios y caracteres extraños
    $url = trim($input_url);
    
    // Patrones para extraer el ID del video
    $patterns = [
        // URL de embed: https://www.youtube.com/embed/VIDEO_ID?params
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        
        // URL de watch: https://www.youtube.com/watch?v=VIDEO_ID
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
        
        // URL de live: https://www.youtube.com/live/VIDEO_ID?params
        '/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
        
        // URL corta: https://youtu.be/VIDEO_ID
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        
        // Variantes con www o sin www
        '/(?:www\.)?youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        '/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})/'
    ];
    
    // Intentar extraer el ID con cada patrón
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            $video_id = $matches[1];
            
            // Validar que el ID tenga exactamente 11 caracteres
            if (strlen($video_id) === 11) {
                // Retornar URL embed limpia
                return "https://www.youtube.com/embed/" . $video_id;
            }
        }
    }
    
    // Si no se pudo extraer el ID, retornar cadena vacía
    return '';
}

// Ejemplo de uso
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    // URLs de ejemplo para testing
    $test_urls = [
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtu.be/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1',
        'https://www.youtube.com/live/dQw4w9WgXcQ?si=abc123',
        'https://youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
        'URL inválida'
    ];
    
    echo "<h3>Testing YouTube URL Cleaner</h3>\n";
    foreach ($test_urls as $url) {
        $clean_url = get_clean_youtube_embed_url($url);
        echo "<p><strong>Input:</strong> " . htmlspecialchars($url) . "<br>";
        echo "<strong>Output:</strong> " . ($clean_url ? htmlspecialchars($clean_url) : 'INVALID') . "</p>\n";
    }
}
?>