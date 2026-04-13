'use client';

import { useState, useEffect, useCallback } from 'react';
import { TrendingUp, DollarSign, Wallet, Check, Clock, X, RefreshCw } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import { getEcho } from '@/lib/echo';
import type { Kpi } from '@/types/compras';

interface HistorialSaldoItem {
  fecha: string;
  saldo_inicial: number;
  ingresos_ventas: number;
  egresos_compras: number;
  egresos_gastos: number;
  saldo_final: number;
}

interface RendicionItem {
  id: number; token: string; saldo_anterior: number; total_compras: number;
  saldo_resultante: number; monto_transferido: number | null; saldo_nuevo: number | null;
  estado: string; created_at: string; aprobado_at: string | null;
}

export default function KpisDashboard() {
  const [kpis, setKpis] = useState<Kpi | null>(null);
  const [historial, setHistorial] = useState<HistorialSaldoItem[]>([]);
  const [rendiciones, setRendiciones] = useState<RendicionItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAll = useCallback(() => {
    Promise.all([
      comprasApi.get<{ success: boolean; data: Kpi }>('/kpis').then(r => r.data),
      comprasApi.get<{ success: boolean; historial: HistorialSaldoItem[] }>('/kpis/historial-saldo').then(r => r.historial || []),
      comprasApi.get<{ success: boolean; rendiciones: RendicionItem[] }>('/kpis/rendiciones').then(r => r.rendiciones || []),
    ]).then(([k, h, r]) => {
      setKpis(k);
      setHistorial(h);
      setRendiciones(r);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  // Realtime: listen for compra.registrada and rendicion.actualizada
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.channel('compras');
    channel.listen('.compra.registrada', () => fetchAll());
    channel.listen('.rendicion.actualizada', () => fetchAll());

    return () => { echo.leave('compras'); };
  }, [fetchAll]);

  if (loading) return <div className="p-6 text-center text-sm text-gray-500">Cargando...</div>;
  if (!kpis) return <div className="p-6 text-center text-sm text-gray-500">Error al cargar KPIs</div>;

  const cards = [
    { label: 'Ventas mes anterior', value: kpis.ventas_mes_anterior, icon: TrendingUp, positive: true },
    { label: 'Ventas mes actual', value: kpis.ventas_mes_actual, icon: TrendingUp, positive: true },
    { label: 'Sueldos', value: kpis.sueldos, icon: DollarSign, positive: false },
    { label: 'Saldo disponible', value: kpis.saldo_disponible, icon: Wallet, positive: kpis.saldo_disponible >= 0 },
  ];

  return (
    <div className="space-y-4">
      {/* KPI Cards */}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {cards.map(card => (
          <div key={card.label}
            className={`rounded-xl border p-4 shadow-sm ${card.positive ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-gray-600">{card.label}</span>
              <card.icon className={`h-4 w-4 ${card.positive ? 'text-green-600' : 'text-red-600'}`} />
            </div>
            <p className={`mt-1 text-xl font-bold ${card.positive ? 'text-green-700' : 'text-red-700'}`}>
              {formatearPesosCLP(card.value)}
            </p>
          </div>
        ))}
      </div>

      {/* Historial Saldo */}
      {historial.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-semibold text-gray-700">Historial de Saldo</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-xs text-gray-500">
                  <th className="pb-2 pr-3">Fecha</th>
                  <th className="pb-2 pr-3 text-right">Saldo inicial</th>
                  <th className="pb-2 pr-3 text-right">Ventas</th>
                  <th className="pb-2 pr-3 text-right">Compras</th>
                  <th className="pb-2 pr-3 text-right">Gastos</th>
                  <th className="pb-2 text-right">Saldo final</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {historial.map((row, i) => (
                  <tr key={i}>
                    <td className="py-2 pr-3">{formatearFecha(row.fecha)}</td>
                    <td className="py-2 pr-3 text-right">{formatearPesosCLP(row.saldo_inicial)}</td>
                    <td className="py-2 pr-3 text-right text-green-600">{formatearPesosCLP(row.ingresos_ventas)}</td>
                    <td className="py-2 pr-3 text-right text-red-600">{formatearPesosCLP(row.egresos_compras)}</td>
                    <td className="py-2 pr-3 text-right text-red-600">{formatearPesosCLP(row.egresos_gastos)}</td>
                    <td className={`py-2 text-right font-medium ${row.saldo_final >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                      {formatearPesosCLP(row.saldo_final)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
      {/* Historial Rendiciones */}
      {rendiciones.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <h3 className="mb-3 text-sm font-semibold text-gray-700">Historial de Rendiciones</h3>
          <div className="space-y-2">
            {rendiciones.map(r => {
              const badge = r.estado === 'aprobada'
                ? <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] text-green-700"><Check className="h-2.5 w-2.5" />Aprobada</span>
                : r.estado === 'rechazada'
                ? <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] text-red-700"><X className="h-2.5 w-2.5" />Rechazada</span>
                : <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] text-amber-700"><Clock className="h-2.5 w-2.5" />Pendiente</span>;

              return (
                <div key={r.id} className="rounded-lg border bg-gray-50 p-3">
                  <div className="flex items-center justify-between mb-2">
                    {badge}
                    <span className="text-xs text-gray-400">{new Date(r.created_at).toLocaleDateString('es-CL')}</span>
                  </div>
                  <div className="grid grid-cols-3 gap-2 text-center text-xs">
                    <div>
                      <p className="text-gray-500">Saldo ant.</p>
                      <p className="font-semibold">{formatearPesosCLP(r.saldo_anterior)}</p>
                    </div>
                    <div>
                      <p className="text-gray-500">Compras</p>
                      <p className="font-semibold text-red-600">-{formatearPesosCLP(r.total_compras)}</p>
                    </div>
                    <div>
                      <p className="text-gray-500">{r.saldo_resultante >= 0 ? 'Caja' : 'Bolsillo'}</p>
                      <p className={`font-semibold ${r.saldo_resultante >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                        {formatearPesosCLP(r.saldo_resultante)}
                      </p>
                    </div>
                  </div>
                  {r.monto_transferido != null && (
                    <div className="grid grid-cols-2 gap-2 text-center text-xs mt-2 pt-2 border-t">
                      <div>
                        <p className="text-gray-500">Transferido</p>
                        <p className="font-semibold text-blue-600">+{formatearPesosCLP(r.monto_transferido)}</p>
                      </div>
                      <div>
                        <p className="text-gray-500">Saldo nuevo</p>
                        <p className="font-bold text-green-700">{formatearPesosCLP(r.saldo_nuevo ?? 0)}</p>
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
