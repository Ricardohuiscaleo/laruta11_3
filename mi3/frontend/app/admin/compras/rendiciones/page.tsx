'use client';

import { useState, useEffect } from 'react';
import { FileText, Send, Loader2, Check, CheckCheck, Copy, ExternalLink } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';

interface CompraItem {
  id: number;
  fecha_compra: string;
  proveedor: string;
  monto_total: number;
}

interface RendicionPreview {
  saldo_anterior: number;
  total_compras: number;
  saldo_resultante: number;
  compras_count: number;
  compras: CompraItem[];
}

function formatFecha(f: string) {
  const d = new Date(f);
  const months = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.'];
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

export default function RendicionesPage() {
  const [preview, setPreview] = useState<RendicionPreview | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [generated, setGenerated] = useState<{ token: string; message: string } | null>(null);
  const [copied, setCopied] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const res = await comprasApi.get<{ success: boolean } & RendicionPreview>('/rendiciones/preview');
      setPreview(res);
    } catch {}
    setLoading(false);
  };

  useEffect(() => { load(); }, []);

  const buildMessage = (p: RendicionPreview, count: number): string => {
    const lines: string[] = [];
    lines.push('📋 Generar Rendición');
    lines.push('');
    lines.push('Saldo anterior');
    lines.push(formatearPesosCLP(p.saldo_anterior));
    lines.push('Gastado');
    lines.push('-' + formatearPesosCLP(p.total_compras));
    lines.push(p.saldo_resultante >= 0 ? 'Caja' : 'Bolsillo Ricardo');
    lines.push(formatearPesosCLP(Math.abs(p.saldo_resultante)));
    p.compras.forEach((c, i) => {
      const date = formatFecha(c.fecha_compra);
      lines.push(`${i + 1}. ${c.proveedor} ${date}`);
      lines.push(formatearPesosCLP(c.monto_total));
    });
    return lines.join('\n');
  };

  const handleCreate = async () => {
    if (!preview || preview.compras_count === 0) return;
    setGenerating(true);
    try {
      const res = await comprasApi.post<{ success: boolean; token: string; compras_rendidas: number }>('/rendiciones', {});
      if (res.success) {
        const message = buildMessage(preview, res.compras_rendidas);
        setGenerated({ token: res.token, message });
        setCopied(false);
      }
    } catch { alert('Error al crear rendición'); }
    setGenerating(false);
  };

  const handleCopy = async () => {
    if (!generated) return;
    try {
      await navigator.clipboard.writeText(generated.message);
      setCopied(true);
    } catch {}
  };

  const handleShare = async () => {
    if (!generated) return;
    await handleCopy();
    const waUrl = `https://wa.me/?text=${encodeURIComponent(generated.message)}`;
    window.open(waUrl, '_blank');
  };

  if (loading) return <div className="flex items-center justify-center py-12"><Loader2 className="h-6 w-6 animate-spin text-mi3-500" /></div>;

  return (
    <div className="space-y-4">
      {!generated && preview && preview.compras_count === 0 && (
        <div className="rounded-xl border bg-green-50 p-4 text-center text-sm text-green-700">
          <Check className="mx-auto h-6 w-6 mb-2" />
          Todas las compras están rendidas
        </div>
      )}

      {!generated && preview && preview.compras_count > 0 && (
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
              <p className="text-xs text-gray-500">{preview.saldo_resultante >= 0 ? 'Caja' : 'Bolsillo Ricardo'}</p>
              <p className={`text-lg font-bold ${preview.saldo_resultante >= 0 ? 'text-green-700' : 'text-amber-700'}`}>
                {formatearPesosCLP(Math.abs(preview.saldo_resultante))}
              </p>
            </div>
          </div>

          <div className="space-y-1 max-h-60 overflow-y-auto">
            {preview.compras.map((c, i) => (
              <div key={c.id} className="flex items-center justify-between rounded border bg-gray-50 px-3 py-1.5 text-sm">
                <div>
                  <span className="text-xs text-gray-400 mr-1">{i + 1}.</span>
                  <span className="font-medium">{c.proveedor}</span>
                  <span className="ml-1.5 text-xs text-gray-400">{formatFecha(c.fecha_compra)}</span>
                </div>
                <span className="font-medium">{formatearPesosCLP(c.monto_total)}</span>
              </div>
            ))}
          </div>

          <button onClick={handleCreate} disabled={generating}
            className="w-full rounded-lg bg-mi3-500 py-2.5 text-sm font-medium text-white hover:bg-mi3-600 disabled:opacity-50 flex items-center justify-center gap-2">
            {generating ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
            {generating ? 'Generando...' : 'Generar Rendición'}
          </button>
        </div>
      )}

      {generated && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
              <FileText className="h-4 w-4" /> Rendición generada
            </h3>
            <CheckCheck className="h-4 w-4 text-green-600" />
          </div>

          <pre className="whitespace-pre-wrap text-sm leading-relaxed bg-gray-50 rounded-lg p-3 border font-sans">
            {generated.message}
          </pre>

          <a
            href={`https://mi.laruta11.cl/rendicion/${generated.token}`}
            target="_blank" rel="noopener noreferrer"
            className="flex items-center justify-center gap-1.5 text-xs text-mi3-600 hover:underline"
          >
            <ExternalLink className="h-3 w-3" /> Ver página pública de la rendición
          </a>

          <div className="grid grid-cols-2 gap-2">
            <button onClick={handleCopy}
              className="rounded-lg border py-2.5 text-sm font-medium flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors">
              {copied ? <Check className="h-4 w-4 text-green-600" /> : <Copy className="h-4 w-4" />}
              {copied ? 'Copiado' : 'Copiar'}
            </button>
            <button onClick={handleShare}
              className="rounded-lg bg-green-600 py-2.5 text-sm font-medium text-white hover:bg-green-700 flex items-center justify-center gap-2 transition-colors">
              <Send className="h-4 w-4" /> Compartir por WhatsApp
            </button>
          </div>
        </div>
      )}

      {!preview && !loading && (
        <div className="text-center text-sm text-gray-400 py-8">Error al cargar vista previa</div>
      )}
    </div>
  );
}
