<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GmailService
{
    /**
     * Send a liquidacion email to a worker.
     */
    public function sendLiquidacionEmail(Personal $persona, string $mes, array $liquidacionData): bool
    {
        $token = $this->getValidToken();
        if (!$token) {
            Log::error('GmailService: No valid Gmail token available');
            return false;
        }

        $email = $persona->email ?? $persona->usuario?->email;
        if (!$email) {
            Log::warning("GmailService: No email for personal {$persona->id}");
            return false;
        }

        $subject = "Liquidación {$mes} - La Ruta 11";
        $html = $this->generateLiquidacionHtml($persona, $mes, $liquidacionData);

        return $this->sendEmail($token, $email, $subject, $html);
    }

    /**
     * Send an RL6 collection email to a user.
     *
     * Reuses getValidToken() and sendEmail() infrastructure.
     * Logs result in email_logs with status 'sent' or 'failed'.
     *
     * @return array{success: bool, gmail_message_id: ?string, error?: string}
     */
    public function sendRL6CollectionEmail(
        int $userId,
        string $email,
        string $html,
        string $subject,
        string $emailType
    ): array {
        $token = $this->getValidToken();

        if (!$token) {
            Log::error('GmailService: No valid Gmail token for RL6 email');

            EmailLog::create([
                'user_id' => $userId,
                'email_to' => $email,
                'email_type' => $emailType,
                'subject' => $subject,
                'status' => 'failed',
                'error_message' => 'No se pudo obtener token de Gmail',
                'sent_at' => now(),
            ]);

            return [
                'success' => false,
                'gmail_message_id' => null,
                'error' => 'No se pudo obtener token de Gmail',
            ];
        }

        return $this->sendRL6Email($token, $userId, $email, $subject, $html, $emailType);
    }

    /**
     * Send an RL6 email via Gmail API and log the result.
     *
     * @return array{success: bool, gmail_message_id: ?string, error?: string}
     */
    private function sendRL6Email(
        string $token,
        int $userId,
        string $to,
        string $subject,
        string $html,
        string $emailType
    ): array {
        $from = config('mi3.gmail.sender_email');

        $message = "From: La Ruta 11 <{$from}>\r\n";
        $message .= "To: {$to}\r\n";
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

        $responseData = json_decode($response, true);
        $success = $httpCode === 200;

        EmailLog::create([
            'user_id' => $userId,
            'email_to' => $to,
            'email_type' => $emailType,
            'subject' => $subject,
            'status' => $success ? 'sent' : 'failed',
            'gmail_message_id' => $responseData['id'] ?? null,
            'gmail_thread_id' => $responseData['threadId'] ?? null,
            'error_message' => $success ? null : ($responseData['error']['message'] ?? 'Gmail API error'),
            'sent_at' => now(),
        ]);

        if (!$success) {
            Log::error('GmailService: Failed to send RL6 email', [
                'to' => $to,
                'userId' => $userId,
                'httpCode' => $httpCode,
                'response' => $response,
            ]);

            return [
                'success' => false,
                'gmail_message_id' => null,
                'error' => $responseData['error']['message'] ?? 'Error al enviar email via Gmail',
            ];
        }

        return [
            'success' => true,
            'gmail_message_id' => $responseData['id'] ?? null,
        ];
    }

    /**
     * Get a valid Gmail access token, refreshing if expired.
     *
     * Replicates the logic from caja3/api/gmail/get_token_db.php.
     */
    private function getValidToken(): ?string
    {
        $row = DB::table('gmail_tokens')->orderByDesc('id')->first();

        if (!$row) {
            return null;
        }

        // Token still valid
        if (time() < $row->expires_at) {
            return $row->access_token;
        }

        // Refresh the token
        return $this->refreshToken($row);
    }

    /**
     * Refresh an expired Gmail OAuth token.
     */
    private function refreshToken(object $tokenRow): ?string
    {
        $clientId = config('services.gmail.client_id');
        $clientSecret = config('services.gmail.client_secret');

        if (!$clientId || !$clientSecret) {
            Log::error('GmailService: Missing Gmail OAuth client_id or client_secret in config');
            return null;
        }

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $tokenRow->refresh_token,
                'grant_type' => 'refresh_token',
            ]),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            Log::error('GmailService: Failed to refresh token', ['response' => $response]);
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            Log::error('GmailService: Token refresh error', ['error' => $data['error_description'] ?? $data['error']]);
            return null;
        }

        $newExpiresAt = time() + ($data['expires_in'] ?? 3600);

        DB::table('gmail_tokens')
            ->where('id', $tokenRow->id)
            ->update([
                'access_token' => $data['access_token'],
                'expires_at' => $newExpiresAt,
                'updated_at' => now(),
            ]);

        return $data['access_token'];
    }

    /**
     * Send an email via Gmail API REST endpoint.
     */
    private function sendEmail(string $token, string $to, string $subject, string $html): bool
    {
        $from = config('mi3.gmail.sender_email');

        $message = "From: La Ruta 11 <{$from}>\r\n";
        $message .= "To: {$to}\r\n";
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

        $responseData = json_decode($response, true);

        // Log the email attempt
        EmailLog::create([
            'user_id' => null,
            'email_to' => $to,
            'email_type' => 'liquidacion_mi3',
            'subject' => $subject,
            'status' => $httpCode === 200 ? 'sent' : 'failed',
            'gmail_message_id' => $responseData['id'] ?? null,
            'gmail_thread_id' => $responseData['threadId'] ?? null,
            'sent_at' => now(),
        ]);

        if ($httpCode !== 200) {
            Log::error('GmailService: Failed to send email', [
                'to' => $to,
                'httpCode' => $httpCode,
                'response' => $response,
            ]);
        }

        return $httpCode === 200;
    }

    /**
     * Format a number as CLP currency string.
     */
    private function formatMoney(int|float $value): string
    {
        return '$' . number_format((int) $value, 0, ',', '.');
    }

    /**
     * Generate HTML for a liquidacion email.
     */
    private function generateLiquidacionHtml(Personal $persona, string $mes, array $liquidacionData): string
    {
        $nombre = e($persona->nombre);
        $secciones = '';
        $granTotal = 0;

        foreach ($liquidacionData as $seccion) {
            $centro = $seccion['centro_costo'] ?? 'all';
            $centroLabel = match ($centro) {
                'ruta11' => 'La Ruta 11',
                'seguridad' => 'Seguridad',
                default => 'General',
            };

            $total = $seccion['total'] ?? 0;
            $granTotal += $total;

            $sueldoBaseStr = $this->formatMoney($seccion['sueldo_base'] ?? 0);
            $diasTrabajados = $seccion['dias_trabajados'] ?? 0;

            $secciones .= "<tr><td colspan='2' style='background:#f0f4ff;padding:12px;font-weight:bold;font-size:16px;'>{$centroLabel}</td></tr>";
            $secciones .= "<tr><td style='padding:8px 12px;'>Sueldo Base</td><td style='padding:8px 12px;text-align:right;'>{$sueldoBaseStr}</td></tr>";
            $secciones .= "<tr><td style='padding:8px 12px;'>Días Trabajados</td><td style='padding:8px 12px;text-align:right;'>{$diasTrabajados}</td></tr>";

            if (($seccion['reemplazos_hechos'] ?? 0) > 0) {
                $montoRealizados = $this->formatMoney(collect($seccion['reemplazos_realizados'] ?? [])->sum('monto'));
                $secciones .= "<tr><td style='padding:8px 12px;color:#16a34a;'>Reemplazos Realizados</td><td style='padding:8px 12px;text-align:right;color:#16a34a;'>+{$montoRealizados}</td></tr>";
            }

            if (($seccion['dias_reemplazados'] ?? 0) > 0) {
                $montoRecibidos = $this->formatMoney(collect($seccion['reemplazos_recibidos'] ?? [])->sum('monto'));
                $secciones .= "<tr><td style='padding:8px 12px;color:#dc2626;'>Reemplazos Recibidos</td><td style='padding:8px 12px;text-align:right;color:#dc2626;'>-{$montoRecibidos}</td></tr>";
            }

            if (($seccion['total_ajustes'] ?? 0) != 0) {
                $ajColor = $seccion['total_ajustes'] >= 0 ? '#16a34a' : '#dc2626';
                $ajSign = $seccion['total_ajustes'] >= 0 ? '+' : '';
                $ajMonto = $this->formatMoney($seccion['total_ajustes']);
                $secciones .= "<tr><td style='padding:8px 12px;color:{$ajColor};'>Ajustes</td><td style='padding:8px 12px;text-align:right;color:{$ajColor};'>{$ajSign}{$ajMonto}</td></tr>";
            }

            $totalStr = $this->formatMoney($total);
            $secciones .= "<tr><td style='padding:8px 12px;font-weight:bold;border-top:1px solid #e5e7eb;'>Total {$centroLabel}</td><td style='padding:8px 12px;text-align:right;font-weight:bold;border-top:1px solid #e5e7eb;'>{$totalStr}</td></tr>";
        }

        $granTotalStr = $this->formatMoney($granTotal);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family:Arial,sans-serif;background:#f8fafc;padding:20px;">';
        $html .= '<div style="max-width:600px;margin:0 auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
        $html .= '<div style="background:#1a73e8;color:white;padding:24px;text-align:center;">';
        $html .= '<h1 style="margin:0;font-size:22px;">Liquidación ' . e($mes) . '</h1>';
        $html .= '<p style="margin:8px 0 0;opacity:0.9;">La Ruta 11</p></div>';
        $html .= '<div style="padding:24px;">';
        $html .= '<p style="margin:0 0 16px;">Hola <strong>' . $nombre . '</strong>, aquí está tu liquidación del mes:</p>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= $secciones;
        $html .= '<tr style="background:#1a73e8;color:white;">';
        $html .= '<td style="padding:12px;font-weight:bold;font-size:18px;">TOTAL</td>';
        $html .= '<td style="padding:12px;text-align:right;font-weight:bold;font-size:18px;">' . $granTotalStr . '</td>';
        $html .= '</tr></table>';
        $html .= '<p style="margin:24px 0 0;font-size:13px;color:#6b7280;text-align:center;">';
        $html .= 'Este es un correo automático generado por mi3. Si tienes dudas, contacta al administrador.</p>';
        $html .= '</div></div></body></html>';

        return $html;
    }
}
