<?php
// Headers CORS para permitir requests desde Astro
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class S3Manager {
    private $config;
    
    public function __construct() {
        // Try multiple possible paths
        $paths = [
            __DIR__ . '/../config.php',
            dirname(dirname(dirname($_SERVER['DOCUMENT_ROOT']))) . '/config.php',
            dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/config.php',
            dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/../config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/../../config.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->config = require $path;
                // Debug: verificar que las credenciales AWS estén cargadas
                if (empty($this->config['aws_access_key_id']) || empty($this->config['aws_secret_access_key'])) {
                    throw new Exception('Credenciales AWS no configuradas correctamente');
                }
                return;
            }
        }
        
        throw new Exception('Config file not found in any expected location');
    }
    
    private function parseMemoryLimit($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        switch($last) {
            case 'g': $limit *= 1024;
            case 'm': $limit *= 1024;
            case 'k': $limit *= 1024;
        }
        return $limit;
    }
    
    private function compressImage($sourcePath, $quality = 85, $maxWidth = 1920, $maxHeight = 1080) {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            throw new Exception('Extensión GD no disponible para compresión');
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new Exception('No se pudo leer la información de la imagen');
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Check memory limit for large images
        $memoryNeeded = $width * $height * 4; // 4 bytes per pixel
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit != -1) {
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            if ($memoryNeeded > $memoryLimitBytes * 0.6) { // More conservative memory usage
                throw new Exception('Imagen demasiado grande para procesar en memoria');
            }
        }
        
        // Additional check for very large images
        if ($width * $height > 25000000) { // 25 megapixels max
            throw new Exception('Imagen demasiado grande (máximo 25 megapixeles)');
        }
        
        // Create image resource based on type
        $source = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = @imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = @imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $source = @imagecreatefromwebp($sourcePath);
                } else {
                    throw new Exception('Soporte WebP no disponible');
                }
                break;
            default:
                throw new Exception('Tipo de imagen no soportado para compresión');
        }
        
        if (!$source) {
            throw new Exception('No se pudo crear el recurso de imagen desde el archivo');
        }
        
        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Create new image
        $compressed = @imagecreatetruecolor($newWidth, $newHeight);
        if (!$compressed) {
            imagedestroy($source);
            throw new Exception('No se pudo crear la imagen comprimida');
        }
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
            $transparent = imagecolorallocatealpha($compressed, 255, 255, 255, 127);
            imagefilledrectangle($compressed, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        $resizeResult = @imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        if (!$resizeResult) {
            imagedestroy($source);
            imagedestroy($compressed);
            throw new Exception('Error al redimensionar la imagen');
        }
        
        // Save compressed image to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'compressed_');
        if (!$tempFile) {
            imagedestroy($source);
            imagedestroy($compressed);
            throw new Exception('No se pudo crear archivo temporal');
        }
        
        $saveResult = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $saveResult = @imagejpeg($compressed, $tempFile, $quality);
                break;
            case IMAGETYPE_PNG:
                $saveResult = @imagepng($compressed, $tempFile, 9 - intval($quality / 10));
                break;
            case IMAGETYPE_GIF:
                $saveResult = @imagegif($compressed, $tempFile);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $saveResult = @imagewebp($compressed, $tempFile, $quality);
                } else {
                    $saveResult = false;
                }
                break;
        }
        
        if (!$saveResult) {
            imagedestroy($source);
            imagedestroy($compressed);
            unlink($tempFile);
            throw new Exception('Error al guardar la imagen comprimida');
        }
        
        imagedestroy($source);
        imagedestroy($compressed);
        
        return $tempFile;
    }
    
    public function uploadImage($file, $customName = null, $folder = 'menu', $compress = true) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No se recibió un archivo válido');
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP');
        }
        
        $originalSize = filesize($file['tmp_name']);
        $filePath = $file['tmp_name'];
        
        // Compress image if requested and file is large (but not too large)
        if ($compress && $originalSize > 500000 && $originalSize < 8388608) { // Only compress between 500KB and 8MB
            try {
                $compressedPath = $this->compressImage($filePath, 70, 1400, 1000); // More aggressive compression
                $filePath = $compressedPath;
            } catch (Exception $e) {
                // If compression fails, continue with original file
                error_log('Compression failed for file size ' . $originalSize . ': ' . $e->getMessage());
            }
        }
        
        // Generate filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($customName) {
            $filename = $customName;
            if (!preg_match('/\.' . $extension . '$/i', $filename)) {
                $filename .= '.' . $extension;
            }
        } else {
            $filename = time() . '_' . $file['name'];
        }
        
        $key = $folder . '/' . $filename;
        
        // Read file content
        $content = file_get_contents($filePath);
        $finalSize = strlen($content);
        
        // Clean up temporary file if compression was used
        if ($compress && $originalSize > 500000 && isset($compressedPath)) {
            unlink($compressedPath);
        }
        $contentType = $mimeType;
        $contentLength = strlen($content);
        
        // AWS S3 PUT request (more reliable for large files)
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->config['s3_bucket'] . '/' . $key;
        $stringToSign = "PUT\n\n{$contentType}\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->config['aws_secret_access_key'], true));
        
        $url = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/' . $key;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Date: ' . $date,
            'Authorization: AWS ' . $this->config['aws_access_key_id'] . ':' . $signature,
            'Content-Type: ' . $contentType,
            'Content-Length: ' . $contentLength
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 minutes timeout for large files
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1024); // 1KB/s minimum speed
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 120); // for 2 minutes
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorMsg = 'Error al subir a S3: HTTP ' . $httpCode;
            if ($error) $errorMsg .= ' - ' . $error;
            if ($result) $errorMsg .= ' - ' . substr($result, 0, 200);
            throw new Exception($errorMsg);
        }
        
        $finalUrl = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/' . $key;
        
        return [
            'key' => $key,
            'url' => $finalUrl,
            'filename' => $filename,
            'original_size' => $originalSize,
            'final_size' => $finalSize,
            'compression_ratio' => $originalSize > 0 ? round((($originalSize - $finalSize) / $originalSize) * 100, 1) : 0
        ];
    }
    
    public function listImages($folder = 'menu') {

        
        // Try authenticated request
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->config['s3_bucket'] . '/';
        $stringToSign = "GET\n\n\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->config['aws_secret_access_key'], true));
        
        $url = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/?prefix=' . $folder . '/';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Date: ' . $date,
            'Authorization: AWS ' . $this->config['aws_access_key_id'] . ':' . $signature
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        

        
        $images = [];
        if ($httpCode === 200 && $result) {
            $xml = simplexml_load_string($result);
            if ($xml && isset($xml->Contents)) {
                foreach ($xml->Contents as $content) {
                    $key = (string)$content->Key;
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $key)) {
                        $images[] = [
                            'key' => $key,
                            'name' => basename($key),
                            'url' => 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/' . $key,
                            'size' => (int)$content->Size,
                            'modified' => (string)$content->LastModified
                        ];
                    }
                }
            }
        }
        
        return $images;
    }
    
    public function renameImage($oldKey, $newName, $folder = 'menu') {
        // Generar nueva key
        $extension = pathinfo($oldKey, PATHINFO_EXTENSION);
        if (!preg_match('/\.' . $extension . '$/i', $newName)) {
            $newName .= '.' . $extension;
        }
        $newKey = $folder . '/' . $newName;
        
        // Paso 1: Copiar archivo con nuevo nombre
        $date = gmdate('D, d M Y H:i:s T');
        $sourceResource = '/' . $this->config['s3_bucket'] . '/' . $oldKey;
        $destResource = '/' . $this->config['s3_bucket'] . '/' . $newKey;
        
        $stringToSign = "PUT\n\n\n{$date}\nx-amz-copy-source:{$sourceResource}\n{$destResource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->config['aws_secret_access_key'], true));
        
        $copyUrl = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/' . $newKey;
        
        $ch = curl_init($copyUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Date: ' . $date,
            'Authorization: AWS ' . $this->config['aws_access_key_id'] . ':' . $signature,
            'x-amz-copy-source: ' . $sourceResource
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Error al copiar archivo: HTTP ' . $httpCode);
        }
        
        // Paso 2: Eliminar archivo original
        $this->deleteImage($oldKey);
        
        return [
            'old_key' => $oldKey,
            'new_key' => $newKey,
            'new_url' => 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/' . $newKey
        ];
    }
    
    public function deleteImage($key) {
        $date = gmdate('D, d M Y H:i:s T');
        $resource = '/' . $this->config['s3_bucket'] . '/?delete';
        $deleteXml = '<?xml version="1.0" encoding="UTF-8"?><Delete><Object><Key>' . htmlspecialchars($key) . '</Key></Object></Delete>';
        $contentMd5 = base64_encode(md5($deleteXml, true));
        
        $stringToSign = "POST\n{$contentMd5}\ntext/xml\n{$date}\n{$resource}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->config['aws_secret_access_key'], true));
        
        $url = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/?delete';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $deleteXml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Date: ' . $date,
            'Authorization: AWS ' . $this->config['aws_access_key_id'] . ':' . $signature,
            'Content-Type: text/xml',
            'Content-MD5: ' . $contentMd5
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $errorMsg = 'Error al eliminar imagen: HTTP ' . $httpCode;
            if ($error) $errorMsg .= ' - ' . $error;
            if ($result) $errorMsg .= ' - ' . substr($result, 0, 200);
            throw new Exception($errorMsg);
        }
        
        return true;
    }
}

try {
    $s3 = new S3Manager();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error de configuración: ' . $e->getMessage()]);
    exit;
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'list':
                try {
                    $result = $s3->listImages();
                    echo json_encode(['success' => true, 'images' => $result, 'debug' => 'List successful']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => 'List failed']);
                }
                break;
                
            case 'upload':
                try {
                    if (!isset($_FILES['image'])) {
                        throw new Exception('No se recibió ningún archivo');
                    }
                    
                    $customName = $_POST['custom_name'] ?? null;
                    $compress = !isset($_POST['no_compress']) || $_POST['no_compress'] !== 'true';
                    $result = $s3->uploadImage($_FILES['image'], $customName, 'menu', $compress);
                    
                    $message = 'Imagen subida exitosamente';
                    if ($result['compression_ratio'] > 0) {
                        $message .= sprintf(' (comprimida %s%%)', $result['compression_ratio']);
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => $message,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
                
            case 'delete':
                try {
                    if (!isset($_POST['key'])) {
                        throw new Exception('Falta el parámetro key');
                    }
                    
                    $result = $s3->deleteImage($_POST['key']);
                    echo json_encode(['success' => true, 'message' => 'Imagen eliminada exitosamente']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
                
            case 'rename':
                try {
                    if (!isset($_POST['old_key']) || !isset($_POST['new_name'])) {
                        throw new Exception('Faltan parámetros: old_key y new_name');
                    }
                    
                    $result = $s3->renameImage($_POST['old_key'], $_POST['new_name']);
                    echo json_encode(['success' => true, 'message' => 'Imagen renombrada exitosamente', 'data' => $result]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                break;
        }
        exit;
    }
}
?>