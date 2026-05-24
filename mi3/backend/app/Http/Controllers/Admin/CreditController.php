<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminDataUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\R11CreditTransaction;
use App\Models\Rl6CreditTransaction;
use App\Models\Usuario;
use App\Services\Credit\RL6CreditService;
use App\Services\Email\GmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    // ── RL6 Transactions ─────────────────────────────────────────

    public function rl6Transactions(int $id): JsonResponse
    {
        $transactions = Rl6CreditTransaction::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'amount' => (float) $t->amount,
                'type' => $t->type,
                'description' => $t->description,
                'order_id' => $t->order_id,
                'created_at' => $t->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    // ── Payment Receipt Management ───────────────────────────────

    public function rl6Receipts(Request $request, RL6CreditService $service): JsonResponse
    {
        $userId = $request->input('user_id');
        $receipts = $service->getReceipts($userId);

        return response()->json([
            'success' => true,
            'data' => $receipts,
        ]);
    }

    public function rl6ApproveReceipt(Request $request, string $orderNumber, RL6CreditService $service): JsonResponse
    {
        $adminId = $request->user()->id ?? $request->input('admin_id');
        if (!$adminId) {
            return response()->json(['success' => false, 'error' => 'admin_id requerido'], 400);
        }

        try {
            $service->approveReceipt($orderNumber, (int) $adminId);
            return response()->json(['success' => true, 'message' => 'Comprobante aprobado y crédito actualizado']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function rl6RejectReceipt(Request $request, string $orderNumber, RL6CreditService $service): JsonResponse
    {
        $adminId = $request->user()->id ?? $request->input('admin_id');
        $notes = $request->input('notes');

        if (!$adminId) {
            return response()->json(['success' => false, 'error' => 'admin_id requerido'], 400);
        }

        try {
            $service->rejectReceipt($orderNumber, (int) $adminId, $notes);
            return response()->json(['success' => true, 'message' => 'Comprobante rechazado']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Pending Credit Applications ──────────────────────────────

    public function pendingCredits(RL6CreditService $service): JsonResponse
    {
        $rl6 = $service->getPendingApplications();
        $r11 = $service->getPendingR11Applications();

        $all = collect(array_merge($rl6, $r11))
            ->sortByDesc('fecha_solicitud')
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $all,
            'meta' => [
                'total_rl6' => count($rl6),
                'total_r11' => count($r11),
            ],
        ]);
    }

    // ── Resumen público RL6 ─────────────────────────────────

    public function resumenPublico(): JsonResponse
    {
        $service = app(RL6CreditService::class);
        $rl6 = $service->getRL6Users();

        $deudores = collect($rl6['data'])
            ->filter(fn($u) => $u['credito_usado'] > 0)
            ->map(fn($u) => [
                'nombre' => $u['nombre'],
                'rut' => $u['rut'],
                'grado_militar' => $u['grado_militar'],
                'unidad_trabajo' => $u['unidad_trabajo'],
                'limite_credito' => $u['limite_credito'],
                'credito_usado' => $u['credito_usado'],
                'disponible' => $u['disponible'],
                'es_moroso' => $u['es_moroso'],
                'dias_mora' => $u['dias_mora'],
                'pagado_este_mes' => $u['pagado_este_mes'],
            ])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'generated_at' => now()->toIso8601String(),
            'periodo' => [
                'inicio' => now()->subMonth()->format('Y-m-22'),
                'fin' => now()->format('Y-m-21'),
            ],
            'summary' => $rl6['summary'],
            'deudores' => $deudores,
        ]);
    }

    // ── Archivar/Desarchivar RL6 ───────────────────────────────

    public function rl6Archive(int $id): JsonResponse
    {
        $user = Usuario::findOrFail($id);
        if (!$user->es_militar_rl6) {
            return response()->json(['success' => false, 'error' => 'No es usuario RL6'], 400);
        }
        $user->update(['rl6_archived' => true]);
        return response()->json(['success' => true, 'message' => 'Usuario archivado']);
    }

    public function rl6Unarchive(int $id): JsonResponse
    {
        $user = Usuario::findOrFail($id);
        $user->update(['rl6_archived' => false]);
        return response()->json(['success' => true, 'message' => 'Usuario restaurado']);
    }

    public function rl6Archived(RL6CreditService $service): JsonResponse
    {
        $users = Usuario::where('es_militar_rl6', 1)
            ->where('rl6_archived', 1)
            ->select(['id', 'nombre', 'email', 'rut', 'grado_militar', 'unidad_trabajo',
                'limite_credito', 'credito_usado', 'fecha_ultimo_pago'])
            ->orderBy('nombre')
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
