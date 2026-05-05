'use client';

import React, { useEffect, useState, useCallback, useRef } from 'react';
import dynamic from 'next/dynamic';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { getEcho } from '@/lib/echo';
import {
  Loader2, TrendingUp, Calculator, Target, Receipt, ChefHat,
  ShoppingBag, ArrowLeftRight, CreditCard, ClipboardCheck,
  Wifi, WifiOff, Bell, BellOff, ChevronRight,
} from 'lucide-react';

const MonthlyChart = dynamic(() => import('@/components/admin/dashboard/MonthlyChart'), {
  ssr: false, loading: () => <div className="h-48 bg-gray-50 rounded-xl animate-pulse" />,
});
const TopProductsChart = dynamic(() => import('@/components/admin/dashboard/TopProductsChart'), {
  ssr: false, loading: () => <div className="h-48 bg-gray-50 rounded-xl animate-pulse" />,
});

/* ─── Types ─── */
interface PnlData {
  ingresos: { ventas_netas: number; total_ordenes: number; ticket_promedio: number };
  costo_ventas: { costo_ingredientes: number; costo_ingredientes_pct?: number; margen_bruto: number; margen_bruto_pct: number };
  gastos_operacion: {
    nomina_ruta11: number; nomina_ruta11_pct?: number; gas: number; gas_pct?: number;
    limpieza: number; limpieza_pct?: number; mermas: number; mermas_pct?: number;
    otros_gastos: number; otros_gastos_pct?: number; total_opex: number; total_opex_pct?: number;
  };
  resultado: { resultado_neto: number; resultado_neto_pct: number };
  meta: { meta_mensual: number; porcentaje_meta: number; ventas_proyectadas: number; meta_equilibrio: number };
  flujo_caja: { compras_mes: number };
}
interface DashboardData { ventas_mes: number; compras_mes: number; nomina_mes: number; resultado_bruto: number; pnl: PnlData }
interface ShiftKpis { total_sales: number; total_delivery: number; total_cost: number; total_profit: number; order_count: number; avg_ticket: number }
interface PaymentBreakdown { method: string; order_count: number; total_sales: number; total_cost: number; profit: number }
interface LiveSale { order_number: string; customer_name: string; total: number; timestamp: string }
interface CmvIngredient { ingredient_id: number; name: string; total_quantity: number; unit: string; total_cost: number; percentage: number }
interface CmvData { total_cmv: number; cmv_percentage: number; ingredients: CmvIngredient[]; untracked_cmv?: number }
interface CmvProductBreakdown { product_name: string; times_sold: number; recipe_qty: number; total_consumed: number; unit: string; cost: number }

/* ─── Helpers ─── */
const apps = [
  { label: 'Compras', icon: ShoppingBag, color: 'bg-blue-500', section: 'compras' },
  { label: 'Checklists', icon: ClipboardCheck, color: 'bg-amber-500', section: 'checklists' },
  { label: 'Cambios', icon: ArrowLeftRight, color: 'bg-purple-500', section: 'cambios' },
  { label: 'Créditos', icon: CreditCard, color: 'bg-green-600', section: 'creditos' },
];
const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const methodLabels: Record<string, string> = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transfer', webpay: 'Webpay', pedidosya: 'PYA', rl6_credit: 'RL6', r11_credit: 'R11' };

function PnlRow({ label, value, pct, bold, color, indent }: {
  label: string; value: number; pct?: number; bold?: boolean; color?: string; indent?: boolean;
}) {
  const isNeg = value < 0;
  const textColor = color ?? (isNeg ? 'text-red-600' : 'text-gray-900');
  return (
    <div className={cn('flex items-center justify-between py-1.5 px-3', bold && 'font-semibold', indent && 'pl-6')}>
      <span className={cn('text-xs', bold ? 'text-gray-900' : 'text-gray-600')}>{label}</span>
      <div className="flex items-center gap-2">
        {pct !== undefined && <span className="text-[10px] text-gray-400 tabular-nums w-10 text-right">{pct.toFixed(1)}%</span>}
        <span className={cn('text-xs tabular-nums w-24 text-right', textColor)}>{isNeg ? '-' : ''}{formatCLP(Math.abs(value))}</span>
      </div>
    </div>
  );
}

function PnlRowExpandable({ label, value, pct, bold, color, icon, children }: {
  label: string; value: number; pct?: number; bold?: boolean; color?: string;
  icon?: React.ReactNode; children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const isNeg = value < 0;
  const textColor = color ?? (isNeg ? 'text-red-600' : 'text-gray-900');
  return (
    <div>
      <button type="button" onClick={() => setOpen(v => !v)} className="flex items-center justify-between w-full py-1.5 px-3 hover:bg-gray-50 transition-colors" aria-expanded={open}>
        <div className="flex items-center gap-1.5">
          <ChevronRight className={cn('h-3 w-3 text-gray-400 transition-transform', open && 'rotate-90')} />
          {icon}
          <span className={cn('text-xs', bold ? 'font-semibold text-gray-900' : 'text-gray-600')}>{label}</span>
        </div>
        <div className="flex items-center gap-2">
          {pct !== undefined && <span className="text-[10px] text-gray-400 tabular-nums w-10 text-right">{pct.toFixed(1)}%</span>}
          <span className={cn('text-xs tabular-nums w-24 text-right font-semibold', textColor)}>{isNeg ? '-' : ''}{formatCLP(Math.abs(value))}</span>
        </div>
      </button>
      {open && <div className="border-t border-gray-50">{children}</div>}
    </div>
  );
}

function MetaProgress({ meta, ventas }: { meta: number; ventas: number }) {
  const barMax = meta * 2;
  const barPct = Math.min(100, Math.max(0, (ventas / barMax) * 100));
  const isPast = ventas >= meta;
  const barColor = isPast ? 'bg-green-500' : barPct >= 35 ? 'bg-amber-500' : 'bg-red-500';
  const label = isPast ? `+${formatCLP(ventas - meta)} sobre equilibrio` : `-${formatCLP(meta - ventas)} para equilibrio`;
  return (
    <div className="px-3 py-2">
      <div className="flex items-center justify-between mb-1">
        <span className="text-[10px] font-medium text-gray-500 flex items-center gap-1"><Target className="h-3 w-3" /> {label}</span>
      </div>
      <div className="relative w-full h-2 bg-gray-200 rounded-full overflow-hidden">
        <div className={cn('h-full rounded-full transition-all', barColor)} style={{ width: `${barPct}%` }} role="progressbar" aria-valuenow={barPct} aria-valuemin={0} aria-valuemax={100} aria-label={label} />
        <div className="absolute top-0 h-full w-0.5 bg-gray-900/40" style={{ left: '50%' }} />
      </div>
    </div>
  );
}

function LiveSalesMonitor({ shiftKpis, breakdown, liveSales, loading }: {
  shiftKpis: ShiftKpis | null; breakdown: PaymentBreakdown[]; liveSales: LiveSale[]; loading: boolean;
}) {
  const [wsConnected, setWsConnected] = useState(false);
  const [soundOn, setSoundOn] = useState(() => typeof window !== 'undefined' && localStorage.getItem('live-sound') !== 'off');
  const [expanded, setExpanded] = useState(false);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const pusher = (echo.connector as any)?.pusher;
    if (pusher) {
      pusher.connection.bind('connected', () => setWsConnected(true));
      pusher.connection.bind('disconnected', () => setWsConnected(false));
      setWsConnected(pusher.connection.state === 'connected');
    }
  }, []);

  return (
    <div className="rounded-xl bg-gradient-to-r from-red-600 to-red-500 text-white shadow-sm overflow-hidden">
      <div className="px-4 py-3">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-2">
            <span className="text-xs font-medium opacity-80">Turno Actual</span>
            {wsConnected ? <Wifi className="h-3 w-3 text-green-300" aria-label="Conectado" /> : <WifiOff className="h-3 w-3 text-red-300" aria-label="Desconectado" />}
          </div>
          <button type="button" onClick={() => { const n = !soundOn; setSoundOn(n); localStorage.setItem('live-sound', n ? 'on' : 'off'); }} className="opacity-70 hover:opacity-100" aria-label={soundOn ? 'Silenciar' : 'Activar sonido'}>
            {soundOn ? <Bell className="h-3.5 w-3.5" /> : <BellOff className="h-3.5 w-3.5" />}
          </button>
        </div>
        {loading ? (
          <div className="flex justify-center py-2"><Loader2 className="h-5 w-5 animate-spin text-white/60" /></div>
        ) : (
          <div className="grid grid-cols-3 gap-3">
            <div><p className="text-[10px] opacity-70">Ventas</p><p className="text-lg font-bold">{formatCLP(shiftKpis?.total_sales ?? 0)}</p></div>
            <div><p className="text-[10px] opacity-70">Pedidos</p><p className="text-lg font-bold">{shiftKpis?.order_count ?? 0}</p></div>
            <div><p className="text-[10px] opacity-70">Ticket</p><p className="text-lg font-bold">{formatCLP(shiftKpis?.avg_ticket ?? 0)}</p></div>
          </div>
        )}
        {breakdown.length > 0 && (
          <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-2 pt-2 border-t border-white/20">
            {breakdown.map(b => (
              <span key={b.method} className="text-[10px] opacity-80">{methodLabels[b.method] ?? b.method}: {formatCLP(b.total_sales)}</span>
            ))}
          </div>
        )}
      </div>
      {liveSales.length > 0 && (
        <div className="border-t border-white/20">
          <button type="button" onClick={() => setExpanded(v => !v)} className="w-full px-4 py-1.5 text-[10px] font-medium opacity-70 hover:opacity-100" aria-expanded={expanded}>
            {expanded ? '▾ Ocultar' : `▸ ${liveSales.length} ventas recientes`}
          </button>
          {expanded && (
            <div className="px-4 pb-3 space-y-1">
              {liveSales.slice(0, 5).map((s, i) => (
                <div key={i} className="flex items-center justify-between text-xs opacity-90">
                  <span className="truncate max-w-[60%]">#{s.order_number} — {s.customer_name || 'Sin nombre'}</span>
                  <span className="font-semibold">{formatCLP(s.total)}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function DashboardSection() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [shiftKpis, setShiftKpis] = useState<ShiftKpis | null>(null);
  const [breakdown, setBreakdown] = useState<PaymentBreakdown[]>([]);
  const [cmvData, setCmvData] = useState<CmvData | null>(null);
  const [cmvExpanded, setCmvExpanded] = useState<Record<number, CmvProductBreakdown[] | 'loading'>>({});
  const [loading, setLoading] = useState(true);
  const [edrLoading, setEdrLoading] = useState(false);
  const [shiftLoading, setShiftLoading] = useState(true);
  const [liveSales, setLiveSales] = useState<LiveSale[]>([]);
  const [selectedMonth, setSelectedMonth] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });

  const isCurrentMonth = selectedMonth === (() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  })();

  const navigateMonth = (dir: -1 | 1) => {
    const [y, m] = selectedMonth.split('-').map(Number);
    const d = new Date(y, m - 1 + dir, 1);
    setSelectedMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    setCmvExpanded({}); // Reset expanded state on month change
  };

  const toggleCmvIngredient = async (ingredientId: number) => {
    if (cmvExpanded[ingredientId]) {
      setCmvExpanded(prev => { const next = { ...prev }; delete next[ingredientId]; return next; });
      return;
    }
    setCmvExpanded(prev => ({ ...prev, [ingredientId]: 'loading' }));
    try {
      const monthParam = isCurrentMonth ? '' : `&month=${selectedMonth}`;
      const res = await apiFetch<{ success: boolean; data: { products: CmvProductBreakdown[] } }>(
        `/admin/ventas/cmv/${ingredientId}/products?period=month${monthParam}`
      );
      setCmvExpanded(prev => ({ ...prev, [ingredientId]: res?.data?.products ?? [] }));
    } catch {
      setCmvExpanded(prev => { const next = { ...prev }; delete next[ingredientId]; return next; });
    }
  };

  const selectedMonthLabel = (() => {
    const [y, m] = selectedMonth.split('-').map(Number);
    return `${meses[m - 1]} ${y}`;
  })();

  const fetchData = useCallback(async () => {
    if (data) setEdrLoading(true); else setLoading(true);
    try {
      const monthParam = isCurrentMonth ? '' : `?month=${selectedMonth}`;
      const [dashRes, cmvRes] = await Promise.allSettled([
        apiFetch<{ success: boolean; data: DashboardData }>(`/admin/dashboard${monthParam}`),
        apiFetch<{ success: boolean; data: CmvData }>(`/admin/ventas/cmv?period=month${isCurrentMonth ? '' : `&month=${selectedMonth}`}`),
      ]);
      if (dashRes.status === 'fulfilled' && dashRes.value?.data) setData(dashRes.value.data);
      if (cmvRes.status === 'fulfilled' && cmvRes.value?.data) setCmvData(cmvRes.value.data);
    } catch (err) {
      console.error('[Dashboard] fetchData error:', err);
    }
    finally { setLoading(false); setEdrLoading(false); }
  }, [selectedMonth, isCurrentMonth]);

  // Shift KPIs: only fetch once on mount (not affected by month navigation)
  const fetchShift = useCallback(async () => {
    setShiftLoading(true);
    try {
      const shiftRes = await apiFetch<{ success: boolean; data: { kpis: ShiftKpis; payment_breakdown: PaymentBreakdown[] } }>('/admin/ventas/kpis?period=shift_today');
      if (shiftRes.data) {
        setShiftKpis(shiftRes.data.kpis ?? null);
        setBreakdown(shiftRes.data.payment_breakdown ?? []);
      }
    } catch (err) {
      console.error('[Dashboard] fetchShift error:', err);
    }
    finally { setShiftLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);
  useEffect(() => { fetchShift(); }, [fetchShift]);

  // Use refs so the WebSocket listener always calls the latest fetch functions
  // without re-subscribing to the channel (which would cause leave/join loops).
  const fetchDataRef = useRef(fetchData);
  const fetchShiftRef = useRef(fetchShift);
  const isCurrentMonthRef = useRef(isCurrentMonth);
  useEffect(() => { fetchDataRef.current = fetchData; }, [fetchData]);
  useEffect(() => { fetchShiftRef.current = fetchShift; }, [fetchShift]);
  useEffect(() => { isCurrentMonthRef.current = isCurrentMonth; }, [isCurrentMonth]);

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.channel('admin.ventas');
    channel.listen('.venta.nueva', (payload: any) => {
      if (payload?.order) {
        setLiveSales(prev => [{ order_number: payload.order.order_number ?? '', customer_name: payload.order.customer_name ?? '', total: payload.order.total ?? 0, timestamp: new Date().toISOString() }, ...prev].slice(0, 10));
      }
      fetchShiftRef.current();
      if (isCurrentMonthRef.current) fetchDataRef.current();
    });
    return () => { echo.leave('admin.ventas'); };
  }, []); // stable — subscribe once, never re-subscribe

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  const pnl = data?.pnl;
  const ventas = pnl?.ingresos.ventas_netas ?? 0;
  const go = pnl?.gastos_operacion;
  const metaEquilibrio = pnl?.meta.meta_equilibrio ?? 0;
  const cogsPct = ventas > 0 ? ((pnl?.costo_ventas.costo_ingredientes ?? 0) / ventas) * 100 : 0;

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Panel Admin</h1>

      <LiveSalesMonitor shiftKpis={shiftKpis} breakdown={breakdown} liveSales={liveSales} loading={shiftLoading} />

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="space-y-3">
          <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
            <div className="bg-neutral-800 px-4 py-2.5 flex items-center justify-between">
              <div className="flex items-center gap-2"><Receipt className="h-4 w-4 text-amber-400" /><h2 className="text-sm font-semibold text-white">Estado de Resultados</h2></div>
              <div className="flex items-center gap-1">
                <button type="button" onClick={() => navigateMonth(-1)} className="h-7 w-7 flex items-center justify-center rounded-md text-gray-400 hover:text-white hover:bg-white/10 transition-colors" aria-label="Mes anterior">◀</button>
                <span className="text-xs text-gray-300 font-medium min-w-[90px] text-center">{selectedMonthLabel}</span>
                <button type="button" onClick={() => navigateMonth(1)} disabled={isCurrentMonth} className="h-7 w-7 flex items-center justify-center rounded-md text-gray-400 hover:text-white hover:bg-white/10 transition-colors disabled:opacity-30 disabled:cursor-not-allowed" aria-label="Mes siguiente">▶</button>
              </div>
            </div>
            {pnl && metaEquilibrio > 0 && !edrLoading && <MetaProgress meta={metaEquilibrio} ventas={ventas} />}
            {edrLoading ? (
              <div className="px-3 py-6 space-y-3" role="status" aria-label="Cargando estado de resultados">
                <div className="h-2 bg-gray-200 rounded-full animate-pulse" />
                <div className="grid grid-cols-3 gap-2">
                  {[1,2,3].map(i => <div key={i} className="h-12 bg-gray-100 rounded-lg animate-pulse" />)}
                </div>
                {[1,2,3,4,5].map(i => <div key={i} className="h-6 bg-gray-100 rounded animate-pulse" />)}
                <div className="h-12 bg-gray-200 rounded-b-xl animate-pulse" />
              </div>
            ) : (
            <>
            <div className="grid grid-cols-3 gap-2 px-3 py-2">
              <div className="rounded-lg bg-gray-50 px-2 py-1.5 text-center"><p className="text-[9px] font-medium text-gray-500 uppercase">Pedidos</p><p className="text-base font-bold text-gray-900">{pnl?.ingresos.total_ordenes ?? 0}</p></div>
              <div className="rounded-lg bg-gray-50 px-2 py-1.5 text-center"><p className="text-[9px] font-medium text-gray-500 uppercase">Ticket</p><p className="text-base font-bold text-gray-900">{formatCLP(pnl?.ingresos.ticket_promedio ?? 0)}</p></div>
              <div className="rounded-lg bg-gray-50 px-2 py-1.5 text-center"><p className="text-[9px] font-medium text-gray-500 uppercase">Proyección</p><p className="text-base font-bold text-gray-900">{formatCLP(pnl?.meta.ventas_proyectadas ?? 0)}</p></div>
            </div>
            <div className="divide-y divide-gray-100">
              <PnlRowExpandable label="Ventas Netas" value={ventas} pct={100} bold color="text-green-700" icon={<TrendingUp className="h-3 w-3 text-green-600" />}>
                <div className="px-3 py-2 space-y-1">
                  {(pnl?.ingresos as any)?.payment_breakdown?.length > 0
                    ? (pnl?.ingresos as any).payment_breakdown.map((b: any) => (
                      <div key={b.method} className="flex items-center justify-between text-xs text-gray-600">
                        <span>{methodLabels[b.method] ?? b.method} ({b.order_count})</span>
                        <span className="tabular-nums font-medium">{formatCLP(b.total_sales)}</span>
                      </div>
                    ))
                    : breakdown.map(b => (
                      <div key={b.method} className="flex items-center justify-between text-xs text-gray-600">
                        <span>{methodLabels[b.method] ?? b.method} ({b.order_count})</span>
                        <span className="tabular-nums font-medium">{formatCLP(b.total_sales)}</span>
                      </div>
                    ))
                  }
                </div>
              </PnlRowExpandable>
              <PnlRowExpandable label="Costo Ingredientes (CMV)" value={-(pnl?.costo_ventas.costo_ingredientes ?? 0)} pct={cogsPct} color="text-red-600" icon={<ChefHat className="h-3 w-3 text-orange-600" />}>
                {cmvData && cmvData.ingredients.length > 0 ? (
                  <div className="overflow-x-auto">
                    <table className="w-full text-[11px]" role="table" aria-label="CMV por ingrediente">
                      <thead><tr className="text-gray-400 text-[9px] uppercase tracking-wider"><th className="text-left px-3 py-1 font-medium">Ingrediente</th><th className="text-right px-2 py-1 font-medium">Consumo</th><th className="text-right px-2 py-1 font-medium">Costo</th><th className="text-right px-2 py-1 font-medium">%</th></tr></thead>
                      <tbody className="divide-y divide-gray-50">
                        {cmvData.ingredients.map(ing => {
                          const expanded = cmvExpanded[ing.ingredient_id];
                          const isOpen = !!expanded;
                          const isLoading = expanded === 'loading';
                          const products = Array.isArray(expanded) ? expanded : [];
                          return (
                            <React.Fragment key={ing.ingredient_id}>
                              <tr
                                className={cn('cursor-pointer hover:bg-gray-50 transition-colors', ing.percentage > 10 && 'bg-red-50/60')}
                                onClick={() => toggleCmvIngredient(ing.ingredient_id)}
                              >
                                <td className="px-3 py-1 font-medium text-gray-700">
                                  <span className="inline-flex items-center gap-1">
                                    <ChevronRight className={cn('h-3 w-3 text-gray-400 transition-transform', isOpen && 'rotate-90')} />
                                    {ing.name}
                                  </span>
                                </td>
                                <td className="text-right px-2 py-1 text-gray-500 tabular-nums">{ing.total_quantity} {ing.unit}</td>
                                <td className="text-right px-2 py-1 text-gray-900 font-medium tabular-nums">{formatCLP(ing.total_cost)}</td>
                                <td className={cn('text-right px-2 py-1 tabular-nums font-medium', ing.percentage > 10 ? 'text-red-600' : 'text-gray-500')}>{ing.percentage}%</td>
                              </tr>
                              {isOpen && (
                                <tr>
                                  <td colSpan={4} className="px-0 py-0">
                                    {isLoading ? (
                                      <div className="flex items-center gap-2 px-6 py-2 text-[10px] text-gray-400"><Loader2 className="h-3 w-3 animate-spin" />Cargando...</div>
                                    ) : products.length > 0 ? (
                                      <div className="bg-gray-50/80 px-6 py-1.5 space-y-0.5">
                                        {products.map((p, i) => (
                                          <div key={i} className="flex items-center justify-between text-[10px] text-gray-600">
                                            <span>{p.product_name} ×{p.times_sold}</span>
                                            <span className="tabular-nums">{p.total_consumed} {p.unit} ({p.recipe_qty}{p.unit}/u) · {formatCLP(p.cost)}</span>
                                          </div>
                                        ))}
                                      </div>
                                    ) : (
                                      <div className="px-6 py-1.5 text-[10px] text-gray-400">Sin desglose disponible</div>
                                    )}
                                  </td>
                                </tr>
                              )}
                            </React.Fragment>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                ) : <p className="px-3 py-2 text-xs text-gray-400">Sin datos</p>}
                {cmvData && (cmvData.untracked_cmv ?? 0) > 0 && (
                  <div className="px-3 py-1.5 bg-amber-50 border-t text-[11px] flex items-center justify-between">
                    <span className="text-amber-700">⚠ Sin trazabilidad (órdenes sin inventario)</span>
                    <span className="font-medium text-amber-800 tabular-nums">{formatCLP(cmvData.untracked_cmv ?? 0)}</span>
                  </div>
                )}
              </PnlRowExpandable>
              <PnlRow label="Margen Bruto" value={pnl?.costo_ventas.margen_bruto ?? 0} pct={pnl?.costo_ventas.margen_bruto_pct ?? 0} bold color={(pnl?.costo_ventas.margen_bruto ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-600'} />
              <PnlRowExpandable label="Gastos Operación" value={-(go?.total_opex ?? 0)} pct={go?.total_opex_pct ?? 0} color="text-red-600" icon={<Calculator className="h-3 w-3 text-red-600" />}>
                <div>
                  <PnlRow label="Nómina" value={-(go?.nomina_ruta11 ?? 0)} pct={go?.nomina_ruta11_pct ?? 0} indent />
                  <PnlRowExpandable label="Gas" value={-(go?.gas ?? 0)} pct={go?.gas_pct ?? 0}>
                    <div className="px-6 py-1 space-y-1">
                      {((go as any)?.gas_items ?? []).length > 0
                        ? (go as any).gas_items.map((item: any, i: number) => (
                          <div key={i} className="flex items-center justify-between text-[11px] text-gray-600">
                            <span>{item.proveedor} <span className="text-gray-400">· {item.fecha?.slice(0, 10)}</span></span>
                            <span className="tabular-nums font-medium">{formatCLP(item.monto)}</span>
                          </div>
                        ))
                        : <p className="text-[11px] text-gray-400">Sin compras de gas</p>
                      }
                    </div>
                  </PnlRowExpandable>
                  <PnlRowExpandable label="Limpieza" value={-(go?.limpieza ?? 0)} pct={go?.limpieza_pct ?? 0}>
                    <div className="px-6 py-1 space-y-1">
                      {((go as any)?.limpieza_items ?? []).length > 0
                        ? (go as any).limpieza_items.map((item: any, i: number) => (
                          <div key={i} className="flex items-center justify-between text-[11px] text-gray-600">
                            <span>{item.proveedor} ({item.cantidad} {item.unidad}) <span className="text-gray-400">· {item.fecha?.slice(0, 10)}</span></span>
                            <span className="tabular-nums font-medium">{formatCLP(item.monto)}</span>
                          </div>
                        ))
                        : <p className="text-[11px] text-gray-400">Sin compras de limpieza</p>
                      }
                    </div>
                  </PnlRowExpandable>
                  <PnlRowExpandable label="Mermas" value={-(go?.mermas ?? 0)} pct={go?.mermas_pct ?? 0}>
                    <div className="px-6 py-1 space-y-1">
                      {((go as any)?.mermas_items ?? []).length > 0
                        ? (go as any).mermas_items.map((item: any, i: number) => (
                          <div key={i} className="flex items-center justify-between text-[11px] text-gray-600">
                            <span>{item.name} ({item.quantity} {item.unit}) {item.reason && <span className="text-gray-400">— {item.reason}</span>}</span>
                            <span className="tabular-nums font-medium">{formatCLP(item.cost)}</span>
                          </div>
                        ))
                        : <p className="text-[11px] text-gray-400">Sin mermas</p>
                      }
                    </div>
                  </PnlRowExpandable>
                  <PnlRow label="Otros" value={-(go?.otros_gastos ?? 0)} pct={go?.otros_gastos_pct ?? 0} indent />
                </div>
              </PnlRowExpandable>
              {metaEquilibrio > 0 && <PnlRow label="Meta Equilibrio" value={metaEquilibrio} bold color="text-blue-700" />}
              <div className={cn('py-3 rounded-b-xl', (pnl?.resultado.resultado_neto ?? 0) >= 0 ? 'bg-green-600' : 'bg-red-600')}>
                <div className="flex items-center justify-between px-4">
                  <span className="text-sm font-bold text-white">Resultado Neto</span>
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-white/80 tabular-nums font-semibold">{(pnl?.resultado.resultado_neto_pct ?? 0).toFixed(1)}%</span>
                    <span className="text-xl font-black tabular-nums text-white">
                      {(pnl?.resultado.resultado_neto ?? 0) < 0 ? '-' : ''}{formatCLP(Math.abs(pnl?.resultado.resultado_neto ?? 0))}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            </>
            )}
          </div>
          <div className="rounded-xl border bg-white shadow-sm p-4">
            <h3 className="text-xs font-semibold text-gray-500 mb-2">Aplicaciones</h3>
            <div className="grid grid-cols-4 gap-2">
              {apps.map((app, i) => { const Icon = app.icon; return (
                <a key={i} href={`/admin/${app.section}`} className="flex flex-col items-center gap-1.5 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                  <div className={cn(app.color, 'h-10 w-10 rounded-xl flex items-center justify-center shadow-sm')}><Icon className="h-5 w-5 text-white" /></div>
                  <span className="text-[10px] font-medium text-gray-700">{app.label}</span>
                </a>
              ); })}
            </div>
          </div>
        </div>
        <div className="space-y-3">
          <MonthlyChart />
          <TopProductsChart />
        </div>
      </div>
    </div>
  );
}
