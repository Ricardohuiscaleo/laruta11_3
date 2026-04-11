'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatMonthES, cn } from '@/lib/utils';
import {
  Loader2,
  ChevronLeft,
  ChevronRight,
  ArrowUpRight,
  ArrowDownLeft,
  ArrowRightLeft,
  TrendingUp,
  TrendingDown,
  Scale,
} from 'lucide-react';
import type { ReplacementData, ApiResponse } from '@/types';

/* ─── Helpers ─── */

/** Current month as YYYY-MM */
function getCurrentMonth(): string {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  return `${y}-${m}`;
}

/** Shift month by delta (-1 or +1) */
function shiftMonth(mes: string, delta: number): string {
  const [y, m] = mes.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  const ny = d.getFullYear();
  const nm = String(d.getMonth() + 1).padStart(2, '0');
  return `${ny}-${nm}`;
}

/** Display pago_por label */
function pagoPorLabel(pago: string): string {
  switch (pago) {
    case 'empresa':
      return 'Empresa';
    case 'empresa_adelanto':
      return 'Adelanto';
    case 'titular':
    case 'personal':
      return 'Titular';
    default:
      return pago;
  }
}

/** Format date as "5 ene", "12 feb" etc. */
function formatShortDate(dateStr: string): string {
  const date = new Date(dateStr + 'T12:00:00');
  return date.toLocaleDateString('es-CL', { day: 'numeric', month: 'short' });
}

/* ─── Replacement row components ─── */

function RealizadoRow({
  item,
}: {
  item: { fecha: string; titular: string; monto: number; pago_por: string };
}) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
      <div className="flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100">
          <ArrowUpRight className="h-4 w-4 text-green-600" />
        </div>
        <div>
          <p className="text-sm font-medium text-gray-900">{item.titular}</p>
          <p className="text-xs text-gray-500">{formatShortDate(item.fecha)}</p>
        </div>
      </div>
      <div className="text-right">
        <p className="text-sm font-semibold text-green-600">
          +{formatCLP(item.monto)}
        </p>
        <p className="text-xs text-gray-400">{pagoPorLabel(item.pago_por)}</p>
      </div>
    </div>
  );
}

function RecibidoRow({
  item,
}: {
  item: { fecha: string; reemplazante: string; monto: number; pago_por: string };
}) {
  return (
    <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
      <div className="flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
          <ArrowDownLeft className="h-4 w-4 text-red-600" />
        </div>
        <div>
          <p className="text-sm font-medium text-gray-900">
            {item.reemplazante}
          </p>
          <p className="text-xs text-gray-500">{formatShortDate(item.fecha)}</p>
        </div>
      </div>
      <div className="text-right">
        <p className="text-sm font-semibold text-red-600">
          -{formatCLP(item.monto)}
        </p>
        <p className="text-xs text-gray-400">{pagoPorLabel(item.pago_por)}</p>
      </div>
    </div>
  );
}

/* ─── Main Page ─── */

export default function ReemplazosPage() {
  const [mes, setMes] = useState(getCurrentMonth);
  const [data, setData] = useState<ReplacementData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const isFutureMonth = mes >= shiftMonth(getCurrentMonth(), 1);

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<ReplacementData>>(`/worker/replacements?mes=${mes}`)
      .then((res) => setData(res.data ?? null))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [mes]);

  const balance = data?.resumen.balance ?? 0;
  const balanceColor =
    balance > 0
      ? 'text-green-600'
      : balance < 0
        ? 'text-red-600'
        : 'text-gray-500';

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Reemplazos</h1>
        <Link
          href="/dashboard/cambios"
          className="rounded-lg bg-red-500 px-3 py-2 text-sm font-medium text-white hover:bg-red-600"
        >
          Solicitar Reemplazo
        </Link>
      </div>

      {/* Month navigation */}
      <div className="flex items-center justify-between rounded-xl border bg-white px-4 py-3 shadow-sm">
        <button
          onClick={() => setMes(shiftMonth(mes, -1))}
          className="rounded-full p-1.5 hover:bg-gray-100"
          aria-label="Mes anterior"
        >
          <ChevronLeft className="h-5 w-5 text-gray-600" />
        </button>
        <span className="text-sm font-semibold text-gray-900">
          {formatMonthES(mes)}
        </span>
        <button
          onClick={() => setMes(shiftMonth(mes, 1))}
          disabled={isFutureMonth}
          className={cn(
            'rounded-full p-1.5',
            isFutureMonth
              ? 'text-gray-300 cursor-not-allowed'
              : 'hover:bg-gray-100 text-gray-600'
          )}
          aria-label="Mes siguiente"
        >
          <ChevronRight className="h-5 w-5" />
        </button>
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-12">
          <Loader2 className="h-8 w-8 animate-spin text-red-500" />
        </div>
      )}

      {/* Error */}
      {!loading && error && (
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-600">
          {error}
        </div>
      )}

      {/* Content */}
      {!loading && !error && data && (
        <>
          {/* Monthly summary */}
          <div className="grid grid-cols-3 gap-3">
            <div className="rounded-xl border bg-white p-3 shadow-sm text-center">
              <TrendingUp className="mx-auto h-5 w-5 text-green-500 mb-1" />
              <p className="text-xs text-gray-500">Ganado</p>
              <p className="text-sm font-bold text-green-600">
                {formatCLP(data.resumen.total_ganado)}
              </p>
            </div>
            <div className="rounded-xl border bg-white p-3 shadow-sm text-center">
              <TrendingDown className="mx-auto h-5 w-5 text-red-500 mb-1" />
              <p className="text-xs text-gray-500">Descontado</p>
              <p className="text-sm font-bold text-red-600">
                {formatCLP(data.resumen.total_descontado)}
              </p>
            </div>
            <div className="rounded-xl border bg-white p-3 shadow-sm text-center">
              <Scale className="mx-auto h-5 w-5 text-gray-400 mb-1" />
              <p className="text-xs text-gray-500">Balance</p>
              <p className={cn('text-sm font-bold', balanceColor)}>
                {balance >= 0 ? '+' : ''}
                {formatCLP(balance)}
              </p>
            </div>
          </div>

          {/* Reemplazos que hice */}
          <div className="rounded-xl border bg-white shadow-sm">
            <div className="flex items-center gap-2 border-b px-4 py-3">
              <ArrowUpRight className="h-4 w-4 text-green-600" />
              <h2 className="text-sm font-semibold text-gray-900">
                Reemplazos que hice
              </h2>
              <span className="ml-auto text-xs text-gray-400">
                {data.realizados.length}
              </span>
            </div>
            <div className="px-4">
              {data.realizados.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">
                  No hiciste reemplazos este mes
                </p>
              ) : (
                data.realizados.map((item, i) => (
                  <RealizadoRow key={`r-${i}`} item={item} />
                ))
              )}
            </div>
          </div>

          {/* Me reemplazaron */}
          <div className="rounded-xl border bg-white shadow-sm">
            <div className="flex items-center gap-2 border-b px-4 py-3">
              <ArrowDownLeft className="h-4 w-4 text-red-600" />
              <h2 className="text-sm font-semibold text-gray-900">
                Me reemplazaron
              </h2>
              <span className="ml-auto text-xs text-gray-400">
                {data.recibidos.length}
              </span>
            </div>
            <div className="px-4">
              {data.recibidos.length === 0 ? (
                <p className="py-6 text-center text-sm text-gray-400">
                  No te reemplazaron este mes
                </p>
              ) : (
                data.recibidos.map((item, i) => (
                  <RecibidoRow key={`d-${i}`} item={item} />
                ))
              )}
            </div>
          </div>

          {/* Empty state when both are empty */}
          {data.realizados.length === 0 && data.recibidos.length === 0 && (
            <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
              <ArrowRightLeft className="mx-auto h-12 w-12 text-gray-300" />
              <p className="mt-3 text-gray-500">
                No hay reemplazos en {formatMonthES(mes).toLowerCase()}
              </p>
              <Link
                href="/dashboard/cambios"
                className="mt-4 inline-block rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600"
              >
                Solicitar Reemplazo
              </Link>
            </div>
          )}
        </>
      )}
    </div>
  );
}
