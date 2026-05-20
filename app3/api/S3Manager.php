<?php
/**
 * S3Manager — Upload files to Cloudflare R2 or AWS S3
 * 
 * R2: uses shell_exec + curl with AWS Signature V4 (avoids PHP curl bugs)
 * S3: uses POST with policy (legacy)
 */
class S3Manager {
    private $config;
    
    public function __construct($config = null) {
        if ($config) {
            $this->config = $config;
        } else {
            $paths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
            foreach ($paths as $path) {
                if (file_exists($path)) { $this->config = require $path; return; }
            }
            throw new Exception('Config file not found');
        }
    }
    
    private function compressImage($sourcePath, $quality = 85, $maxWidth = 1200, $maxHeight = 800) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) throw new Exception('No se pudo leer la información de la imagen');
        
        list($width, $height, $type) = $imageInfo;
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG:  $source = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_GIF:  $source = imagecreatefromgif($sourcePath); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($sourcePath); break;
            default: throw new Exception('Tipo de imagen no soportado');
        }
        
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        $compressed = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($compressed, false);
            imagesavealpha($compressed, true);
            imagefilledrectangle($compressed, 0, 0, $newWidth, $newHeight, imagecolorallocatealpha($compressed, 255, 255, 255, 127));
        }
        imagecopyresampled($compressed, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'compressed_');
        switch ($type) {
            case IMAGETYPE_JPEG: imagejpeg($compressed, $tempFile, $quality); break;
            case IMAGETYPE_PNG:  imagepng($compressed, $tempFile, 9 - intval($quality / 10)); break;
            case IMAGETYPE_GIF:  imagegif($compressed, $tempFile); break;
            case IMAGETYPE_WEBP: imagewebp($compressed, $tempFile, $quality); break;
        }
        imagedestroy($source);
        imagedestroy($compressed);
        return $tempFile;
    }
    
    /**
     * Build AWS Signature V4 Authorization header
     */
    private function signRequest($method, $url, $region, $service, $payloadHash, $contentType, $amzDate) {
        $accessKey = $this->config['aws_access_key_id'];
        $secretKey = $this->config['aws_secret_access_key'];
        $host = parse_url($url, PHP_URL_HOST);
        $uri = parse_url($url, PHP_URL_PATH);
        $date = substr($amzDate, 0, 8);
        
        $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest = "{$method}\n{$uri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        return "{$algorithm} Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
    }
    
    public function uploadFile($file, $key, $compress = true) {
        error_log('S3Manager uploadFile: ' . json_encode([
            'name' => $file['name'] ?? '?', 'size' => $file['size'] ?? 0, 'key' => $key
        ]));
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No se recibió un archivo válido');
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP');
        }
        
        $filePath = $file['tmp_name'];
        $originalSize = filesize($filePath);
        
        if ($compress && $originalSize > 500000) {
            $filePath = $this->compressImage($filePath);
        }
        
        $endpoint = $this->config['s3_endpoint'] ?? null;
        
        if ($endpoint) {
            // === Cloudflare R2: PUT via shell curl with AWS Signature V4 ===
            $url = rtrim($endpoint, '/') . '/' . $this->config['s3_bucket'] . '/' . $key;
            $region = $this->config['s3_region'] ?? 'auto';
            $amzDate = gmdate('Ymd\THis\Z');
            $payloadHash = hash('sha256', file_get_contents($filePath));
            $authorization = $this->signRequest('PUT', $url, $region, 's3', $payloadHash, $mimeType, $amzDate);
            
            // shell_exec curl: most reliable for PUT + custom headers in PHP
            $cmd = sprintf(
                "curl -s -w '\n%%{http_code}' -X PUT %s -H 'Content-Type: %s' -H 'x-amz-content-sha256: %s' -H 'x-amz-date: %s' -H 'Authorization: %s' --data-binary @%s 2>&1",
                escapeshellarg($url),
                escapeshellarg($mimeType),
                escapeshellarg($payloadHash),
                escapeshellarg($amzDate),
                escapeshellarg($authorization),
                escapeshellarg($filePath)
            );
            
            $output = shell_exec($cmd);
            $lines = explode("\n", trim($output));
            $httpCode = (int)array_pop($lines);
            $body = implode("\n", $lines);
            
            error_log("S3Manager R2: HTTP {$httpCode} — {$url}");
            
            if ($httpCode !== 200 && $httpCode !== 204) {
                throw new Exception("Error HTTP {$httpCode} subiendo a R2. " . substr($body, 0, 300));
            }
        } else {
            // === AWS S3: POST with policy (legacy) ===
            $url = 'https://' . $this->config['s3_bucket'] . '.s3.amazonaws.com/';
            
            $policy = base64_encode(json_encode([
                'expiration' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600),
                'conditions' => [
                    ['bucket' => $this->config['s3_bucket']],
                    ['key' => $key],
                    ['Content-Type' => $mimeType],
                    ['content-length-range', 0, 10485760]
                ]
            ]));
            
            $signature = base64_encode(hash_hmac('sha1', $policy, $this->config['aws_secret_access_key'], true));
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'key' => $key,
                    'AWSAccessKeyId' => $this->config['aws_access_key_id'],
                    'policy' => $policy,
                    'signature' => $signature,
                    'Content-Type' => $mimeType,
                    'file' => new CURLFile($filePath, $mimeType, basename($file['name']))
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
            ]);
            
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) throw new Exception("Error cURL: {$error}");
            if ($httpCode !== 200 && $httpCode !== 204) {
                throw new Exception("Error HTTP {$httpCode} subiendo a S3. " . substr($result, 0, 300));
            }
        }
        
        if ($compress && $originalSize > 500000 && $filePath !== $file['tmp_name']) {
            @unlink($filePath);
        }
        
        return $this->config['s3_url'] . '/' . $key;
    }
    
    public function deleteFile($key) {
        $endpoint = $this->config['s3_endpoint'] ?? null;
        if ($endpoint) {
            $url = rtrim($endpoint, '/') . '/' . $this->config['s3_bucket'] . '/' . $key;
        } else {
            $url = "https://{$this->config['s3_bucket']}.s3.{$this->config['s3_region']}.amazonaws.com/{$key}";
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($endpoint) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->config['aws_access_key_id'] . ':' . $this->config['aws_secret_access_key']);
        }
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 204 || $httpCode === 200;
    }
}
