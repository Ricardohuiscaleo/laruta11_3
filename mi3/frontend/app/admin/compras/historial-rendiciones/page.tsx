'use client';

import { useState, useEffect, useCallback } from 'react';
import { Check, Clock, X, Edit2, RefreshCw } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';

interface RendicionItem {
  id: number; token: string; saldo_anterior: number; total_compras: number;
  saldo_resultante: number; monto_transferido: number | null; saldo_nuevo: number | null;
  estado: string; created_at: string; aprobado_at: string | null;
}

export default function HistorialRendicionesPage() {
  const [rendiciones, setRendiciones] = useState<RendicionItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [rectifyId, setRectifyId] = useState<number | null>(null);
  const [rectifyMonto, setRectifyMonto] = useState('');
  const [rectifyMotivo, setRectifyMotivo] = useState('');
  const [rectifying, setRectifying] = useState(false);

  const fetchRendiciones = useCallback(async () => {
    setLoading(true);
    try {
      const res = await comprasApi.get<{ success: boolean; rendiciones: RendicionItem[] }>('/kpis/rendiciones');
      setRendiciones(res.rendiciones || []);
    } catch {}
    setLoading(false);
  }, []);

  useEffect(() => { fetchRendiciones(); }, [fetchRendiciones]);

  const openRectify = (r: RendicionItem) => {
    setRectifyId(r.id);
    setRectifyMonto(String(r.monto_transferido ?? 0));
    setRectifyMotivo('');
  };

  const submitRectify = async () => {
    if (rectifyId === null) return;
    setRectifying(true);
    try {
      await comprasApi.post(`/rendiciones/${rectifyId}/rectificar`, {
        monto_transferido: parseFloat(rectifyMonto.replace(/\./g, '').replace(/,/g, '')),
        motivo: rectifyMotivo || null,
      });
      setRectifyId(null);
      fetchRendiciones();
    } catch {
      alert('Error al rectificar');
    } finally {
      setRectifying(false);
    }
  };

  if (loading) return <div className="p-6 text-center text-sm text-gray-500">Cargando...</div>;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-gray-700">Historial de Rendiciones</h2>
        <button onClick={fetchRendiciones} className="rounded-lg border p-1.5 text-gray-500 hover:bg-gray-100">
          <RefreshCw className="h-3.5 w-3.5" />
        </button>
      </div>

      {rendiciones.length === 0 && (
        <p className="text-sm text-gray-400 text-center py-8">Sin rendiciones aún</p>
      )}

      <div className="space-y-2">
        {rendiciones.map(r => {
          const badge = r.estado === 'aprobada'
            ? <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] text-green-700"><Check className="h-2.5 w-2.5" />Aprobada</span>
            : r.estado === 'rechazada'
            ? <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] text-red-700"><X className="h-2.5 w-2.5" />Rechazada</span>
            : <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] text-amber-700"><Clock className="h-2.5 w-2.5" />Pendiente</span>;

          return (
            <div key={r.id} className="rounded-xl border bg-white p-4 shadow-sm">
              {rectifyId === r.id ? (
                <div className="space-y-2">
                  <p className="text-xs font-semibold text-gray-700">Rectificar monto transferido</p>
                  <div className="flex gap-2">
                    <input
                      type="text"
                      value={rectifyMonto}
                      onChange={e => setRectifyMonto(e.target.value)}
                      className="w-full rounded border px-2 py-1 text-xs"
                      placeholder="Nuevo monto"
                    />
                    <input
                      type="text"
                      value={rectifyMotivo}
                      onChange={e => setRectifyMotivo(e.target.value)}
                      className="w-full rounded border px-2 py-1 text-xs"
                      placeholder="Motivo (opcional)"
                    />
                  </div>
                  <div className="flex gap-2">
                    <button onClick={submitRectify} disabled={rectifying}
                      className="rounded bg-blue-600 px-3 py-1 text-xs text-white disabled:opacity-50">
                      {rectifying ? 'Guardando...' : 'Guardar'}
                    </button>
                    <button onClick={() => setRectifyId(null)}
                      className="rounded bg-gray-200 px-3 py-1 text-xs">
                      Cancelar
                    </button>
                  </div>
                </div>
              ) : (
                <>
                  <div className="flex items-center justify-between mb-2">
                    {badge}
                    <div className="flex items-center gap-2">
                      {r.estado === 'aprobada' && (
                        <button onClick={() => openRectify(r)}
                          className="inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-[10px] text-amber-700 hover:bg-amber-200">
                          <Edit2 className="h-2.5 w-2.5" />Rectificar
                        </button>
                      )}
                      <span className="text-xs text-gray-400">{new Date(r.created_at).toLocaleDateString('es-CL')}</span>
                    </div>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center text-xs">
                    <div>
                      <p className="text-gray-500">Saldo ant.</p>
                      <p className="font-semibold">{formatearPesosCLP(r.saldo_anterior)}</p>
                    </div>
                    <div>
                      <p className="text-gray-500">Compras</p>
                      <p className="font-semibold text-red-600">-{formatearPesosCLP(r.total_compras)}</p>
                    </div>
                    <div>
                      <p className="text-gray-500">{r.saldo_resultante >= 0 ? 'Caja' : 'Bolsillo'}</p>
                      <p className={`font-semibold ${r.saldo_resultante >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {formatearPesosCLP(r.saldo_resultante)}
                      </p>
                    </div>
                  </div>
                  {r.monto_transferido != null && (
                    <div className="grid grid-cols-2 gap-2 text-center text-xs mt-2 pt-2 border-t">
                      <div>
                        <p className="text-gray-500">Transferido</p>
                        <p className="font-semibold text-blue-600">+{formatearPesosCLP(r.monto_transferido)}</p>
                      </div>
                      <div>
                        <p className="text-gray-500">Saldo nuevo</p>
                        <p className="font-bold text-green-700">{formatearPesosCLP(r.saldo_nuevo ?? 0)}</p>
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
