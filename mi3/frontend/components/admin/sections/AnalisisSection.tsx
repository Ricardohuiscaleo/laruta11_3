'use client';

import { useEffect, useState, useCallback } from 'react';
import dynamic from 'next/dynamic';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, TrendingUp, TrendingDown, Clock, Package, Truck,
  AlertTriangle, BarChart3, Calendar, DollarSign, ShoppingBag,
} from 'lucide-react';
import type { SectionHeaderConfig } from '@/components/admin/AdminShell';

const BarChart = dynamic(() => import('recharts').then(m => m.BarChart), { ssr: false });
const Bar = dynamic(() => import('recharts').then(m => m.Bar), { ssr: false });
const XAxis = dynamic(() => import('recharts').then(m => m.XAxis), { ssr: false });
const YAxis = dynamic(() => import('recharts').then(m => m.YAxis), { ssr: false });
const Tooltip = dynamic(() => import('recharts').then(m => m.Tooltip), { ssr: false });
const ResponsiveContainer = dynamic(() => import('recharts').then(m => m.ResponsiveContainer), { ssr: false });
const LineChart = dynamic(() => import('recharts').then(m => m.LineChart), { ssr: false });
const Line = dynamic(() => import('recharts').then(m => m.Line), { ssr: false });
const CartesianGrid = dynamic(() => import('recharts').then(m => m.CartesianGrid), { ssr: false });
const Legend = dynamic(() => import('recharts').then(m => m.Legend), { ssr: false });

interface AnalisisData {
  mes: string;
  ventas_totales: number;
  total_ordenes: number;
  ticket_promedio: number;
  compras_mes: number;
  resultado_estimado: number;
  horas: { hora: string; ordenes: number; ingresos: number }[];
  top_productos: { id: number; nombre: string; cantidad: number; pedidos: number; ingresos: number }[];
  bottom_productos: { id: number; nombre: string; cantidad: number; precio: number }[];
  delivery_impact: { tipos: { tipo: string; ordenes: number; pct_ordenes: number; ingresos: number; ingresos_sin_envio: number; fee_envio: number }[]; total_delivery_fees: number };
  dia_semana: { dia: string; ordenes: number; ingresos: number }[];
  historial_mensual: { mes: string; ordenes: number; ingresos: number; fee_envio: number }[];
  productos: { total: number; activos: number; inactivos: number };
}

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function nombreMes(ym: string) {
  const [y, m] = ym.split('-');
  return `${meses[parseInt(m) - 1]} ${y}`;
}

function KpiCard({ label, value, sub, color, icon: Icon }: { label: string; value: string; sub?: string; color: string; icon: React.ComponentType<{ className?: string }> }) {
  return (
    <div className="rounded-xl border bg-white p-4 shadow-sm">
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">{label}</span>
        <Icon className={cn('h-5 w-5', color)} />
      </div>
      <div className={cn('text-2xl font-bold', color.replace('text-', 'text-'))}>{value}</div>
      {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
    </div>
  );
}

export default function AnalisisSection({ onHeaderConfig }: { onHeaderConfig?: (config: SectionHeaderConfig) => void }) {
  const [data, setData] = useState<AnalisisData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedMonth, setSelectedMonth] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });

  useEffect(() => {
    onHeaderConfig?.({ accent: 'red' });
  }, [onHeaderConfig]);

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await apiFetch<{ success: boolean; data: AnalisisData; error?: string }>(`/admin/analisis/resumen?month=${selectedMonth}`);
      if (res.success) setData(res.data);
      else setError(res.error || 'Error al cargar datos');
    } catch (e) {
      setError('Error de conexión al servidor');
    } finally {
      setLoading(false);
    }
  }, [selectedMonth]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const changeMonth = (delta: number) => {
    const [y, m] = selectedMonth.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    setSelectedMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
  };

  const isCurrentMonth = selectedMonth === (() => { const n = new Date(); return `${n.getFullYear()}-${String(n.getMonth() + 1).padStart(2, '0')}`; })();

  if (loading && !data) {
    return (
      <div className="flex items-center justify-center py-20">
        <Loader2 className="h-8 w-8 animate-spin text-red-500" />
      </div>
    );
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

  const hourData = Array.from({ length: 24 }, (_, i) => {
    const h = String(i).padStart(2, '0');
    const found = data.horas.find(x => x.hora === h);
    return { hora: h, ordenes: found?.ordenes || 0, ingresos: found?.ingresos || 0 };
  });

  const bestHour = [...hourData].sort((a, b) => b.ingresos - a.ingresos)[0];
  const worstHours = hourData.filter(h => h.ordenes === 0).map(h => `${h.hora}:00`);
  const activeHours = hourData.filter(h => h.ordenes > 0).length;

  const topProducts = data.top_productos.slice(0, 5);
  const bottomProducts = data.bottom_productos.slice(0, 5);

  const hasDelivery = data.delivery_impact?.tipos?.some((t: any) => t.tipo === 'delivery');
  const deliveryData = data.delivery_impact?.tipos || [];

  const historial = data.historial_mensual || [];
  const maxIngreso = Math.max(...historial.map(h => h.ingresos), 1);

  return (
    <div className="space-y-6 py-4">
      {/* Month selector */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <button onClick={() => changeMonth(-1)} className="px-3 py-1.5 text-sm font-medium rounded-lg border hover:bg-gray-50 transition-colors">&larr;</button>
          <span className="text-base font-semibold text-gray-800 min-w-[140px] text-center">
            {nombreMes(data.mes)}
            {isCurrentMonth && <span className="ml-2 text-xs text-amber-500 font-normal">(parcial)</span>}
          </span>
          <button onClick={() => changeMonth(1)} disabled={isCurrentMonth} className={cn("px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors", isCurrentMonth ? "opacity-30 cursor-not-allowed" : "hover:bg-gray-50")}>&rarr;</button>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <KpiCard label="Ventas" value={formatCLP(data.ventas_totales)} sub={`${data.total_ordenes} pedidos`} color="text-green-600" icon={TrendingUp} />
        <KpiCard label="Ticket Promedio" value={formatCLP(data.ticket_promedio)} sub="por pedido" color="text-blue-600" icon={DollarSign} />
        <KpiCard label="Compras" value={formatCLP(data.compras_mes)} sub="insumos del mes" color="text-red-600" icon={ShoppingBag} />
        <KpiCard label="Resultado" value={formatCLP(data.resultado_estimado)} sub={data.resultado_estimado >= 0 ? "superávit estimado" : "déficit estimado"} color={data.resultado_estimado >= 0 ? "text-green-600" : "text-red-600"} icon={BarChart3} />
      </div>

      {/* Two-column layout for larger screens */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {/* Monthly trend */}
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Calendar className="h-4 w-4 text-red-500" />
            Tendencia Mensual (últimos 12 meses)
          </h3>
          {historial.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <BarChart data={historial}>
                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                <XAxis dataKey="mes" tickFormatter={nombreMes} tick={{ fontSize: 10 }} interval={2} />
                <YAxis tickFormatter={(v: any) => `$${((v as number) / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
                <Tooltip formatter={(v: any) => formatCLP(v as number)} labelFormatter={(ym: any) => nombreMes(ym as string)} />
                <Legend />
                <Bar dataKey="ingresos" name="Ingresos" fill="#22c55e" radius={[4, 4, 0, 0]} />
                <Bar dataKey="fee_envio" name="Fee Delivery" fill="#f97316" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <p className="text-sm text-gray-400 py-8 text-center">Sin datos históricos</p>
          )}
        </div>

        {/* Hourly distribution */}
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Clock className="h-4 w-4 text-red-500" />
            Distribución por Hora
          </h3>
          <div className="mb-2 flex items-center gap-3 text-xs text-gray-500">
            <span className="inline-flex items-center gap-1"><span className="h-2.5 w-2.5 rounded-full bg-green-500" /> {activeHours}/24 hrs activas</span>
            {bestHour && <span>Mejor hora: <strong>{bestHour.hora}:00</strong></span>}
          </div>
          <ResponsiveContainer width="100%" height={200}>
            <BarChart data={hourData}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="hora" tick={{ fontSize: 9 }} interval={2} />
              <YAxis tickFormatter={(v: any) => `$${((v as number) / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
              <Tooltip formatter={(v: any) => formatCLP(v as number)} labelFormatter={(h: any) => `${h}:00`} />
              <Bar dataKey="ingresos" name="Ingresos" fill="#3b82f6" radius={[2, 2, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
          {worstHours.length > 0 && (
            <p className="text-xs text-gray-400 mt-2">
              Horas sin ventas en este período: {worstHours.join(', ')}
            </p>
          )}
        </div>
      </div>

      {/* Products section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top products */}
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <TrendingUp className="h-4 w-4 text-green-500" />
            Top Productos
          </h3>
          {topProducts.length > 0 ? (
            <div className="space-y-1.5">
              {topProducts.map((p, i) => (
                <div key={p.id} className="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-gray-50">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className={cn("h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0", i === 0 ? "bg-amber-500" : i === 1 ? "bg-gray-400" : i === 2 ? "bg-orange-600" : "bg-gray-200 text-gray-500")}>{i + 1}</span>
                    <span className="text-sm truncate">{p.nombre}</span>
                  </div>
                  <div className="text-right shrink-0 ml-2">
                    <span className="text-sm font-semibold">{p.cantidad} uds</span>
                    <span className="text-xs text-gray-400 ml-2">{formatCLP(p.ingresos)}</span>
                  </div>
                </div>
              ))}
            </div>
          ) : <p className="text-sm text-gray-400 py-4 text-center">Sin ventas este mes</p>}
        </div>

        {/* Bottom products */}
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <TrendingDown className="h-4 w-4 text-red-500" />
            Productos Sin Rotación
          </h3>
          {bottomProducts.length > 0 ? (
            <div className="space-y-1.5">
              {bottomProducts.map(p => (
                <div key={p.id} className="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-gray-50">
                  <span className="text-sm truncate">{p.nombre}</span>
                  <div className="text-right shrink-0 ml-2">
                    <span className="text-xs font-medium text-red-500">0 vendidos</span>
                    {p.precio > 0 && <span className="text-xs text-gray-400 ml-2">{formatCLP(p.precio)}</span>}
                  </div>
                </div>
              ))}
            </div>
          ) : <p className="text-sm text-gray-400 py-4 text-center">Sin datos</p>}
          <p className="text-xs text-gray-400 mt-3">
            {data.productos.activos} productos activos · {data.bottom_productos.length} con 0 ventas este mes
          </p>
        </div>
      </div>

      {/* Delivery impact */}
      {deliveryData.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Truck className="h-4 w-4 text-orange-500" />
            Impacto Delivery
          </h3>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {deliveryData.map((t: any) => (
              <div key={t.tipo} className="p-3 rounded-lg bg-gray-50">
                <p className="text-xs text-gray-500 uppercase font-medium">{t.tipo === 'pickup' ? 'Retiro' : t.tipo === 'delivery' ? 'Delivery' : t.tipo}</p>
                <p className="text-lg font-bold mt-1">{t.pct_ordenes}%</p>
                <p className="text-xs text-gray-400">{t.ordenes} pedidos · {formatCLP(t.ingresos_sin_envio)}</p>
                {t.tipo === 'delivery' && t.fee_envio > 0 && (
                  <p className="text-xs text-amber-600 font-medium mt-1">Fee total: {formatCLP(t.fee_envio)}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Day of week */}
      {data.dia_semana.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Calendar className="h-4 w-4 text-purple-500" />
            Ventas por Día de la Semana
          </h3>
          <ResponsiveContainer width="100%" height={180}>
            <BarChart data={data.dia_semana}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey="dia" tick={{ fontSize: 10 }} />
              <YAxis tickFormatter={(v: any) => `$${((v as number) / 1000).toFixed(0)}k`} tick={{ fontSize: 10 }} />
              <Tooltip formatter={(v: any) => formatCLP(v as number)} />
              <Bar dataKey="ingresos" name="Ingresos" fill="#a855f7" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}

      {/* Strategic recommendations */}
      <div className="rounded-xl border-l-4 border-l-amber-500 bg-amber-50 p-4">
        <h3 className="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
          <AlertTriangle className="h-4 w-4 text-amber-500" />
          Puntos Clave
        </h3>
        <ul className="space-y-1.5 text-sm text-gray-700">
          {worstHours.length > 3 && (
            <li className="flex items-start gap-2">
              <span className="text-amber-500 mt-0.5">•</span>
              <span><strong>{worstHours.length} horas</strong> sin actividad comercial este mes. Evaluar reducción de horario operacional.</span>
            </li>
          )}
          {activeHours > 0 && bestHour && (
            <li className="flex items-start gap-2">
              <span className="text-amber-500 mt-0.5">•</span>
              <span>El pico de ventas es a las <strong>{bestHour.hora}:00</strong> con {formatCLP(bestHour.ingresos)}. Concentrar personal en horas punta.</span>
            </li>
          )}
          {hasDelivery && (
            <li className="flex items-start gap-2">
              <span className="text-amber-500 mt-0.5">•</span>
              <span>Delivery representa <strong>{deliveryData.find((t: any) => t.tipo === 'delivery')?.pct_ordenes}%</strong> de pedidos. Fee total: {formatCLP(data.delivery_impact.total_delivery_fees)}. Evaluar impacto en precio final.</span>
            </li>
          )}
          {bottomProducts.length > 5 && (
            <li className="flex items-start gap-2">
              <span className="text-amber-500 mt-0.5">•</span>
              <span><strong>{data.bottom_productos.length} productos</strong> sin ventas este mes. Revisar rotación y considerar dar de baja o replantear receta.</span>
            </li>
          )}
          {data.productos.inactivos > 0 && (
            <li className="flex items-start gap-2">
              <span className="text-amber-500 mt-0.5">•</span>
              <span><strong>{data.productos.inactivos} productos</strong> están inactivos. Evaluar si reactivar o eliminar definitivamente para simplificar inventario.</span>
            </li>
          )}
          {data.compras_mes > data.ventas_totales * 0.4 && (
            <li className="flex items-start gap-2">
              <span className="text-red-500 mt-0.5">•</span>
              <span className="text-red-800">Las compras representan <strong>{((data.compras_mes / data.ventas_totales) * 100).toFixed(0)}%</strong> de las ventas. Revisar costos de insumos y mermas.</span>
            </li>
          )}
        </ul>
      </div>
    </div>
  );
}
