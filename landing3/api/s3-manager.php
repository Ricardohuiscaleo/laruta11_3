<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;

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
    private $s3;
    
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
                if (empty($this->config['aws_access_key_id']) || empty($this->config['aws_secret_access_key'])) {
                    throw new Exception('Credenciales AWS no configuradas correctamente');
                }
                return;
            }
        }
        
        throw new Exception('Config file not found in any expected location');
    }
    
    private function getS3() {
        if (!$this->s3) {
            $config = [
                'version' => 'latest',
                'region'  => $this->config['aws_region'],
                'credentials' => [
                    'key'    => $this->config['aws_access_key_id'],
                    'secret' => $this->config['aws_secret_access_key'],
                ],
            ];
            if (!empty($this->config['aws_endpoint'])) {
                $config['endpoint'] = $this->config['aws_endpoint'];
                $config['use_path_style_endpoint'] = $this->config['aws_use_path_style'];
            }
            $this->s3 = new S3Client($config);
        }
        return $this->s3;
    }
    
    private function getBucket() {
        return $this->config['s3_bucket'] ?: 'laruta11-images';
    }
    
    private function getPublicUrl($key) {
        $baseUrl = $this->config['s3_url'];
        return rtrim($baseUrl, '/') . '/' . ltrim($key, '/');
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
        if (!extension_loaded('gd')) {
            throw new Exception('Extensión GD no disponible para compresión');
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new Exception('No se pudo leer la información de la imagen');
        }
        
        list($width, $height, $type) = $imageInfo;
        
        $memoryNeeded = $width * $height * 4;
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit != -1) {
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            if ($memoryNeeded > $memoryLimitBytes * 0.6) {
                throw new Exception('Imagen demasiado grande para procesar en memoria');
            }
        }
        
        if ($width * $height > 25000000) {
            throw new Exception('Imagen demasiado grande (máximo 25 megapixeles)');
        }
        
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
        
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        $compressed = @imagecreatetruecolor($newWidth, $newHeight);
        if (!$compressed) {
            imagedestroy($source);
            throw new Exception('No se pudo crear la imagen comprimida');
        }
        
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
            $transparent = imagecolorallocatealpha($compressed, 255, 255, 255, 127);
            imagefilledrectangle($compressed, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        $resizeResult = @imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        if (!$resizeResult) {
            imagedestroy($source);
            imagedestroy($compressed);
            throw new Exception('Error al redimensionar la imagen');
        }
        
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
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP');
        }
        
        $originalSize = filesize($file['tmp_name']);
        $filePath = $file['tmp_name'];
        
        if ($compress && $originalSize > 500000 && $originalSize < 8388608) {
            try {
                $compressedPath = $this->compressImage($filePath, 70, 1400, 1000);
                $filePath = $compressedPath;
            } catch (Exception $e) {
                error_log('Compression failed for file size ' . $originalSize . ': ' . $e->getMessage());
            }
        }
        
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
        
        $s3 = $this->getS3();
        $result = $s3->putObject([
            'Bucket'      => $this->getBucket(),
            'Key'         => $key,
            'SourceFile'  => $filePath,
            'ContentType' => $mimeType,
        ]);
        
        if ($compress && $originalSize > 500000 && isset($compressedPath)) {
            unlink($compressedPath);
        }
        
        $finalSize = filesize($filePath);
        
        return [
            'key' => $key,
            'url' => $this->getPublicUrl($key),
            'filename' => $filename,
            'original_size' => $originalSize,
            'final_size' => $finalSize,
            'compression_ratio' => $originalSize > 0 ? round((($originalSize - $finalSize) / $originalSize) * 100, 1) : 0
        ];
    }
    
    public function listImages($folder = 'menu') {
        $s3 = $this->getS3();
        $results = $s3->listObjectsV2([
            'Bucket' => $this->getBucket(),
            'Prefix' => $folder . '/',
        ]);
        
        $images = [];
        if (isset($results['Contents'])) {
            foreach ($results['Contents'] as $content) {
                $key = (string)$content['Key'];
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $key)) {
                    $images[] = [
                        'key' => $key,
                        'name' => basename($key),
                        'url' => $this->getPublicUrl($key),
                        'size' => (int)$content['Size'],
                        'modified' => (string)$content['LastModified']
                    ];
                }
            }
        }
        
        return $images;
    }
    
    public function renameImage($oldKey, $newName, $folder = 'menu') {
        $extension = pathinfo($oldKey, PATHINFO_EXTENSION);
        if (!preg_match('/\.' . $extension . '$/i', $newName)) {
            $newName .= '.' . $extension;
        }
        $newKey = $folder . '/' . $newName;
        
        $s3 = $this->getS3();
        $bucket = $this->getBucket();
        
        $s3->copyObject([
            'Bucket'     => $bucket,
            'Key'        => $newKey,
            'CopySource' => "{$bucket}/{$oldKey}",
        ]);
        
        $s3->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $oldKey,
        ]);
        
        return [
            'old_key' => $oldKey,
            'new_key' => $newKey,
            'new_url' => $this->getPublicUrl($newKey),
        ];
    }
    
    public function deleteImage($key) {
        $s3 = $this->getS3();
        $s3->deleteObject([
            'Bucket' => $this->getBucket(),
            'Key'    => $key,
        ]);
        
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