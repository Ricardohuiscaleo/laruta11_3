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

function formatCLP(v: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v);
}

function CustomTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-lg bg-white border shadow-lg p-3 text-xs space-y-1">
      <p className="font-semibold text-gray-900">{label}</p>
      {payload.map((p: any) => (
        <div key={p.dataKey} className="flex items-center gap-2">
          <span className="h-2 w-2 rounded-full" style={{ backgroundColor: p.fill }} />
          <span className="text-gray-600">{p.name}:</span>
          <span className="font-medium text-gray-900">{formatCLP(p.value)}</span>
        </div>
      ))}
    </div>
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
      <div className="flex items-center justify-center h-48" role="status" aria-label="Cargando gráfico">
        <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
      </div>
    );
  }

  if (data.length === 0) {
    return <p className="text-center text-xs text-gray-400 py-8">Sin datos mensuales</p>;
  }

  return (
    <div className="rounded-xl border bg-white shadow-sm p-4">
      <h3 className="text-sm font-semibold text-gray-900 mb-3">Ventas Mensuales</h3>
      <ResponsiveContainer width="100%" height={220}>
        <BarChart data={data} margin={{ top: 5, right: 5, left: -10, bottom: 5 }}>
          <XAxis dataKey="label" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => `${Math.round(v / 1000)}k`} />
          <Tooltip content={<CustomTooltip />} />
          <Legend iconSize={8} wrapperStyle={{ fontSize: 11 }} />
          <Bar dataKey="total_sales" name="Ventas" stackId="a" fill="#22C55E" radius={[0, 0, 0, 0]} />
          <Bar dataKey="total_cost" name="Costo" stackId="b" fill="#EF4444" radius={[0, 0, 0, 0]} />
          <Bar dataKey="total_delivery" name="Delivery" stackId="b" fill="#3B82F6" radius={[2, 2, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
