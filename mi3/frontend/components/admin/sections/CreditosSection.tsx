'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, CheckCircle, XCircle, DollarSign, Mail, AlertTriangle, Eye, X, ThumbsUp, ThumbsDown, FileText } from 'lucide-react';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';
import type { RL6CreditUser, RL6Summary, EmailEstado, PaymentReceipt, PendingCredit } from '@/types/admin';
import EmailPreviewModal from '@/components/admin/EmailPreviewModal';
import CreditSummaryTrailing from '@/components/admin/CreditSummaryTrailing';

/* ─── R11 types (existing) ─── */

interface CreditUser {
  id: number;
  nombre: string;
  limite: number;
  usado: number;
  disponible: number;
  bloqueado: boolean;
  aprobado: boolean;
}

type CreditTab = 'r11' | 'rl6' | 'solicitudes';

/* ─── Props ─── */

interface CreditosSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

/* ─── Transaction type ─── */
interface Transaction {
  id: number;
  amount: number;
  type: 'debit' | 'refund';
  description: string | null;
  order_id: string | null;
  created_at: string;
}

/* ─── UserDetailModal ─── */
function UserDetailModal({
  user,
  onClose,
  onApproveReceipt,
  onRejectReceipt,
}: {
  user: RL6CreditUser;
  onClose: () => void;
  onApproveReceipt: (orderNumber: string) => void;
  onRejectReceipt: (orderNumber: string) => void;
}) {
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [receipts, setReceipts] = useState<PaymentReceipt[]>([]);
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    Promise.all([
      apiFetch<{ data: Transaction[] }>(`/admin/credits/rl6/${user.id}/transactions`),
      apiFetch<{ data: PaymentReceipt[] }>(`/admin/credits/rl6/receipts?user_id=${user.id}`),
    ])
      .then(([txRes, recRes]) => {
        setTransactions(txRes.data || []);
        setReceipts(recRes.data || []);
      })
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
  }, [user.id]);

  const handleApprove = async (orderNumber: string) => {
    setActing(orderNumber);
    try {
      await apiFetch(`/admin/credits/rl6/receipts/${orderNumber}/approve`, {
        method: 'POST',
        body: JSON.stringify({}),
      });
      setReceipts(prev => prev.map(r =>
        r.order_number === orderNumber ? { ...r, receipt_status: 'approved', payment_status: 'paid' } : r
      ));
    } catch (err: any) { alert(err.message); }
    finally { setActing(null); }
  };

  const handleReject = async (orderNumber: string) => {
    const notes = prompt('Motivo del rechazo (opcional):');
    setActing(orderNumber);
    try {
      await apiFetch(`/admin/credits/rl6/receipts/${orderNumber}/reject`, {
        method: 'POST',
        body: JSON.stringify({ notes }),
      });
      setReceipts(prev => prev.map(r =>
        r.order_number === orderNumber ? { ...r, receipt_status: 'rejected', payment_status: 'unpaid' } : r
      ));
    } catch (err: any) { alert(err.message); }
    finally { setActing(null); }
  };

  const receiptStatusBadge = (status: string | null) => {
    switch (status) {
      case 'pending_review':
        return <span className="rounded-full bg-yellow-400 px-2 py-0.5 text-xs font-bold text-black">REVISAR</span>;
      case 'approved':
        return <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Aprobado</span>;
      case 'rejected':
        return <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">Rechazado</span>;
      default:
        return <span className="text-xs text-gray-400">—</span>;
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-10 pb-10">
      <div className="fixed inset-0 bg-black/40" onClick={onClose} />
      <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto z-10 mx-4">
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between rounded-t-xl">
          <div>
            <h3 className="text-lg font-bold text-gray-900">{user.nombre}</h3>
            <p className="text-sm text-gray-500">{user.rut} · {user.grado_militar} · {user.unidad_trabajo}</p>
          </div>
          <button onClick={onClose} className="rounded-full p-2 hover:bg-gray-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {/* Credit Summary */}
          <div className="grid grid-cols-3 gap-3">
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500">Límite</p>
              <p className="text-lg font-bold">{formatCLP(user.limite_credito)}</p>
            </div>
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500">Usado</p>
              <p className="text-lg font-bold text-orange-600">{formatCLP(user.credito_usado)}</p>
            </div>
            <div className="bg-gray-50 rounded-lg p-3 text-center">
              <p className="text-xs text-gray-500">Disponible</p>
              <p className="text-lg font-bold text-green-600">{formatCLP(user.disponible)}</p>
            </div>
          </div>

          {/* Transactions */}
          <div>
            <h4 className="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
              <FileText className="h-4 w-4" /> Transacciones
            </h4>
            {loading ? (
              <div className="flex justify-center py-6"><Loader2 className="h-6 w-6 animate-spin text-gray-400" /></div>
            ) : transactions.length === 0 ? (
              <p className="text-sm text-gray-400 text-center py-4">Sin transacciones</p>
            ) : (
              <div className="space-y-2 max-h-48 overflow-y-auto">
                {transactions.map(tx => (
                  <div key={tx.id} className="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm">
                    <div>
                      <span className={cn('font-semibold', tx.type === 'refund' ? 'text-green-600' : 'text-red-600')}>
                        {tx.type === 'refund' ? 'Pago' : 'Consumo'}
                      </span>
                      <span className="text-gray-500 ml-2">{tx.description || '—'}</span>
                    </div>
                    <div className="text-right">
                      <span className={cn('font-bold', tx.type === 'refund' ? 'text-green-600' : 'text-red-600')}>
                        {tx.type === 'refund' ? '+' : '-'}${Math.round(tx.amount).toLocaleString('es-CL')}
                      </span>
                      <p className="text-xs text-gray-400">{new Date(tx.created_at).toLocaleDateString('es-CL')}</p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Receipts */}
          <div>
            <h4 className="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
              <Eye className="h-4 w-4" /> Comprobantes de Pago
            </h4>
            {loading ? (
              <div className="flex justify-center py-6"><Loader2 className="h-6 w-6 animate-spin text-gray-400" /></div>
            ) : receipts.length === 0 ? (
              <p className="text-sm text-gray-400 text-center py-4">Sin comprobantes</p>
            ) : (
              <div className="space-y-3">
                {receipts.map(r => (
                  <div key={r.order_number} className="border rounded-lg p-3">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm font-semibold">{r.order_number}</p>
                        <p className="text-xs text-gray-500">{r.description} · ${Math.round(r.amount).toLocaleString('es-CL')}</p>
                        <p className="text-xs text-gray-400">{new Date(r.payment_date).toLocaleDateString('es-CL', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                      </div>
                      <div className="text-right flex items-center gap-2">
                        {receiptStatusBadge(r.receipt_status)}
                        {r.receipt_path && r.receipt_path !== 'legacy_tuu' && (
                          <a
                            href={`https://app.laruta11.cl/uploads/receipts/${r.receipt_path}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="rounded p-1 hover:bg-blue-50"
                            title="Ver comprobante"
                          >
                            <Eye className="h-4 w-4 text-blue-500" />
                          </a>
                        )}
                      </div>
                    </div>
                    {r.receipt_status === 'pending_review' && (
                      <div className="flex gap-2 mt-2 pt-2 border-t">
                        <button
                          onClick={() => handleApprove(r.order_number)}
                          disabled={acting === r.order_number}
                          className="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
                        >
                          {acting === r.order_number ? <Loader2 className="h-3 w-3 animate-spin" /> : <ThumbsUp className="h-3 w-3" />}
                          Aprobar
                        </button>
                        <button
                          onClick={() => handleReject(r.order_number)}
                          disabled={acting === r.order_number}
                          className="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        >
                          {acting === r.order_number ? <Loader2 className="h-3 w-3 animate-spin" /> : <ThumbsDown className="h-3 w-3" />}
                          Rechazar
                        </button>
                      </div>
                    )}
                    {r.receipt_admin_notes && (
                      <p className="mt-1 text-xs text-gray-500 italic">Notas: {r.receipt_admin_notes}</p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

export default function CreditosSection({ onHeaderConfig }: CreditosSectionProps) {
  /* ─── Tab state ─── */
  const [activeTab, setActiveTab] = useState<CreditTab>('r11');

  /* ─── R11 state ─── */
  const [credits, setCredits] = useState<CreditUser[]>([]);
  const [r11Loading, setR11Loading] = useState(true);
  const [r11Error, setR11Error] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  /* ─── RL6 state ─── */
  const [rl6Data, setRl6Data] = useState<RL6CreditUser[] | null>(null);
  const [rl6Summary, setRl6Summary] = useState<RL6Summary | null>(null);
  const [rl6Loading, setRl6Loading] = useState(false);
  const [rl6Error, setRl6Error] = useState('');
  const [rl6Acting, setRl6Acting] = useState<number | null>(null);

  /* ─── Receipt state ─── */
  const [rl6Receipts, setRl6Receipts] = useState<PaymentReceipt[]>([]);

  /* ─── Detail modal state ─── */
  const [selectedUser, setSelectedUser] = useState<RL6CreditUser | null>(null);

  /* ─── Email preview state ─── */
  const [emailPreview, setEmailPreview] = useState<{ html: string; tipo: EmailEstado; email: string } | null>(null);
  const [emailSending, setEmailSending] = useState(false);
  const [emailPreviewUser, setEmailPreviewUser] = useState<RL6CreditUser | null>(null);

  /* ─── Bulk action state ─── */
  const [bulkSending, setBulkSending] = useState(false);
  const [bulkResult, setBulkResult] = useState<{ total_sent: number; total_failed: number; failed: string[] } | null>(null);

  /* ─── Pending credit applications state ─── */
  const [pendingCredits, setPendingCredits] = useState<PendingCredit[]>([]);
  const [pendingLoading, setPendingLoading] = useState(false);
  const [pendingActing, setPendingActing] = useState<number | null>(null);

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as CreditTab);
  }, []);

  /* ─── Register tabs via onHeaderConfig ─── */
  useEffect(() => {
    onHeaderConfig?.({
      tabs: [
        { key: 'r11', label: 'Créditos R11' },
        { key: 'rl6', label: 'Créditos RL6' },
        { key: 'solicitudes', label: 'Solicitudes' },
      ],
      activeTab,
      onTabChange: handleTabChange,
      trailing: (
        <CreditSummaryTrailing
          activeTab={activeTab === 'solicitudes' ? 'rl6' : activeTab}
          rl6Summary={rl6Summary}
          r11Data={credits}
          loading={activeTab === 'r11' ? r11Loading : rl6Loading}
        />
      ),
      accent: 'red',
    });
  }, [activeTab, handleTabChange, onHeaderConfig, rl6Summary, credits, r11Loading, rl6Loading]);

  /* ─── R11 data fetch ─── */
  const fetchR11 = useCallback(() => {
    setR11Loading(true);
    apiFetch<{ success: boolean; data: CreditUser[] }>('/admin/credits')
      .then(res => setCredits(res.data || []))
      .catch(e => setR11Error(e.message))
      .finally(() => setR11Loading(false));
  }, []);

  useEffect(() => { fetchR11(); }, [fetchR11]);

  /* ─── RL6 data fetch ─── */
  const fetchRL6 = useCallback(() => {
    setRl6Loading(true);
    setRl6Error('');
    Promise.all([
      apiFetch<{ data: RL6CreditUser[]; summary: RL6Summary }>('/admin/credits/rl6'),
      apiFetch<{ data: PaymentReceipt[] }>('/admin/credits/rl6/receipts'),
    ])
      .then(([creditsRes, receiptsRes]) => {
        setRl6Data(creditsRes.data || []);
        setRl6Summary(creditsRes.summary || null);
        setRl6Receipts(receiptsRes.data || []);
      })
      .catch(e => setRl6Error(e.message))
      .finally(() => setRl6Loading(false));
  }, []);

  useEffect(() => {
    if (activeTab === 'rl6' && rl6Data === null) {
      fetchRL6();
    }
  }, [activeTab, rl6Data, fetchRL6]);

  /* ─── Pending credits fetch ─── */
  const fetchPending = useCallback(() => {
    setPendingLoading(true);
    apiFetch<{ data: PendingCredit[]; meta: { total_rl6: number; total_r11: number } }>('/admin/credits/pending')
      .then(res => setPendingCredits(res.data || []))
      .catch(e => console.error(e))
      .finally(() => setPendingLoading(false));
  }, []);

  useEffect(() => {
    if (activeTab === 'solicitudes') {
      fetchPending();
    }
  }, [activeTab, fetchPending]);

  /* ─── User has pending receipt? ─── */
  const userHasPendingReceipt = (userId: number) =>
    rl6Receipts.some(r => r.user_id === userId && r.receipt_status === 'pending_review');

  /* ─── R11 actions ─── */
  const r11Action = async (id: number, endpoint: string) => {
    setActing(id);
    try {
      await apiFetch(`/admin/credits/${id}/${endpoint}`, { method: 'POST' });
      fetchR11();
    } catch (err: any) { alert(err.message); }
    finally { setActing(null); }
  };

  /* ─── RL6 actions ─── */
  const rl6Approve = async (user: RL6CreditUser) => {
    const input = prompt(`Límite de crédito para ${user.nombre}:`, String(user.limite_credito || 50000));
    if (!input) return;
    const limite = Number(input);
    if (isNaN(limite) || limite <= 0) { alert('Monto inválido'); return; }
    setRl6Acting(user.id);
    try {
      await apiFetch(`/admin/credits/rl6/${user.id}/approve`, {
        method: 'POST',
        body: JSON.stringify({ limite }),
      });
      setRl6Data(prev => prev?.map(u =>
        u.id === user.id ? { ...u, credito_aprobado: true, limite_credito: limite, disponible: limite - u.credito_usado } : u
      ) ?? null);
    } catch (err: any) { alert(err.message); }
    finally { setRl6Acting(null); }
  };

  const rl6Reject = async (user: RL6CreditUser) => {
    if (!confirm(`¿Rechazar crédito de ${user.nombre}?`)) return;
    setRl6Acting(user.id);
    try {
      await apiFetch(`/admin/credits/rl6/${user.id}/reject`, { method: 'POST' });
      setRl6Data(prev => prev?.filter(u => u.id !== user.id) ?? null);
      setRl6Summary(prev => prev ? { ...prev, total_usuarios: prev.total_usuarios - 1 } : null);
    } catch (err: any) { alert(err.message); }
    finally { setRl6Acting(null); }
  };

  const rl6ManualPayment = async (user: RL6CreditUser) => {
    const input = prompt(`Monto de pago manual para ${user.nombre}:`, String(user.credito_usado));
    if (!input) return;
    const monto = Number(input);
    if (isNaN(monto) || monto <= 0) { alert('Monto inválido'); return; }
    setRl6Acting(user.id);
    try {
      await apiFetch(`/admin/credits/rl6/${user.id}/manual-payment`, {
        method: 'POST',
        body: JSON.stringify({ monto }),
      });
      const newUsado = Math.max(0, user.credito_usado - monto);
      setRl6Data(prev => prev?.map(u =>
        u.id === user.id ? {
          ...u,
          credito_usado: newUsado,
          disponible: u.limite_credito - newUsado,
          credito_bloqueado: newUsado === 0 ? false : u.credito_bloqueado,
          fecha_ultimo_pago: new Date().toISOString().split('T')[0],
          es_moroso: false,
          dias_mora: 0,
        } : u
      ) ?? null);
      setRl6Summary(prev => {
        if (!prev) return null;
        const paid = Math.min(monto, user.credito_usado);
        return {
          ...prev,
          total_deuda_actual: Math.max(0, prev.total_deuda_actual - paid),
          total_deuda_morosos: user.es_moroso ? Math.max(0, prev.total_deuda_morosos - paid) : prev.total_deuda_morosos,
          total_morosos: user.es_moroso && newUsado === 0 ? prev.total_morosos - 1 : prev.total_morosos,
        };
      });
    } catch (err: any) { alert(err.message); }
    finally { setRl6Acting(null); }
  };

  /* ─── Email preview & send ─── */
  const rl6PreviewEmail = async (user: RL6CreditUser) => {
    setEmailPreviewUser(user);
    setRl6Acting(user.id);
    try {
      const res = await apiFetch<{ html: string; tipo: EmailEstado; email: string }>(
        `/admin/credits/rl6/${user.id}/preview-email`
      );
      setEmailPreview({ html: res.html, tipo: res.tipo, email: res.email });
    } catch (err: any) {
      alert(err.message);
      setEmailPreviewUser(null);
    } finally {
      setRl6Acting(null);
    }
  };

  const rl6SendEmail = async () => {
    if (!emailPreviewUser) return;
    setEmailSending(true);
    try {
      const res = await apiFetch<{ success: boolean; tipo?: string }>(
        `/admin/credits/rl6/${emailPreviewUser.id}/send-email`,
        { method: 'POST' }
      );
      const now = new Date().toISOString();
      setRl6Data(prev => prev?.map(u =>
        u.id === emailPreviewUser.id
          ? { ...u, ultimo_email_enviado: now, ultimo_email_tipo: res.tipo || emailPreview?.tipo || null }
          : u
      ) ?? null);
      setEmailPreview(null);
      setEmailPreviewUser(null);
      alert('Email enviado correctamente');
    } catch (err: any) {
      alert(`Error al enviar email: ${err.message}`);
    } finally {
      setEmailSending(false);
    }
  };

  const closeEmailPreview = () => {
    if (!emailSending) {
      setEmailPreview(null);
      setEmailPreviewUser(null);
    }
  };

  /* ─── Bulk email "Cobrar a Morosos" ─── */
  const morosos = rl6Data?.filter(u => u.es_moroso) ?? [];

  const rl6BulkEmail = async () => {
    if (morosos.length === 0) return;
    if (!confirm(`¿Enviar email de cobranza a ${morosos.length} usuario${morosos.length > 1 ? 's' : ''} moroso${morosos.length > 1 ? 's' : ''}?`)) return;

    setBulkSending(true);
    setBulkResult(null);
    try {
      const res = await apiFetch<{ total_sent: number; total_failed: number; failed: { nombre: string }[] }>(
        '/admin/credits/rl6/send-bulk-emails',
        {
          method: 'POST',
          body: JSON.stringify({ user_ids: morosos.map(u => u.id) }),
        }
      );
      const now = new Date().toISOString();
      setRl6Data(prev => {
        if (!prev) return null;
        const failedNames = new Set(res.failed?.map(f => f.nombre) ?? []);
        return prev.map(u => {
          if (morosos.some(m => m.id === u.id) && !failedNames.has(u.nombre)) {
            return { ...u, ultimo_email_enviado: now, ultimo_email_tipo: 'moroso' };
          }
          return u;
        });
      });
      setBulkResult({
        total_sent: res.total_sent,
        total_failed: res.total_failed,
        failed: res.failed?.map(f => f.nombre) ?? [],
      });
    } catch (err: any) {
      alert(`Error en envío masivo: ${err.message}`);
    } finally {
      setBulkSending(false);
    }
  };

  /* ─── Pending credit actions ─── */
  const approvePendingCredit = async (app: PendingCredit) => {
    const input = prompt(`Límite de crédito para ${app.nombre}:`, '50000');
    if (!input) return;
    const limite = Number(input);
    if (isNaN(limite) || limite <= 0) { alert('Monto inválido'); return; }
    setPendingActing(app.id);
    try {
      if (app.tipo === 'RL6') {
        await apiFetch(`/admin/credits/rl6/${app.id}/approve`, {
          method: 'POST',
          body: JSON.stringify({ limite }),
        });
      } else {
        await apiFetch(`/admin/credits/${app.id}/approve`, {
          method: 'POST',
          body: JSON.stringify({ limite_credito_r11: limite }),
        });
      }
      setPendingCredits(prev => prev.filter(p => p.id !== app.id));
    } catch (err: any) { alert(err.message); }
    finally { setPendingActing(null); }
  };

  const rejectPendingCredit = async (app: PendingCredit) => {
    if (!confirm(`¿Rechazar solicitud de crédito de ${app.nombre}?`)) return;
    setPendingActing(app.id);
    try {
      if (app.tipo === 'RL6') {
        await apiFetch(`/admin/credits/rl6/${app.id}/reject`, { method: 'POST' });
      } else {
        await apiFetch(`/admin/credits/${app.id}/reject`, { method: 'POST' });
      }
      setPendingCredits(prev => prev.filter(p => p.id !== app.id));
    } catch (err: any) { alert(err.message); }
    finally { setPendingActing(null); }
  };

  /* ─── Mora badge helper ─── */
  const renderMoraBadge = (user: RL6CreditUser) => {
    if (userHasPendingReceipt(user.id)) {
      return (
        <span className="inline-flex items-center rounded-full bg-yellow-400 px-2 py-0.5 text-xs font-bold text-black">
          REVISAR
        </span>
      );
    }
    if (user.es_moroso) {
      return (
        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
          Moroso
        </span>
      );
    }
    if (user.credito_usado > 0) {
      return (
        <span className="inline-flex items-center rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700">
          Con deuda
        </span>
      );
    }
    return (
      <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
        Al día
      </span>
    );
  };

  /* ─── Render R11 tab ─── */
  const renderR11 = () => {
    if (r11Loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
    if (r11Error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{r11Error}</div>;

    if (credits.length === 0) {
      return (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin trabajadores con crédito R11</p>
        </div>
      );
    }

    return (
      <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
            <tr>
              <th className="px-4 py-3">Nombre</th>
              <th className="px-4 py-3">Límite</th>
              <th className="px-4 py-3">Usado</th>
              <th className="px-4 py-3">Disponible</th>
              <th className="px-4 py-3">Estado</th>
              <th className="px-4 py-3">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {credits.map(c => (
              <tr key={c.id}>
                <td className="px-4 py-3 font-medium">{c.nombre}</td>
                <td className="px-4 py-3">{formatCLP(c.limite)}</td>
                <td className="px-4 py-3">{formatCLP(c.usado)}</td>
                <td className="px-4 py-3 text-green-600 font-medium">{formatCLP(c.disponible)}</td>
                <td className="px-4 py-3">
                  <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium',
                    c.bloqueado ? 'bg-red-100 text-red-700' : c.aprobado ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'
                  )}>
                    {c.bloqueado ? 'Bloqueado' : c.aprobado ? 'Activo' : 'Pendiente'}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-1">
                    {!c.aprobado && (
                      <button onClick={() => r11Action(c.id, 'approve')} disabled={acting === c.id}
                        className="rounded p-1 hover:bg-green-50" title="Aprobar">
                        <CheckCircle className="h-4 w-4 text-green-500" />
                      </button>
                    )}
                    <button onClick={() => r11Action(c.id, 'reject')} disabled={acting === c.id}
                      className="rounded p-1 hover:bg-red-50" title="Rechazar">
                      <XCircle className="h-4 w-4 text-red-500" />
                    </button>
                    <button onClick={() => r11Action(c.id, 'manual-payment')} disabled={acting === c.id}
                      className="rounded p-1 hover:bg-gray-100" title="Pago manual">
                      <DollarSign className="h-4 w-4 text-gray-500" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  /* ─── Render RL6 tab ─── */
  const renderRL6 = () => {
    if (rl6Loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
    if (rl6Error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{rl6Error}</div>;
    if (!rl6Data || rl6Data.length === 0) {
      return (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin usuarios con crédito RL6</p>
        </div>
      );
    }

    return (
      <div className="space-y-3">
        {morosos.length > 0 && (
          <div className="flex items-center gap-3">
            <button
              onClick={rl6BulkEmail}
              disabled={bulkSending}
              className="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            >
              {bulkSending ? <Loader2 className="h-4 w-4 animate-spin" /> : <AlertTriangle className="h-4 w-4" />}
              {bulkSending ? 'Enviando...' : `Cobrar a Morosos (${morosos.length})`}
            </button>
          </div>
        )}

        {bulkResult && (
          <div className={cn(
            'rounded-lg p-3 text-sm',
            bulkResult.total_failed > 0 ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-800'
          )}>
            <p className="font-medium">
              Enviados: {bulkResult.total_sent} · Fallidos: {bulkResult.total_failed}
            </p>
            {bulkResult.failed.length > 0 && (
              <p className="mt-1 text-xs">Fallaron: {bulkResult.failed.join(', ')}</p>
            )}
            <button onClick={() => setBulkResult(null)} className="mt-1 text-xs underline">Cerrar</button>
          </div>
        )}

      <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
            <tr>
              <th className="px-4 py-3">Nombre</th>
              <th className="px-4 py-3">RUT</th>
              <th className="px-4 py-3">Grado</th>
              <th className="px-4 py-3">Límite</th>
              <th className="px-4 py-3">Usado</th>
              <th className="px-4 py-3">Disponible</th>
              <th className="px-4 py-3">Estado Mora</th>
              <th className="px-4 py-3">Días Mora</th>
              <th className="px-4 py-3">Último Email</th>
              <th className="px-4 py-3">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {rl6Data.map(u => (
              <tr
                key={u.id}
                onClick={() => setSelectedUser(u)}
                className="cursor-pointer hover:bg-gray-50 transition-colors"
              >
                <td className="px-4 py-3 font-medium">{u.nombre}</td>
                <td className="px-4 py-3 text-gray-600">{u.rut || '—'}</td>
                <td className="px-4 py-3 text-gray-600">{u.grado_militar || '—'}</td>
                <td className="px-4 py-3">{formatCLP(u.limite_credito)}</td>
                <td className="px-4 py-3">{formatCLP(u.credito_usado)}</td>
                <td className="px-4 py-3 text-green-600 font-medium">{formatCLP(u.disponible)}</td>
                <td className="px-4 py-3">{renderMoraBadge(u)}</td>
                <td className="px-4 py-3 text-gray-600">
                  {u.es_moroso ? (
                    <span className="font-medium text-red-600">{u.dias_mora}d</span>
                  ) : '—'}
                </td>
                <td className="px-4 py-3 text-gray-500 text-xs">
                  {u.ultimo_email_enviado ? (
                    <div>
                      <div>{new Date(u.ultimo_email_enviado).toLocaleDateString('es-CL')}</div>
                      {u.ultimo_email_tipo && (
                        <div className="text-gray-400">{u.ultimo_email_tipo}</div>
                      )}
                    </div>
                  ) : '—'}
                </td>
                <td className="px-4 py-3" onClick={e => e.stopPropagation()}>
                  <div className="flex gap-1">
                    {u.credito_usado > 0 && (
                      <button onClick={() => rl6PreviewEmail(u)} disabled={rl6Acting === u.id}
                        className="rounded p-1 hover:bg-blue-50" title="Enviar Email">
                        <Mail className="h-4 w-4 text-blue-500" />
                      </button>
                    )}
                    <button onClick={() => rl6Approve(u)} disabled={rl6Acting === u.id}
                      className="rounded p-1 hover:bg-green-50" title="Aprobar crédito">
                      <CheckCircle className="h-4 w-4 text-green-500" />
                    </button>
                    <button onClick={() => rl6Reject(u)} disabled={rl6Acting === u.id}
                      className="rounded p-1 hover:bg-red-50" title="Rechazar crédito">
                      <XCircle className="h-4 w-4 text-red-500" />
                    </button>
                    <button onClick={() => rl6ManualPayment(u)} disabled={rl6Acting === u.id}
                      className="rounded p-1 hover:bg-gray-100" title="Pago manual">
                      <DollarSign className="h-4 w-4 text-gray-500" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      </div>
    );
  };

  /* ─── Render Solicitudes tab ─── */
  const renderSolicitudes = () => {
    if (pendingLoading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

    if (pendingCredits.length === 0) {
      return (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin solicitudes de crédito pendientes</p>
        </div>
      );
    }

    return (
      <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
            <tr>
              <th className="px-4 py-3">Nombre</th>
              <th className="px-4 py-3">RUT</th>
              <th className="px-4 py-3">Tipo</th>
              <th className="px-4 py-3">Grado/Relación</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Solicitado</th>
              <th className="px-4 py-3">Acciones</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {pendingCredits.map(app => (
              <tr key={`${app.tipo}-${app.id}`}>
                <td className="px-4 py-3 font-medium">{app.nombre}</td>
                <td className="px-4 py-3 text-gray-600">{app.rut || '—'}</td>
                <td className="px-4 py-3">
                  <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium',
                    app.tipo === 'RL6' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'
                  )}>
                    {app.tipo}
                  </span>
                </td>
                <td className="px-4 py-3 text-gray-600">{app.grado_militar || app.relacion_r11 || '—'}</td>
                <td className="px-4 py-3 text-gray-500 text-xs">{app.email}</td>
                <td className="px-4 py-3 text-gray-500 text-xs">
                  {app.fecha_solicitud ? new Date(app.fecha_solicitud).toLocaleDateString('es-CL') : '—'}
                </td>
                <td className="px-4 py-3">
                  <div className="flex gap-1">
                    <button onClick={() => approvePendingCredit(app)} disabled={pendingActing === app.id}
                      className="rounded p-1 hover:bg-green-50" title="Aprobar">
                      <CheckCircle className="h-4 w-4 text-green-500" />
                    </button>
                    <button onClick={() => rejectPendingCredit(app)} disabled={pendingActing === app.id}
                      className="rounded p-1 hover:bg-red-50" title="Rechazar">
                      <XCircle className="h-4 w-4 text-red-500" />
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    );
  };

  /* ─── Main render ─── */
  return (
    <div className="space-y-4 pt-4">
      {activeTab === 'r11' && renderR11()}
      {activeTab === 'rl6' && renderRL6()}
      {activeTab === 'solicitudes' && renderSolicitudes()}

      {/* User detail modal */}
      {selectedUser && (
        <UserDetailModal
          user={selectedUser}
          onClose={() => setSelectedUser(null)}
          onApproveReceipt={(orderNumber) => {
            setSelectedUser(prev => prev ? { ...prev } : null);
          }}
          onRejectReceipt={(orderNumber) => {
            setSelectedUser(prev => prev ? { ...prev } : null);
          }}
        />
      )}

      {/* Email preview modal */}
      <EmailPreviewModal
        open={!!emailPreview}
        onClose={closeEmailPreview}
        onConfirm={rl6SendEmail}
        html={emailPreview?.html ?? ''}
        emailTipo={emailPreview?.tipo ?? 'recordatorio'}
        recipientEmail={emailPreview?.email ?? ''}
        sending={emailSending}
      />
    </div>
  );
}
