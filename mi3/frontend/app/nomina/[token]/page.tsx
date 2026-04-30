'use client';

import { useState, useEffect } from 'react';
import { Loader2, Share2 } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const fmt = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

const MONTH_NAMES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
function fmtMonth(mes: string) {
  const [y, m] = mes.split('-');
  return `${MONTH_NAMES[parseInt(m, 10) - 1]} ${y}`;
}

interface AjusteDetail { id: number; monto: number; concepto: string; categoria: string; }
interface ReplacementGroup { personal_id: number; nombre: string; dias: number[]; monto: number; }
interface Worker {
  personal_id: number; nombre: string; rol: string; sueldo_base: number;
  dias_trabajados: number; total_reemplazando: number; total_reemplazado: number;
  reemplazos_realizados: ReplacementGroup[]; reemplazos_recibidos: ReplacementGroup[];
  descuentos: AjusteDetail[]; bonos: AjusteDetail[];
  total_descuentos: number; total_bonos: number;
  credito_r11_pendiente: number; total_a_pagar: number;
}
interface CentroData {
  workers: Worker[];
  summary: { presupuesto: number; total_sueldos_base: number; total_descuentos: number; total_creditos: number; total_a_pagar: number; };
}
interface SnapshotData { ruta11: CentroData; seguridad: CentroData; }

export default function NominaPublicPage({ params }: { params: { token: string } }) {
  const [mes, setMes] = useState('');
  const [data, setData] = useState<SnapshotData | null>(null);
  const [createdAt, setCreatedAt] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    fetch(`${API}/api/v1/nomina/${params.token}`, { headers: { Accept: 'application/json' } })
      .then(r => r.json())
      .then(d => {
        if (d.success) { setMes(d.mes); setData(d.data); setCreatedAt(d.created_at); }
        else setError('Nómina no encontrada');
      })
      .catch(() => setError('Error de conexión'))
      .finally(() => setLoading(false));
  }, [params.token]);

  if (loading) return <div className="flex min-h-screen items-center justify-center"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (error) return <div className="flex min-h-screen items-center justify-center text-red-500">{error}</div>;
  if (!data) return null;

  const r11 = data.ruta11;
  const seg = data.seguridad;
  const r11Workers = r11.workers.filter(w => !w.rol?.includes('dueño'));
  const segWorkers = seg.workers.filter(w => !w.rol?.includes('dueño'));
  const grandTotal = r11.summary.total_a_pagar + seg.summary.total_a_pagar;

  const buildMessage = () => {
    const lines: string[] = [
      `📋 *NÓMINA ${fmtMonth(mes).toUpperCase()}*`,
      `━━━━━━━━━━━━━━━━━━━━`,
      ``,
      `🍔 *LA RUTA 11*`,
    ];
    r11Workers.forEach(w => {
      let detail = `  • ${w.nombre}: *${fmt(w.total_a_pagar)}*`;
      const parts: string[] = [];
      if (w.total_descuentos !== 0) parts.push(`desc ${fmt(w.total_descuentos)}`);
      if (w.credito_r11_pendiente > 0) parts.push(`crédito -${fmt(w.credito_r11_pendiente)}`);
      if (parts.length > 0) detail += ` (${parts.join(', ')})`;
      lines.push(detail);
    });
    lines.push(`  *Subtotal: ${fmt(r11.summary.total_a_pagar)}*`);
    if (segWorkers.length > 0) {
      lines.push(``);
      lines.push(`🔒 *CAM SEGURIDAD*`);
      segWorkers.forEach(w => {
        lines.push(`  • ${w.nombre}: *${fmt(w.total_a_pagar)}*`);
      });
      lines.push(`  *Subtotal: ${fmt(seg.summary.total_a_pagar)}*`);
    }
    lines.push(``);
    lines.push(`━━━━━━━━━━━━━━━━━━━━`);
    lines.push(`💰 *TOTAL NÓMINA: ${fmt(grandTotal)}*`);
    lines.push(``);
    lines.push(`🔗 https://mi.laruta11.cl/nomina/${params.token}`);
    return lines.join('\n');
  };

  const handleShare = async () => {
    const msg = buildMessage();
    try {
      if (navigator.share) { await navigator.share({ text: msg }); }
      else { await navigator.clipboard.writeText(msg); setCopied(true); setTimeout(() => setCopied(false), 2500); }
    } catch { await navigator.clipboard.writeText(msg); setCopied(true); setTimeout(() => setCopied(false), 2500); }
  };

  const fecha = createdAt ? new Date(createdAt).toLocaleDateString('es-CL') : '';

  return (
    <div style={{ maxWidth: '100%', padding: '0 4px', paddingBottom: '24px' }} className="space-y-2">
      {/* Header */}
      <div className="text-center py-3 relative">
        <h1 className="text-xl font-bold text-gray-900">📋 Nómina {fmtMonth(mes)}</h1>
        <div className="inline-flex items-center gap-1.5 mt-1">
          <p className="text-xs text-gray-400">La Ruta 11{fecha ? ` — ${fecha}` : ''}</p>
          <button onClick={handleShare} title="Compartir" className="inline-flex items-center justify-center h-5 w-5 rounded-full text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
            <Share2 className="h-3.5 w-3.5" />
          </button>
        </div>
        {copied && (
          <div className="absolute left-1/2 -translate-x-1/2 mt-1 px-3 py-1 rounded-full bg-gray-800 text-white text-[11px] whitespace-nowrap shadow-lg">
            ✅ Mensaje copiado
          </div>
        )}
      </div>

      {/* Summary pills */}
      <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
        <div className="grid grid-cols-3">
          <div className="p-2.5 text-center border-r">
            <p className="text-[10px] text-gray-500 leading-tight">Ruta 11</p>
            <p className="text-sm font-bold mt-0.5">{fmt(r11.summary.total_a_pagar)}</p>
          </div>
          <div className="p-2.5 text-center border-r">
            <p className="text-[10px] text-gray-500 leading-tight">Seguridad</p>
            <p className="text-sm font-bold mt-0.5">{fmt(seg.summary.total_a_pagar)}</p>
          </div>
          <div className="p-2.5 text-center bg-amber-50">
            <p className="text-[10px] text-gray-500 leading-tight">Total</p>
            <p className="text-sm font-bold text-amber-700 mt-0.5">{fmt(grandTotal)}</p>
          </div>
        </div>
      </div>

      {/* La Ruta 11 */}
      <CentroCard title="🍔 La Ruta 11" workers={r11Workers} summary={r11.summary} showCredits />

      {/* Cam Seguridad */}
      {segWorkers.length > 0 && (
        <CentroCard title="🔒 Cam Seguridad" workers={segWorkers} summary={seg.summary} />
      )}

      {/* Copy button */}
      <button onClick={handleShare} className="w-full rounded-xl bg-amber-600 py-3 text-sm font-semibold text-white hover:bg-amber-700 flex items-center justify-center gap-2">
        <Share2 className="h-4 w-4" />
        {copied ? '✅ Copiado' : 'Copiar Resumen'}
      </button>
    </div>
  );
}

/* ─── Centro Card ─── */

function CentroCard({ title, workers, summary, showCredits }: {
  title: string;
  workers: Worker[];
  summary: CentroData['summary'];
  showCredits?: boolean;
}) {
  const [expandedId, setExpandedId] = useState<number | null>(null);

  return (
    <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div className="px-3 py-2 border-b bg-gray-50 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-gray-700">{title} ({workers.length})</h3>
        <span className="text-sm font-bold text-amber-700">{fmt(summary.total_a_pagar)}</span>
      </div>

      {/* Summary breakdown */}
      <div className="px-3 py-2 border-b bg-gray-50/50 flex flex-wrap gap-x-4 gap-y-0.5 text-[11px] text-gray-500">
        <span>Sueldos: {fmt(summary.total_sueldos_base)}</span>
        {summary.total_descuentos !== 0 && <span className="text-red-500">Desc: {fmt(summary.total_descuentos)}</span>}
        {showCredits && summary.total_creditos > 0 && <span className="text-red-500">Créditos: -{fmt(summary.total_creditos)}</span>}
      </div>

      <div className="divide-y">
        {workers.map(w => {
          const isExpanded = expandedId === w.personal_id;
          const hasDetail = w.descuentos.length > 0 || w.bonos.length > 0
            || w.credito_r11_pendiente > 0
            || w.reemplazos_realizados.length > 0
            || w.reemplazos_recibidos.length > 0;

          return (
            <div key={w.personal_id}>
              <button
                type="button"
                className="w-full px-3 py-2 text-left"
                onClick={() => hasDetail && setExpandedId(isExpanded ? null : w.personal_id)}
                aria-expanded={isExpanded}
              >
                <div className="flex items-center justify-between">
                  <div>
                    <span className="text-sm font-semibold text-gray-800">{w.nombre}</span>
                    <span className="ml-1.5 text-[10px] text-gray-400 capitalize">{w.rol}</span>
                  </div>
                  <span className="text-sm font-bold tabular-nums">{fmt(w.total_a_pagar)}</span>
                </div>
                <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5 text-[11px] text-gray-500">
                  <span>Base: {fmt(w.sueldo_base)}</span>
                  <span>Días: {w.dias_trabajados}</span>
                  {w.total_descuentos !== 0 && <span className="text-red-500">Desc: {fmt(w.total_descuentos)}</span>}
                  {w.credito_r11_pendiente > 0 && <span className="text-red-500">💳 -{fmt(w.credito_r11_pendiente)}</span>}
                  {w.total_reemplazando > 0 && <span className="text-green-600">+Reemp: {fmt(w.total_reemplazando)}</span>}
                  {w.total_reemplazado > 0 && <span className="text-red-500">-Reemp: {fmt(w.total_reemplazado)}</span>}
                </div>
              </button>

              {isExpanded && (
                <div className="px-3 pb-3 space-y-2 text-xs">
                  {w.reemplazos_realizados.length > 0 && (
                    <div>
                      <p className="font-medium text-gray-500 mb-0.5">Reemplazos realizados (+)</p>
                      {w.reemplazos_realizados.map((r, i) => (
                        <div key={i} className="flex justify-between text-green-600 py-0.5">
                          <span>→ {r.nombre} · días {r.dias.join(', ')}</span>
                          <span>+{fmt(r.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  {w.reemplazos_recibidos.length > 0 && (
                    <div>
                      <p className="font-medium text-gray-500 mb-0.5">Reemplazos recibidos (-)</p>
                      {w.reemplazos_recibidos.map((r, i) => (
                        <div key={i} className="flex justify-between text-red-600 py-0.5">
                          <span>← {r.nombre} · días {r.dias.join(', ')}</span>
                          <span>-{fmt(r.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  {w.bonos.length > 0 && (
                    <div>
                      <p className="font-medium text-gray-500 mb-0.5">Bonos (+)</p>
                      {w.bonos.map(a => (
                        <div key={a.id} className="flex justify-between rounded bg-green-50 px-2 py-1">
                          <span>{a.concepto}{a.categoria ? ` (${a.categoria})` : ''}</span>
                          <span className="font-semibold text-green-600">+{fmt(a.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  {w.descuentos.length > 0 && (
                    <div>
                      <p className="font-medium text-gray-500 mb-0.5">Descuentos (-)</p>
                      {w.descuentos.map(a => (
                        <div key={a.id} className="flex justify-between rounded bg-red-50 px-2 py-1">
                          <span>{a.concepto}{a.categoria ? ` (${a.categoria})` : ''}</span>
                          <span className="font-semibold text-red-600">{fmt(a.monto)}</span>
                        </div>
                      ))}
                    </div>
                  )}
                  {w.credito_r11_pendiente > 0 && (
                    <div className="flex justify-between rounded bg-orange-50 px-2 py-1">
                      <span>💳 Crédito R11 pendiente</span>
                      <span className="font-semibold text-red-600">-{fmt(w.credito_r11_pendiente)}</span>
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })}
      </div>

      <div className="px-3 py-2 border-t bg-gray-50 flex justify-between">
        <span className="text-sm font-semibold text-gray-700">Total a Pagar</span>
        <span className="text-sm font-bold text-amber-700 tabular-nums">{fmt(summary.total_a_pagar)}</span>
      </div>
    </div>
  );
}
