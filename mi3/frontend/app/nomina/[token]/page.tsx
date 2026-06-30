'use client';

import { useState, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import { Loader2, Share2, ChevronDown, ChevronUp, Wallet, TrendingDown, CreditCard, ArrowUpRight, ArrowDownRight, Check, X, Clock } from 'lucide-react';

const API = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
const fmt = (n: number) => '$' + Math.round(n).toLocaleString('es-CL');

const MONTH_SHORT = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
const MONTH_NAMES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
function fmtDias(dias: number[], mes: string) {
  const m = parseInt(mes.split('-')[1], 10) - 1;
  return dias.map(d => `${d} ${MONTH_SHORT[m]}`).join(', ');
}
function fmtMonth(mes: string) {
  const [y, m] = mes.split('-');
  return `${MONTH_NAMES[parseInt(m, 10) - 1]} ${y}`;
}

interface AjusteDetail { id: number; monto: number; concepto: string; categoria: string; }
interface ReplacementGroup { personal_id: number; nombre: string; dias: number[]; monto: number; }
interface Worker {
  personal_id: number; nombre: string; rol: string; sueldo_base: number;
  dias_trabajados: number; dias_normales?: number; total_reemplazando: number; total_reemplazado: number;
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
  const searchParams = useSearchParams();
  const workerId = searchParams.get('worker');

  const [mes, setMes] = useState('');
  const [data, setData] = useState<SnapshotData | null>(null);
  const [createdAt, setCreatedAt] = useState('');
  const [aprobadoPor, setAprobadoPor] = useState<string | null>(null);
  const [aprobadoAt, setAprobadoAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    fetch(`${API}/api/v1/nomina/${params.token}`, { headers: { Accept: 'application/json' } })
      .then(r => r.json())
      .then(d => {
        if (d.success) { setMes(d.mes); setData(d.data); setCreatedAt(d.created_at); setAprobadoPor(d.aprobado_por); setAprobadoAt(d.aprobado_at); }
        else setError('Nómina no encontrada');
      })
      .catch(() => setError('Error de conexión'))
      .finally(() => setLoading(false));
  }, [params.token]);

  const handleAprobar = async () => {
    setSubmitting(true);
    try {
      const res = await fetch(`${API}/api/v1/nomina/${params.token}/approve`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({}),
      }).then(r => r.json());
      if (res.success) { setAprobadoPor(res.aprobado_por); setAprobadoAt(res.aprobado_at); }
      else alert(res.error || 'Error');
    } catch { alert('Error de conexión'); }
    setSubmitting(false);
  };

  if (loading) return <div className="flex min-h-screen items-center justify-center"><Loader2 className="h-8 w-8 animate-spin text-gray-400" /></div>;
  if (error) return <div className="flex min-h-screen items-center justify-center text-red-500">{error}</div>;
  if (!data) return null;

  const r11 = data.ruta11;
  const seg = data.seguridad ?? { workers: [], summary: { presupuesto: 0, total_sueldos_base: 0, total_descuentos: 0, total_creditos: 0, total_a_pagar: 0 } };
  let r11Workers = r11.workers.filter(w => !w.rol?.includes('dueño'));
  let segWorkers = seg.workers.filter(w => !w.rol?.includes('dueño'));

  // Filter to single worker if workerId param present
  if (workerId) {
    const pid = parseInt(workerId, 10);
    r11Workers = r11Workers.filter(w => w.personal_id === pid);
    segWorkers = segWorkers.filter(w => w.personal_id === pid);
  }

  const singleWorker = workerId
    ? [...r11Workers, ...segWorkers][0] ?? null
    : null;

  const grandTotal = r11.summary.total_a_pagar + seg.summary.total_a_pagar;

  const buildMessage = () => {
    if (singleWorker) {
      return `📋 *Nómina ${fmtMonth(mes)}*\n👤 *${singleWorker.nombre}*\n💰 Total: ${fmt(singleWorker.total_a_pagar)}\n\nVer detalle 👉🏻 https://mi.laruta11.cl/nomina/${params.token}?worker=${workerId}`;
    }
    const lines: string[] = [
      `📋 *NÓMINA ${fmtMonth(mes).toUpperCase()}*`,
      `━━━━━━━━━━`,
      `🍔 *LA RUTA 11*`,
      `*Subtotal: ${fmt(r11.summary.total_a_pagar)}*`,
    ];
    if (segWorkers.length > 0) {
      lines.push(`🔒 *CAM SEGURIDAD*`);
      lines.push(`*Subtotal: ${fmt(seg.summary.total_a_pagar)}*`);
    }
    lines.push(`━━━━━━━━━━`);
    lines.push(`💰 *TOTAL NÓMINA: ${fmt(grandTotal)}*`);
    lines.push(``);
    lines.push(`Ver detalle 👉🏻 https://mi.laruta11.cl/nomina/${params.token}`);
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
    <div style={{ maxWidth: '100%', padding: '0 4px', paddingBottom: '24px' }} className="space-y-3">
      {/* Header */}
      <div className="text-center py-3 relative">
        <h1 className="text-xl font-bold text-gray-900">
          {singleWorker ? `👤 ${singleWorker.nombre}` : `📋 Nómina ${fmtMonth(mes)}`}
        </h1>
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

      {/* Approval status */}
      {aprobadoAt ? (
        <div className="rounded-xl bg-green-50 border border-green-200 p-3 flex items-center gap-2">
          <Check className="h-5 w-5 text-green-600 shrink-0" />
          <div>
            <p className="text-sm font-medium text-green-800">Nómina aprobada</p>
            <p className="text-xs text-green-600">
              Por {aprobadoPor} · {new Date(aprobadoAt).toLocaleDateString('es-CL')} {new Date(aprobadoAt).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}
            </p>
          </div>
        </div>
      ) : (
        <div className="rounded-xl border border-amber-200 p-3 space-y-2">
          <div className="flex items-center gap-2">
            <Clock className="h-5 w-5 text-amber-500 shrink-0" />
            <p className="text-sm font-medium text-amber-800">Nómina pendiente de aprobación</p>
          </div>
          <button
            onClick={handleAprobar}
            disabled={submitting}
            className="flex items-center gap-1 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-50"
          >
            <Check className="h-3.5 w-3.5" />
            {submitting ? 'Aprobando...' : 'Aprobar Nómina'}
          </button>
        </div>
      )}

      {/* ═══ LA RUTA 11 ═══ */}
      {r11Workers.length > 0 && (
        <CentroCard title="🍔 La Ruta 11" workers={r11Workers} summary={r11.summary} mes={mes} showCredits />
      )}

      {/* ═══ CAM SEGURIDAD ═══ */}
      {segWorkers.length > 0 && (
        <CentroCard title="🔒 Cam Seguridad" workers={segWorkers} summary={seg.summary} mes={mes} />
      )}

      {/* Grand total (only show full view, not single worker) */}
      {!singleWorker && (
        <div className="rounded-xl bg-gray-100 p-3 text-center">
          <p className="text-xs text-gray-500">Total Nómina La Ruta 11</p>
          <p className="text-lg font-bold text-gray-800">{fmt(grandTotal)}</p>
        </div>
      )}
    </div>
  );
}

/* ─── Centro Card ─── */

function CentroCard({ title, workers, summary, mes, showCredits }: {
  title: string;
  workers: Worker[];
  summary: CentroData['summary'];
  mes: string;
  showCredits?: boolean;
}) {
  const [expandedId, setExpandedId] = useState<number | null>(null);

  return (
    <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
      {/* Header */}
      <div className="px-3 py-2.5 border-b bg-gray-50">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-gray-700">{title}</h3>
          <span className="text-xs text-gray-400">{workers.length} persona{workers.length !== 1 ? 's' : ''}</span>
        </div>
      </div>

      {/* Workers */}
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
                className="w-full px-3 py-2.5 text-left"
                onClick={() => setExpandedId(isExpanded ? null : w.personal_id)}
                aria-expanded={isExpanded}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-1.5">
                    {hasDetail ? (
                      isExpanded
                        ? <ChevronUp className="h-4 w-4 text-gray-400 shrink-0" />
                        : <ChevronDown className="h-4 w-4 text-gray-400 shrink-0" />
                    ) : (
                      <div className="w-4 shrink-0" />
                    )}
                    <div>
                      <span className="text-sm font-semibold text-gray-800">{w.nombre}</span>
                      <span className="ml-1.5 text-[10px] text-gray-400 capitalize">{w.rol}</span>
                    </div>
                  </div>
                  <span className="text-sm font-bold tabular-nums">{fmt(w.total_a_pagar)}</span>
                </div>
                {/* Inline: Base, Descuentos, Créditos — with lucide icons */}
                <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 ml-[22px] text-[11px] text-gray-500">
                  <span className="inline-flex items-center gap-0.5">
                    <Wallet className="h-3 w-3" /> {fmt(w.sueldo_base)}
                  </span>
                  {w.total_descuentos !== 0 && (
                    <span className="inline-flex items-center gap-0.5 text-red-500">
                      <TrendingDown className="h-3 w-3" /> {fmt(w.total_descuentos)}
                    </span>
                  )}
                  {w.credito_r11_pendiente > 0 && (
                    <span className="inline-flex items-center gap-0.5 text-red-500">
                      <CreditCard className="h-3 w-3" /> -{fmt(w.credito_r11_pendiente)}
                    </span>
                  )}
                  {w.total_reemplazando > 0 && (
                    <span className="inline-flex items-center gap-0.5 text-green-600">
                      <ArrowUpRight className="h-3 w-3" /> +{fmt(w.total_reemplazando)}
                    </span>
                  )}
                  {w.total_reemplazado > 0 && (
                    <span className="inline-flex items-center gap-0.5 text-red-500">
                      <ArrowDownRight className="h-3 w-3" /> -{fmt(w.total_reemplazado)}
                    </span>
                  )}
                </div>
              </button>

              {isExpanded && (
                <div className="px-3 pb-3 ml-[22px] space-y-2 text-xs">
                  {w.reemplazos_realizados.length > 0 && (
                    <div>
                      <p className="font-medium text-gray-500 mb-0.5">Reemplazos realizados (+)</p>
                      {w.reemplazos_realizados.map((r, i) => (
                        <div key={i} className="flex justify-between text-green-600 py-0.5">
                          <span>→ Reemplazó a {r.nombre} · {fmtDias(r.dias, mes)}</span>
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
                          <span>← {r.nombre} lo reemplazó · {fmtDias(r.dias, mes)}</span>
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

      {/* Total footer */}
      <div className="px-3 py-3 border-t bg-amber-50 flex justify-between items-center">
        <span className="text-sm font-bold text-gray-800">Total a Pagar</span>
        <span className="text-lg font-bold text-amber-700 tabular-nums">{fmt(summary.total_a_pagar)}</span>
      </div>
    </div>
  );
}
