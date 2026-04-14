'use client';

import { useState, useEffect } from 'react';
import { Check, X, Clock, Loader2 } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const fmt = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');
const roundUp = (n: number, to: number) => Math.ceil(n / to) * to;

interface Rendicion {
  id: number; token: string; saldo_anterior: number; total_compras: number;
  saldo_resultante: number; monto_transferido: number | null; saldo_nuevo: number | null;
  estado: string; created_at: string; aprobado_at: string | null; notas: string | null;
}
interface CompraItem { nombre: string; cantidad: number; unidad: string; precio_unitario: number }
interface CompraData { id: number; fecha_compra: string; proveedor: string; monto_total: number; items: CompraItem[]; imagenes: string[] }

export default function RendicionPublicPage({ params }: { params: { token: string } }) {
  const [rendicion, setRendicion] = useState<Rendicion | null>(null);
  const [compras, setCompras] = useState<CompraData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [monto, setMonto] = useState('');
  const [observaciones, setObservaciones] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);
  const [photoModal, setPhotoModal] = useState<string | null>(null);

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
        body: JSON.stringify({ monto_transferido: parseFloat(monto), notas: observaciones || null }),
      }).then(r => r.json());
      if (res.success) { setRendicion(res.rendicion); setDone(true); }
      else alert(res.error || 'Error');
    } catch { alert('Error de conexión'); }
    setSubmitting(false);
  };

  const handleRechazar = async () => {
    if (!observaciones) { alert('Escribe una observación para rechazar'); return; }
    setSubmitting(true);
    try {
      const res = await fetch(`${API}/api/v1/rendicion/${params.token}/rechazar`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ notas: observaciones }),
      }).then(r => r.json());
      if (res.success) { setRendicion(res.rendicion); setDone(true); }
    } catch { alert('Error'); }
    setSubmitting(false);
  };

  if (loading) return <div className="flex min-h-screen items-center justify-center"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (error) return <div className="flex min-h-screen items-center justify-center text-red-500">{error}</div>;
  if (!rendicion) return null;

  const isPendiente = rendicion.estado === 'pendiente';
  const saldoNegativo = rendicion.saldo_resultante < 0;
  const deuda = Math.abs(rendicion.saldo_resultante);
  const exactoRedondeado = saldoNegativo ? roundUp(deuda, 1000) : 0;
  const smart = saldoNegativo ? roundUp(deuda + 100000, 10000) : roundUp(100000, 10000);

  return (
    /* Full-width container with only 4px horizontal margin */
    <div style={{ maxWidth: '100%', padding: '0 4px', paddingBottom: '24px' }} className="space-y-2">

      {/* Header */}
      <div className="text-center py-3">
        <h1 className="text-xl font-bold text-gray-900">📋 Rendición de Gastos</h1>
        <p className="text-xs text-gray-400 mt-1">La Ruta 11 — {new Date(rendicion.created_at).toLocaleDateString('es-CL')}</p>
      </div>

      {/* Status */}
      <div className="text-center">
        {rendicion.estado === 'aprobada' && <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm text-green-700"><Check className="h-4 w-4" /> Aprobada</span>}
        {rendicion.estado === 'rechazada' && <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-sm text-red-700"><X className="h-4 w-4" /> Rechazada</span>}
        {isPendiente && <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-sm text-amber-700"><Clock className="h-4 w-4" /> Pendiente</span>}
      </div>

      {/* Summary — mobile friendly: 3 compact pills + optional transferred row */}
      <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
        <div className="grid grid-cols-3">
          <div className="p-2 text-center border-r">
            <p className="text-[10px] text-gray-500 leading-tight">Saldo anterior</p>
            <p className="text-sm font-bold mt-0.5">{fmt(rendicion.saldo_anterior)}</p>
          </div>
          <div className="p-2 text-center border-r bg-red-50">
            <p className="text-[10px] text-gray-500 leading-tight">Gastado</p>
            <p className="text-sm font-bold text-red-600 mt-0.5">-{fmt(rendicion.total_compras)}</p>
          </div>
          <div className={`p-2 text-center ${rendicion.saldo_resultante >= 0 ? 'bg-green-50' : 'bg-orange-50'}`}>
            <p className="text-[10px] text-gray-500 leading-tight">
              {rendicion.saldo_resultante >= 0 ? 'A favor' : 'Deuda'}
            </p>
            <p className={`text-sm font-bold mt-0.5 ${rendicion.saldo_resultante >= 0 ? 'text-green-700' : 'text-orange-700'}`}>
              {rendicion.saldo_resultante >= 0 ? fmt(rendicion.saldo_resultante) : fmt(Math.abs(rendicion.saldo_resultante))}
            </p>
          </div>
        </div>

        {rendicion.monto_transferido != null && (
          <div className="grid grid-cols-2 border-t">
            <div className="p-2 text-center border-r bg-blue-50">
              <p className="text-[10px] text-gray-500 leading-tight">Transferido</p>
              <p className="text-sm font-bold text-blue-700 mt-0.5">+{fmt(rendicion.monto_transferido)}</p>
            </div>
            <div className="p-2 text-center bg-green-50">
              <p className="text-[10px] text-gray-500 leading-tight">Saldo nuevo</p>
              <p className="text-sm font-bold text-green-700 mt-0.5">{fmt(rendicion.saldo_nuevo ?? 0)}</p>
            </div>
          </div>
        )}
      </div>

      {/* Compras */}
      <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
        <div className="px-3 py-2 border-b bg-gray-50">
          <h3 className="text-sm font-semibold text-gray-700">Compras ({compras.length})</h3>
        </div>
        <div className="divide-y">
          {compras.map((c, i) => (
            <div key={c.id} className="px-2 py-2">
              {/* Compra header */}
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm font-semibold text-gray-800">{i + 1}. {c.proveedor}</span>
                <span className="text-sm font-bold">{fmt(c.monto_total)}</span>
              </div>
              <p className="text-xs text-gray-400 mb-1.5">📅 {c.fecha_compra}</p>

              {/* Items table */}
              {c.items.length > 0 && (
                <table className="w-full text-xs border-collapse">
                  <thead>
                    <tr className="bg-gray-100 text-gray-500">
                      <th className="text-left py-1 px-1.5 font-medium rounded-tl">Producto</th>
                      <th className="text-center py-1 px-1.5 font-medium w-10">Cant.</th>
                      <th className="text-right py-1 px-1.5 font-medium w-16">P.Unit.</th>
                      <th className="text-right py-1 px-1.5 font-medium w-18 rounded-tr">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {c.items.map((item, j) => {
                      const subtotal = item.cantidad * item.precio_unitario;
                      return (
                        <tr key={j} className="text-gray-700">
                          <td className="py-1 px-1.5 leading-tight">{item.nombre}</td>
                          <td className="py-1 px-1.5 text-center">{item.cantidad}</td>
                          <td className="py-1 px-1.5 text-right text-gray-500">{item.precio_unitario > 0 ? fmt(item.precio_unitario) : '—'}</td>
                          <td className="py-1 px-1.5 text-right font-medium">{subtotal > 0 ? fmt(subtotal) : '—'}</td>
                        </tr>
                      );
                    })}
                  </tbody>
                  <tfoot>
                    <tr className="bg-gray-50 font-semibold text-gray-800 border-t border-gray-200">
                      <td colSpan={3} className="py-1 px-1.5 text-right text-xs text-gray-500">Total</td>
                      <td className="py-1 px-1.5 text-right">{fmt(c.monto_total)}</td>
                    </tr>
                  </tfoot>
                </table>
              )}

              {/* Photo thumbnails */}
              {c.imagenes.length > 0 && (
                <div className="flex gap-2 overflow-x-auto pt-2">
                  {c.imagenes.map((url, k) => (
                    <button key={k} onClick={() => setPhotoModal(url)}
                      className="relative h-16 w-16 flex-shrink-0 rounded-lg border overflow-hidden hover:ring-2 hover:ring-blue-400">
                      <img src={url} alt={`Foto ${k + 1}`} className="h-full w-full object-cover" />
                    </button>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </div>

      {/* Approve/Reject */}
      {isPendiente && !done && (
        <div className="rounded-xl border bg-white shadow-sm p-3 space-y-3">
          <h3 className="text-sm font-semibold text-gray-700">Aprobar rendición</h3>

          <div className="space-y-2">
            <label className="text-xs text-gray-500">Monto a transferir a Ricardo</label>

            {saldoNegativo ? (
              <>
                <p className="text-xs text-orange-600 font-medium">⚠️ Transferir {fmt(deuda)}.</p>
                <div className="flex gap-2">
                  <button onClick={() => setMonto(String(exactoRedondeado))}
                    className={`flex-1 rounded-lg border py-2.5 text-sm font-medium transition-colors ${monto === String(exactoRedondeado) ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                    {fmt(exactoRedondeado)}
                    <span className="block text-[10px] text-gray-400">Devolver</span>
                  </button>
                  <button onClick={() => setMonto(String(smart))}
                    className={`flex-1 rounded-lg border py-2.5 text-sm font-medium transition-colors ${monto === String(smart) ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                    {fmt(smart)}
                    <span className="block text-[10px] text-gray-400">Devolver + Caja</span>
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="text-xs text-green-600 font-medium">✅ Ricardo tiene {fmt(rendicion.saldo_resultante)} en caja. No es obligatorio transferir.</p>
                <div className="flex gap-2">
                  <button onClick={() => setMonto('0')}
                    className={`flex-1 rounded-lg border py-2.5 text-sm font-medium transition-colors ${monto === '0' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                    $0
                    <span className="block text-[10px] text-gray-400">Solo aprobar</span>
                  </button>
                  <button onClick={() => setMonto(String(smart))}
                    className={`flex-1 rounded-lg border py-2.5 text-sm font-medium transition-colors ${monto === String(smart) ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'}`}>
                    {fmt(smart)}
                    <span className="block text-[10px] text-gray-400">+ Caja extra</span>
                  </button>
                </div>
              </>
            )}
            <input type="number" value={monto} onChange={e => setMonto(e.target.value)}
              placeholder="Otro monto..."
              className="w-full rounded-lg border px-3 py-2.5 text-base" />
            {monto && (
              <p className="text-xs text-gray-500">
                Saldo nuevo del cajero: <span className="font-semibold">{fmt(parseFloat(monto) + rendicion.saldo_resultante)}</span>
              </p>
            )}
          </div>

          {/* Observaciones */}
          <div>
            <label className="text-xs text-gray-500">Observaciones (opcional para aprobar, requerido para rechazar)</label>
            <textarea value={observaciones} onChange={e => setObservaciones(e.target.value)}
              rows={2} placeholder="Observaciones..."
              className="w-full rounded-lg border px-3 py-2 text-base mt-1" />
          </div>

          {/* Action buttons */}
          <div className="flex gap-2">
            <button onClick={handleAprobar} disabled={submitting || !monto}
              className="flex-1 rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2">
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
              Aprobar
            </button>
            <button onClick={handleRechazar} disabled={submitting}
              className="rounded-lg border-2 border-red-300 px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 disabled:opacity-50">
              Rechazar
            </button>
          </div>
        </div>
      )}

      {done && (
        <div className={`rounded-xl border p-4 text-center text-sm ${rendicion.estado === 'aprobada' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'}`}>
          {rendicion.estado === 'aprobada' ? <Check className="mx-auto h-6 w-6 mb-1" /> : <X className="mx-auto h-6 w-6 mb-1" />}
          Rendición {rendicion.estado}
          {rendicion.monto_transferido != null && (
            <p className="mt-1 font-semibold">Transferido: {fmt(rendicion.monto_transferido)} → Saldo nuevo: {fmt(rendicion.saldo_nuevo ?? 0)}</p>
          )}
        </div>
      )}

      {/* Photo modal */}
      {photoModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" onClick={() => setPhotoModal(null)}>
          <div className="relative max-h-[90vh] max-w-[90vw]">
            <button onClick={() => setPhotoModal(null)} className="absolute -right-2 -top-2 z-10 rounded-full bg-white p-1.5 shadow-lg">
              <X className="h-5 w-5" />
            </button>
            <img src={photoModal} alt="Respaldo" className="max-h-[85vh] rounded-lg object-contain" style={{ imageOrientation: 'from-image' }} />
          </div>
        </div>
      )}
    </div>
  );
}
