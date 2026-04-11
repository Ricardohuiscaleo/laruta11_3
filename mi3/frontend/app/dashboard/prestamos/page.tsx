'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2,
  Plus,
  X,
  Wallet,
  Clock,
  CheckCircle2,
  XCircle,
  Ban,
} from 'lucide-react';
import type { Prestamo, ApiResponse } from '@/types';

/* ─── Estado badge colors ─── */
const estadoConfig: Record<
  Prestamo['estado'],
  { label: string; className: string; icon: React.ReactNode }
> = {
  pendiente: {
    label: 'Pendiente',
    className: 'bg-yellow-100 text-yellow-700',
    icon: <Clock className="h-3.5 w-3.5" />,
  },
  aprobado: {
    label: 'Aprobado',
    className: 'bg-green-100 text-green-700',
    icon: <CheckCircle2 className="h-3.5 w-3.5" />,
  },
  rechazado: {
    label: 'Rechazado',
    className: 'bg-red-100 text-red-700',
    icon: <XCircle className="h-3.5 w-3.5" />,
  },
  pagado: {
    label: 'Pagado',
    className: 'bg-blue-100 text-blue-700',
    icon: <CheckCircle2 className="h-3.5 w-3.5" />,
  },
  cancelado: {
    label: 'Cancelado',
    className: 'bg-gray-100 text-gray-600',
    icon: <Ban className="h-3.5 w-3.5" />,
  },
};

function EstadoBadge({ estado }: { estado: Prestamo['estado'] }) {
  const cfg = estadoConfig[estado];
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
        cfg.className
      )}
    >
      {cfg.icon}
      {cfg.label}
    </span>
  );
}

/* ─── Progress bar for active loans ─── */
function ProgressBar({ pagadas, total }: { pagadas: number; total: number }) {
  const pct = total > 0 ? Math.round((pagadas / total) * 100) : 0;
  return (
    <div className="mt-2">
      <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
        <span>
          {pagadas} de {total} cuota{total !== 1 ? 's' : ''}
        </span>
        <span>{pct}%</span>
      </div>
      <div className="h-2 w-full rounded-full bg-gray-200">
        <div
          className="h-2 rounded-full bg-red-500 transition-all"
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

/* ─── Loan card ─── */
function PrestamoCard({ prestamo }: { prestamo: Prestamo }) {
  const isActive =
    prestamo.estado === 'aprobado' && prestamo.cuotas_pagadas < prestamo.cuotas;
  const montoMostrar = prestamo.monto_aprobado ?? prestamo.monto_solicitado;
  const montoCuota =
    prestamo.monto_aprobado && prestamo.cuotas > 0
      ? Math.round(prestamo.monto_aprobado / prestamo.cuotas)
      : null;

  // Next deduction date: 1st of next month from now
  const getProximaFecha = () => {
    if (!isActive) return null;
    const now = new Date();
    const next = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    return next.toLocaleDateString('es-CL', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  };

  return (
    <div className="rounded-xl border bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-lg font-bold text-gray-900">
            {formatCLP(montoMostrar)}
          </p>
          {prestamo.monto_aprobado !== null &&
            prestamo.monto_aprobado !== prestamo.monto_solicitado && (
              <p className="text-xs text-gray-400 line-through">
                Solicitado: {formatCLP(prestamo.monto_solicitado)}
              </p>
            )}
        </div>
        <EstadoBadge estado={prestamo.estado} />
      </div>

      <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
        <span>
          {prestamo.cuotas} cuota{prestamo.cuotas !== 1 ? 's' : ''}
        </span>
        <span>
          {new Date(prestamo.created_at).toLocaleDateString('es-CL')}
        </span>
      </div>

      {prestamo.motivo && (
        <p className="mt-2 text-sm text-gray-600">{prestamo.motivo}</p>
      )}

      {prestamo.notas_admin && (
        <p className="mt-1 text-xs text-gray-400 italic">
          Admin: {prestamo.notas_admin}
        </p>
      )}

      {isActive && (
        <>
          <ProgressBar
            pagadas={prestamo.cuotas_pagadas}
            total={prestamo.cuotas}
          />
          {montoCuota !== null && (
            <div className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm">
              <p className="text-red-700">
                Próxima cuota: <span className="font-semibold">{formatCLP(montoCuota)}</span>
              </p>
              {getProximaFecha() && (
                <p className="text-xs text-red-500">
                  Descuento estimado: {getProximaFecha()}
                </p>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}

/* ─── Modal overlay for loan request ─── */
function SolicitarModal({
  onClose,
  onSuccess,
}: {
  onClose: () => void;
  onSuccess: () => void;
}) {
  const [monto, setMonto] = useState('');
  const [cuotas, setCuotas] = useState('1');
  const [motivo, setMotivo] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');

    const montoNum = Number(monto);
    if (!montoNum || montoNum <= 0) {
      setFormError('El monto debe ser mayor a $0');
      return;
    }
    const cuotasNum = Number(cuotas);
    if (cuotasNum < 1 || cuotasNum > 3) {
      setFormError('Las cuotas deben ser entre 1 y 3');
      return;
    }

    setSubmitting(true);
    try {
      await apiFetch('/worker/loans', {
        method: 'POST',
        body: JSON.stringify({
          monto: montoNum,
          cuotas: cuotasNum,
          motivo: motivo.trim() || null,
        }),
      });
      onSuccess();
    } catch (err: any) {
      setFormError(err.message || 'Error al enviar solicitud');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/40"
        onClick={onClose}
      />
      {/* Panel */}
      <div className="relative w-full max-w-md rounded-t-2xl sm:rounded-2xl bg-white p-5 shadow-xl">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-bold text-gray-900">
            Solicitar Préstamo
          </h2>
          <button
            onClick={onClose}
            className="rounded-full p-1 hover:bg-gray-100"
          >
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        {formError && (
          <div className="mb-3 rounded-lg bg-red-50 p-2.5 text-sm text-red-600">
            {formError}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700">
              Monto solicitado
            </label>
            <input
              type="number"
              required
              min="1"
              placeholder="Ej: 200000"
              value={monto}
              onChange={(e) => setMonto(e.target.value)}
              className="mt-1 block w-full rounded-lg border px-3 py-2.5 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              Cuotas
            </label>
            <select
              value={cuotas}
              onChange={(e) => setCuotas(e.target.value)}
              className="mt-1 block w-full rounded-lg border px-3 py-2.5 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
            >
              <option value="1">1 cuota</option>
              <option value="2">2 cuotas</option>
              <option value="3">3 cuotas</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700">
              Motivo (opcional)
            </label>
            <input
              type="text"
              value={motivo}
              onChange={(e) => setMotivo(e.target.value)}
              placeholder="Ej: Gastos médicos"
              className="mt-1 block w-full rounded-lg border px-3 py-2.5 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
            />
          </div>

          <button
            type="submit"
            disabled={submitting}
            className="w-full rounded-lg bg-red-500 py-2.5 text-sm font-semibold text-white hover:bg-red-600 disabled:opacity-50"
          >
            {submitting ? 'Enviando...' : 'Enviar Solicitud'}
          </button>
        </form>
      </div>
    </div>
  );
}

/* ─── Main Page ─── */
export default function PrestamosPage() {
  const [prestamos, setPrestamos] = useState<Prestamo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');

  const hasActive = prestamos.some(
    (p) => p.estado === 'aprobado' && p.cuotas_pagadas < p.cuotas
  );

  const fetchLoans = () => {
    setLoading(true);
    apiFetch<ApiResponse<Prestamo[]>>('/worker/loans')
      .then((res) => setPrestamos(res.data || []))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchLoans();
  }, []);

  const handleSuccess = () => {
    setShowModal(false);
    setSuccessMsg('Solicitud enviada. El administrador la revisará pronto.');
    fetchLoans();
    setTimeout(() => setSuccessMsg(''), 4000);
  };

  if (loading) {
    return (
      <div className="flex justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-red-500" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">Préstamos</h1>
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-600">
          {error}
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Préstamos</h1>
        <button
          onClick={() => setShowModal(true)}
          disabled={hasActive}
          title={
            hasActive
              ? 'Debes completar tu préstamo activo antes de solicitar otro'
              : 'Solicitar préstamo'
          }
          className={cn(
            'flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white',
            hasActive
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
        >
          <Plus className="h-4 w-4" /> Solicitar Préstamo
        </button>
      </div>

      {/* Success toast */}
      {successMsg && (
        <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
          {successMsg}
        </div>
      )}

      {/* Active loan warning */}
      {hasActive && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          Tienes un préstamo activo. Debes completarlo antes de solicitar otro.
        </div>
      )}

      {/* Loan list or empty state */}
      {prestamos.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Wallet className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-gray-500">No tienes préstamos</p>
          <button
            onClick={() => setShowModal(true)}
            className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600"
          >
            <Plus className="h-4 w-4" /> Solicitar Préstamo
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          {prestamos.map((p) => (
            <PrestamoCard key={p.id} prestamo={p} />
          ))}
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <SolicitarModal
          onClose={() => setShowModal(false)}
          onSuccess={handleSuccess}
        />
      )}
    </div>
  );
}
