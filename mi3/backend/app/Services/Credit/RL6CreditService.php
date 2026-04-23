<?php

namespace App\Services\Credit;

use App\Models\EmailLog;
use App\Models\Rl6CreditTransaction;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RL6CreditService
{
    /**
     * Get all active RL6 credit users with moroso calculations and summary.
     */
    public function getRL6Users(): array
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentYearMonth = $now->format('Y-m');

        // Ciclo vencido: día 22 mes anterior → día 21 mes actual
        $inicioCicloVencido = Carbon::now()->subMonth()->format('Y-m-22');
        $finCicloVencido = $now->format('Y-m-21') . ' 23:59:59';

        $users = Usuario::where('es_militar_rl6', 1)
            ->where('credito_aprobado', 1)
            ->select([
                'id', 'nombre', 'email', 'telefono', 'rut',
                'grado_militar', 'unidad_trabajo',
                'limite_credito', 'credito_usado', 'credito_bloqueado',
                'credito_aprobado', 'fecha_ultimo_pago',
            ])
            ->orderBy('nombre', 'asc')
            ->get();

        $userIds = $users->pluck('id')->toArray();

        // Deuda ciclo vencido por usuario
        $deudaCicloVencido = Rl6CreditTransaction::whereIn('user_id', $userIds)
            ->where('type', 'debit')
            ->whereBetween('created_at', [$inicioCicloVencido, $finCicloVencido])
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'user_id');

        // Pagos del mes actual (refunds)
        $pagosEsteMes = Rl6CreditTransaction::whereIn('user_id', $userIds)
            ->where('type', 'refund')
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$currentYearMonth])
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(amount), 0) as total')
            ->pluck('total', 'user_id');

        // Último email enviado por usuario
        $ultimoEmails = EmailLog::whereIn('user_id', $userIds)
            ->where('status', 'sent')
            ->whereIn('email_type', ['sin_deuda', 'recordatorio', 'urgente', 'moroso'])
            ->orderByDesc('sent_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn($logs) => $logs->first());

        $data = $users->map(function ($user) use ($deudaCicloVencido, $pagosEsteMes, $ultimoEmails) {
            $deudaCiclo = (float) ($deudaCicloVencido[$user->id] ?? 0);
            $pagadoEsteMes = (float) ($pagosEsteMes[$user->id] ?? 0);
            $ultimoEmail = $ultimoEmails[$user->id] ?? null;

            [$esMoroso, $diasMora] = $this->calculateMoroso($user, $deudaCiclo);

            return [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'email' => $user->email,
                'telefono' => $user->telefono,
                'rut' => $user->rut,
                'grado_militar' => $user->grado_militar,
                'unidad_trabajo' => $user->unidad_trabajo,
                'limite_credito' => (float) $user->limite_credito,
                'credito_usado' => (float) $user->credito_usado,
                'disponible' => (float) ($user->limite_credito - $user->credito_usado),
                'credito_aprobado' => (bool) $user->credito_aprobado,
                'credito_bloqueado' => (bool) $user->credito_bloqueado,
                'fecha_ultimo_pago' => $user->fecha_ultimo_pago,
                'es_moroso' => $esMoroso,
                'dias_mora' => $diasMora,
                'deuda_ciclo_vencido' => $deudaCiclo,
                'pagado_este_mes' => $pagadoEsteMes,
                'ultimo_email_enviado' => $ultimoEmail?->sent_at,
                'ultimo_email_tipo' => $ultimoEmail?->email_type,
            ];
        })->values()->toArray();

        return [
            'data' => $data,
            'summary' => $this->getSummary($data),
        ];
    }

    /**
     * Calculate summary totals from the processed user data array.
     */
    public function getSummary(array $users): array
    {
        $totalUsuarios = count($users);
        $totalCreditoOtorgado = 0.0;
        $totalDeudaActual = 0.0;
        $totalMorosos = 0;
        $totalDeudaMorosos = 0.0;
        $pagosDelMesCount = 0;
        $pagosDelMesMonto = 0.0;
        $usersConDeuda = 0;

        foreach ($users as $user) {
            $totalCreditoOtorgado += $user['limite_credito'];
            $totalDeudaActual += $user['credito_usado'];

            if ($user['es_moroso']) {
                $totalMorosos++;
                $totalDeudaMorosos += $user['credito_usado'];
            }

            if ($user['pagado_este_mes'] > 0) {
                $pagosDelMesCount++;
                $pagosDelMesMonto += $user['pagado_este_mes'];
            }

            if ($user['credito_usado'] > 0) {
                $usersConDeuda++;
            }
        }

        $tasaCobro = $usersConDeuda > 0
            ? round(($pagosDelMesCount / $usersConDeuda) * 100, 1)
            : 0.0;

        return [
            'total_usuarios' => $totalUsuarios,
            'total_credito_otorgado' => $totalCreditoOtorgado,
            'total_deuda_actual' => $totalDeudaActual,
            'total_morosos' => $totalMorosos,
            'total_deuda_morosos' => $totalDeudaMorosos,
            'pagos_del_mes_count' => $pagosDelMesCount,
            'pagos_del_mes_monto' => $pagosDelMesMonto,
            'tasa_cobro' => $tasaCobro,
        ];
    }

    /**
     * Calculate moroso status for a user.
     *
     * Moroso = today > day 21 AND deuda_ciclo_vencido > 0 AND fecha_ultimo_pago not in current month.
     *
     * @return array{0: bool, 1: int} [es_moroso, dias_mora]
     */
    public function calculateMoroso(object $user, float $deudaCicloVencido): array
    {
        $now = Carbon::now();
        $currentDay = $now->day;
        $currentYearMonth = $now->format('Y-m');

        if ($currentDay <= 21) {
            return [false, 0];
        }

        if ($deudaCicloVencido <= 0) {
            return [false, 0];
        }

        $fechaPago = $user->fecha_ultimo_pago;
        $pagoEsteMes = !empty($fechaPago) && substr($fechaPago, 0, 7) === $currentYearMonth;

        if ($pagoEsteMes) {
            return [false, 0];
        }

        return [true, $currentDay - 21];
    }

    /**
     * Approve credit for a user.
     */
    public function approveCredit(int $id, float $limite): void
    {
        Usuario::where('id', $id)->update([
            'credito_aprobado' => 1,
            'limite_credito' => $limite,
        ]);
    }

    /**
     * Reject credit for a user.
     */
    public function rejectCredit(int $id): void
    {
        Usuario::where('id', $id)->update([
            'credito_aprobado' => 0,
            'limite_credito' => 0,
        ]);
    }

    /**
     * Process a manual payment (refund) for a user.
     */
    public function manualPayment(int $id, float $monto, ?string $desc = null): void
    {
        $user = Usuario::findOrFail($id);

        DB::transaction(function () use ($user, $monto, $desc) {
            // Create refund transaction
            Rl6CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $monto,
                'type' => 'refund',
                'description' => $desc ?? 'Pago manual - Transferencia',
            ]);

            $newCreditoUsado = max(0, (float) $user->credito_usado - $monto);

            $updateData = [
                'credito_usado' => $newCreditoUsado,
                'fecha_ultimo_pago' => Carbon::now()->toDateString(),
            ];

            // Unblock if credit reaches 0
            if ($newCreditoUsado == 0 && $user->credito_bloqueado) {
                $updateData['credito_bloqueado'] = 0;
            }

            $user->update($updateData);
        });
    }

    /**
     * Calculate email estado based on user credit data.
     *
     * Returns: sin_deuda, recordatorio, urgente, moroso
     */
    public function calculateEmailEstado(array $userData): string
    {
        $creditoUsado = (float) ($userData['credito_usado'] ?? 0);

        if ($creditoUsado <= 0) {
            return 'sin_deuda';
        }

        $day = Carbon::now()->day;
        $fechaPago = $userData['fecha_ultimo_pago'] ?? null;
        $pagoEsteMes = !empty($fechaPago) && substr($fechaPago, 0, 7) === Carbon::now()->format('Y-m');
        $deudaCicloVencido = (float) ($userData['deuda_ciclo_vencido'] ?? 0);
        $soloDeudaCicloNuevo = $creditoUsado > 0 && $deudaCicloVencido <= 0;

        if ($pagoEsteMes || $soloDeudaCicloNuevo) {
            return 'recordatorio';
        }

        if ($day <= 20) {
            return 'recordatorio';
        }

        if ($day === 21) {
            return 'urgente';
        }

        // day > 21, has unpaid ciclo vencido debt, did not pay this month
        return 'moroso';
    }

    /**
     * Preview email for a user: generate HTML and detect tipo.
     *
     * @return array{html: string, tipo: string, email: string}
     */
    public function previewEmail(int $id): array
    {
        $user = Usuario::findOrFail($id);

        $now = Carbon::now();
        $inicioCicloVencido = Carbon::now()->subMonth()->format('Y-m-22');
        $finCicloVencido = $now->format('Y-m-21') . ' 23:59:59';

        $deudaCicloVencido = (float) Rl6CreditTransaction::where('user_id', $id)
            ->where('type', 'debit')
            ->whereBetween('created_at', [$inicioCicloVencido, $finCicloVencido])
            ->sum('amount');

        $userData = [
            'id' => $user->id,
            'nombre' => $user->nombre,
            'email' => $user->email,
            'grado_militar' => $user->grado_militar,
            'unidad_trabajo' => $user->unidad_trabajo,
            'limite_credito' => (float) $user->limite_credito,
            'credito_usado' => (float) $user->credito_usado,
            'fecha_ultimo_pago' => $user->fecha_ultimo_pago,
            'deuda_ciclo_vencido' => $deudaCicloVencido,
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
     * Build the email HTML for a given user and tipo.
     *
     * Replicates caja3/api/gmail/preview_email_dynamic.php logic with
     * responsive design and colors per Email_Estado.
     */
    public function buildEmailHtml(array $user, string $tipo): string
    {
        $nombre = htmlspecialchars($user['nombre'] ?? '');
        $grado = htmlspecialchars($user['grado_militar'] ?? '');
        $unidad = htmlspecialchars($user['unidad_trabajo'] ?? '');
        $uid = $user['id'];
        $fmt = fn($n) => number_format((float) $n, 0, ',', '.');

        $creditoTotal = (float) ($user['limite_credito'] ?? 0);
        $creditoUsado = (float) ($user['credito_usado'] ?? 0);
        $creditoDisponible = $creditoTotal - $creditoUsado;

        $now = Carbon::now();
        $day = $now->day;
        $fechaPago = $user['fecha_ultimo_pago'] ?? null;
        $pagoEsteMes = !empty($fechaPago) && substr($fechaPago, 0, 7) === $now->format('Y-m');
        $deudaCicloVencido = (float) ($user['deuda_ciclo_vencido'] ?? 0);
        $soloDeudaCicloNuevo = $creditoUsado > 0 && $deudaCicloVencido <= 0;

        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $mesIdx = (int) $now->format('n') - 1;
        $anio = (int) $now->format('Y');

        if ($pagoEsteMes || $soloDeudaCicloNuevo) {
            $mesIdx = ($mesIdx + 1) % 12;
            if ($mesIdx === 0) {
                $anio++;
            }
        }
        $mes = $meses[$mesIdx];

        // Calculate dias_restantes and dias_mora based on tipo
        $diasRestantes = 0;
        $diasMora = 0;
        if ($tipo === 'recordatorio') {
            if ($pagoEsteMes || $soloDeudaCicloNuevo) {
                $nextMonth21 = Carbon::parse($now->format('Y-m') . '-21')->addMonth();
                $diasRestantes = (int) $now->startOfDay()->diffInDays($nextMonth21, false);
            } else {
                $diasRestantes = 21 - $day;
            }
        } elseif ($tipo === 'moroso') {
            $diasMora = $day - 21;
        }

        $anioActual = date('Y');

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
        $alertBadge = $this->buildAlertBadge($tipo, $t, $diasRestantes, $diasMora, $mes, $anio);

        $fechaVencimiento = "21 de {$mes}, {$anio}";

        // CTA button (not shown for sin_deuda)
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

        return $this->buildEmailTemplate(
            $t, $nombre, $grado, $unidad, $alertBadge,
            $fmt($creditoTotal), $fmt($creditoUsado), $fmt($creditoDisponible),
            $fechaVencimiento, $stepsHtml, $ctaBtn, $anioActual
        );
    }

    /**
     * Build the alert badge HTML based on email tipo.
     */
    private function buildAlertBadge(string $tipo, array $t, int $diasRestantes, int $diasMora, string $mes, int $anio): string
    {
        $td = "<tr><td style='padding:0 20px 24px;'>";

        if ($tipo === 'sin_deuda') {
            return "{$td}<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
                . "🎉 ¡Tu crédito está al día este mes!</p></div></td></tr>";
        }

        if ($tipo === 'recordatorio') {
            $diasTxt = $diasRestantes === 1 ? 'día' : 'días';
            return "{$td}<div style='background:{$t['badge_bg']};border:2px dashed {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:14px;font-weight:800;margin:0;'>"
                . "📅 Tienes <strong>{$diasRestantes} {$diasTxt}</strong> para pagar antes del 21 de {$mes}</p></div></td></tr>";
        }

        if ($tipo === 'urgente') {
            $venceTxt = $diasRestantes === 0 ? 'HOY' : "en {$diasRestantes} día" . ($diasRestantes === 1 ? '' : 's');
            return "{$td}<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
                . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
                . "🚨 ¡ÚLTIMO AVISO! Vence {$venceTxt} — 21 de {$mes}, {$anio}</p></div></td></tr>";
        }

        // moroso
        $diasTxt = $diasMora === 1 ? 'día' : 'días';
        return "{$td}<div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'>"
            . "<p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>"
            . "⚠️ Tu pago venció hace <strong>{$diasMora} {$diasTxt}</strong> — Regulariza tu situación</p></div></td></tr>";
    }

    /**
     * Build the full email HTML template.
     */
    private function buildEmailTemplate(
        array $t,
        string $nombre,
        string $grado,
        string $unidad,
        string $alertBadge,
        string $fmtTotal,
        string $fmtUsado,
        string $fmtDisponible,
        string $fechaVencimiento,
        string $stepsHtml,
        string $ctaBtn,
        string $anioActual
    ): string {
        return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background-color:{$t['bg']};'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:{$t['bg']};padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(0,0,0,0.15);border:1px solid {$t['border']};'>

  <tr><td style='background:{$t['grad']};padding:48px 20px;text-align:center;'>
    <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width:80px;height:80px;margin:0 auto 16px;display:block;filter:drop-shadow(0 10px 20px rgba(0,0,0,0.2));'>
    <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;letter-spacing:-0.5px;'>La Ruta 11</h1>
    <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>Estado de Cuenta</p>
  </td></tr>

  <tr><td style='padding:32px 20px 20px;background:#ffffff;'>
    <div style='text-align:center;margin-bottom:32px;'>
      <h2 style='color:#111827;margin:0 0 12px;font-size:24px;font-weight:800;'>¡Hola, {$nombre}! 👋</h2>
      <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>Aquí tienes el detalle de tu crédito <strong>RL6</strong>. ¡Gracias por tu confianza!</p>
    </div>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr><td align='center' style='padding-bottom:32px;'>
        <div style='display:inline-block;background:{$t['badge_bg']};padding:8px 24px;border-radius:999px;margin:0 8px;'>
          <p style='color:{$t['badge_color']};margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>{$grado}</p>
        </div>
        <div style='display:inline-block;background:#f3f4f6;padding:8px 24px;border-radius:999px;margin:0 8px;'>
          <p style='color:#4b5563;margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>Unidad {$unidad}</p>
        </div>
      </td></tr>
    </table>
  </td></tr>

  {$alertBadge}

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
        <td><p style='color:{$t['fecha_color']};font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 0 4px;'>Fecha Límite</p><p style='color:{$t['fecha_color']};font-size:18px;font-weight:700;margin:0;'>{$fechaVencimiento}</p></td>
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
