<?php

namespace App\Services\Delivery;

use App\Models\Compra;
use App\Models\DailySettlement;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    private string $s3Bucket;
    private string $s3Region;
    private string $s3Key;
    private string $s3Secret;

    public function __construct()
    {
        $this->s3Bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
        $this->s3Region = config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $this->s3Key    = config('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID', ''));
        $this->s3Secret = config('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY', ''));
    }

    /**
     * Genera (o actualiza) el settlement diario para la fecha dada.
     * Idempotente: usa updateOrCreate por settlement_date.
     * No modifica un settlement ya en status='paid'.
     */
    public function generateDailySettlement(Carbon $date): DailySettlement
    {
        $dateStr = $date->format('Y-m-d');

        // Verificar si ya existe un settlement pagado — no modificar
        $existing = DailySettlement::where('settlement_date', $dateStr)->first();
        if ($existing && $existing->status === 'paid') {
            return $existing;
        }

        // Calcular total de delivery fees para la fecha
        $totals = DB::table('tuu_orders')
            ->where('order_status', 'delivered')
            ->whereRaw('DATE(delivered_at) = ?', [$dateStr])
            ->selectRaw('COUNT(*) as total_orders, SUM(COALESCE(delivery_fee, 0)) as total_fees')
            ->first();

        $totalOrders = (int) ($totals->total_orders ?? 0);
        $totalFees   = (float) ($totals->total_fees ?? 0);

        // Generar desglose por rider
        $settlementData = $this->buildSettlementData($dateStr);

        $settlement = DailySettlement::updateOrCreate(
            ['settlement_date' => $dateStr],
            [
                'total_orders_delivered' => $totalOrders,
                'total_delivery_fees'    => $totalFees,
                'settlement_data'        => $settlementData,
                'status'                 => 'pending',
            ]
        );

        return $settlement;
    }

    /**
     * Sube el comprobante de pago a S3, actualiza el settlement como 'paid'
     * y crea la compra correspondiente.
     *
     * @return array{settlement: DailySettlement, compra_created: bool, compra_id: int|null}
     */
    public function uploadVoucherAndPay(int $settlementId, $file, int $paidBy): array
    {
        $settlement = DailySettlement::findOrFail($settlementId);

        // Subir comprobante a S3
        $voucherUrl = $this->uploadToS3($file, $settlementId);

        // Actualizar settlement
        $settlement->update([
            'status'               => 'paid',
            'payment_voucher_url'  => $voucherUrl,
            'paid_at'              => now(),
            'paid_by'              => $paidBy,
        ]);

        $settlement->refresh();

        // Crear compra automáticamente
        $compraId = $this->createCompraFromSettlement($settlement);

        if ($compraId !== null) {
            $settlement->update(['compra_id' => $compraId]);
            $settlement->refresh();
        }

        return [
            'settlement'    => $settlement,
            'compra_created' => $compraId !== null,
            'compra_id'     => $compraId,
        ];
    }

    /**
     * Crea un registro en la tabla compras a partir del settlement.
     * Envuelto en try/catch. Retorna compra_id o null si falla.
     */
    public function createCompraFromSettlement(DailySettlement $settlement): ?int
    {
        try {
            $adminNombre = 'Sistema';
            if ($settlement->paid_by) {
                $admin = Personal::find($settlement->paid_by);
                if ($admin) {
                    $adminNombre = $admin->nombre;
                }
            }

            $compra = Compra::create([
                'fecha_compra'    => $settlement->paid_at,
                'proveedor'       => 'ARIAKA',
                'tipo_compra'     => 'servicio',
                'monto_total'     => $settlement->total_delivery_fees,
                'metodo_pago'     => 'transferencia',
                'estado'          => 'pagado',
                'notas'           => "Servicio delivery {$settlement->settlement_date->format('Y-m-d')} - {$settlement->total_orders_delivered} pedidos",
                'imagen_respaldo' => $settlement->payment_voucher_url
                    ? [$settlement->payment_voucher_url]
                    : [],
                'usuario'         => $adminNombre,
            ]);

            return $compra->id;
        } catch (\Throwable $e) {
            Log::error('[SettlementService] Error al crear compra desde settlement', [
                'settlement_id' => $settlement->id,
                'settlement_date' => $settlement->settlement_date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Construye el JSON de desglose por rider para la fecha dada.
     */
    private function buildSettlementData(string $dateStr): array
    {
        $rows = DB::table('tuu_orders as o')
            ->join('personal as p', 'o.rider_id', '=', 'p.id')
            ->where('o.order_status', 'delivered')
            ->whereRaw('DATE(o.delivered_at) = ?', [$dateStr])
            ->whereNotNull('o.rider_id')
            ->selectRaw('o.rider_id, p.nombre as rider_nombre, COUNT(*) as pedidos, SUM(COALESCE(o.delivery_fee, 0)) as total_fees')
            ->groupBy('o.rider_id', 'p.nombre')
            ->get();

        return $rows->map(fn ($row) => [
            'rider_id'     => $row->rider_id,
            'rider_nombre' => $row->rider_nombre,
            'pedidos'      => (int) $row->pedidos,
            'total_fees'   => (float) $row->total_fees,
        ])->values()->toArray();
    }

    /**
     * Sube un archivo a S3 usando SigV4 directo (mismo approach que ImagenService).
     * Retorna la URL pública del archivo subido.
     */
    private function uploadToS3($file, int $settlementId): string
    {
        $extension = $file instanceof UploadedFile
            ? $file->getClientOriginalExtension()
            : 'jpg';

        $uniqueId  = time() . '_' . bin2hex(random_bytes(4));
        $objectKey = "delivery/settlements/voucher_{$settlementId}_{$uniqueId}.{$extension}";

        $body = $file instanceof UploadedFile
            ? file_get_contents($file->getRealPath())
            : file_get_contents($file);

        $contentType = $file instanceof UploadedFile
            ? ($file->getMimeType() ?? 'application/octet-stream')
            : 'application/octet-stream';

        $this->s3PutObject($objectKey, $body, $contentType);

        return "https://{$this->s3Bucket}.s3.amazonaws.com/{$objectKey}";
    }

    /**
     * PUT object a S3 con firma SigV4 directa.
     */
    private function s3PutObject(string $objectKey, string $body, string $contentType = 'application/octet-stream'): void
    {
        $host        = "{$this->s3Bucket}.s3.{$this->s3Region}.amazonaws.com";
        $url         = "https://{$host}/{$objectKey}";
        $now         = gmdate('Ymd\THis\Z');
        $date        = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $headers = [
            'content-type'         => $contentType,
            'host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $now,
        ];

        $signedHeaders    = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }

        $canonicalRequest = "PUT\n/{$objectKey}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope  = "{$date}/{$this->s3Region}/s3/aws4_request";
        $stringToSign     = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $date, "AWS4{$this->s3Secret}", true);
        $kRegion  = hash_hmac('sha256', $this->s3Region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->s3Key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS    => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT       => 30,
            CURLOPT_HTTPHEADER    => [
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
            throw new \RuntimeException(
                "[SettlementService] S3 PUT failed: HTTP {$code} for {$objectKey}. Response: " . substr($resp, 0, 300)
            );
        }
    }
}
