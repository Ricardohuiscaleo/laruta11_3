<?php

declare(strict_types=1);

namespace App\Services\Compra;

use Illuminate\Support\Facades\Log;

class AwsSignatureService
{
    private string $accessKey;
    private string $secretKey;
    private string $region;

    public function __construct()
    {
        $this->accessKey = config('services.aws.key') ?? env('AWS_ACCESS_KEY_ID', '');
        $this->secretKey = config('services.aws.secret') ?? env('AWS_SECRET_ACCESS_KEY', '');
        $this->region = config('services.aws.region') ?? env('AWS_DEFAULT_REGION', 'us-east-1');
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function hasCredentials(): bool
    {
        return $this->accessKey !== '' && $this->secretKey !== '';
    }

    /**
     * Make a SigV4-signed POST request to an AWS service.
     *
     * @param string $url        Full endpoint URL
     * @param string $jsonBody   JSON-encoded request body
     * @param string $service    AWS service name (bedrock, rekognition, etc.)
     * @param array  $extraHeaders Additional headers (e.g. X-Amz-Target for Rekognition)
     * @param int    $timeout    Request timeout in seconds
     * @return array|null Decoded JSON response or null on failure
     */
    public function signedPost(
        string $url,
        string $jsonBody,
        string $service,
        array $extraHeaders = [],
        int $timeout = 20,
    ): ?array {
        if (!$this->hasCredentials()) {
            throw new \RuntimeException('AWS credentials not configured');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $rawPath = parse_url($url, PHP_URL_PATH) ?? '/';
        $pathSegments = explode('/', $rawPath);
        $encodedPath = implode('/', array_map(fn(string $s): string => rawurlencode($s), $pathSegments));

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $headers = [
            'content-type' => 'application/json',
            'host' => $host,
            'x-amz-date' => $now,
        ];
        foreach ($extraHeaders as $k => $v) {
            $headers[strtolower($k)] = $v;
        }
        ksort($headers);

        $signedHeaderNames = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $payloadHash = hash('sha256', $jsonBody);

        $canonicalRequest = implode("\n", [
            'POST',
            $encodedPath,
            '',
            $canonicalHeaders,
            $signedHeaderNames,
            $payloadHash,
        ]);

        $credentialScope = "{$date}/{$this->region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secretKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaderNames}, Signature={$signature}";

        // Build curl headers
        $curlHeaders = [
            'Content-Type: application/json',
            'X-Amz-Date: ' . $now,
            'Authorization: ' . $authorization,
        ];
        foreach ($extraHeaders as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        return $this->executeCurl($url, $jsonBody, $curlHeaders, $timeout, $service);
    }

    /**
     * Create a curl handle for parallel execution (curl_multi).
     */
    public function createSignedCurlHandle(
        string $url,
        string $jsonBody,
        string $service,
        array $extraHeaders = [],
        int $timeout = 10,
    ): \CurlHandle {
        if (!$this->hasCredentials()) {
            throw new \RuntimeException('AWS credentials not configured');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $rawPath = parse_url($url, PHP_URL_PATH) ?? '/';
        $pathSegments = explode('/', $rawPath);
        $encodedPath = implode('/', array_map(fn(string $s): string => rawurlencode($s), $pathSegments));

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $headers = [
            'content-type' => 'application/json',
            'host' => $host,
            'x-amz-date' => $now,
        ];
        foreach ($extraHeaders as $k => $v) {
            $headers[strtolower($k)] = $v;
        }
        ksort($headers);

        $signedHeaderNames = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $payloadHash = hash('sha256', $jsonBody);
        $canonicalRequest = implode("\n", ['POST', $encodedPath, '', $canonicalHeaders, $signedHeaderNames, $payloadHash]);
        $credentialScope = "{$date}/{$this->region}/{$service}/aws4_request";
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $now, $credentialScope, hash('sha256', $canonicalRequest)]);

        $kDate = hash_hmac('sha256', $date, "AWS4{$this->secretKey}", true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaderNames}, Signature={$signature}";

        $curlHeaders = [
            'Content-Type: application/json',
            'X-Amz-Date: ' . $now,
            'Authorization: ' . $authorization,
        ];
        foreach ($extraHeaders as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $curlHeaders,
        ]);

        return $ch;
    }

    private function executeCurl(string $url, string $jsonBody, array $curlHeaders, int $timeout, string $service): ?array
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $curlHeaders,
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                if (str_contains($curlError, 'timed out')) {
                    throw new \RuntimeException("AWS {$service} request timed out");
                }
                Log::error("[AwsSignature] Curl error ({$service}): {$curlError}");
                return null;
            }

            if ($httpCode === 200) {
                return json_decode($responseBody, true);
            }

            $errorBody = substr($responseBody, 0, 500);
            Log::error("[AwsSignature] {$service} error: HTTP {$httpCode} {$errorBody}");
            return ['__error' => true, '__http_code' => $httpCode, '__message' => $errorBody, '__service' => $service];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("[AwsSignature] {$service} request error: " . $e->getMessage());
            return null;
        }
    }
}
