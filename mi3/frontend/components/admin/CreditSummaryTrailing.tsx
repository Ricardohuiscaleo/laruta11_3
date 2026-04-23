'use client';

import { formatCLP, cn } from '@/lib/utils';
import type { RL6Summary } from '@/types/admin';

/* ─── R11 CreditUser (matches CreditosSection's local type) ─── */
interface CreditUser {
  id: number;
  nombre: string;
  limite: number;
  usado: number;
  disponible: number;
  bloqueado: boolean;
  aprobado: boolean;
}

interface CreditSummaryTrailingProps {
  activeTab: 'r11' | 'rl6';
  rl6Summary: RL6Summary | null;
  r11Data: CreditUser[] | null;
  loading: boolean;
}

/* ─── Skeleton pill ─── */
function Skeleton({ className }: { className?: string }) {
  return (
    <div
      className={cn('animate-pulse rounded bg-gray-200', className)}
      aria-hidden="true"
    />
  );
}

/* ─── Single metric card ─── */
function Metric({
  label,
  value,
  secondary,
  color,
  dot,
  loading,
}: {
  label: string;
  value: string;
  secondary?: string;
  color?: string;
  dot?: boolean;
  loading?: boolean;
}) {
  if (loading) {
    return (
      <div className="flex flex-col gap-0.5 min-w-0">
        <Skeleton className="h-3 w-14" />
        <Skeleton className="h-4 w-20" />
      </div>
    );
  }

  return (
    <div className="flex flex-col min-w-0">
      <span className="text-[10px] font-medium text-gray-400 uppercase tracking-wide truncate">
        {label}
      </span>
      <div className="flex items-center gap-1">
        {dot && <span className="h-1.5 w-1.5 rounded-full bg-red-500 shrink-0" />}
        <span className={cn('text-sm font-semibold truncate', color || 'text-gray-900')}>
          {value}
        </span>
      </div>
      {secondary && (
        <span className="text-[10px] text-gray-400 truncate">{secondary}</span>
      )}
    </div>
  );
}

/* ─── Tasa de cobro color ─── */
function tasaColor(tasa: number): string {
  if (tasa >= 80) return 'text-green-600';
  if (tasa >= 50) return 'text-amber-600';
  return 'text-red-600';
}

/* ─── Main component ─── */
export default function CreditSummaryTrailing({
  activeTab,
  rl6Summary,
  r11Data,
  loading,
}: CreditSummaryTrailingProps) {
  /* ─── R11 aggregated metrics ─── */
  const r11Count = r11Data?.length ?? 0;
  const r11Otorgado = r11Data?.reduce((sum, u) => sum + u.limite, 0) ?? 0;
  const r11Deuda = r11Data?.reduce((sum, u) => sum + u.usado, 0) ?? 0;
  const r11Disponible = r11Otorgado - r11Deuda;

  if (activeTab === 'rl6') {
    const s = rl6Summary;
    const morosColor = s && s.total_morosos > 0 ? 'text-red-600' : 'text-green-600';
    const morosDot = s ? s.total_morosos > 0 : false;

    return (
      <div className="flex items-center gap-4 overflow-x-auto py-0.5" style={{ scrollbarWidth: 'none' }}>
        {/* Desktop: all 5 metrics */}
        <div className="hidden md:flex items-center gap-4">
          <Metric label="Clientes RL6" value={String(s?.total_usuarios ?? 0)} loading={loading} />
          <Metric label="Crédito Otorgado" value={s ? formatCLP(s.total_credito_otorgado) : '$0'} loading={loading} />
          <Metric label="Deuda Actual" value={s ? formatCLP(s.total_deuda_actual) : '$0'} loading={loading} />
          <Metric
            label="Morosos"
            value={String(s?.total_morosos ?? 0)}
            secondary={s ? formatCLP(s.total_deuda_morosos) : undefined}
            color={morosColor}
            dot={morosDot}
            loading={loading}
          />
          <Metric
            label="Tasa de Cobro"
            value={s ? `${s.tasa_cobro}%` : '0%'}
            color={s ? tasaColor(s.tasa_cobro) : undefined}
            loading={loading}
          />
        </div>

        {/* Mobile: 3 condensed metrics (Deuda Actual, Morosos, Tasa de Cobro) */}
        <div className="flex md:hidden items-center gap-4">
          <Metric label="Deuda Actual" value={s ? formatCLP(s.total_deuda_actual) : '$0'} loading={loading} />
          <Metric
            label="Morosos"
            value={String(s?.total_morosos ?? 0)}
            secondary={s ? formatCLP(s.total_deuda_morosos) : undefined}
            color={morosColor}
            dot={morosDot}
            loading={loading}
          />
          <Metric
            label="Tasa de Cobro"
            value={s ? `${s.tasa_cobro}%` : '0%'}
            color={s ? tasaColor(s.tasa_cobro) : undefined}
            loading={loading}
          />
        </div>
      </div>
    );
  }

  /* ─── R11 tab ─── */
  return (
    <div className="flex items-center gap-4 overflow-x-auto py-0.5" style={{ scrollbarWidth: 'none' }}>
      {/* Desktop: all 4 metrics */}
      <div className="hidden md:flex items-center gap-4">
        <Metric label="Usuarios R11" value={String(r11Count)} loading={loading} />
        <Metric label="Crédito Otorgado" value={formatCLP(r11Otorgado)} loading={loading} />
        <Metric label="Deuda Actual" value={formatCLP(r11Deuda)} loading={loading} />
        <Metric label="Disponible" value={formatCLP(r11Disponible)} loading={loading} />
      </div>

      {/* Mobile: 3 condensed metrics (Deuda Actual, Disponible, Usuarios R11) */}
      <div className="flex md:hidden items-center gap-4">
        <Metric label="Deuda Actual" value={formatCLP(r11Deuda)} loading={loading} />
        <Metric label="Disponible" value={formatCLP(r11Disponible)} loading={loading} />
        <Metric label="Usuarios R11" value={String(r11Count)} loading={loading} />
      </div>
    </div>
  );
}
