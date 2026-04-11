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
     * Path: checklist/YYYY/MM/{unique_filename}
     *
     * @return string The public URL of the uploaded photo
     */
    public function subirFotoS3(UploadedFile $foto): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $filename = uniqid('checklist_') . '.' . ($foto->getClientOriginalExtension() ?: 'jpg');
        $path = "checklist/{$year}/{$month}/{$filename}";

        $stored = Storage::disk('s3')->put($path, file_get_contents($foto->getRealPath()), 'public');

        if (!$stored) {
            throw new \RuntimeException('Error al subir la foto a S3');
        }

        return Storage::disk('s3')->url($path);
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
     * Orchestrate upload + AI analysis. Save result in checklist_items.
     * If Bedrock times out, mark analysis as "pendiente" (don't fail).
     *
     * @return array{url: string, ai_score: int|null, ai_observations: string|null, ai_status: string}
     */
    public function subirYAnalizar(UploadedFile $foto, int $itemId, string $contexto): array
    {
        // Step 1: Upload to S3
        $url = $this->subirFotoS3($foto);

        // Save photo URL immediately
        $item = ChecklistItem::findOrFail($itemId);
        $item->update(['photo_url' => $url]);

        // Step 2: AI analysis (with timeout handling)
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
