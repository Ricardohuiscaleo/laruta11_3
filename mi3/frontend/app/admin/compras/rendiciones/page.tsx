'use client';

import { useState, useEffect } from 'react';
import { FileText, Check, X, Clock, Send, Loader2, Plus, Eye } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';

interface RendicionPreview {
  saldo_anterior: number;
  total_compras: number;
  saldo_resultante: number;
  compras_count: number;
  compras: Array<{ id: number; fecha_compra: string; proveedor: string; monto_total: number; items: Array<{ nombre: string; cantidad: number; unidad: string }>; imagenes: string[] }>;
}

interface Rendicion {
  id: number;
  token: string;
  saldo_anterior: number;
  total_compras: number;
  saldo_resultante: number;
  monto_transferido: number | null;
  saldo_nuevo: number | null;
  estado: 'pendiente' | 'aprobada' | 'rechazada';
  creado_por: string;
  aprobado_por: string | null;
  created_at: string;
  aprobado_at: string | null;
}

export default function RendicionesPage() {
  const [preview, setPreview] = useState<RendicionPreview | null>(null);
  const [rendiciones, setRendiciones] = useState<Rendicion[]>([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showPreview, setShowPreview] = useState(false);
  const [copied, setCopied] = useState<string | null>(null);

  const load = async () => {
    setLoading(true);
    try {
      const [prev, list] = await Promise.all([
        comprasApi.get<{ success: boolean } & RendicionPreview>('/rendiciones/preview'),
        comprasApi.get<{ success: boolean; rendiciones: Rendicion[] }>('/rendiciones'),
      ]);
      setPreview(prev);
      setRendiciones(list.rendiciones);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const handleCreate = async () => {
    if (!preview || preview.compras_count === 0) return;
    setCreating(true);
    try {
      const res = await comprasApi.post<{ success: boolean; token: string; compras_rendidas: number }>('/rendiciones', {});
      if (res.success) {
        const url = `https://mi.laruta11.cl/rendicion/${res.token}`;
        const msg = `📋 *RENDICIÓN DE GASTOS*\n💰 Saldo anterior: ${formatearPesosCLP(preview.saldo_anterior)}\n🛒 Total compras: ${formatearPesosCLP(preview.total_compras)} (${res.compras_rendidas} compras)\n💳 Saldo: ${formatearPesosCLP(preview.saldo_resultante)}\n\n🔗 ${url}`;
        await navigator.clipboard.writeText(msg);
        setCopied(res.token);
        setTimeout(() => setCopied(null), 5000);
        load();
      }
    } catch { alert('Error al crear rendición'); }
    setCreating(false);
  };

  const getLink = (token: string) => `https://mi.laruta11.cl/rendicion/${token}`;

  const estadoBadge = (estado: string) => {
    if (estado === 'aprobada') return <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700"><Check className="h-3 w-3" /> Aprobada</span>;
    if (estado === 'rechazada') return <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700"><X className="h-3 w-3" /> Rechazada</span>;
    return <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700"><Clock className="h-3 w-3" /> Pendiente</span>;
  };

  if (loading) return <div className="flex items-center justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-mi3-500" /></div>;

  return (
    <div className="space-y-4">
      {/* Preview / New rendición */}
      {preview && preview.compras_count > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
              <FileText className="h-4 w-4" /> Nueva rendición
            </h3>
            <span className="text-xs text-gray-400">{preview.compras_count} compras sin rendir</span>
          </div>

          <div className="grid grid-cols-3 gap-3 text-center">
            <div className="rounded-lg bg-gray-50 p-3">
              <p className="text-xs text-gray-500">Saldo anterior</p>
              <p className="text-lg font-bold">{formatearPesosCLP(preview.saldo_anterior)}</p>
            </div>
            <div className="rounded-lg bg-red-50 p-3">
              <p className="text-xs text-gray-500">Gastado</p>
              <p className="text-lg font-bold text-red-600">-{formatearPesosCLP(preview.total_compras)}</p>
            </div>
            <div className={`rounded-lg p-3 ${preview.saldo_resultante >= 0 ? 'bg-green-50' : 'bg-amber-50'}`}>
              <p className="text-xs text-gray-500">Saldo</p>
              <p className={`text-lg font-bold ${preview.saldo_resultante >= 0 ? 'text-green-700' : 'text-amber-700'}`}>
                {formatearPesosCLP(preview.saldo_resultante)}
              </p>
            </div>
          </div>

          {/* Toggle compras detail */}
          <button onClick={() => setShowPreview(!showPreview)} className="text-xs text-mi3-600 hover:underline">
            {showPreview ? 'Ocultar detalle' : 'Ver compras incluidas'}
          </button>

          {showPreview && (
            <div className="space-y-1 max-h-60 overflow-y-auto">
              {preview.compras.map(c => (
                <div key={c.id} className="flex items-center justify-between rounded border bg-gray-50 px-3 py-1.5 text-sm">
                  <div>
                    <span className="font-medium">{c.proveedor}</span>
                    <span className="ml-2 text-xs text-gray-400">{c.fecha_compra}</span>
                  </div>
                  <span className="font-medium">{formatearPesosCLP(c.monto_total)}</span>
                </div>
              ))}
            </div>
          )}

          <button onClick={handleCreate} disabled={creating}
            className="w-full rounded-lg bg-mi3-500 py-2.5 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50 flex items-center justify-center gap-2">
            {creating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
            {creating ? 'Generando...' : 'Generar rendición y copiar para WhatsApp'}
          </button>

          {copied && (
            <p className="text-center text-xs text-green-600">✅ Rendición creada y texto copiado al portapapeles. Pégalo en WhatsApp.</p>
          )}
        </div>
      )}

      {preview && preview.compras_count === 0 && (
        <div className="rounded-xl border bg-green-50 p-4 text-center text-sm text-green-700">
          <Check className="mx-auto h-6 w-6 mb-2" />
          Todas las compras están rendidas
        </div>
      )}

      {/* History */}
      <div className="space-y-2">
        <h3 className="text-sm font-semibold text-gray-700">Historial de rendiciones</h3>
        {rendiciones.length === 0 && <p className="text-sm text-gray-400">Sin rendiciones aún</p>}
        {rendiciones.map(r => (
          <div key={r.id} className="rounded-xl border bg-white p-3 shadow-sm">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                {estadoBadge(r.estado)}
                <span className="text-xs text-gray-400">{new Date(r.created_at).toLocaleDateString('es-CL')}</span>
              </div>
              <a href={getLink(r.token)} target="_blank" rel="noopener" className="text-xs text-mi3-600 hover:underline flex items-center gap-1">
                <Eye className="h-3 w-3" /> Ver
              </a>
            </div>
            <div className="grid grid-cols-2 gap-2 text-sm">
              <div><span className="text-xs text-gray-500">Saldo anterior</span><p className="font-medium">{formatearPesosCLP(r.saldo_anterior)}</p></div>
              <div><span className="text-xs text-gray-500">Gastado</span><p className="font-medium text-red-600">-{formatearPesosCLP(r.total_compras)}</p></div>
              {r.monto_transferido != null && (
                <div><span className="text-xs text-gray-500">Transferido</span><p className="font-medium text-green-600">+{formatearPesosCLP(r.monto_transferido)}</p></div>
              )}
              {r.saldo_nuevo != null && (
                <div><span className="text-xs text-gray-500">Saldo nuevo</span><p className="font-bold">{formatearPesosCLP(r.saldo_nuevo)}</p></div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
