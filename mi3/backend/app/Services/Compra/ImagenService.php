<?php

namespace App\Services\Compra;

use App\Models\Compra;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImagenService
{
    /**
     * Upload image to S3 under temp prefix.
     * Compresses if > 500KB using GD.
     *
     * @return array{tempUrl: string, tempKey: string}
     */
    public function uploadTemp(UploadedFile $file): array
    {
        $uuid = Str::uuid()->toString();
        $tempKey = "compras/temp/{$uuid}.jpg";

        $contents = file_get_contents($file->getRealPath());

        // Compress if > 500KB
        if ($file->getSize() > 500 * 1024) {
            $contents = $this->compress($file->getRealPath());
        }

        Storage::disk('s3')->put($tempKey, $contents);

        // Use direct S3 URL (bucket is configured for public read via bucket policy)
        $bucket = config('filesystems.disks.s3.bucket', 'laruta11-images');
        $tempUrl = "https://{$bucket}.s3.amazonaws.com/{$tempKey}";

        return [
            'tempUrl' => $tempUrl,
            'tempKey' => $tempKey,
        ];
    }

    /**
     * Move image from temp to definitivo path.
     *
     * @return string Final public URL
     */
    public function moverADefinitivo(string $tempKey, int $compraId): string
    {
        $timestamp = time();
        $finalKey = "compras/respaldo_{$compraId}_{$timestamp}.jpg";

        // Copy then delete (S3 doesn't have native move)
        Storage::disk('s3')->copy($tempKey, $finalKey);
        Storage::disk('s3')->delete($tempKey);

        $bucket = config('filesystems.disks.s3.bucket', 'laruta11-images');
        return "https://{$bucket}.s3.amazonaws.com/{$finalKey}";
    }

    /**
     * Move all temp images to definitivo and update compra.imagen_respaldo.
     *
     * @return array Final URLs
     */
    public function asociarImagenes(int $compraId, array $tempKeys): array
    {
        $finalUrls = [];

        foreach ($tempKeys as $tempKey) {
            // Add small delay between moves to ensure unique timestamps
            if (count($finalUrls) > 0) {
                usleep(1000); // 1ms
            }
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
     * Compress image using GD library.
     * Returns compressed image contents as string.
     */
    private function compress(string $path): string
    {
        $imageInfo = getimagesize($path);

        if (!$imageInfo) {
            return file_get_contents($path);
        }

        $type = $imageInfo[2];
        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default        => null,
        };

        if (!$source) {
            return file_get_contents($path);
        }

        // Resize if too large (max 1200x800)
        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = 1200;
        $maxHeight = 800;

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1.0);

        if ($ratio < 1.0) {
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        // Output as JPEG with quality 60
        ob_start();
        imagejpeg($source, null, 60);
        $contents = ob_get_clean();
        imagedestroy($source);

        return $contents;
    }
}
