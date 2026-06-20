<?php

namespace App\Services\Credit;

use App\Models\EmailLog;
use App\Models\R11CreditTransaction;
use App\Models\Usuario;
use App\Services\Email\GmailService;
use Carbon\Carbon;

class R11CreditService
{
    /**
     * Get credit information for a user.
     */
    public function getCreditInfo(Usuario $usuario): array
    {
        return [
            'activo' => (bool) $usuario->es_credito_r11,
            'aprobado' => (bool) $usuario->credito_r11_aprobado,
            'bloqueado' => (bool) $usuario->credito_r11_bloqueado,
            'limite' => (float) $usuario->limite_credito_r11,
            'usado' => (float) $usuario->credito_r11_usado,
            'disponible' => (float) ($usuario->limite_credito_r11 - $usuario->credito_r11_usado),
            'relacion_r11' => $usuario->relacion_r11,
            'fecha_aprobacion' => $usuario->fecha_aprobacion_r11,
        ];
    }

    /**
     * Get credit transaction history for a user.
     */
    public function getTransactions(int $userId): array
    {
        return R11CreditTransaction::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get R11 users with debt sorted by amount desc, with days since last payment.
     */
    public function getMorosos(): array
    {
        $now = Carbon::now();

        $users = Usuario::where('es_credito_r11', 1)
            ->where('credito_r11_aprobado', 1)
            ->where('credito_r11_usado', '>', 0)
            ->select([
                'id', 'nombre', 'email', 'telefono',
                'limite_credito_r11', 'credito_r11_usado',
                'relacion_r11', 'fecha_ultimo_pago_r11', 'fecha_aprobacion_r11',
                'credito_r11_bloqueado', 'created_at',
            ])
            ->orderBy('credito_r11_usado', 'desc')
            ->get()
            ->map(function ($user) use ($now) {
                $fechaRef = $user->fecha_ultimo_pago_r11
                    ?? $user->fecha_aprobacion_r11
                    ?? $user->created_at?->toDateString();
                $diasSinPago = $fechaRef
                    ? (int) $now->startOfDay()->diffInDays(Carbon::parse($fechaRef)->startOfDay())
                    : 0;

                $limite = (float) $user->limite_credito_r11;
                $usado = (float) $user->credito_r11_usado;

                return [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'relacion' => $user->relacion_r11,
                    'limite_credito' => $limite,
                    'credito_usado' => $usado,
                    'disponible' => $limite - $usado,
                    'fecha_ultimo_pago' => $user->fecha_ultimo_pago_r11,
                    'dias_sin_pago' => $diasSinPago,
                    'bloqueado' => (bool) $user->credito_r11_bloqueado,
                ];
            })
            ->values()
            ->toArray();

        return $users;
    }

    /**
     * Calculate email estado for an R11 user.
     */
    public function calculateEmailEstado(array $userData): string
    {
        $creditoUsado = (float) ($userData['credito_usado'] ?? 0);

        if ($creditoUsado <= 0) {
            return 'sin_deuda';
        }

        $fechaPago = $userData['fecha_ultimo_pago'] ?? null;
        $pagoEsteMes = !empty($fechaPago) && substr($fechaPago, 0, 7) === Carbon::now()->format('Y-m');

        if ($pagoEsteMes) {
            return 'recordatorio';
        }

        $diasSinPago = (int) ($userData['dias_sin_pago'] ?? 999);

        if ($diasSinPago <= 30) {
            return 'recordatorio';
        }

        if ($diasSinPago <= 60) {
            return 'urgente';
        }

        return 'moroso';
    }

    /**
     * Preview email for a moroso R11 user.
     */
    public function previewEmail(int $id): array
    {
        $user = Usuario::findOrFail($id);

        $now = Carbon::now();
        $fechaRef = $user->fecha_ultimo_pago_r11
            ?? $user->fecha_aprobacion_r11
            ?? $user->created_at?->toDateString();
        $diasSinPago = $fechaRef
            ? (int) $now->startOfDay()->diffInDays(Carbon::parse($fechaRef)->startOfDay())
            : 0;

        $userData = [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'email' => $user->email,
            'relacion' => $user->relacion_r11,
            'limite_credito' => (float) $user->limite_credito_r11,
            'credito_usado' => (float) $user->credito_r11_usado,
            'fecha_ultimo_pago' => $user->fecha_ultimo_pago_r11,
            'dias_sin_pago' => $diasSinPago,
        ];

        $tipo = $this->calculateEmailEstado($userData);
        $html = $this->buildEmailHtml($userData, $tipo);

        return [
            'html' => $html,
            'tipo' => $tipo,
            'email' => $user->email,
        ];
    }

    /**
     * Build the email HTML for an R11 user.
     */
    public function buildEmailHtml(array $user, string $tipo): string
    {
        $nombre = htmlspecialchars($user['nombre'] ?? '');
        $relacion = htmlspecialchars($user['relacion'] ?? '');
        $uid = $user['id'];
        $fmt = fn($n) => number_format((float) $n, 0, ',', '.');

        $creditoTotal = (float) ($user['limite_credito'] ?? 0);
        $creditoUsado = (float) ($user['credito_usado'] ?? 0);
        $creditoDisponible = $creditoTotal - $creditoUsado;
        $diasSinPago = (int) ($user['dias_sin_pago'] ?? 0);

        $now = Carbon::now();
        $anioActual = date('Y');
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $mes = $meses[(int) $now->format('n') - 1];

        // Theme colors per tipo
        $themes = [
            'sin_deuda' => [
                'grad' => 'linear-gradient(135deg,#10b981 0%,#059669 100%)',
                'bg' => '#f0fdf4', 'border' => '#bbf7d0',
                'badge_bg' => '#d1fae5', 'badge_color' => '#065f46',
                'fecha_bg' => '#d1fae5', 'fecha_color' => '#065f46',
                'fecha_border' => '#6ee7b7', 'icon_bg' => '#10b981',
            ],
            'recordatorio' => [
                'grad' => 'linear-gradient(135deg,#FF6B35 0%,#F7931E 100%)',
                'bg' => '#fff7ed', 'border' => '#fed7aa',
                'badge_bg' => '#fff7ed', 'badge_color' => '#c2410c',
                'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d',
                'fecha_border' => '#fecaca', 'icon_bg' => '#ef4444',
            ],
            'urgente' => [
                'grad' => 'linear-gradient(135deg,#ef4444 0%,#dc2626 100%)',
                'bg' => '#fef2f2', 'border' => '#fecaca',
                'badge_bg' => '#fef2f2', 'badge_color' => '#991b1b',
                'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d',
                'fecha_border' => '#fca5a5', 'icon_bg' => '#dc2626',
            ],
            'moroso' => [
                'grad' => 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)',
                'bg' => '#fef2f2', 'border' => '#fca5a5',
                'badge_bg' => '#fef2f2', 'badge_color' => '#7f1d1d',
                'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d',
                'fecha_border' => '#fca5a5', 'icon_bg' => '#991b1b',
            ],
        ];
        $t = $themes[$tipo] ?? $themes['recordatorio'];

        // Alert badge
        $badgeHtml = '';
        if ($tipo === 'sin_deuda') {
            $badgeHtml = "<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
                . "🎉 ¡Tu crédito está al día!</p></div>";
        } elseif ($tipo === 'recordatorio') {
            $badgeHtml = "<div style='background:{$t['badge_bg']};border:2px dashed {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:14px;font-weight:800;margin:0;'>"
                . "📅 Tienes un saldo pendiente de <strong>\${$fmt($creditoUsado)}</strong></p></div>";
        } elseif ($tipo === 'urgente') {
            $badgeHtml = "<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
                . "🚨 ¡Llevas {$diasSinPago} días sin pagar! Regulariza tu situación</p></div>";
        } else {
            $badgeHtml = "<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
                . "⚠️ Tu pago está vencido hace <strong>{$diasSinPago} días</strong> — Regulariza tu situación</p></div>";
        }

        // CTA button
        $ctaBtn = '';
        if ($tipo !== 'sin_deuda') {
            $fmtUsado = $fmt($creditoUsado);
            $ctaBtn = "<tr><td style='padding:0 20px 35px;' align='center'>"
                . "<a href='https://app.laruta11.cl/pagar-credito?user_id={$uid}&monto={$fmtUsado}' "
                . "style='display:inline-block;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);"
                . "color:#ffffff;text-decoration:none;padding:20px 40px;border-radius:32px;font-weight:800;"
                . "font-size:18px;box-shadow:0 10px 30px rgba(59,130,246,0.3);white-space:nowrap;'>"
                . "&#128179; PAGAR MI CR&Eacute;DITO</a>"
                . "<p style='color:#9ca3af;font-size:11px;margin:24px 0 0;font-weight:700;'>"
                . "<span style='display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;margin-right:6px;'></span>"
                . "PROCESO 100% SEGURO V&Iacute;A TUU.CL</p></td></tr>";
        }

        // Payment steps
        $stepsHtml = '';
        $stepTexts = [
            'Inicia sesión en <strong>app.laruta11.cl</strong>',
            'Ve a tu <strong>Perfil</strong> → <strong>Crédito</strong>',
            'Haz clic en <strong>&quot;Pagar Crédito&quot;</strong>',
        ];
        foreach ($stepTexts as $i => $txt) {
            $n = $i + 1;
            $stepsHtml .= "<table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:10px;'><tr>"
                . "<td width='32' style='padding-right:12px;vertical-align:top;'>"
                . "<div style='background:{$t['grad']};color:white;width:32px;height:32px;border-radius:50%;"
                . "text-align:center;line-height:32px;font-weight:800;font-size:14px;'>{$n}</div></td>"
                . "<td style='vertical-align:top;'><p style='color:{$t['fecha_color']};font-size:14px;"
                . "line-height:32px;margin:0;font-weight:600;'>{$txt}</p></td></tr></table>";
        }

        $fmtTotal = $fmt($creditoTotal);
        $fmtUsado = $fmt($creditoUsado);
        $fmtDisponible = $fmt($creditoDisponible);

        $badgeRow = "<tr><td style='padding:0 20px 24px;'>{$badgeHtml}</td></tr>";

        $fechaVencimiento = $user['fecha_ultimo_pago']
            ? Carbon::parse($user['fecha_ultimo_pago'])->format('d/m/Y')
            : '—';

        return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background-color:{$t['bg']};'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:{$t['bg']};padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(0,0,0,0.15);border:1px solid {$t['border']};'>

  <tr><td style='background:{$t['grad']};padding:48px 20px;text-align:center;'>
    <img src='https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/menu/logo.png' alt='La Ruta 11' style='width:80px;height:80px;margin:0 auto 16px;display:block;filter:drop-shadow(0 10px 20px rgba(0,0,0,0.2));'>
    <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;letter-spacing:-0.5px;'>La Ruta 11</h1>
    <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>Estado de Cuenta</p>
  </td></tr>

  <tr><td style='padding:32px 20px 20px;background:#ffffff;'>
    <div style='text-align:center;margin-bottom:32px;'>
      <h2 style='color:#111827;margin:0 0 12px;font-size:24px;font-weight:800;'>¡Hola, {$nombre}! 👋</h2>
      <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>Aquí tienes el detalle de tu crédito <strong>R11</strong>. ¡Gracias por tu confianza!</p>
    </div>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr><td align='center' style='padding-bottom:32px;'>
        <div style='display:inline-block;background:{$t['badge_bg']};padding:8px 24px;border-radius:999px;margin:0 8px;'>
          <p style='color:{$t['badge_color']};margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>{$relacion}</p>
        </div>
      </td></tr>
    </table>
  </td></tr>

  {$badgeRow}

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:#f9fafb;padding:24px;border-radius:32px;'>
      <h3 style='text-align:center;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:3px;margin:0 0 24px;'>Resumen Financiero</h3>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#9ca3af;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Cupo Total</p><p style='color:#1f2937;font-size:20px;font-weight:800;margin:0;'>\${$fmtTotal}</p></div></td>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#fb923c;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Consumido</p><p style='color:#ea580c;font-size:20px;font-weight:800;margin:0;'>\${$fmtUsado}</p></div></td>
        </tr>
        <tr>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#34d399;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Disponible</p><p style='color:#059669;font-size:20px;font-weight:800;margin:0;'>\${$fmtDisponible}</p></div></td>
          <td width='50%' style='padding:8px;'><div style='background:{$t['grad']};padding:20px;border-radius:24px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,0.15);'><p style='color:rgba(255,255,255,0.85);font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Total a Pagar</p><p style='color:#ffffff;font-size:20px;font-weight:800;margin:0;'>\${$fmtUsado}</p></div></td>
        </tr>
      </table>
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:{$t['fecha_bg']};border-radius:24px;padding:20px;border:2px dashed {$t['fecha_border']};'>
      <table width='100%' cellpadding='0' cellspacing='0'><tr>
        <td width='48' style='padding-right:16px;'><div style='background:{$t['icon_bg']};color:#ffffff;width:48px;height:48px;border-radius:16px;text-align:center;line-height:48px;font-size:20px;box-shadow:0 4px 14px rgba(0,0,0,0.2);'>🗓️</div></td>
        <td><p style='color:{$t['fecha_color']};font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 0 4px;'>Último Pago</p><p style='color:{$t['fecha_color']};font-size:18px;font-weight:700;margin:0;'>{$fechaVencimiento}</p></td>
      </tr></table>
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:{$t['bg']};border:2px solid {$t['border']};border-radius:24px;padding:24px;'>
      <h3 style='color:{$t['badge_color']};margin:0 0 20px;font-size:16px;font-weight:800;text-align:center;'>💡 Cómo Pagar tu Crédito</h3>
      {$stepsHtml}
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 8px;text-align:center;'><p style='color:#9ca3af;font-size:11px;margin:0;font-weight:500;'>✨ Si ya iniciaste sesi&oacute;n haz clic ac&aacute; abajo 👇</p></td></tr>

  {$ctaBtn}

  <tr><td style='background-color:#111827;padding:40px 20px;text-align:center;'>
    <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center' style='padding-bottom:32px;'>
      <a href='tel:+56936227422' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>Soporte</a>
      <a href='tel:+56945392581' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>Ventas</a>
      <a href='https://app.laruta11.cl' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>App</a>
    </td></tr></table>
    <p style='color:#6b7280;margin:0;font-size:11px;line-height:1.8;font-weight:500;'>Yumbel 2629, Arica, Chile<br><span style='color:#4b5563;'>© {$anioActual} La Ruta 11 SpA. Sabores con historia.</span></p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
    }
}
