'use client';

import { useState, useEffect } from 'react';
import { TrendingUp, TrendingDown, DollarSign, Wallet } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import type { Kpi } from '@/types/compras';

interface HistorialSaldoItem {
  fecha: string;
  saldo_inicial: number;
  ingresos_ventas: number;
  egresos_compras: number;
  egresos_gastos: number;
  saldo_final: number;
}

export default function KpisDashboard() {
  const [kpis, setKpis] = useState<Kpi | null>(null);
  const [historial, setHistorial] = useState<HistorialSaldoItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      comprasApi.get<{ success: boolean; data: Kpi }>('/kpis').then(r => r.data),
      comprasApi.get<{ success: boolean; historial: HistorialSaldoItem[] }>('/kpis/historial-saldo').then(r => r.historial || []),
    ]).then(([k, h]) => {
      setKpis(k);
      setHistorial(h);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

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
    </div>
  );
}
