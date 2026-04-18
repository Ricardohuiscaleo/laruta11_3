<?php

declare(strict_types=1);

namespace App\Services\Compra;

use Illuminate\Support\Facades\Log;

class RekognitionService
{
    private string $bucket;

    public function __construct(private AwsSignatureService $signer)
    {
        $this->bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
    }

    /**
     * Run DetectLabels and DetectText in parallel using curl_multi.
     *
     * @return array{labels: array, texts: array, elapsed_ms: int}
     */
    public function perceive(string $s3Key): array
    {
        $start = microtime(true);
        $region = $this->signer->getRegion();
        $endpoint = "https://rekognition.{$region}.amazonaws.com";

        $labelsBody = json_encode([
            'Image' => ['S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key]],
            'MaxLabels' => 15,
            'MinConfidence' => 70,
        ]);

        $textBody = json_encode([
            'Image' => ['S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key]],
        ]);

        $chLabels = $this->signer->createSignedCurlHandle(
            $endpoint,
            $labelsBody,
            'rekognition',
            ['X-Amz-Target' => 'RekognitionService.DetectLabels'],
            10,
        );

        $chText = $this->signer->createSignedCurlHandle(
            $endpoint,
            $textBody,
            'rekognition',
            ['X-Amz-Target' => 'RekognitionService.DetectText'],
            10,
        );

        // Execute both in parallel
        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $chLabels);
        curl_multi_add_handle($mh, $chText);

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.1);
            }
        } while ($running > 0);

        $labelsResponse = curl_multi_getcontent($chLabels);
        $labelsHttpCode = curl_getinfo($chLabels, CURLINFO_HTTP_CODE);
        $textResponse = curl_multi_getcontent($chText);
        $textHttpCode = curl_getinfo($chText, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $chLabels);
        curl_multi_remove_handle($mh, $chText);
        curl_close($chLabels);
        curl_close($chText);
        curl_multi_close($mh);

        $labels = $this->parseLabelsResponse($labelsResponse, $labelsHttpCode);
        $texts = $this->parseTextResponse($textResponse, $textHttpCode);

        $elapsed = (int) round((microtime(true) - $start) * 1000);

        return [
            'labels' => $labels,
            'texts' => $texts,
            'elapsed_ms' => $elapsed,
        ];
    }

    /**
     * @return array<array{name: string, confidence: float, parents: array}>
     */
    public function detectLabels(string $s3Key, int $maxLabels = 15, float $minConfidence = 70): array
    {
        $region = $this->signer->getRegion();
        $endpoint = "https://rekognition.{$region}.amazonaws.com";

        $body = json_encode([
            'Image' => ['S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key]],
            'MaxLabels' => $maxLabels,
            'MinConfidence' => $minConfidence,
        ]);

        $response = $this->signer->signedPost(
            $endpoint,
            $body,
            'rekognition',
            ['X-Amz-Target' => 'RekognitionService.DetectLabels'],
            10,
        );

        return $this->parseLabelsResponse(
            $response !== null ? json_encode($response) : null,
            $response !== null ? 200 : 0,
        );
    }

    /**
     * @return array<array{text: string, confidence: float, type: string}>
     */
    public function detectText(string $s3Key): array
    {
        $region = $this->signer->getRegion();
        $endpoint = "https://rekognition.{$region}.amazonaws.com";

        $body = json_encode([
            'Image' => ['S3Object' => ['Bucket' => $this->bucket, 'Name' => $s3Key]],
        ]);

        $response = $this->signer->signedPost(
            $endpoint,
            $body,
            'rekognition',
            ['X-Amz-Target' => 'RekognitionService.DetectText'],
            10,
        );

        return $this->parseTextResponse(
            $response !== null ? json_encode($response) : null,
            $response !== null ? 200 : 0,
        );
    }

    /**
     * @return array<array{name: string, confidence: float, parents: array}>
     */
    private function parseLabelsResponse(?string $responseBody, int $httpCode): array
    {
        if ($httpCode !== 200 || !$responseBody) {
            Log::warning("[Rekognition] DetectLabels failed: HTTP {$httpCode}");
            return [];
        }

        try {
            $data = json_decode($responseBody, true);
            $labels = [];
            foreach ($data['Labels'] ?? [] as $label) {
                $labels[] = [
                    'name' => $label['Name'] ?? '',
                    'confidence' => round((float) ($label['Confidence'] ?? 0), 1),
                    'parents' => array_map(
                        fn(array $p): string => $p['Name'] ?? '',
                        $label['Parents'] ?? [],
                    ),
                ];
            }
            return $labels;
        } catch (\Exception $e) {
            Log::warning('[Rekognition] DetectLabels parse error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Filter to LINE type only (WORD entries are subsets of LINE).
     *
     * @return array<array{text: string, confidence: float, type: string}>
     */
    private function parseTextResponse(?string $responseBody, int $httpCode): array
    {
        if ($httpCode !== 200 || !$responseBody) {
            Log::warning("[Rekognition] DetectText failed: HTTP {$httpCode}");
            return [];
        }

        try {
            $data = json_decode($responseBody, true);
            $texts = [];
            foreach ($data['TextDetections'] ?? [] as $detection) {
                $type = $detection['Type'] ?? '';
                if ($type !== 'LINE') {
                    continue;
                }
                $texts[] = [
                    'text' => $detection['DetectedText'] ?? '',
                    'confidence' => round((float) ($detection['Confidence'] ?? 0), 1),
                    'type' => $type,
                ];
            }
            return $texts;
        } catch (\Exception $e) {
            Log::warning('[Rekognition] DetectText parse error: ' . $e->getMessage());
            return [];
        }
    }
}
