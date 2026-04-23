'use client';

import { useState, useEffect, useCallback } from 'react';
import { Wallet, Loader2, AlertCircle, Check, Calendar } from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';

interface DiaCapital {
  fecha: string;
  saldo_inicial: number | null;
  ingresos_ventas: number | null;
  egresos_compras: number | null;
  egresos_gastos: number | null;
  saldo_final: number | null;
  tiene_cierre: boolean;
}

interface Totales {
  total_ingresos: number;
  total_egresos_compras: number;
  total_egresos_gastos: number;
  variacion_neta: number;
}

interface ResumenMensual {
  dias: DiaCapital[];
  totales: Totales;
}

function currentMonth(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function CapitalTrabajoSection() {
  const [mes, setMes] = useState(currentMonth);
  const [data, setData] = useState<ResumenMensual | null>(null);
  const [loading, setLoading] = useState(true);
  const [closing, setClosing] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const fetchData = useCallback(() => {
    setLoading(true);
    setError(null);
    apiFetch<{ success: boolean; data: ResumenMensual }>(`/admin/capital-trabajo?mes=${mes}`)
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => setError('Error al cargar datos'))
      .finally(() => setLoading(false));
  }, [mes]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleCierre = useCallback(async (fecha: string) => {
    setClosing(fecha);
    setError(null);
    try {
      await apiFetch('/admin/capital-trabajo/cierre', {
        method: 'POST',
        body: JSON.stringify({ fecha }),
      });
      setSuccess(`Cierre del ${fecha} completado`);
      fetchData();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Error al cerrar día';
      setError(msg);
    } finally {
      setClosing(null);
    }
  }, [fetchData]);

  const formatDay = (fecha: string) => {
    const d = new Date(fecha + 'T12:00:00');
    const day = d.getDate();
    const weekday = d.toLocaleDateString('es-CL', { weekday: 'short' });
    return `${weekday} ${day}`;
  };

  return (
    <div className="space-y-4">
      {/* Month selector */}
      <div className="flex items-center gap-3">
        <label htmlFor="mes-selector" className="text-sm font-medium text-gray-700 flex items-center gap-1.5">
          <Calendar className="h-4 w-4 text-gray-500" /> Mes
        </label>
        <input
          id="mes-selector"
          type="month"
          value={mes}
          onChange={e => setMes(e.target.value)}
          className="rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {success && (
        <div className="flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">
          <Check className="h-4 w-4 shrink-0" />
          <span>{success}</span>
        </div>
      )}

      {loading ? (
        <div className="flex justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      ) : !data || data.dias.length === 0 ? (
        <div className="rounded-xl border bg-white p-8 text-center text-sm text-gray-500">
          Sin datos para este mes
        </div>
      ) : (
        <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                  <th className="px-3 py-2.5">Fecha</th>
                  <th className="px-3 py-2.5 text-right">Saldo Inicial</th>
                  <th className="px-3 py-2.5 text-right">Ingresos</th>
                  <th className="px-3 py-2.5 text-right">Compras</th>
                  <th className="px-3 py-2.5 text-right">Gastos</th>
                  <th className="px-3 py-2.5 text-right">Saldo Final</th>
                  <th className="px-3 py-2.5 w-24"></th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {data.dias.map(dia => (
                  <tr
                    key={dia.fecha}
                    className={cn(!dia.tiene_cierre && 'bg-amber-50/50')}
                  >
                    <td className="px-3 py-2.5 font-medium text-gray-900 whitespace-nowrap">
                      {formatDay(dia.fecha)}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-gray-700">
                      {dia.tiene_cierre ? formatCLP(dia.saldo_inicial ?? 0) : '—'}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-green-700">
                      {dia.tiene_cierre ? formatCLP(dia.ingresos_ventas ?? 0) : '—'}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-red-600">
                      {dia.tiene_cierre ? formatCLP(dia.egresos_compras ?? 0) : '—'}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums text-red-600">
                      {dia.tiene_cierre ? formatCLP(dia.egresos_gastos ?? 0) : '—'}
                    </td>
                    <td className="px-3 py-2.5 text-right tabular-nums font-medium text-gray-900">
                      {dia.tiene_cierre ? formatCLP(dia.saldo_final ?? 0) : '—'}
                    </td>
                    <td className="px-3 py-2.5 text-right">
                      {!dia.tiene_cierre ? (
                        <button
                          onClick={() => handleCierre(dia.fecha)}
                          disabled={closing === dia.fecha}
                          className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:opacity-50 min-h-[28px]"
                          aria-label={`Cerrar día ${dia.fecha}`}
                        >
                          {closing === dia.fecha ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                          ) : (
                            <span className="rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">Sin cierre</span>
                          )}
                        </button>
                      ) : (
                        <span className="text-xs text-green-600">✓</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="bg-gray-100 border-t-2 font-semibold text-gray-900">
                  <td className="px-3 py-2.5">Totales</td>
                  <td className="px-3 py-2.5 text-right"></td>
                  <td className="px-3 py-2.5 text-right tabular-nums text-green-700">
                    {formatCLP(data.totales.total_ingresos)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums text-red-600">
                    {formatCLP(data.totales.total_egresos_compras)}
                  </td>
                  <td className="px-3 py-2.5 text-right tabular-nums text-red-600">
                    {formatCLP(data.totales.total_egresos_gastos)}
                  </td>
                  <td className={cn(
                    'px-3 py-2.5 text-right tabular-nums',
                    data.totales.variacion_neta >= 0 ? 'text-green-700' : 'text-red-700',
                  )}>
                    {data.totales.variacion_neta >= 0 ? '+' : ''}{formatCLP(data.totales.variacion_neta)}
                  </td>
                  <td className="px-3 py-2.5"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
