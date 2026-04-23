<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminDataUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\R11CreditTransaction;
use App\Models\Usuario;
use App\Services\Credit\RL6CreditService;
use App\Services\Email\GmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CreditController extends Controller
{
    public function index(): JsonResponse
    {
        $usuarios = Usuario::where('es_credito_r11', 1)
            ->with('personal')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'nombre' => $u->nombre,
                    'email' => $u->email,
                    'personal' => $u->personal,
                    'limite' => (float) $u->limite_credito_r11,
                    'usado' => (float) $u->credito_r11_usado,
                    'disponible' => (float) ($u->limite_credito_r11 - $u->credito_r11_usado),
                    'aprobado' => (bool) $u->credito_r11_aprobado,
                    'bloqueado' => (bool) $u->credito_r11_bloqueado,
                    'relacion_r11' => $u->relacion_r11,
                    'fecha_aprobacion' => $u->fecha_aprobacion_r11,
                ];
            });

        return response()->json(['success' => true, 'data' => $usuarios]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'limite_credito_r11' => 'required|numeric|min:0',
        ]);

        $usuario = Usuario::findOrFail($id);
        $usuario->update([
            'credito_r11_aprobado' => 1,
            'limite_credito_r11' => $data['limite_credito_r11'],
            'fecha_aprobacion_r11' => now()->toDateString(),
        ]);

        try {
            broadcast(new AdminDataUpdatedEvent('creditos', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast creditos approve: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $usuario]);
    }

    public function reject(int $id): JsonResponse
    {
        $usuario = Usuario::findOrFail($id);
        $usuario->update([
            'credito_r11_aprobado' => 0,
            'limite_credito_r11' => 0,
        ]);

        try {
            broadcast(new AdminDataUpdatedEvent('creditos', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast creditos reject: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $usuario]);
    }

    public function manualPayment(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $usuario = Usuario::findOrFail($id);
        $monto = (float) $data['monto'];

        R11CreditTransaction::create([
            'user_id' => $usuario->id,
            'amount' => $monto,
            'type' => 'refund',
            'description' => $data['descripcion'] ?? 'Pago manual admin',
        ]);

        $nuevoUsado = max(0, (float) $usuario->credito_r11_usado - $monto);
        $updateData = [
            'credito_r11_usado' => $nuevoUsado,
            'fecha_ultimo_pago_r11' => now()->toDateString(),
        ];

        if ($nuevoUsado == 0 && $usuario->credito_r11_bloqueado) {
            $updateData['credito_r11_bloqueado'] = 0;
        }

        $usuario->update($updateData);

        try {
            broadcast(new AdminDataUpdatedEvent('creditos', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast creditos manual-payment: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $usuario]);
    }

    // ── RL6 Credit Methods ──────────────────────────────────────────

    public function rl6Index(RL6CreditService $service): JsonResponse
    {
        $result = $service->getRL6Users();

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'summary' => $result['summary'],
        ]);
    }

    public function rl6Approve(Request $request, int $id, RL6CreditService $service): JsonResponse
    {
        $data = $request->validate([
            'limite' => 'required|numeric|min:0',
        ]);

        $service->approveCredit($id, (float) $data['limite']);

        return response()->json(['success' => true]);
    }

    public function rl6Reject(int $id, RL6CreditService $service): JsonResponse
    {
        $service->rejectCredit($id);

        return response()->json(['success' => true]);
    }

    public function rl6ManualPayment(Request $request, int $id, RL6CreditService $service): JsonResponse
    {
        $data = $request->validate([
            'monto' => 'required|numeric|gt:0',
            'descripcion' => 'nullable|string|max:255',
        ]);

        $service->manualPayment($id, (float) $data['monto'], $data['descripcion'] ?? null);

        return response()->json(['success' => true]);
    }

    public function rl6PreviewEmail(int $id, RL6CreditService $service): JsonResponse
    {
        $preview = $service->previewEmail($id);

        return response()->json([
            'success' => true,
            'html' => $preview['html'],
            'tipo' => $preview['tipo'],
            'email' => $preview['email'],
        ]);
    }

    public function rl6SendEmail(int $id, RL6CreditService $service, GmailService $gmail): JsonResponse
    {
        $preview = $service->previewEmail($id);

        $subjectMap = [
            'sin_deuda' => '✅ Tu crédito RL6 está al día - La Ruta 11',
            'recordatorio' => '📋 Recordatorio de pago RL6 - La Ruta 11',
            'urgente' => '🚨 Último aviso de pago RL6 - La Ruta 11',
            'moroso' => '⚠️ Pago vencido RL6 - La Ruta 11',
        ];
        $subject = $subjectMap[$preview['tipo']] ?? 'Estado de cuenta RL6 - La Ruta 11';

        $result = $gmail->sendRL6CollectionEmail(
            $id,
            $preview['email'],
            $preview['html'],
            $subject,
            $preview['tipo']
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'tipo' => $preview['tipo'],
                'gmail_message_id' => $result['gmail_message_id'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Error al enviar email',
        ], 500);
    }

    public function rl6SendBulkEmails(Request $request, RL6CreditService $service, GmailService $gmail): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
        ]);

        $totalSent = 0;
        $totalFailed = 0;
        $failed = [];

        foreach ($data['user_ids'] as $userId) {
            try {
                $preview = $service->previewEmail($userId);

                $subjectMap = [
                    'sin_deuda' => '✅ Tu crédito RL6 está al día - La Ruta 11',
                    'recordatorio' => '📋 Recordatorio de pago RL6 - La Ruta 11',
                    'urgente' => '🚨 Último aviso de pago RL6 - La Ruta 11',
                    'moroso' => '⚠️ Pago vencido RL6 - La Ruta 11',
                ];
                $subject = $subjectMap[$preview['tipo']] ?? 'Estado de cuenta RL6 - La Ruta 11';

                $result = $gmail->sendRL6CollectionEmail(
                    $userId,
                    $preview['email'],
                    $preview['html'],
                    $subject,
                    $preview['tipo']
                );

                if ($result['success']) {
                    $totalSent++;
                } else {
                    $totalFailed++;
                    $failed[] = [
                        'user_id' => $userId,
                        'error' => $result['error'] ?? 'Error desconocido',
                    ];
                }
            } catch (\Throwable $e) {
                $totalFailed++;
                $failed[] = [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ];
                Log::error("RL6 bulk email failed for user {$userId}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'success' => true,
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'failed' => $failed,
        ]);
    }
}
