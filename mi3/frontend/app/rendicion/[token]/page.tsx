'use client';

import { useState, useEffect } from 'react';
import { Check, X, Clock, FileText, Loader2, Image as ImageIcon } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const fmt = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

interface Rendicion {
  id: number; token: string; saldo_anterior: number; total_compras: number;
  saldo_resultante: number; monto_transferido: number | null; saldo_nuevo: number | null;
  estado: string; creado_por: string; aprobado_por: string | null;
  created_at: string; aprobado_at: string | null; notas: string | null;
}

interface CompraItem { nombre: string; cantidad: number; unidad: string; precio_unitario: number }
interface CompraData { id: number; fecha_compra: string; proveedor: string; monto_total: number; items: CompraItem[]; imagenes: string[] }

export default function RendicionPublicPage({ params }: { params: { token: string } }) {
  const [rendicion, setRendicion] = useState<Rendicion | null>(null);
  const [compras, setCompras] = useState<CompraData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [monto, setMonto] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  useEffect(() => {
    fetch(`${API}/api/v1/rendicion/${params.token}`, { headers: { Accept: 'application/json' } })
      .then(r => r.json())
      .then(d => { if (d.success) { setRendicion(d.rendicion); setCompras(d.compras); } else setError('Rendición no encontrada'); })
      .catch(() => setError('Error de conexión'))
      .finally(() => setLoading(false));
  }, [params.token]);

  const handleAprobar = async () => {
    if (!monto || parseFloat(monto) < 0) return;
    setSubmitting(true);
    try {
      const res = await fetch(`${API}/api/v1/rendicion/${params.token}/aprobar`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ monto_transferido: parseFloat(monto) }),
      }).then(r => r.json());
      if (res.success) { setRendicion(res.rendicion); setDone(true); }
      else alert(res.error || 'Error');
    } catch { alert('Error de conexión'); }
    setSubmitting(false);
  };

  const handleRechazar = async () => {
    setSubmitting(true);
    try {
      const res = await fetch(`${API}/api/v1/rendicion/${params.token}/rechazar`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({}),
      }).then(r => r.json());
      if (res.success) { setRendicion(res.rendicion); setDone(true); }
    } catch { alert('Error'); }
    setSubmitting(false);
  };

  if (loading) return <div className="flex min-h-screen items-center justify-center"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (error) return <div className="flex min-h-screen items-center justify-center text-red-500">{error}</div>;
  if (!rendicion) return null;

  const isPendiente = rendicion.estado === 'pendiente';

  return (
    <div className="mx-auto max-w-lg p-4 space-y-4">
      {/* Header */}
      <div className="text-center">
        <h1 className="text-xl font-bold text-gray-900">📋 Rendición de Gastos</h1>
        <p className="text-xs text-gray-400 mt-1">La Ruta 11 — {new Date(rendicion.created_at).toLocaleDateString('es-CL')}</p>
      </div>

      {/* Status badge */}
      <div className="text-center">
        {rendicion.estado === 'aprobada' && <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm text-green-700"><Check className="h-4 w-4" /> Aprobada</span>}
        {rendicion.estado === 'rechazada' && <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-sm text-red-700"><X className="h-4 w-4" /> Rechazada</span>}
        {rendicion.estado === 'pendiente' && <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-sm text-amber-700"><Clock className="h-4 w-4" /> Pendiente de aprobación</span>}
      </div>

      {/* Summary */}
      <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
        <div className="grid grid-cols-3 gap-3 text-center">
          <div className="rounded-lg bg-gray-50 p-3">
            <p className="text-xs text-gray-500">Saldo anterior</p>
            <p className="text-base font-bold">{fmt(rendicion.saldo_anterior)}</p>
          </div>
          <div className="rounded-lg bg-red-50 p-3">
            <p className="text-xs text-gray-500">Gastado</p>
            <p className="text-base font-bold text-red-600">-{fmt(rendicion.total_compras)}</p>
          </div>
          <div className={`rounded-lg p-3 ${rendicion.saldo_resultante >= 0 ? 'bg-green-50' : 'bg-amber-50'}`}>
            <p className="text-xs text-gray-500">{rendicion.saldo_resultante >= 0 ? 'A devolver' : 'Faltante'}</p>
            <p className={`text-base font-bold ${rendicion.saldo_resultante >= 0 ? 'text-green-700' : 'text-amber-700'}`}>
              {fmt(Math.abs(rendicion.saldo_resultante))}
            </p>
          </div>
        </div>

        {rendicion.monto_transferido != null && (
          <div className="grid grid-cols-2 gap-3 text-center border-t pt-3">
            <div className="rounded-lg bg-blue-50 p-3">
              <p className="text-xs text-gray-500">Transferido</p>
              <p className="text-base font-bold text-blue-700">+{fmt(rendicion.monto_transferido)}</p>
            </div>
            <div className="rounded-lg bg-green-50 p-3">
              <p className="text-xs text-gray-500">Saldo nuevo</p>
              <p className="text-base font-bold text-green-700">{fmt(rendicion.saldo_nuevo ?? 0)}</p>
            </div>
          </div>
        )}
      </div>

      {/* Compras detail */}
      <div className="rounded-xl border bg-white p-4 shadow-sm space-y-2">
        <h3 className="text-sm font-semibold text-gray-700">Compras ({compras.length})</h3>
        {compras.map((c, i) => (
          <div key={c.id} className="rounded-lg border bg-gray-50 p-3 space-y-1">
            <div className="flex items-center justify-between">
              <span className="text-sm font-semibold">{i + 1}. {c.proveedor}</span>
              <span className="text-sm font-bold">{fmt(c.monto_total)}</span>
            </div>
            <p className="text-xs text-gray-400">📅 {c.fecha_compra}</p>
            {c.items.map((item, j) => (
              <p key={j} className="text-xs text-gray-600">• {item.nombre} ({item.cantidad} {item.unidad})</p>
            ))}
            {c.imagenes.length > 0 && (
              <div className="flex gap-1 mt-1">
                {c.imagenes.map((url, k) => (
                  <a key={k} href={url} target="_blank" rel="noopener" className="text-xs text-mi3-600 hover:underline flex items-center gap-0.5">
                    <ImageIcon className="h-3 w-3" /> Foto {k + 1}
                  </a>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Approve/Reject (only if pending) */}
      {isPendiente && !done && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
          <h3 className="text-sm font-semibold text-gray-700">Aprobar rendición</h3>
          <div>
            <label className="text-xs text-gray-500">Monto a transferir ($)</label>
            <input type="number" value={monto} onChange={e => setMonto(e.target.value)}
              placeholder={Math.abs(rendicion.saldo_resultante).toString()}
              className="w-full rounded-lg border px-3 py-2 text-base mt-1" />
            <p className="text-xs text-gray-400 mt-1">
              {rendicion.saldo_resultante < 0
                ? `Mínimo sugerido: ${fmt(Math.abs(rendicion.saldo_resultante))} (cubre el faltante)`
                : 'El cajero tiene saldo a favor. Puede transferir $0 o más para generar caja.'}
            </p>
          </div>
          <div className="flex gap-2">
            <button onClick={handleAprobar} disabled={submitting || !monto}
              className="flex-1 rounded-lg bg-green-600 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2">
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
              Aprobar
            </button>
            <button onClick={handleRechazar} disabled={submitting}
              className="rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-50">
              Rechazar
            </button>
          </div>
        </div>
      )}

      {done && (
        <div className="rounded-xl border bg-green-50 p-4 text-center text-sm text-green-700">
          <Check className="mx-auto h-6 w-6 mb-2" />
          Rendición {rendicion.estado}
        </div>
      )}
    </div>
  );
}
