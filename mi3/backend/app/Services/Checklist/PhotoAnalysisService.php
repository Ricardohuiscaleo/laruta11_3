<?php

namespace App\Services\Checklist;

use App\Models\ChecklistItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PhotoAnalysisService
{
    /**
     * Prompts for each context (photo type × checklist type).
     */
    protected const PROMPTS = [
        'interior_apertura' => <<<'PROMPT'
Eres el supervisor de calidad de un food truck de hamburguesas llamado "La Ruta 11". Analiza esta foto del INTERIOR del food truck tomada al momento de APERTURA (antes de abrir al público).

Evalúa estos puntos específicos:
1. LIMPIEZA: ¿Las superficies de trabajo (plancha, mesones, tablas) están limpias y listas? ¿El piso está limpio?
2. ORDEN: ¿Los ingredientes, aderezos y utensilios están organizados en su lugar? ¿Hay cosas fuera de lugar?
3. EQUIPOS: ¿Se ve la plancha encendida/lista? ¿El televisor con la carta está visible? ¿Las máquinas TUU están en posición?
4. PROBLEMAS: ¿Hay basura visible, derrames, equipos dañados, o algo que necesite atención inmediata?

Responde en JSON con este formato exacto:
{"score": <0-100>, "observations": "<texto en español, máximo 3 oraciones. Sé específico: menciona qué está bien ✅ y qué necesita mejora ⚠️. Si hay un problema urgente, empieza con 🚨.>"}
PROMPT,
        'exterior_apertura' => <<<'PROMPT'
Eres el supervisor de calidad de un food truck de hamburguesas llamado "La Ruta 11". Analiza esta foto del EXTERIOR del food truck tomada al momento de APERTURA (antes de abrir al público).

Evalúa estos puntos específicos:
1. MONTAJE: ¿Las mesas, sillas y basureros están colocados correctamente? ¿La vitrina de aderezos está afuera?
2. SEÑALIZACIÓN: ¿Se ve el letrero/branding de La Ruta 11? ¿El televisor exterior muestra la carta?
3. ZONA DE CLIENTES: ¿El área de comedor está limpia y lista para recibir clientes?
4. PROBLEMAS: ¿Hay basura en el suelo, muebles dañados, o algo que dé mala imagen al cliente?

Responde en JSON con este formato exacto:
{"score": <0-100>, "observations": "<texto en español, máximo 3 oraciones. Sé específico: menciona qué está bien ✅ y qué necesita mejora ⚠️. Si hay un problema urgente, empieza con 🚨.>"}
PROMPT,
        'interior_cierre' => <<<'PROMPT'
Eres el supervisor de calidad de un food truck de hamburguesas llamado "La Ruta 11". Analiza esta foto del INTERIOR del food truck tomada al momento de CIERRE (después de cerrar al público).

Evalúa estos puntos específicos:
1. LIMPIEZA: ¿La plancha, mesones y superficies están limpias y desengrasadas? ¿El piso está limpio?
2. ALMACENAMIENTO: ¿Los ingredientes están guardados? ¿Los aderezos y salsas están refrigerados/tapados?
3. EQUIPOS: ¿La plancha está apagada? ¿Los equipos eléctricos están desconectados? ¿Todo está en su lugar?
4. PROBLEMAS: ¿Hay comida sin guardar, grasa acumulada, equipos encendidos, o riesgos de seguridad?

Responde en JSON con este formato exacto:
{"score": <0-100>, "observations": "<texto en español, máximo 3 oraciones. Sé específico: menciona qué está bien ✅ y qué necesita mejora ⚠️. Si hay un problema urgente, empieza con 🚨.>"}
PROMPT,
        'exterior_cierre' => <<<'PROMPT'
Eres el supervisor de calidad de un food truck de hamburguesas llamado "La Ruta 11". Analiza esta foto del EXTERIOR del food truck tomada al momento de CIERRE (después de cerrar al público).

Evalúa estos puntos específicos:
1. GUARDADO: ¿Las mesas, sillas, basureros y vitrina están guardados dentro del food truck o asegurados?
2. LIMPIEZA: ¿El área exterior está limpia, sin basura ni restos de comida en el suelo?
3. SEGURIDAD: ¿El food truck se ve correctamente cerrado? ¿No hay equipos o productos afuera expuestos?
4. PROBLEMAS: ¿Hay muebles olvidados afuera, basura acumulada, o algo que represente un riesgo?

Responde en JSON con este formato exacto:
{"score": <0-100>, "observations": "<texto en español, máximo 3 oraciones. Sé específico: menciona qué está bien ✅ y qué necesita mejora ⚠️. Si hay un problema urgente, empieza con 🚨.>"}
PROMPT,
    ];

    /**
     * Get the prompt for a given context.
     */
    public function getPromptForContext(string $contexto): string
    {
        if (!isset(self::PROMPTS[$contexto])) {
            throw new \InvalidArgumentException("Contexto inválido: {$contexto}. Debe ser uno de: " . implode(', ', array_keys(self::PROMPTS)));
        }

        return self::PROMPTS[$contexto];
    }

    /**
     * Get all valid contexts.
     *
     * @return string[]
     */
    public static function getValidContexts(): array
    {
        return array_keys(self::PROMPTS);
    }

    /**
     * Upload a photo to S3 bucket laruta11-images.
     * Uses direct PUT with SigV4 (same as ImagenService) because Flysystem doesn't work.
     *
     * @return string The public URL of the uploaded photo
     */
    public function subirFotoS3(UploadedFile $foto): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $filename = uniqid('checklist_') . '.' . ($foto->getClientOriginalExtension() ?: 'jpg');
        $objectKey = "checklist/{$year}/{$month}/{$filename}";

        $contents = file_get_contents($foto->getRealPath());
        $contentType = $foto->getMimeType() ?: 'image/jpeg';

        $bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
        $region = config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $key = config('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID', ''));
        $secret = config('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY', ''));

        $host = "{$bucket}.s3.{$region}.amazonaws.com";
        $url = "https://{$host}/{$objectKey}";
        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $contents);

        $headers = [
            'content-type' => $contentType,
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) $canonicalHeaders .= "{$k}:{$v}\n";

        $canonicalRequest = "PUT\n/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$date}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $date, "AWS4{$secret}", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $contents,
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
            Log::error("[PhotoAnalysisService] S3 PUT failed: HTTP {$code} for {$objectKey}");
            throw new \RuntimeException('Error al subir la foto a S3');
        }

        return "https://{$bucket}.s3.amazonaws.com/{$objectKey}";
    }

    /**
     * Send a photo to Amazon Nova Pro (Bedrock) for AI analysis.
     * Timeout: 15 seconds.
     *
     * @return array{score: int, observations: string}
     */
    public function analizarConIA(string $s3Url, string $contexto): array
    {
        $prompt = $this->getPromptForContext($contexto);

        try {
            $client = $this->createBedrockClient();

            // Download image and encode as base64 for Bedrock
            $imageBytes = file_get_contents($s3Url);
            if ($imageBytes === false) {
                throw new \RuntimeException("No se pudo descargar la imagen: {$s3Url}");
            }
            $imageB64 = base64_encode($imageBytes);

            $response = $client->invokeModel([
                'modelId' => 'amazon.nova-pro-v1:0',
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'body' => json_encode([
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'image' => [
                                        'format' => 'jpeg',
                                        'source' => [
                                            'bytes' => $imageB64,
                                        ],
                                    ],
                                ],
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                    'inferenceConfig' => [
                        'max_new_tokens' => 800,
                        'temperature' => 0.2,
                    ],
                ]),
            ]);

            $body = json_decode($response['body'], true);
            $text = $body['output']['message']['content'][0]['text'] ?? '';

            return $this->parseAIResponse($text);
        } catch (\Aws\Exception\AwsException $e) {
            Log::error('Bedrock AI analysis error', [
                'url' => $s3Url,
                'context' => $contexto,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Upload photo to S3, mark item as completed, return immediately.
     * AI analysis runs but doesn't block the response.
     */
    public function subirYAnalizar(UploadedFile $foto, int $itemId, string $contexto): array
    {
        // Step 1: Upload to S3 (fast, ~1s)
        $url = $this->subirFotoS3($foto);

        // Step 2: Save photo URL + mark item completed immediately
        $item = ChecklistItem::findOrFail($itemId);
        $item->update([
            'photo_url' => $url,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Update checklist progress
        $checklist = $item->checklist;
        $completedCount = $checklist->items()->where('is_completed', true)->count();
        $totalCount = $checklist->total_items;
        $percentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0;
        $checklist->update([
            'completed_items' => $completedCount,
            'completion_percentage' => $percentage,
            'status' => $completedCount > 0 ? 'active' : $checklist->status,
            'started_at' => $checklist->started_at ?? now(),
        ]);

        // Step 3: AI analysis (non-blocking — if it fails, photo is still saved)
        $aiScore = null;
        $aiObservations = null;
        $aiStatus = 'pendiente';

        try {
            $result = $this->analizarConIA($url, $contexto);
            $aiScore = $result['score'];
            $aiObservations = $result['observations'];
            $aiStatus = 'completado';

            $item->update([
                'ai_score' => $aiScore,
                'ai_observations' => $aiObservations,
                'ai_analyzed_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('AI analysis failed or timed out, marking as pendiente', [
                'item_id' => $itemId,
                'context' => $contexto,
                'error' => $e->getMessage(),
            ]);

            $item->update([
                'ai_observations' => 'pendiente',
            ]);
        }

        return [
            'url' => $url,
            'ai_score' => $aiScore,
            'ai_observations' => $aiObservations,
            'ai_status' => $aiStatus,
        ];
    }

    /**
     * Parse the AI response text into score and observations.
     *
     * @return array{score: int, observations: string}
     */
    protected function parseAIResponse(string $text): array
    {
        // Try to parse JSON from the response
        $jsonMatch = [];
        if (preg_match('/\{[^}]*"score"\s*:\s*(\d+)[^}]*"observations"\s*:\s*"([^"]*)"[^}]*\}/s', $text, $jsonMatch)) {
            return [
                'score' => (int) $jsonMatch[1],
                'observations' => $jsonMatch[2],
            ];
        }

        // Try direct JSON decode
        $decoded = json_decode($text, true);
        if ($decoded && isset($decoded['score'])) {
            return [
                'score' => (int) ($decoded['score'] ?? 50),
                'observations' => (string) ($decoded['observations'] ?? $text),
            ];
        }

        // Fallback: return raw text as observations with default score
        return [
            'score' => 50,
            'observations' => $text,
        ];
    }

    /**
     * Create a Bedrock Runtime client with 15s timeout.
     *
     * @return \Aws\BedrockRuntime\BedrockRuntimeClient
     */
    protected function createBedrockClient(): \Aws\BedrockRuntime\BedrockRuntimeClient
    {
        return new \Aws\BedrockRuntime\BedrockRuntimeClient([
            'region' => config('services.bedrock.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'version' => 'latest',
            'credentials' => [
                'key' => config('services.bedrock.key', env('AWS_ACCESS_KEY_ID')),
                'secret' => config('services.bedrock.secret', env('AWS_SECRET_ACCESS_KEY')),
            ],
            'http' => [
                'timeout' => 15,
                'connect_timeout' => 5,
            ],
        ]);
    }
}
