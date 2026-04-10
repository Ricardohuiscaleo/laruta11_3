<?php

namespace App\Console\Commands;

use App\Services\Credit\R11CreditService;
use App\Services\Email\GmailService;
use Illuminate\Console\Command;

class R11AutoDeductCommand extends Command
{
    protected $signature = 'mi3:r11-auto-deduct';
    protected $description = 'Descuento automático de crédito R11 en nómina (día 1)';

    public function __construct(
        private R11CreditService $creditService,
        private GmailService $gmailService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando descuento automático R11...');

        $result = $this->creditService->autoDeduct();

        $resultados = $result['resultados'];
        $advertencias = $result['advertencias'];

        // Log warnings
        foreach ($advertencias as $warning) {
            $this->warn("⚠ {$warning}");
        }

        // Log results
        if (empty($resultados)) {
            $this->info('No hay deudores R11 para procesar este mes.');
        } else {
            $this->info('Descuentos procesados: ' . count($resultados));
            foreach ($resultados as $r) {
                $monto = number_format($r['monto'], 0, ',', '.');
                $this->line("  • {$r['nombre']}: \${$monto} (personal_id: {$r['personal_id']})");
            }
        }

        // Send summary email to admin
        $this->sendSummaryEmail($resultados, $advertencias);

        $this->info('Descuento automático R11 finalizado.');

        return self::SUCCESS;
    }

    private function sendSummaryEmail(array $resultados, array $advertencias): void
    {
        $mes = now()->locale('es')->monthName . ' ' . now()->year;
        $subject = "Resumen Descuento R11 - {$mes}";

        $html = $this->buildSummaryHtml($resultados, $advertencias, $mes);

        // Use GmailService internals to send to admin
        $adminEmail = config('mi3.admin_email');
        if (!$adminEmail) {
            $this->warn('⚠ No se configuró mi3.admin_email — resumen no enviado.');
            return;
        }

        try {
            // Build and send via Gmail API using the same token mechanism
            $token = $this->getGmailToken();
            if (!$token) {
                $this->warn('⚠ No hay token Gmail válido — resumen no enviado.');
                return;
            }

            $from = config('mi3.gmail.sender_email', 'noreply@laruta11.cl');
            $message = "From: La Ruta 11 <{$from}>\r\n";
            $message .= "To: {$adminEmail}\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($html));

            $encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

            $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['raw' => $encoded]),
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$token}",
                    'Content-Type: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $this->info('📧 Resumen enviado a ' . $adminEmail);
            } else {
                $this->warn("⚠ Error enviando resumen (HTTP {$httpCode})");
            }
        } catch (\Throwable $e) {
            $this->warn("⚠ Error enviando resumen: {$e->getMessage()}");
        }
    }

    private function getGmailToken(): ?string
    {
        $row = \Illuminate\Support\Facades\DB::table('gmail_tokens')->orderByDesc('id')->first();
        if (!$row) {
            return null;
        }

        if (time() < $row->expires_at) {
            return $row->access_token;
        }

        // Refresh
        $clientId = config('services.gmail.client_id');
        $clientSecret = config('services.gmail.client_secret');
        if (!$clientId || !$clientSecret) {
            return null;
        }

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $row->refresh_token,
                'grant_type' => 'refresh_token',
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            return null;
        }

        \Illuminate\Support\Facades\DB::table('gmail_tokens')
            ->where('id', $row->id)
            ->update([
                'access_token' => $data['access_token'],
                'expires_at' => time() + ($data['expires_in'] ?? 3600),
                'updated_at' => now(),
            ]);

        return $data['access_token'];
    }

    private function buildSummaryHtml(array $resultados, array $advertencias, string $mes): string
    {
        $rows = '';
        $totalGeneral = 0;

        foreach ($resultados as $r) {
            $monto = number_format($r['monto'], 0, ',', '.');
            $totalGeneral += $r['monto'];
            $rows .= "<tr><td style='padding:8px 12px;border-bottom:1px solid #e5e7eb;'>{$r['nombre']}</td>";
            $rows .= "<td style='padding:8px 12px;text-align:right;border-bottom:1px solid #e5e7eb;'>\${$monto}</td></tr>";
        }

        $totalStr = number_format($totalGeneral, 0, ',', '.');

        $warnHtml = '';
        if (!empty($advertencias)) {
            $warnHtml = '<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px;margin-top:16px;">';
            $warnHtml .= '<strong>⚠ Advertencias:</strong><ul style="margin:8px 0 0;padding-left:20px;">';
            foreach ($advertencias as $w) {
                $warnHtml .= "<li>{$w}</li>";
            }
            $warnHtml .= '</ul></div>';
        }

        $count = count($resultados);

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f8fafc;padding:20px;">
<div style="max-width:600px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
<div style="background:#1a73e8;color:white;padding:24px;text-align:center;">
<h1 style="margin:0;font-size:22px;">Descuento Automático R11</h1>
<p style="margin:8px 0 0;opacity:0.9;">{$mes}</p></div>
<div style="padding:24px;">
<p style="margin:0 0 16px;">Se procesaron <strong>{$count}</strong> descuentos automáticos de crédito R11.</p>
<table style="width:100%;border-collapse:collapse;">
<tr style="background:#f0f4ff;"><th style="padding:8px 12px;text-align:left;">Trabajador</th><th style="padding:8px 12px;text-align:right;">Monto</th></tr>
{$rows}
<tr style="background:#1a73e8;color:white;"><td style="padding:12px;font-weight:bold;">TOTAL</td><td style="padding:12px;text-align:right;font-weight:bold;">\${$totalStr}</td></tr>
</table>
{$warnHtml}
</div></div></body></html>
HTML;
    }
}
