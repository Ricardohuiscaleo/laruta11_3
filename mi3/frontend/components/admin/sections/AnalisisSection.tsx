'use client';

import { useEffect, useState, useCallback } from 'react';
import dynamic from 'next/dynamic';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, TrendingUp, TrendingDown, Clock, Truck,
  AlertTriangle, BarChart3, Calendar, DollarSign, ShoppingBag,
  Zap, ShieldAlert, Package, Percent,
} from 'lucide-react';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';

const BarChart = dynamic(() => import('recharts').then(m => m.BarChart), { ssr: false });
const Bar = dynamic(() => import('recharts').then(m => m.Bar), { ssr: false });
const LineChart = dynamic(() => import('recharts').then(m => m.LineChart), { ssr: false });
const Line = dynamic(() => import('recharts').then(m => m.Line), { ssr: false });
const XAxis = dynamic(() => import('recharts').then(m => m.XAxis), { ssr: false });
const YAxis = dynamic(() => import('recharts').then(m => m.YAxis), { ssr: false });
const Tooltip = dynamic(() => import('recharts').then(m => m.Tooltip), { ssr: false });
const ResponsiveContainer = dynamic(() => import('recharts').then(m => m.ResponsiveContainer), { ssr: false });
const CartesianGrid = dynamic(() => import('recharts').then(m => m.CartesianGrid), { ssr: false });
const Legend = dynamic(() => import('recharts').then(m => m.Legend), { ssr: false });
const ComposedChart = dynamic(() => import('recharts').then(m => m.ComposedChart), { ssr: false });

interface Recomendacion { tipo: string; texto: string; severidad: string; }

interface AnualData {
  periodo: { desde: string; hasta: string };
  resumen: {
    ordenes: number; ventas: number; ticket: number; compras: number;
    delivery_fees: number; cmv: number; margen_bruto: number;
    pct_margen: number; fuga_compras: number; pct_fuga: number;
  };
  historial: {
    mes: string; ordenes: number; ventas: number; compras: number;
    delivery_fees: number; cmv: number; margen: number; pct_margen: number; ticket: number;
  }[];
  top_productos: {
    id: number; nombre: string; cantidad: number; pedidos: number;
    ingresos: number; costo: number; margen: number; pct_margen: number;
  }[];
  horas: { hora: string; ordenes: number; ingresos: number; cmv: number; costo_staff: number; costo_fijo: number; costo_total: number; resultado: number }[];
  horas_muertas: string[];
  horas_activas: number;
  dia_semana: { dia: string; ordenes: number; ingresos: number; ticket: number }[];
  productos: { total: number; activos: number; inactivos: number };
  recomendaciones: Recomendacion[];
}

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function nombreMes(ym: string) {
  const [y, m] = ym.split('-');
  return `${meses[parseInt(m) - 1]}`;
}

function nombreMesCorto(ym: string) {
  const [y, m] = ym.split('-');
  return `${meses[parseInt(m) - 1].substring(0, 3)}`;
}

function KpiCard({ label, value, sub, color, icon: Icon }: { label: string; value: string; sub?: string; color: string; icon: React.ComponentType<{ className?: string }> }) {
  return (
    <div className="rounded-xl border bg-white p-3 shadow-sm">
      <div className="flex items-center justify-between mb-1.5">
        <span className="text-[11px] font-medium text-gray-500 uppercase tracking-wide">{label}</span>
        <Icon className={cn('h-4 w-4', color)} />
      </div>
      <div className="text-xl font-bold">{value}</div>
      {sub && <p className="text-[11px] text-gray-400 mt-0.5">{sub}</p>}
    </div>
  );
}

function SeveridadBadge({ severidad }: { severidad: string }) {
  const map: Record<string, { bg: string; text: string; label: string }> = {
    alta:   { bg: 'bg-red-100', text: 'text-red-700', label: 'Crítico' },
    media:  { bg: 'bg-amber-100', text: 'text-amber-700', label: 'Alerta' },
    baja:   { bg: 'bg-blue-100', text: 'text-blue-700', label: 'Info' },
  };
  const s = map[severidad] || map.baja;
  return <span className={cn('text-[10px] px-1.5 py-0.5 rounded font-semibold uppercase shrink-0', s.bg, s.text)}>{s.label}</span>;
}

const CHART_COLORS = ['#22c55e', '#3b82f6', '#f97316', '#a855f7', '#ef4444'];

export default function AnalisisSection({ onHeaderConfig }: { onHeaderConfig?: (config: SectionHeaderConfig) => void }) {
  const [data, setData] = useState<AnualData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => { onHeaderConfig?.({ accent: 'red' }); }, [onHeaderConfig]);

  const fetchData = useCallback(async () => {
    setLoading(true); setError(null);
    try {
      const res = await apiFetch<{ success: boolean; data: AnualData; error?: string }>('/admin/analisis/anual');
      if (res.success) setData(res.data);
      else setError(res.error || 'Error al cargar datos');
    } catch {
      setError('Error de conexión al servidor');
    } finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  if (loading && !data) {
    return <div className="flex items-center justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-red-500" /></div>;
  }
  if (error) {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <AlertTriangle className="h-10 w-10 text-red-400 mb-3" />
        <p className="text-sm text-gray-600">{error}</p>
        <button onClick={fetchData} className="mt-4 px-4 py-2 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600">Reintentar</button>
      </div>
    );
  }
  if (!data) return null;

  const r = data.resumen;
  const hourData = Array.from({ length: 24 }, (_, i) => ({ hora: String(i).padStart(2, '0'), ordenes: 0, ingresos: 0, cmv: 0, costo_staff: 0, costo_fijo: 0, costo_total: 0, resultado: 0 }));
  data.horas.forEach(h => { const idx = parseInt(h.hora); if (idx >= 0 && idx < 24) hourData[idx] = h; });
  const bestHour = hourData.reduce((a, b) => b.ingresos > a.ingresos ? b : a, hourData[0]);
  const operativo = hourData.filter(h => h.hora >= '15' || h.hora <= '02');
  const activeHours = operativo.filter(h => h.ordenes > 0).length;
  const totalHoras = operativo.length;

  // Color mapping: green (peak) → yellow → red (dead)
  const maxIngreso = Math.max(...operativo.map(h => h.ingresos), 1);
  function horaColor(monto: number) {
    const pct = monto / maxIngreso;
    if (pct >= 0.5) return '#22c55e';   // verde — peak
    if (pct >= 0.15) return '#eab308';  // amarillo — medio
    if (pct >= 0.02) return '#f97316';  // naranja — bajo
    return '#ef4444';                    // rojo — muerto
  }

  return (
    <div className="space-y-5 py-4">
      {/* Periodo header */}
      <div className="flex items-center gap-2 text-sm text-gray-500">
        <Calendar className="h-4 w-4" />
        <span>Análisis acumulado: <strong className="text-gray-700">{nombreMes(data.periodo.desde)} {data.periodo.desde.split('-')[0]}</strong> → <strong className="text-gray-700">{nombreMes(data.periodo.hasta)} {data.periodo.hasta.split('-')[0]}</strong></span>
      </div>

      {/* KPI Cards — top row */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
        <KpiCard label="Ventas totales" value={formatCLP(r.ventas)} sub={`${r.ordenes} pedidos · ticket ${formatCLP(r.ticket)}`} color="text-green-600" icon={TrendingUp} />
        <KpiCard label="Margen bruto" value={formatCLP(r.margen_bruto)} sub={`${r.pct_margen}% margen`} color={r.pct_margen >= 40 ? "text-green-600" : "text-amber-600"} icon={Percent} />
        <KpiCard label="Compras" value={formatCLP(r.compras)} sub={`CMV real: ${formatCLP(r.cmv)}`} color="text-red-600" icon={ShoppingBag} />
        <KpiCard label="Delivery fees" value={formatCLP(r.delivery_fees)} sub={`${r.compras > 0 ? ((r.delivery_fees / r.ventas) * 100).toFixed(1) : 0}% del revenue`} color="text-orange-600" icon={Truck} />
        <KpiCard label="Fuga compras" value={formatCLP(r.fuga_compras)} sub={`${r.pct_fuga}% de lo comprado`} color={r.pct_fuga > 10 ? "text-red-600" : "text-amber-600"} icon={AlertTriangle} />
      </div>

      {/* Monthly trend — sales + CMV + margin */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <Calendar className="h-4 w-4 text-red-500" /> Evolución Mensual — Ingresos, CMV y Margen
        </h3>
        <ResponsiveContainer width="100%" height={240}>
          <ComposedChart data={data.historial}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="mes" tickFormatter={nombreMesCorto} tick={{ fontSize: 10 }} interval={0} />
            <YAxis tickFormatter={(v: any) => `$${((v as number) / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
            <Tooltip formatter={(v: any) => formatCLP(v as number)} labelFormatter={(ym: any) => nombreMes(ym as string)} />
            <Legend wrapperStyle={{ fontSize: 11 }} />
            <Bar dataKey="ventas" name="Ventas" fill="#22c55e" radius={[2, 2, 0, 0]} />
            <Bar dataKey="cmv" name="CMV" fill="#ef4444" radius={[2, 2, 0, 0]} />
            <Line type="monotone" dataKey="delivery_fees" name="Fee Delivery" stroke="#f97316" strokeWidth={2} dot={{ r: 3 }} />
          </ComposedChart>
        </ResponsiveContainer>
      </div>

      {/* Hourly analysis: 15h-02h with costs */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <Clock className="h-4 w-4 text-red-500" /> Distribución Horaria (12 meses) — Staff 5p: 2 cajeras + 2 plancheros + 1 admin
        </h3>
        <div className="mb-2 flex flex-wrap items-center gap-4 text-xs text-gray-500">
          <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-full bg-green-500" /> {activeHours}/{totalHoras}h activas (15h-02h)</span>
          {bestHour && <span>Peak: <strong>{bestHour.hora}:00</strong> · {formatCLP(bestHour.ingresos)} · {bestHour.ordenes} pedidos</span>}
          <span>Staff: $1,500,000/mes · Arriendo: $500,000/mes</span>
        </div>

        {/* Color legend */}
        <div className="flex items-center gap-3 mb-3 text-[10px]">
          <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-[#22c55e]" /> Peak (&gt;50%)</span>
          <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-[#eab308]" /> Medio (15-50%)</span>
          <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-[#f97316]" /> Bajo (2-15%)</span>
          <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-sm bg-[#ef4444]" /> Muerto (&lt;2%)</span>
        </div>

        <ResponsiveContainer width="100%" height={230}>
          <BarChart data={operativo}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="hora" tick={{ fontSize: 11 }} interval={0} />
            <YAxis tickFormatter={(v: any) => (v === 0 ? '0' : `$${((v as number) / 1000000).toFixed(1)}M`)} tick={{ fontSize: 9 }} />
            <Tooltip
              formatter={(v: any) => formatCLP(v as number)}
              labelFormatter={(h: any) => `${h}:00 - ${h}:59`}
              contentStyle={{ fontSize: 11 }}
            />
            <Bar
              dataKey="ingresos"
              name="Ingresos"
              shape={(props: any) => {
                const { x, y, width, height, payload } = props;
                const fill = horaColor(payload.ingresos);
                return <rect x={x} y={y} width={width} height={height} fill={fill} rx={2} ry={2} />;
              }}
            />
          </BarChart>
        </ResponsiveContainer>

        {/* Hour-by-hour breakdown with costs */}
        <div className="mt-3 overflow-x-auto">
          <table className="w-full text-[11px]">
            <thead>
              <tr className="border-b text-left text-gray-400">
                <th className="py-1 pr-2 font-medium">Hora</th>
                <th className="py-1 pr-2 font-medium text-right">Pedidos</th>
                <th className="py-1 pr-2 font-medium text-right">Ingresos</th>
                <th className="py-1 pr-2 font-medium text-right">CMV</th>
                <th className="py-1 pr-2 font-medium text-right">Staff</th>
                <th className="py-1 pr-2 font-medium text-right">Fijo</th>
                <th className="py-1 pr-2 font-medium text-right">Resultado</th>
              </tr>
            </thead>
            <tbody>
              {operativo.map((h) => (
                <tr key={h.hora} className={cn("border-b last:border-0", h.resultado < 0 ? "bg-red-50/30" : "bg-green-50/30")}>
                  <td className="py-1 pr-2 font-medium">{h.hora}:00</td>
                  <td className="py-1 pr-2 text-right">{h.ordenes}</td>
                  <td className="py-1 pr-2 text-right">{formatCLP(h.ingresos)}</td>
                  <td className="py-1 pr-2 text-right text-gray-500">{formatCLP(h.cmv)}</td>
                  <td className="py-1 pr-2 text-right text-gray-500">{formatCLP(h.costo_staff)}</td>
                  <td className="py-1 pr-2 text-right text-gray-500">{formatCLP(h.costo_fijo)}</td>
                  <td className={cn("py-1 pr-2 text-right font-semibold", h.resultado >= 0 ? "text-green-700" : "text-red-600")}>
                    {formatCLP(h.resultado)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Top productos — full profitability table */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
          <Package className="h-4 w-4 text-green-500" /> Top Productos por Rentabilidad (12 meses)
        </h3>
        <p className="text-xs text-gray-400 mb-3">Ordenado por unidades vendidas. Margen = ingresos − costo ingredientes.</p>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="border-b text-left text-gray-500">
                <th className="py-2 pr-3 font-medium">#</th>
                <th className="py-2 pr-3 font-medium">Producto</th>
                <th className="py-2 pr-3 font-medium text-right">Vendidos</th>
                <th className="py-2 pr-3 font-medium text-right">Ingresos</th>
                <th className="py-2 pr-3 font-medium text-right">Costo</th>
                <th className="py-2 pr-3 font-medium text-right">Margen</th>
                <th className="py-2 pr-3 font-medium text-right">%Mg</th>
              </tr>
            </thead>
            <tbody>
              {data.top_productos.map((p, i) => (
                <tr key={p.id} className={cn("border-b last:border-0 hover:bg-gray-50", i % 2 === 0 ? "bg-white" : "bg-gray-50/50")}>
                  <td className="py-1.5 pr-3 text-gray-400">{i + 1}</td>
                  <td className="py-1.5 pr-3 font-medium text-gray-800 max-w-[180px] truncate">{p.nombre}</td>
                  <td className="py-1.5 pr-3 text-right">{p.cantidad}</td>
                  <td className="py-1.5 pr-3 text-right">{formatCLP(p.ingresos)}</td>
                  <td className="py-1.5 pr-3 text-right text-gray-500">{formatCLP(p.costo)}</td>
                  <td className={cn("py-1.5 pr-3 text-right font-semibold", p.margen >= 0 ? "text-green-700" : "text-red-600")}>{formatCLP(p.margen)}</td>
                  <td className={cn("py-1.5 pr-3 text-right font-semibold", p.pct_margen >= 40 ? "text-green-600" : p.pct_margen >= 20 ? "text-amber-600" : "text-red-600")}>{p.pct_margen}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Strategic recommendations */}
      {data.recomendaciones.length > 0 && (
        <div className="rounded-xl border-l-4 border-l-red-500 bg-red-50 p-4">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <ShieldAlert className="h-4 w-4 text-red-500" /> Recomendaciones Estratégicas (basado en 12 meses de datos)
          </h3>
          <div className="space-y-2">
            {data.recomendaciones.map((rec, i) => (
              <div key={i} className="flex items-start gap-2 p-2 rounded-lg bg-white/60">
                <SeveridadBadge severidad={rec.severidad} />
                <span className="text-sm text-gray-800">{rec.texto}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Productos inactivos / summary footer */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className="rounded-xl border bg-white p-3 shadow-sm flex items-center gap-3">
          <Package className="h-5 w-5 text-gray-400 shrink-0" />
          <div><p className="text-[11px] text-gray-500">Productos activos</p><p className="text-lg font-bold">{data.productos.activos}</p></div>
        </div>
        <div className="rounded-xl border bg-white p-3 shadow-sm flex items-center gap-3">
          <TrendingDown className="h-5 w-5 text-gray-400 shrink-0" />
          <div><p className="text-[11px] text-gray-500">Productos inactivos</p><p className="text-lg font-bold text-red-500">{data.productos.inactivos}</p></div>
        </div>
        <div className="rounded-xl border bg-white p-3 shadow-sm flex items-center gap-3">
          <Clock className="h-5 w-5 text-gray-400 shrink-0" />
          <div><p className="text-[11px] text-gray-500">Horas sin ventas 12m</p><p className="text-lg font-bold text-red-500">{data.horas_muertas.length}</p></div>
        </div>
        <div className="rounded-xl border bg-white p-3 shadow-sm flex items-center gap-3">
          <Truck className="h-5 w-5 text-gray-400 shrink-0" />
          <div><p className="text-[11px] text-gray-500">Fee delivery 12m</p><p className="text-lg font-bold text-orange-500">{formatCLP(r.delivery_fees)}</p></div>
        </div>
      </div>
    </div>
  );
}
