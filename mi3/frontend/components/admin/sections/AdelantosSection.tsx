'use client';

import { useEffect, useState, useRef } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatDateES, cn } from '@/lib/utils';
import { Loader2, CheckCircle, XCircle, ChevronDown, ChevronUp } from 'lucide-react';

interface Prestamo {
  id: number;
  personal_id: number;
  monto_solicitado: number;
  monto_aprobado: number | null;
  motivo: string | null;
  estado: 'pendiente' | 'aprobado' | 'rechazado' | 'pagado' | 'cancelado';
  notas_admin: string | null;
  cuotas: number;
  created_at: string;
  personal: { id: number; nombre: string };
}

interface AdelantosSectionProps {
  highlightId?: number | null;
  onNavigate?: (section: string, params?: any) => void;
}

export default function AdelantosSection({ highlightId, onNavigate }: AdelantosSectionProps) {
  const [loans, setLoans] = useState<Prestamo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [expandedId, setExpandedId] = useState<number | null>(highlightId ?? null);
  const [actionMode, setActionMode] = useState<Record<number, 'approve' | 'reject' | null>>({});
  const [formData, setFormData] = useState<Record<number, { monto: string; notas: string }>>({});
  const [acting, setActing] = useState<number | null>(null);
  const [actionError, setActionError] = useState<Record<number, string>>({});
  const [showHistory, setShowHistory] = useState(false);
  const highlightRef = useRef<HTMLDivElement>(null);

  const fetchData = () => {
    setLoading(true);
    apiFetch<{ success: boolean; data: Prestamo[] }>('/admin/loans')
      .then(res => {
        setLoans(res.data || []);
        // Pre-fill form data for pending items
        const initial: Record<number, { monto: string; notas: string }> = {};
        (res.data || []).forEach(l => {
          if (l.estado === 'pendiente') {
            initial[l.id] = { monto: String(l.monto_solicitado), notas: '' };
          }
        });
        setFormData(prev => ({ ...initial, ...prev }));
      })
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchData(); }, []);

  useEffect(() => {
    if (highlightId) {
      setExpandedId(highlightId);
      setTimeout(() => highlightRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
    }
  }, [highlightId]);

  const openAction = (id: number, mode: 'approve' | 'reject') => {
    setActionMode(prev => ({ ...prev, [id]: mode }));
    setActionError(prev => ({ ...prev, [id]: '' }));
    if (mode === 'approve') {
      const loan = loans.find(l => l.id === id);
      if (loan) {
        setFormData(prev => ({
          ...prev,
          [id]: { monto: String(loan.monto_solicitado), notas: prev[id]?.notas || '' },
        }));
      }
    }
  };

  const cancelAction = (id: number) => {
    setActionMode(prev => ({ ...prev, [id]: null }));
    setActionError(prev => ({ ...prev, [id]: '' }));
  };

  const handleApprove = async (id: number) => {
    const data = formData[id];
    if (!data?.monto || Number(data.monto) <= 0) {
      setActionError(prev => ({ ...prev, [id]: 'Monto debe ser mayor a 0' }));
      return;
    }
    setActing(id);
    setActionError(prev => ({ ...prev, [id]: '' }));
    try {
      await apiFetch(`/admin/loans/${id}/approve`, {
        method: 'POST',
        body: JSON.stringify({ monto_aprobado: Number(data.monto), notas: data.notas || undefined }),
      });
      setActionMode(prev => ({ ...prev, [id]: null }));
      fetchData();
    } catch (err: any) {
      setActionError(prev => ({ ...prev, [id]: err.message }));
    } finally {
      setActing(null);
    }
  };

  const handleReject = async (id: number) => {
    setActing(id);
    setActionError(prev => ({ ...prev, [id]: '' }));
    try {
      await apiFetch(`/admin/loans/${id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ notas: formData[id]?.notas || undefined }),
      });
      setActionMode(prev => ({ ...prev, [id]: null }));
      fetchData();
    } catch (err: any) {
      setActionError(prev => ({ ...prev, [id]: err.message }));
    } finally {
      setActing(null);
    }
  };

  const statusBadge = (estado: string) => {
    const colors: Record<string, string> = {
      pendiente: 'bg-yellow-100 text-yellow-700',
      aprobado: 'bg-green-100 text-green-700',
      rechazado: 'bg-red-100 text-red-700',
      pagado: 'bg-blue-100 text-blue-700',
      cancelado: 'bg-gray-100 text-gray-500',
    };
    return <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', colors[estado] || 'bg-gray-100 text-gray-500')}>{estado}</span>;
  };

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" aria-label="Cargando" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600" role="alert">{error}</div>;

  const pending = loans.filter(l => l.estado === 'pendiente');
  const resolved = loans.filter(l => l.estado !== 'pendiente');

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Adelantos de Sueldo</h1>

      {pending.length > 0 ? (
        <div className="space-y-3">
          <h2 className="text-sm font-semibold text-amber-700">Pendientes ({pending.length})</h2>
          {pending.map(loan => {
            const isHighlighted = highlightId === loan.id;
            const mode = actionMode[loan.id];
            const errMsg = actionError[loan.id];
            const fd = formData[loan.id] || { monto: String(loan.monto_solicitado), notas: '' };

            return (
              <div
                key={loan.id}
                ref={isHighlighted ? highlightRef : undefined}
                className={cn(
                  'rounded-xl border-2 p-4 shadow-sm transition-colors',
                  isHighlighted ? 'border-amber-400 bg-amber-50' : 'border-yellow-200 bg-yellow-50'
                )}
              >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0 flex-1">
                    <p className="font-medium text-gray-900">{loan.personal.nombre}</p>
                    <p className="text-lg font-bold text-amber-700">{formatCLP(loan.monto_solicitado)}</p>
                    {loan.motivo && <p className="mt-1 text-sm text-gray-600">{loan.motivo}</p>}
                    <p className="mt-1 text-xs text-gray-400">{formatDateES(loan.created_at.split('T')[0])}</p>
                  </div>

                  {!mode && (
                    <div className="flex gap-2 sm:flex-col">
                      <button
                        onClick={() => openAction(loan.id, 'approve')}
                        disabled={acting !== null}
                        className="flex min-h-[44px] min-w-[44px] items-center gap-1.5 rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
                        aria-label={`Aprobar adelanto de ${loan.personal.nombre}`}
                      >
                        <CheckCircle className="h-4 w-4" /> Aprobar
                      </button>
                      <button
                        onClick={() => openAction(loan.id, 'reject')}
                        disabled={acting !== null}
                        className="flex min-h-[44px] min-w-[44px] items-center gap-1.5 rounded-lg bg-red-500 px-3 py-2 text-sm font-medium text-white hover:bg-red-600 disabled:opacity-50"
                        aria-label={`Rechazar adelanto de ${loan.personal.nombre}`}
                      >
                        <XCircle className="h-4 w-4" /> Rechazar
                      </button>
                    </div>
                  )}
                </div>

                {/* Inline approve form */}
                {mode === 'approve' && (
                  <div className="mt-3 space-y-3 rounded-lg border border-green-200 bg-white p-3">
                    <div>
                      <label htmlFor={`monto-${loan.id}`} className="block text-sm font-medium text-gray-700">
                        Monto a aprobar
                      </label>
                      <input
                        id={`monto-${loan.id}`}
                        type="number"
                        min={1}
                        value={fd.monto}
                        onChange={e => setFormData(prev => ({ ...prev, [loan.id]: { ...fd, monto: e.target.value } }))}
                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                      />
                    </div>
                    <div>
                      <label htmlFor={`notas-approve-${loan.id}`} className="block text-sm font-medium text-gray-700">
                        Notas (opcional)
                      </label>
                      <textarea
                        id={`notas-approve-${loan.id}`}
                        rows={2}
                        value={fd.notas}
                        onChange={e => setFormData(prev => ({ ...prev, [loan.id]: { ...fd, notas: e.target.value } }))}
                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                        placeholder="Notas para el trabajador..."
                      />
                    </div>
                    {errMsg && <div className="rounded-md bg-red-50 p-2 text-sm text-red-600" role="alert">{errMsg}</div>}
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleApprove(loan.id)}
                        disabled={acting === loan.id}
                        className="flex min-h-[44px] items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                      >
                        {acting === loan.id && <Loader2 className="h-4 w-4 animate-spin" />}
                        Confirmar aprobación
                      </button>
                      <button
                        onClick={() => cancelAction(loan.id)}
                        disabled={acting === loan.id}
                        className="min-h-[44px] rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                      >
                        Cancelar
                      </button>
                    </div>
                  </div>
                )}

                {/* Inline reject form */}
                {mode === 'reject' && (
                  <div className="mt-3 space-y-3 rounded-lg border border-red-200 bg-white p-3">
                    <div>
                      <label htmlFor={`notas-reject-${loan.id}`} className="block text-sm font-medium text-gray-700">
                        Motivo del rechazo (opcional)
                      </label>
                      <textarea
                        id={`notas-reject-${loan.id}`}
                        rows={2}
                        value={fd.notas}
                        onChange={e => setFormData(prev => ({ ...prev, [loan.id]: { ...fd, notas: e.target.value } }))}
                        className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500"
                        placeholder="Motivo del rechazo..."
                      />
                    </div>
                    {errMsg && <div className="rounded-md bg-red-50 p-2 text-sm text-red-600" role="alert">{errMsg}</div>}
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleReject(loan.id)}
                        disabled={acting === loan.id}
                        className="flex min-h-[44px] items-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                      >
                        {acting === loan.id && <Loader2 className="h-4 w-4 animate-spin" />}
                        Confirmar rechazo
                      </button>
                      <button
                        onClick={() => cancelAction(loan.id)}
                        disabled={acting === loan.id}
                        className="min-h-[44px] rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50"
                      >
                        Cancelar
                      </button>
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      ) : (
        <div className="rounded-xl border bg-white p-6 text-center shadow-sm">
          <p className="text-sm text-gray-500">Sin adelantos pendientes</p>
        </div>
      )}

      {/* Collapsible history */}
      {resolved.length > 0 && (
        <div>
          <button
            onClick={() => setShowHistory(prev => !prev)}
            className="flex min-h-[44px] items-center gap-2 text-sm font-semibold text-gray-500 hover:text-gray-700"
            aria-expanded={showHistory}
          >
            {showHistory ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
            Historial ({resolved.length})
          </button>
          {showHistory && (
            <div className="mt-2 space-y-2">
              {resolved.map(loan => (
                <div key={loan.id} className="rounded-xl border bg-white p-4 shadow-sm">
                  <div className="flex items-center justify-between">
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-medium text-gray-900">{loan.personal.nombre}</p>
                      <p className="text-sm text-gray-600">
                        Solicitado: {formatCLP(loan.monto_solicitado)}
                        {loan.monto_aprobado != null && <> · Aprobado: {formatCLP(loan.monto_aprobado)}</>}
                      </p>
                      {loan.notas_admin && <p className="mt-0.5 text-xs text-gray-400">{loan.notas_admin}</p>}
                      <p className="mt-0.5 text-xs text-gray-400">{formatDateES(loan.created_at.split('T')[0])}</p>
                    </div>
                    {statusBadge(loan.estado)}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
