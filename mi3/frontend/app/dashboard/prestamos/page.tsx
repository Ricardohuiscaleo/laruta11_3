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
import type { Prestamo, AdelantoInfo, ApiResponse } from '@/types';

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
    label: 'Descontado',
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

/* ─── Adelanto card ─── */
function AdelantoCard({ prestamo }: { prestamo: Prestamo }) {
  const isActive =
    prestamo.estado === 'aprobado' && prestamo.cuotas_pagadas < prestamo.cuotas;
  const montoMostrar = prestamo.monto_aprobado ?? prestamo.monto_solicitado;

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

      <div className="mt-2 text-xs text-gray-500">
        {new Date(prestamo.created_at).toLocaleDateString('es-CL')}
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
        <div className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm">
          <p className="text-red-700">
            Se descontará completo a fin de mes:{' '}
            <span className="font-semibold">{formatCLP(prestamo.monto_aprobado!)}</span>
          </p>
        </div>
      )}
    </div>
  );
}


/* ─── Modal overlay for adelanto request ─── */
function SolicitarModal({
  onClose,
  onSuccess,
}: {
  onClose: () => void;
  onSuccess: () => void;
}) {
  const [monto, setMonto] = useState('');
  const [motivo, setMotivo] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [formError, setFormError] = useState('');
  const [info, setInfo] = useState<AdelantoInfo | null>(null);
  const [loadingInfo, setLoadingInfo] = useState(true);

  useEffect(() => {
    apiFetch<ApiResponse<AdelantoInfo>>('/worker/loans/info')
      .then((res) => setInfo(res.data ?? null))
      .catch(() => {})
      .finally(() => setLoadingInfo(false));
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');

    const montoNum = Number(monto);
    if (!montoNum || montoNum <= 0) {
      setFormError('El monto debe ser mayor a $0');
      return;
    }
    if (info && montoNum > info.monto_maximo) {
      setFormError(`El monto máximo disponible es ${formatCLP(info.monto_maximo)}`);
      return;
    }

    setSubmitting(true);
    try {
      await apiFetch('/worker/loans', {
        method: 'POST',
        body: JSON.stringify({
          monto: montoNum,
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
            Solicitar Adelanto
          </h2>
          <button
            onClick={onClose}
            className="rounded-full p-1 hover:bg-gray-100"
          >
            <X className="h-5 w-5 text-gray-400" />
          </button>
        </div>

        {/* Info about max amount */}
        {loadingInfo ? (
          <div className="mb-3 flex items-center gap-2 text-sm text-gray-400">
            <Loader2 className="h-4 w-4 animate-spin" /> Calculando monto disponible...
          </div>
        ) : info ? (
          <div className="mb-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-700">
            <p>
              Máximo disponible:{' '}
              <span className="font-semibold">{formatCLP(info.monto_maximo)}</span>
            </p>
            <p className="text-xs text-blue-500 mt-0.5">
              Proporcional a {info.dias_trabajados} día{info.dias_trabajados !== 1 ? 's' : ''} trabajado{info.dias_trabajados !== 1 ? 's' : ''} de {info.dias_totales_mes} en el mes
            </p>
          </div>
        ) : null}

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
              max={info?.monto_maximo}
              placeholder={info ? `Máx: ${formatCLP(info.monto_maximo)}` : 'Ej: 200000'}
              value={monto}
              onChange={(e) => setMonto(e.target.value)}
              className="mt-1 block w-full rounded-lg border px-3 py-2.5 text-sm focus:border-red-500 focus:ring-1 focus:ring-red-500"
            />
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

          <p className="text-xs text-gray-400">
            El adelanto se descontará completo de tu sueldo a fin de mes.
          </p>

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

  const hasPending = prestamos.some((p) => p.estado === 'pendiente');

  const canRequest = !hasActive && !hasPending;

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
        <h1 className="text-2xl font-bold text-gray-900">Adelantos</h1>
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
        <h1 className="text-2xl font-bold text-gray-900">Adelantos</h1>
        <button
          onClick={() => setShowModal(true)}
          disabled={!canRequest}
          title={
            hasActive
              ? 'Tienes un adelanto activo pendiente de descuento'
              : hasPending
                ? 'Ya tienes una solicitud pendiente de aprobación'
                : 'Solicitar adelanto'
          }
          className={cn(
            'flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white',
            !canRequest
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
        >
          <Plus className="h-4 w-4" /> Solicitar Adelanto
        </button>
      </div>

      {/* Success toast */}
      {successMsg && (
        <div className="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">
          {successMsg}
        </div>
      )}

      {/* Active adelanto warning */}
      {hasActive && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          Tienes un adelanto activo que se descontará a fin de mes.
        </div>
      )}

      {hasPending && !hasActive && (
        <div className="rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
          Tienes una solicitud pendiente de aprobación.
        </div>
      )}

      {/* Adelanto list or empty state */}
      {prestamos.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <Wallet className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-gray-500">No tienes adelantos</p>
          <button
            onClick={() => setShowModal(true)}
            className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600"
          >
            <Plus className="h-4 w-4" /> Solicitar Adelanto
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          {prestamos.map((p) => (
            <AdelantoCard key={p.id} prestamo={p} />
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
