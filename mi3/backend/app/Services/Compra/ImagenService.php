<?php

namespace App\Services\Compra;

use App\Models\Compra;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Servicio de imágenes para compras.
 * Usa S3 PUT directo con SigV4 (mismo approach que caja3/S3Manager).
 * No usa Flysystem porque no sube correctamente al bucket.
 */
class ImagenService
{
    private string $bucket;
    private string $region;
    private string $key;
    private string $secret;

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
        $this->region = config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->key = config('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID', ''));
        $this->secret = config('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY', ''));
    }

    /**
     * Upload image to S3 under temp prefix.
     * Compresses if > 500KB using GD. Returns metadata for pipeline UI.
     */
    public function uploadTemp(UploadedFile $file): array
    {
        $uuid = Str::uuid()->toString();
        $tempKey = "compras/temp/{$uuid}.jpg";

        $originalSizeKb = (int) round($file->getSize() / 1024);
        $originalRes = @getimagesize($file->getRealPath());
        $originalWidth = $originalRes ? $originalRes[0] : 0;
        $originalHeight = $originalRes ? $originalRes[1] : 0;

        $contents = file_get_contents($file->getRealPath());
        $wasCompressed = false;

        if ($file->getSize() > 500 * 1024) {
            $contents = $this->compress($file->getRealPath());
            $wasCompressed = true;
        }

        $finalSizeKb = (int) round(strlen($contents) / 1024);

        // Get final resolution from compressed image
        $finalWidth = $originalWidth;
        $finalHeight = $originalHeight;
        if ($wasCompressed) {
            $tmpImg = @imagecreatefromstring($contents);
            if ($tmpImg) {
                $finalWidth = imagesx($tmpImg);
                $finalHeight = imagesy($tmpImg);
                imagedestroy($tmpImg);
            }
        }

        $this->putObject($tempKey, $contents, 'image/jpeg');

        $tempUrl = "https://{$this->bucket}.s3.amazonaws.com/{$tempKey}";
        $reductionPct = $originalSizeKb > 0 ? (int) round((1 - $finalSizeKb / $originalSizeKb) * 100) : 0;

        return [
            'tempUrl' => $tempUrl,
            'tempKey' => $tempKey,
            'originalSizeKb' => $originalSizeKb,
            'originalRes' => "{$originalWidth}x{$originalHeight}",
            'finalSizeKb' => $finalSizeKb,
            'finalRes' => "{$finalWidth}x{$finalHeight}",
            'reductionPct' => max(0, $reductionPct),
            'compressed' => $wasCompressed,
        ];
    }

    /**
     * Move image from temp to definitivo path.
     */
    public function moverADefinitivo(string $tempKey, int $compraId): string
    {
        $uniqueId = time() . '_' . bin2hex(random_bytes(4));
        $finalKey = "compras/respaldo_{$compraId}_{$uniqueId}.jpg";

        // Download temp, re-upload to final, delete temp
        $contents = $this->getObject($tempKey);
        if ($contents) {
            $this->putObject($finalKey, $contents, 'image/jpeg');
            $this->deleteObject($tempKey);
        }

        return "https://{$this->bucket}.s3.amazonaws.com/{$finalKey}";
    }

    /**
     * Move all temp images to definitivo and update compra.imagen_respaldo.
     */
    public function asociarImagenes(int $compraId, array $tempKeys): array
    {
        $finalUrls = [];

        foreach ($tempKeys as $tempKey) {
            if (count($finalUrls) > 0) usleep(1000);
            $finalUrls[] = $this->moverADefinitivo($tempKey, $compraId);
        }

        if (!empty($finalUrls)) {
            $compra = Compra::findOrFail($compraId);
            $existing = $compra->imagen_respaldo ?? [];
            $compra->imagen_respaldo = array_merge($existing, $finalUrls);
            $compra->save();
        }

        return $finalUrls;
    }

    /**
     * PUT object to S3 using direct SigV4 signing.
     * Bypasses Flysystem which doesn't upload correctly.
     */
    private function putObject(string $objectKey, string $body, string $contentType = 'application/octet-stream'): bool
    {
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $url = "https://{$host}/{$objectKey}";
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = [
            'content-type' => $contentType,
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $canonicalRequest = "PUT\n/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secret}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "Content-Type: {$contentType}",
                "X-Amz-Date: {$now}",
                "X-Amz-Content-Sha256: {$payloadHash}",
                "Authorization: {$auth}",
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            \Illuminate\Support\Facades\Log::error("[ImagenService] S3 PUT failed: HTTP {$code} for {$objectKey}. Response: " . substr($resp, 0, 300));
            throw new \RuntimeException("Error subiendo imagen a S3: HTTP {$code}");
        }

        return true;
    }

    /**
     * GET object from S3 using direct SigV4 signing.
     */
    private function getObject(string $objectKey): ?string
    {
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $url = "https://{$host}/{$objectKey}";
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', '');

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $canonicalRequest = "GET\n/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secret}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "X-Amz-Date: {$now}",
                "X-Amz-Content-Sha256: {$payloadHash}",
                "Authorization: {$auth}",
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 200 ? $body : null;
    }

    /**
     * DELETE object from S3.
     */
    private function deleteObject(string $objectKey): bool
    {
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $url = "https://{$host}/{$objectKey}";
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', '');

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $canonicalRequest = "DELETE\n/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secret}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                "X-Amz-Date: {$now}",
                "X-Amz-Content-Sha256: {$payloadHash}",
                "Authorization: {$auth}",
            ],
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code === 204 || $code === 200;
    }

    /**
     * Compress image using GD library.
     */
    private function compress(string $path): string
    {
        $imageInfo = getimagesize($path);
        if (!$imageInfo) return file_get_contents($path);

        $type = $imageInfo[2];
        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => null,
        };

        if (!$source) return file_get_contents($path);

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = min(1200 / $width, 800 / $height, 1.0);

        if ($ratio < 1.0) {
            $newW = (int) ($width * $ratio);
            $newH = (int) ($height * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagejpeg($source, null, 60);
        $contents = ob_get_clean();
        imagedestroy($source);

        return $contents;
    }
}
