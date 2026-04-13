'use client';

import { useState, useEffect } from 'react';
import { X, Send, Loader2, Check, ExternalLink } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import type { Compra } from '@/types/compras';

interface RendicionWhatsAppProps {
  compras: Compra[];
  onClose: () => void;
  onCreated?: () => void;
}

const YOJHANS_PHONE = '56962aborrar'; // placeholder — will use WhatsApp web share

export default function RendicionWhatsApp({ compras, onClose, onCreated }: RendicionWhatsAppProps) {
  const [saldoAnterior, setSaldoAnterior] = useState<number>(0);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [created, setCreated] = useState(false);
  const [token, setToken] = useState('');

  const totalCompras = compras.reduce((sum, c) => sum + c.monto_total, 0);
  const saldoResultante = saldoAnterior - totalCompras;

  // Load saldo anterior from last approved rendición
  useEffect(() => {
    comprasApi.get<{ success: boolean; saldo_anterior: number }>('/rendiciones/preview')
      .then(r => { if (r.success) setSaldoAnterior(r.saldo_anterior); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const generateWhatsAppText = (tok: string) => {
    const link = `https://mi.laruta11.cl/rendicion/${tok}`;
    const lines = [
      '📋 *RENDICIÓN DE GASTOS*',
      '━━━━━━━━━━━━━━━━━━━━',
      `*💰 Saldo anterior:* ${formatearPesosCLP(saldoAnterior)}`,
      `*🛒 Total compras:* ${formatearPesosCLP(totalCompras)} (${compras.length} compras)`,
      '',
    ];

    compras.forEach((c, i) => {
      lines.push(`*${i + 1}. ${c.proveedor}*`);
      lines.push(`📅 ${formatearFecha(c.fecha_compra)} 💵 ${formatearPesosCLP(c.monto_total)}`);
      if (c.detalles) {
        c.detalles.forEach(d => {
          const nombre = (d as any).nombre_item || (d as any).nombre || '?';
          lines.push(`• ${nombre} (${d.cantidad} ${d.unidad})`);
        });
      }
    });

    lines.push('━━━━━━━━━━━━━━━━━━━━');
    if (saldoResultante >= 0) {
      lines.push(`*✅ Saldo a favor:* ${formatearPesosCLP(saldoResultante)}`);
    } else {
      lines.push(`*⚠️ Por transferir:* ${formatearPesosCLP(Math.abs(saldoResultante))}`);
    }
    lines.push('');
    lines.push(`🔗 Ver detalle y aprobar: ${link}`);

    return lines.join('\n');
  };

  const handleCreate = async () => {
    setCreating(true);
    try {
      const res = await comprasApi.post<{ success: boolean; token: string }>('/rendiciones', {});
      if (res.success) {
        setToken(res.token);
        setCreated(true);
        // Open WhatsApp with the message
        const text = encodeURIComponent(generateWhatsAppText(res.token));
        window.open(`https://wa.me/?text=${text}`, '_blank');
        onCreated?.();
      }
    } catch { alert('Error al crear rendición'); }
    setCreating(false);
  };

  if (loading) return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
      <Loader2 className="h-8 w-8 animate-spin text-white" />
    </div>
  );

  return (
    <div className="fixed inset-0 z-40 flex items-end sm:items-center justify-center bg-black/50 p-0 sm:p-4" onClick={onClose}>
      <div className="w-full max-w-md rounded-t-xl sm:rounded-xl bg-white shadow-xl max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between border-b px-4 py-3 sticky top-0 bg-white z-10">
          <h3 className="text-base font-semibold text-gray-900">📋 Generar Rendición</h3>
          <button onClick={onClose} className="rounded-full p-1 hover:bg-gray-100"><X className="h-5 w-5" /></button>
        </div>

        <div className="space-y-4 p-4">
          {/* Summary */}
          <div className="grid grid-cols-3 gap-2 text-center">
            <div className="rounded-lg bg-gray-50 p-3">
              <p className="text-xs text-gray-500">Saldo anterior</p>
              <p className="text-base font-bold">{formatearPesosCLP(saldoAnterior)}</p>
            </div>
            <div className="rounded-lg bg-red-50 p-3">
              <p className="text-xs text-gray-500">Gastado</p>
              <p className="text-base font-bold text-red-600">-{formatearPesosCLP(totalCompras)}</p>
            </div>
            <div className={`rounded-lg p-3 ${saldoResultante >= 0 ? 'bg-green-50' : 'bg-amber-50'}`}>
              <p className="text-xs text-gray-500">{saldoResultante >= 0 ? 'A favor' : 'Por transferir'}</p>
              <p className={`text-base font-bold ${saldoResultante >= 0 ? 'text-green-700' : 'text-amber-700'}`}>
                {formatearPesosCLP(Math.abs(saldoResultante))}
              </p>
            </div>
          </div>

          {/* Compras list */}
          <div className="space-y-1 max-h-48 overflow-y-auto">
            {compras.map((c, i) => (
              <div key={c.id} className="flex items-center justify-between rounded border bg-gray-50 px-3 py-1.5 text-sm">
                <div>
                  <span className="font-medium">{i + 1}. {c.proveedor}</span>
                  <span className="ml-2 text-xs text-gray-400">{formatearFecha(c.fecha_compra)}</span>
                </div>
                <span className="font-medium">{formatearPesosCLP(c.monto_total)}</span>
              </div>
            ))}
          </div>

          {/* Actions */}
          {!created ? (
            <button onClick={handleCreate} disabled={creating || compras.length === 0}
              className="w-full rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50 flex items-center justify-center gap-2">
              {creating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
              {creating ? 'Generando...' : 'Generar y enviar por WhatsApp'}
            </button>
          ) : (
            <div className="space-y-2">
              <div className="rounded-lg bg-green-50 p-3 text-center text-sm text-green-700">
                <Check className="mx-auto h-5 w-5 mb-1" />
                Rendición creada — WhatsApp abierto
              </div>
              <a href={`/rendicion/${token}`} target="_blank" rel="noopener"
                className="flex w-full items-center justify-center gap-2 rounded-lg border border-mi3-300 py-2 text-sm text-mi3-600 hover:bg-mi3-50">
                <ExternalLink className="h-4 w-4" /> Ver página pública
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
