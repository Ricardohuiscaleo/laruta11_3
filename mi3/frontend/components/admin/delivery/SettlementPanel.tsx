'use client';

import { useState, useEffect, useRef } from 'react';
import { Upload, AlertTriangle, CheckCircle, Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api';

interface Settlement {
  id: number;
  settlement_date: string;
  total_orders_delivered: number;
  total_delivery_fees: number;
  status: 'pending' | 'paid';
  payment_voucher_url: string | null;
  paid_at: string | null;
}

interface UploadState {
  loading: boolean;
  warning: string | null;
  error: string | null;
  success: boolean;
}

function formatCLP(amount: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
}

function formatDate(dateStr: string) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-CL', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

export default function SettlementPanel() {
  const [settlements, setSettlements] = useState<Settlement[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploadStates, setUploadStates] = useState<Record<number, UploadState>>({});
  const fileInputRefs = useRef<Record<number, HTMLInputElement | null>>({});

  useEffect(() => {
    apiFetch<{ success: boolean; settlements: Settlement[] }>('/admin/delivery/settlements')
      .then((res) => {
        if (res.settlements) setSettlements(res.settlements);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleUpload = async (settlementId: number, file: File) => {
    setUploadStates((prev) => ({
      ...prev,
      [settlementId]: { loading: true, warning: null, error: null, success: false },
    }));

    try {
      const formData = new FormData();
      formData.append('voucher', file);

      const res = await apiFetch<{
        success: boolean;
        warning?: string;
        settlement?: Settlement;
      }>(`/admin/delivery/settlements/${settlementId}/voucher`, {
        method: 'POST',
        body: formData,
      });

      setUploadStates((prev) => ({
        ...prev,
        [settlementId]: {
          loading: false,
          warning: res.warning ?? null,
          error: null,
          success: true,
        },
      }));

      if (res.settlement) {
        setSettlements((prev) =>
          prev.map((s) => (s.id === settlementId ? res.settlement! : s))
        );
      }
    } catch (err: any) {
      setUploadStates((prev) => ({
        ...prev,
        [settlementId]: {
          loading: false,
          warning: null,
          error: err?.message ?? 'Error al subir comprobante',
          success: false,
        },
      }));
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-8">
        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
      </div>
    );
  }

  if (settlements.length === 0) {
    return (
      <p className="py-6 text-center text-sm text-gray-400">Sin liquidaciones registradas</p>
    );
  }

  return (
    <div className="space-y-3">
      {settlements.map((s) => {
        const state = uploadStates[s.id];
        const isPending = s.status === 'pending' && s.total_delivery_fees > 0;

        return (
          <div
            key={s.id}
            className="rounded-xl border bg-white p-4 shadow-sm space-y-3"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold text-gray-900">{formatDate(s.settlement_date)}</p>
                <p className="text-xs text-gray-500 mt-0.5">
                  {s.total_orders_delivered} pedido{s.total_orders_delivered !== 1 ? 's' : ''} entregado{s.total_orders_delivered !== 1 ? 's' : ''}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm font-bold text-gray-900">{formatCLP(s.total_delivery_fees)}</p>
                <span
                  className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                    s.status === 'paid'
                      ? 'bg-green-100 text-green-700'
                      : 'bg-amber-100 text-amber-700'
                  }`}
                >
                  {s.status === 'paid' ? 'Pagado' : 'Pendiente'}
                </span>
              </div>
            </div>

            {/* Warning from server */}
            {state?.warning && (
              <div className="flex items-start gap-2 rounded-lg bg-yellow-50 border border-yellow-200 px-3 py-2">
                <AlertTriangle className="h-4 w-4 shrink-0 text-yellow-600 mt-0.5" />
                <p className="text-xs text-yellow-700">{state.warning}</p>
              </div>
            )}

            {/* Error */}
            {state?.error && (
              <div className="rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                <p className="text-xs text-red-700">{state.error}</p>
              </div>
            )}

            {/* Success */}
            {state?.success && !state.warning && (
              <div className="flex items-center gap-2 rounded-lg bg-green-50 border border-green-200 px-3 py-2">
                <CheckCircle className="h-4 w-4 text-green-600" />
                <p className="text-xs text-green-700">Comprobante subido correctamente</p>
              </div>
            )}

            {/* Upload button */}
            {isPending && (
              <div>
                <input
                  ref={(el) => { fileInputRefs.current[s.id] = el; }}
                  type="file"
                  accept="image/*,application/pdf"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleUpload(s.id, file);
                  }}
                />
                <button
                  onClick={() => fileInputRefs.current[s.id]?.click()}
                  disabled={state?.loading}
                  className="flex w-full items-center justify-center gap-2 rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50 transition-colors"
                >
                  {state?.loading ? (
                    <><Loader2 className="h-4 w-4 animate-spin" /> Subiendo...</>
                  ) : (
                    <><Upload className="h-4 w-4" /> Subir comprobante</>
                  )}
                </button>
              </div>
            )}

            {s.payment_voucher_url && (
              <a
                href={s.payment_voucher_url}
                target="_blank"
                rel="noopener noreferrer"
                className="block text-center text-xs text-blue-600 hover:underline"
              >
                Ver comprobante
              </a>
            )}
          </div>
        );
      })}
    </div>
  );
}
