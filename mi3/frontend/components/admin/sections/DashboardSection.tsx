'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, TrendingUp, Users, Calculator,
  ShoppingBag, ArrowLeftRight, CreditCard, ClipboardCheck,
  Target, Receipt, ChefHat,
} from 'lucide-react';

interface PnlIngresos {
  ventas_netas: number;
  total_ordenes: number;
  ticket_promedio: number;
}

interface PnlCostoVentas {
  costo_ingredientes: number;
  margen_bruto: number;
  margen_bruto_pct: number;
}

interface PnlGastosOperacion {
  nomina_ruta11: number;
  total_opex: number;
}

interface PnlFlujoCaja {
  compras_mes: number;
}

interface PnlResultado {
  resultado_neto: number;
  resultado_neto_pct: number;
}

interface PnlMeta {
  meta_mensual: number;
  porcentaje_meta: number;
  ventas_proyectadas: number;
}

interface PnlData {
  ingresos: PnlIngresos;
  costo_ventas: PnlCostoVentas;
  gastos_operacion: PnlGastosOperacion;
  resultado: PnlResultado;
  meta: PnlMeta;
  flujo_caja: PnlFlujoCaja;
}

interface DashboardData {
  ventas_mes: number;
  compras_mes: number;
  nomina_mes: number;
  resultado_bruto: number;
  pnl: PnlData;
}

const apps = [
  { label: 'Compras', icon: ShoppingBag, color: 'bg-blue-500', href: '/admin/compras' },
  { label: 'Checklists', icon: ClipboardCheck, color: 'bg-amber-500', href: '/admin/checklists' },
  { label: 'Cambios', icon: ArrowLeftRight, color: 'bg-purple-500', href: '/admin/cambios' },
  { label: 'Créditos', icon: CreditCard, color: 'bg-green-600', href: '/admin/creditos' },
];

const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function PnlRow({ label, value, pct, bold, color, indent }: {
  label: string; value: number; pct?: number; bold?: boolean; color?: string; indent?: boolean;
}) {
  const isNeg = value < 0;
  const textColor = color ?? (isNeg ? 'text-red-600' : 'text-gray-900');
  return (
    <div className={cn(
      'flex items-center justify-between py-2 px-3',
      bold && 'font-semibold',
      indent && 'pl-6',
    )}>
      <span className={cn('text-sm', bold ? 'text-gray-900' : 'text-gray-600')}>
        {label}
      </span>
      <div className="flex items-center gap-3">
        {pct !== undefined && (
          <span className="text-xs text-gray-400 tabular-nums w-12 text-right">
            {pct.toFixed(1)}%
          </span>
        )}
        <span className={cn('text-sm tabular-nums w-28 text-right', textColor)}>
          {isNeg ? '-' : ''}{formatCLP(Math.abs(value))}
        </span>
      </div>
    </div>
  );
}

function MetaProgress({ pct, meta, ventas }: { pct: number; meta: number; ventas: number }) {
  // La meta es el punto de equilibrio → va al 50% de la barra
  // 100% de la barra = 2x meta (zona de ganancia plena)
  const barMax = meta * 2;
  const barPct = Math.min(100, Math.max(0, (ventas / barMax) * 100));
  const isPastBreakeven = ventas >= meta;
  const barColor = isPastBreakeven ? 'bg-green-500' : barPct >= 35 ? 'bg-amber-500' : 'bg-red-500';
  const label = isPastBreakeven
    ? `+${formatCLP(ventas - meta)} sobre equilibrio`
    : `-${formatCLP(meta - ventas)} para equilibrio`;

  return (
    <div className="px-3 py-2">
      <div className="flex items-center justify-between mb-1">
        <span className="text-xs font-medium text-gray-500 flex items-center gap-1">
          <Target className="h-3 w-3" /> {label}
        </span>
        <span className="text-xs font-semibold text-gray-700">
          {formatCLP(ventas)} / {formatCLP(barMax)}
        </span>
      </div>
      <div className="relative w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
        <div
          className={cn('h-full rounded-full transition-all', barColor)}
          style={{ width: `${barPct}%` }}
          role="progressbar"
          aria-valuenow={barPct}
          aria-valuemin={0}
          aria-valuemax={100}
          aria-label={label}
        />
        {/* Marcador punto de equilibrio al 50% */}
        <div
          className="absolute top-0 h-full w-0.5 bg-gray-900/40"
          style={{ left: '50%' }}
          title={`Equilibrio: ${formatCLP(meta)}`}
        />
      </div>
      <div className="flex justify-between mt-0.5">
        <span className="text-[9px] text-gray-400">$0</span>
        <span className="text-[9px] text-gray-500 font-medium">Equilibrio {formatCLP(meta)}</span>
        <span className="text-[9px] text-gray-400">{formatCLP(barMax)}</span>
      </div>
    </div>
  );
}

export default function DashboardSection() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; data: DashboardData }>('/admin/dashboard')
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  const pnl = data?.pnl;
  const mesActual = meses[new Date().getMonth()];
  const anio = new Date().getFullYear();
  const ventas = pnl?.ingresos.ventas_netas ?? 0;

  const cogsPct = ventas > 0 ? ((pnl?.costo_ventas.costo_ingredientes ?? 0) / ventas) * 100 : 0;
  const nominaPct = ventas > 0 ? ((pnl?.gastos_operacion.nomina_ruta11 ?? 0) / ventas) * 100 : 0;
  const opexPct = nominaPct;

  return (
    <div className="space-y-6">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Panel Admin</h1>

      {/* Estado de Resultados */}
      <div className="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <div className="bg-gray-900 px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Receipt className="h-4 w-4 text-amber-400" />
            <h2 className="text-sm font-semibold text-white">Estado de Resultados</h2>
          </div>
          <span className="text-xs text-gray-400">{mesActual} {anio}</span>
        </div>

        {/* Meta progress */}
        {pnl && pnl.meta.meta_mensual > 0 && (
          <MetaProgress pct={pnl.meta.porcentaje_meta} meta={pnl.meta.meta_mensual} ventas={ventas} />
        )}

        {/* KPI pills */}
        <div className="grid grid-cols-3 gap-2 px-3 py-2">
          <div className="rounded-xl bg-green-50 px-3 py-2 text-center">
            <p className="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Pedidos</p>
            <p className="text-lg font-bold text-green-700">{pnl?.ingresos.total_ordenes ?? 0}</p>
          </div>
          <div className="rounded-xl bg-blue-50 px-3 py-2 text-center">
            <p className="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Ticket</p>
            <p className="text-lg font-bold text-blue-700">{formatCLP(pnl?.ingresos.ticket_promedio ?? 0)}</p>
          </div>
          <div className="rounded-xl bg-amber-50 px-3 py-2 text-center">
            <p className="text-[10px] font-medium text-gray-500 uppercase tracking-wide">Proyección</p>
            <p className="text-lg font-bold text-amber-700">{formatCLP(pnl?.meta.ventas_proyectadas ?? 0)}</p>
          </div>
        </div>

        <div className="divide-y divide-gray-100">
          {/* 1. INGRESOS */}
          <div className="bg-green-50/50">
            <div className="flex items-center gap-1.5 px-3 pt-2 pb-1">
              <TrendingUp className="h-3.5 w-3.5 text-green-600" />
              <span className="text-xs font-semibold text-green-700 uppercase tracking-wide">Ingresos</span>
            </div>
            <PnlRow label="Ventas Netas" value={ventas} pct={100} bold color="text-green-700" />
          </div>

          {/* 2. COSTO DE VENTAS */}
          <div>
            <div className="flex items-center gap-1.5 px-3 pt-2 pb-1">
              <ChefHat className="h-3.5 w-3.5 text-orange-600" />
              <span className="text-xs font-semibold text-orange-700 uppercase tracking-wide">Costo de Ventas</span>
            </div>
            <PnlRow label="Costo Ingredientes (CMV)" value={-(pnl?.costo_ventas.costo_ingredientes ?? 0)} pct={cogsPct} indent />
          </div>

          {/* 3. MARGEN BRUTO */}
          <div className="bg-emerald-50/50">
            <PnlRow
              label="Margen Bruto"
              value={pnl?.costo_ventas.margen_bruto ?? 0}
              pct={pnl?.costo_ventas.margen_bruto_pct ?? 0}
              bold
              color={(pnl?.costo_ventas.margen_bruto ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-600'}
            />
          </div>

          {/* 4. GASTOS DE OPERACIÓN */}
          <div>
            <div className="flex items-center gap-1.5 px-3 pt-2 pb-1">
              <Calculator className="h-3.5 w-3.5 text-red-600" />
              <span className="text-xs font-semibold text-red-700 uppercase tracking-wide">Gastos Operación</span>
            </div>
            <PnlRow label="Nómina Equipo" value={-(pnl?.gastos_operacion.nomina_ruta11 ?? 0)} pct={nominaPct} indent />
            <PnlRow label="Total OPEX" value={-(pnl?.gastos_operacion.total_opex ?? 0)} pct={opexPct} bold />
          </div>

          {/* 5. RESULTADO NETO */}
          <div className={cn(
            'py-1',
            (pnl?.resultado.resultado_neto ?? 0) >= 0 ? 'bg-green-100/60' : 'bg-red-100/60',
          )}>
            <PnlRow
              label="Resultado Neto"
              value={pnl?.resultado.resultado_neto ?? 0}
              pct={pnl?.resultado.resultado_neto_pct ?? 0}
              bold
              color={(pnl?.resultado.resultado_neto ?? 0) >= 0 ? 'text-green-800' : 'text-red-700'}
            />
          </div>
        </div>
      </div>

      {/* Apps internas */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 mb-3">Aplicaciones</h2>
        <div className="grid grid-cols-4 gap-3">
          {apps.map((app, i) => {
            const Icon = app.icon;
            return (
              <a
                key={i}
                href={app.href}
                className="flex flex-col items-center gap-2 py-3 rounded-xl hover:bg-gray-50 transition-colors"
              >
                <div className={cn(app.color, 'h-12 w-12 rounded-2xl flex items-center justify-center shadow-sm')}>
                  <Icon className="h-6 w-6 text-white" />
                </div>
                <span className="text-xs font-medium text-gray-700">{app.label}</span>
              </a>
            );
          })}
        </div>
      </div>
    </div>
  );
}
