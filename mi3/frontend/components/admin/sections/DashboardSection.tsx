'use client';

import { useEffect, useState, useCallback } from 'react';
import dynamic from 'next/dynamic';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { getEcho } from '@/lib/echo';
import {
  Loader2, TrendingUp, Calculator, Target, Receipt, ChefHat,
  ShoppingBag, ArrowLeftRight, CreditCard, ClipboardCheck,
  Wifi, WifiOff, Bell, BellOff,
} from 'lucide-react';
import CollapsibleSection from '@/components/admin/dashboard/CollapsibleSection';
import CmvSection from '@/components/admin/dashboard/CmvSection';

const MonthlyChart = dynamic(() => import('@/components/admin/dashboard/MonthlyChart'), {
  ssr: false,
  loading: () => <div className="h-48 bg-gray-50 rounded-xl animate-pulse" />,
});
const TopProductsChart = dynamic(() => import('@/components/admin/dashboard/TopProductsChart'), {
  ssr: false,
  loading: () => <div className="h-48 bg-gray-50 rounded-xl animate-pulse" />,
});

/* ─── Types ─── */
interface PnlData {
  ingresos: { ventas_netas: number; total_ordenes: number; ticket_promedio: number };
  costo_ventas: { costo_ingredientes: number; costo_ingredientes_pct?: number; margen_bruto: number; margen_bruto_pct: number };
  gastos_operacion: {
    nomina_ruta11: number; nomina_ruta11_pct?: number;
    gas: number; gas_pct?: number;
    limpieza: number; limpieza_pct?: number;
    mermas: number; mermas_pct?: number;
    otros_gastos: number; otros_gastos_pct?: number;
    total_opex: number; total_opex_pct?: number;
  };
  resultado: { resultado_neto: number; resultado_neto_pct: number };
  meta: { meta_mensual: number; porcentaje_meta: number; ventas_proyectadas: number; meta_equilibrio: number };
  flujo_caja: { compras_mes: number };
}
interface DashboardData { ventas_mes: number; compras_mes: number; nomina_mes: number; resultado_bruto: number; pnl: PnlData }
interface LiveSale { order_number: string; customer_name: string; total: number; timestamp: string }

/* ─── Helpers ─── */
const apps = [
  { label: 'Compras', icon: ShoppingBag, color: 'bg-blue-500', section: 'compras' },
  { label: 'Checklists', icon: ClipboardCheck, color: 'bg-amber-500', section: 'checklists' },
  { label: 'Cambios', icon: ArrowLeftRight, color: 'bg-purple-500', section: 'cambios' },
  { label: 'Créditos', icon: CreditCard, color: 'bg-green-600', section: 'creditos' },
];
const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

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
        <span className="text-[10px] font-semibold text-gray-700">{formatCLP(ventas)} / {formatCLP(barMax)}</span>
      </div>
      <div className="relative w-full h-2 bg-gray-200 rounded-full overflow-hidden">
        <div className={cn('h-full rounded-full transition-all', barColor)} style={{ width: `${barPct}%` }} role="progressbar" aria-valuenow={barPct} aria-valuemin={0} aria-valuemax={100} aria-label={label} />
        <div className="absolute top-0 h-full w-0.5 bg-gray-900/40" style={{ left: '50%' }} title={`Equilibrio: ${formatCLP(meta)}`} />
      </div>
    </div>
  );
}

/* ─── Live Sales Monitor ─── */
function LiveSalesMonitor({ ventas, pedidos, ticket, liveSales }: {
  ventas: number; pedidos: number; ticket: number; liveSales: LiveSale[];
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

  const toggleSound = () => {
    const next = !soundOn;
    setSoundOn(next);
    localStorage.setItem('live-sound', next ? 'on' : 'off');
  };

  return (
    <div className="rounded-xl bg-gradient-to-r from-red-600 to-red-500 text-white shadow-sm overflow-hidden">
      <div className="px-4 py-3">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-2">
            <span className="text-xs font-medium opacity-80">Monitor en Vivo</span>
            {wsConnected
              ? <Wifi className="h-3 w-3 text-green-300" aria-label="Conectado" />
              : <WifiOff className="h-3 w-3 text-red-300" aria-label="Desconectado" />}
          </div>
          <button type="button" onClick={toggleSound} className="opacity-70 hover:opacity-100 transition-opacity" aria-label={soundOn ? 'Silenciar' : 'Activar sonido'}>
            {soundOn ? <Bell className="h-3.5 w-3.5" /> : <BellOff className="h-3.5 w-3.5" />}
          </button>
        </div>
        <div className="grid grid-cols-3 gap-3">
          <div><p className="text-[10px] opacity-70">Ventas turno</p><p className="text-lg font-bold">{formatCLP(ventas)}</p></div>
          <div><p className="text-[10px] opacity-70">Pedidos</p><p className="text-lg font-bold">{pedidos}</p></div>
          <div><p className="text-[10px] opacity-70">Ticket</p><p className="text-lg font-bold">{formatCLP(ticket)}</p></div>
        </div>
      </div>
      {liveSales.length > 0 && (
        <div className="border-t border-white/20">
          <button type="button" onClick={() => setExpanded(v => !v)} className="w-full px-4 py-1.5 text-[10px] font-medium opacity-70 hover:opacity-100 transition-opacity" aria-expanded={expanded}>
            {expanded ? '▾ Ocultar últimas' : `▸ Ver últimas ${liveSales.length} ventas`}
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

/* ─── Main Dashboard ─── */
export default function DashboardSection() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [liveSales, setLiveSales] = useState<LiveSale[]>([]);

  const fetchData = useCallback(async () => {
    try {
      const res = await apiFetch<{ success: boolean; data: DashboardData }>('/admin/dashboard');
      if (res.data) setData(res.data);
    } catch { /* silent */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  // Realtime: listen for new sales
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;
    const channel = echo.channel('admin.ventas');
    channel.listen('.venta.nueva', (payload: any) => {
      if (payload.order) {
        setLiveSales(prev => [{ order_number: payload.order.order_number, customer_name: payload.order.customer_name, total: payload.order.total, timestamp: new Date().toISOString() }, ...prev].slice(0, 10));
      }
      fetchData();
    });
    return () => { echo.leave('admin.ventas'); };
  }, [fetchData]);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  const pnl = data?.pnl;
  const mesActual = meses[new Date().getMonth()];
  const anio = new Date().getFullYear();
  const ventas = pnl?.ingresos.ventas_netas ?? 0;
  const go = pnl?.gastos_operacion;
  const metaEquilibrio = pnl?.meta.meta_equilibrio ?? 0;
  const cogsPct = ventas > 0 ? ((pnl?.costo_ventas.costo_ingredientes ?? 0) / ventas) * 100 : 0;
  const nominaPct = go?.nomina_ruta11_pct ?? (ventas > 0 ? ((go?.nomina_ruta11 ?? 0) / ventas) * 100 : 0);
  const gasPct = go?.gas_pct ?? 0;
  const limpiezaPct = go?.limpieza_pct ?? 0;
  const mermasPct = go?.mermas_pct ?? 0;
  const otrosPct = go?.otros_gastos_pct ?? 0;
  const opexPct = go?.total_opex_pct ?? 0;

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Panel Admin</h1>

      {/* Live Monitor — full width */}
      <LiveSalesMonitor
        ventas={ventas}
        pedidos={pnl?.ingresos.total_ordenes ?? 0}
        ticket={pnl?.ingresos.ticket_promedio ?? 0}
        liveSales={liveSales}
      />

      {/* Split layout: left data, right charts */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* LEFT: Collapsible data sections */}
        <div className="space-y-3">
          {/* Estado de Resultados */}
          <CollapsibleSection
            title={`Estado de Resultados — ${mesActual} ${anio}`}
            summary={<span>{formatCLP(ventas)} · {(pnl?.costo_ventas.margen_bruto_pct ?? 0).toFixed(0)}% margen</span>}
            defaultOpen
            accentColor="border-green-500"
            icon={<Receipt className="h-4 w-4 text-green-600" />}
          >
            {pnl && metaEquilibrio > 0 && <MetaProgress meta={metaEquilibrio} ventas={ventas} />}
            <div className="grid grid-cols-3 gap-2 px-3 py-2">
              <div className="rounded-lg bg-green-50 px-2 py-1.5 text-center">
                <p className="text-[9px] font-medium text-gray-500 uppercase">Pedidos</p>
                <p className="text-base font-bold text-green-700">{pnl?.ingresos.total_ordenes ?? 0}</p>
              </div>
              <div className="rounded-lg bg-blue-50 px-2 py-1.5 text-center">
                <p className="text-[9px] font-medium text-gray-500 uppercase">Ticket</p>
                <p className="text-base font-bold text-blue-700">{formatCLP(pnl?.ingresos.ticket_promedio ?? 0)}</p>
              </div>
              <div className="rounded-lg bg-amber-50 px-2 py-1.5 text-center">
                <p className="text-[9px] font-medium text-gray-500 uppercase">Proyección</p>
                <p className="text-base font-bold text-amber-700">{formatCLP(pnl?.meta.ventas_proyectadas ?? 0)}</p>
              </div>
            </div>
            <div className="divide-y divide-gray-100">
              <div className="bg-green-50/50">
                <div className="flex items-center gap-1.5 px-3 pt-1.5 pb-0.5"><TrendingUp className="h-3 w-3 text-green-600" /><span className="text-[10px] font-semibold text-green-700 uppercase tracking-wide">Ingresos</span></div>
                <PnlRow label="Ventas Netas" value={ventas} pct={100} bold color="text-green-700" />
              </div>
              <div>
                <div className="flex items-center gap-1.5 px-3 pt-1.5 pb-0.5"><ChefHat className="h-3 w-3 text-orange-600" /><span className="text-[10px] font-semibold text-orange-700 uppercase tracking-wide">Costo de Ventas</span></div>
                <PnlRow label="CMV" value={-(pnl?.costo_ventas.costo_ingredientes ?? 0)} pct={cogsPct} indent />
              </div>
              <div className="bg-emerald-50/50">
                <PnlRow label="Margen Bruto" value={pnl?.costo_ventas.margen_bruto ?? 0} pct={pnl?.costo_ventas.margen_bruto_pct ?? 0} bold color={(pnl?.costo_ventas.margen_bruto ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-600'} />
              </div>
              <div>
                <div className="flex items-center gap-1.5 px-3 pt-1.5 pb-0.5"><Calculator className="h-3 w-3 text-red-600" /><span className="text-[10px] font-semibold text-red-700 uppercase tracking-wide">Gastos Operación</span></div>
                <PnlRow label="Nómina" value={-(go?.nomina_ruta11 ?? 0)} pct={nominaPct} indent />
                <PnlRow label="Gas" value={-(go?.gas ?? 0)} pct={gasPct} indent />
                <PnlRow label="Limpieza" value={-(go?.limpieza ?? 0)} pct={limpiezaPct} indent />
                <PnlRow label="Mermas" value={-(go?.mermas ?? 0)} pct={mermasPct} indent />
                <PnlRow label="Otros" value={-(go?.otros_gastos ?? 0)} pct={otrosPct} indent />
                <PnlRow label="Total OPEX" value={-(go?.total_opex ?? 0)} pct={opexPct} bold />
              </div>
              {metaEquilibrio > 0 && (
                <div className="bg-blue-50/50"><PnlRow label="Meta Equilibrio" value={metaEquilibrio} bold color="text-blue-700" /></div>
              )}
              <div className={cn('py-0.5', (pnl?.resultado.resultado_neto ?? 0) >= 0 ? 'bg-green-100/60' : 'bg-red-100/60')}>
                <PnlRow label="Resultado Neto" value={pnl?.resultado.resultado_neto ?? 0} pct={pnl?.resultado.resultado_neto_pct ?? 0} bold color={(pnl?.resultado.resultado_neto ?? 0) >= 0 ? 'text-green-800' : 'text-red-700'} />
              </div>
            </div>
          </CollapsibleSection>

          <CmvSection />

          <div className="rounded-xl border bg-white shadow-sm p-4">
            <h3 className="text-xs font-semibold text-gray-500 mb-2">Aplicaciones</h3>
            <div className="grid grid-cols-4 gap-2">
              {apps.map((app, i) => {
                const Icon = app.icon;
                return (
                  <a key={i} href={`/admin/${app.section}`} className="flex flex-col items-center gap-1.5 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                    <div className={cn(app.color, 'h-10 w-10 rounded-xl flex items-center justify-center shadow-sm')}>
                      <Icon className="h-5 w-5 text-white" />
                    </div>
                    <span className="text-[10px] font-medium text-gray-700">{app.label}</span>
                  </a>
                );
              })}
            </div>
          </div>
        </div>

        {/* RIGHT: Charts */}
        <div className="space-y-3">
          <MonthlyChart />
          <TopProductsChart />
        </div>
      </div>
    </div>
  );
}
