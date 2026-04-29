'use client';

import { useState, useEffect } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend,
} from 'recharts';
import { Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api';

interface MonthData {
  month: string;
  label: string;
  total_sales: number;
  total_cost: number;
  total_delivery: number;
}

function fmtCLP(v: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v);
}

function CustomTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  const d = payload[0]?.payload as MonthData;
  if (!d) return null;
  const profit = d.total_sales - d.total_cost;
  return (
    <div className="rounded-lg bg-white border shadow-lg p-3 text-xs space-y-1">
      <p className="font-semibold text-gray-900">{label}</p>
      <p className="text-green-600">Ventas: {fmtCLP(d.total_sales)}</p>
      <p className="text-red-500">Costo: {fmtCLP(d.total_cost)}</p>
      <p className="text-blue-500">Delivery: {fmtCLP(d.total_delivery)}</p>
      <p className={`font-bold ${profit >= 0 ? 'text-green-700' : 'text-red-700'}`}>
        {profit >= 0 ? 'Ganancia' : 'Pérdida'}: {fmtCLP(Math.abs(profit))}
      </p>
    </div>
  );
}

function ProfitLabel(props: any) {
  const { x, y, width, index } = props;
  const data = props.data as MonthData[] | undefined;
  if (!data?.[index]) return null;
  const d = data[index];
  const profit = d.total_sales - d.total_cost;
  const isPositive = profit >= 0;
  return (
    <text x={x + width / 2} y={y - 4} textAnchor="middle" fontSize={9} fontWeight={600} fill={isPositive ? '#15803d' : '#dc2626'}>
      {isPositive ? '+' : ''}{Math.round(profit / 1000)}k
    </text>
  );
}

export default function MonthlyChart() {
  const [data, setData] = useState<MonthData[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; data: MonthData[] }>('/admin/ventas/monthly?months=6')
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-48 rounded-xl border bg-white" role="status" aria-label="Cargando gráfico">
        <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
      </div>
    );
  }

  if (data.length === 0) {
    return <div className="rounded-xl border bg-white p-4"><p className="text-center text-xs text-gray-400 py-8">Sin datos mensuales</p></div>;
  }

  return (
    <div className="rounded-xl border bg-white shadow-sm p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-gray-900">Ventas Mensuales</h3>
        <span className="text-[10px] text-gray-400">Pérdidas / Ganancias</span>
      </div>
      <ResponsiveContainer width="100%" height={240}>
        <BarChart data={data} margin={{ top: 20, right: 5, left: -10, bottom: 5 }}>
          <XAxis dataKey="label" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `${Math.round(v / 1000)}k`} />
          <Tooltip content={<CustomTooltip />} />
          <Legend iconSize={8} wrapperStyle={{ fontSize: 11 }} />
          <Bar dataKey="total_cost" name="Costo" stackId="stack" fill="#FCA5A5" />
          <Bar dataKey="total_delivery" name="Delivery" stackId="stack" fill="#93C5FD" />
          <Bar dataKey="total_sales" name="Ventas" fill="#86EFAC" label={<ProfitLabel data={data} />} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
