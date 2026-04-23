'use client';

import { useEffect, useState, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, CheckCircle, XCircle, DollarSign, Mail, AlertTriangle } from 'lucide-react';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';
import type { RL6CreditUser, RL6Summary, EmailEstado } from '@/types/admin';
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

type CreditTab = 'r11' | 'rl6';

/* ─── Props ─── */

interface CreditosSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

export default function CreditosSection({ onHeaderConfig }: CreditosSectionProps) {
  /* ─── Tab state ─── */
  const [activeTab, setActiveTab] = useState<CreditTab>('r11');

  /* ─── R11 state (existing) ─── */
  const [credits, setCredits] = useState<CreditUser[]>([]);
  const [r11Loading, setR11Loading] = useState(true);
  const [r11Error, setR11Error] = useState('');
  const [acting, setActing] = useState<number | null>(null);

  /* ─── RL6 state (new) ─── */
  const [rl6Data, setRl6Data] = useState<RL6CreditUser[] | null>(null);
  const [rl6Summary, setRl6Summary] = useState<RL6Summary | null>(null);
  const [rl6Loading, setRl6Loading] = useState(false);
  const [rl6Error, setRl6Error] = useState('');
  const [rl6Acting, setRl6Acting] = useState<number | null>(null);

  /* ─── Email preview state ─── */
  const [emailPreview, setEmailPreview] = useState<{ html: string; tipo: EmailEstado; email: string } | null>(null);
  const [emailSending, setEmailSending] = useState(false);
  const [emailPreviewUser, setEmailPreviewUser] = useState<RL6CreditUser | null>(null);

  /* ─── Bulk action state ─── */
  const [bulkSending, setBulkSending] = useState(false);
  const [bulkResult, setBulkResult] = useState<{ total_sent: number; total_failed: number; failed: string[] } | null>(null);

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as CreditTab);
  }, []);

  /* ─── Register tabs via onHeaderConfig ─── */
  useEffect(() => {
    onHeaderConfig?.({
      tabs: [
        { key: 'r11', label: 'Créditos R11' },
        { key: 'rl6', label: 'Créditos RL6' },
      ],
      activeTab,
      onTabChange: handleTabChange,
      trailing: (
        <CreditSummaryTrailing
          activeTab={activeTab}
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

  /* ─── RL6 data fetch (lazy load on tab activation) ─── */
  const fetchRL6 = useCallback(() => {
    setRl6Loading(true);
    setRl6Error('');
    apiFetch<{ data: RL6CreditUser[]; summary: RL6Summary }>('/admin/credits/rl6')
      .then(res => {
        setRl6Data(res.data || []);
        setRl6Summary(res.summary || null);
      })
      .catch(e => setRl6Error(e.message))
      .finally(() => setRl6Loading(false));
  }, []);

  useEffect(() => {
    if (activeTab === 'rl6' && rl6Data === null) {
      fetchRL6();
    }
  }, [activeTab, rl6Data, fetchRL6]);

  /* ─── R11 actions (existing) ─── */
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

  /* ─── Mora badge helper ─── */
  const renderMoraBadge = (user: RL6CreditUser) => {
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
        {/* Bulk action bar */}
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

        {/* Bulk result summary */}
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
              <tr key={u.id}>
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
                <td className="px-4 py-3">
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

  /* ─── Main render ─── */
  return (
    <div className="space-y-4 pt-4">
      {activeTab === 'r11' && renderR11()}
      {activeTab === 'rl6' && renderRL6()}

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
