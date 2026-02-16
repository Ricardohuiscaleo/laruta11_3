<?php
class S3Manager {
    private $config;
    
    public function __construct($config = null) {
        if ($config) {
            $this->config = $config;
        } else {
            // Buscar config.php en múltiples niveles
            $paths = [
                '../config.php',
                '../../config.php', 
                '../../../config.php',
                '../../../../config.php'
            ];
            
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $this->config = require $path;
                    return;
                }
            }
            
            throw new Exception('Config file not found');
        }
    }
    
    private function compressImage($sourcePath, $quality = 85, $maxWidth = 1200, $maxHeight = 800) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new Exception('No se pudo leer la información de la imagen');
        }
        
        list($width, $height, $type) = $imageInfo;
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new Exception('Tipo de imagen no soportado');
        }
        
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        $compressed = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
            $transparent = imagecolorallocatealpha($compressed, 255, 255, 255, 127);
            imagefilledrectangle($compressed, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'compressed_');
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($compressed, $tempFile, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($compressed, $tempFile, 9 - intval($quality / 10));
                break;
            case IMAGETYPE_GIF:
                imagegif($compressed, $tempFile);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($compressed, $tempFile, $quality);
                break;
        }
        
        imagedestroy($source);
        imagedestroy($compressed);
        
        return $tempFile;
    }
    
    public function uploadFile($file, $key, $compress = true) {
        // Debug: ver qué llega
        error_log('S3Manager uploadFile - file array: ' . json_encode([
            'name' => $file['name'] ?? 'not set',
            'type' => $file['type'] ?? 'not set',
            'tmp_name' => $file['tmp_name'] ?? 'not set',
            'error' => $file['error'] ?? 'not set',
            'size' => $file['size'] ?? 'not set'
        ]));
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No se recibió un archivo válido. tmp_name=' . ($file['tmp_name'] ?? 'not set') . ', is_uploaded=' . (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name']) ? 'yes' : 'no'));
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
        
        // Compress if file > 500KB
        if ($compress && $originalSize > 500000) {
            $compressedPath = $this->compressImage($filePath);
            $filePath = $compressedPath;
        }
        
        $contentType = $mimeType;
        
        // AWS S3 POST request with policy
        $policy = base64_encode(json_encode([
            'expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
            'conditions' => [
                ['bucket' => $this->config['s3_bucket']],
                ['key' => $key],
                ['Content-Type' => $contentType],
                ['content-length-range', 0, 10485760] // 10MB max
            ]
        ]));
        
        $signature = base64_encode(hash_hmac('sha1', $policy, $this->config['aws_secret_access_key'], true));
        
        $url = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/';
        
        // Create multipart form data
        $postFields = [
            'key' => $key,
            'AWSAccessKeyId' => $this->config['aws_access_key_id'],
            'policy' => $policy,
            'signature' => $signature,
            'Content-Type' => $contentType,
            'file' => new CURLFile($filePath, $contentType, basename($file['name']))
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Debug info
        $debugInfo = [
            'url' => $url,
            'http_code' => $httpCode,
            'curl_error' => $error,
            'total_time' => $curlInfo['total_time'] ?? 0,
            'connect_time' => $curlInfo['connect_time'] ?? 0
        ];
        
        if ($result === false) {
            throw new Exception("Error cURL: {$error}. Debug: " . json_encode($debugInfo));
        }
        
        if ($error) {
            throw new Exception("Error cURL: {$error}. HTTP: {$httpCode}");
        }
        
        if ($httpCode === 0) {
            throw new Exception("No se pudo conectar a S3. Verifique conectividad de red. Debug: " . json_encode($debugInfo));
        }
        
        if ($httpCode !== 204 && $httpCode !== 200) {
            $errorMsg = "Error HTTP {$httpCode} subiendo a S3";
            if ($result) $errorMsg .= '. Respuesta: ' . substr($result, 0, 300);
            throw new Exception($errorMsg);
        }
        
        // Clean up compressed file
        if ($compress && $originalSize > 500000 && isset($compressedPath)) {
            unlink($compressedPath);
        }
        
        return $this->config['s3_url'] . '/' . $key;
    }
    
    public function deleteFile($key) {
        // Simple DELETE request
        $url = "https://{$this->config['s3_bucket']}.s3.{$this->config['s3_region']}.amazonaws.com/{$key}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 204;
    }
}
?>