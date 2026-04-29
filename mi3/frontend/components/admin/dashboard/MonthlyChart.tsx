'use client';

import { useState, useEffect } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend, LabelList,
} from 'recharts';
import { Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api';

interface MonthData {
  month: string;
  label: string;
  total_sales: number;
  total_cost: number;
  total_delivery: number;
  total_nomina: number;
  nomina_projected?: boolean;
  resultado: number;
}

function fmtCLP(v: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v);
}

function fmtK(v: number) {
  const abs = Math.abs(v);
  if (abs >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
  return `${Math.round(v / 1000)}k`;
}

function CustomTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  const d = payload[0]?.payload as MonthData;
  if (!d) return null;
  return (
    <div className="rounded-lg bg-white border shadow-lg p-3 text-xs space-y-1">
      <p className="font-semibold text-gray-900">{label}</p>
      <p className="text-green-600">Ventas: {fmtCLP(d.total_sales)}</p>
      <p className="text-red-500">Costo: {fmtCLP(d.total_cost)}</p>
      <p className="text-purple-500">Nómina: {fmtCLP(d.total_nomina)}{d.nomina_projected ? ' (proy.)' : ''}</p>
      <p className="text-blue-500">Delivery: {fmtCLP(d.total_delivery)}</p>
      <div className="border-t pt-1 mt-1">
        <p className={`font-bold ${d.resultado >= 0 ? 'text-green-700' : 'text-red-700'}`}>
          {d.resultado >= 0 ? '✅ Ganancia' : '❌ Pérdida'}: {fmtCLP(Math.abs(d.resultado))}
        </p>
      </div>
    </div>
  );
}

function ResultadoLabel(props: any) {
  const { x, y, width, index, data } = props;
  if (!data?.[index]) return null;
  const d = data[index] as MonthData;
  const isPos = d.resultado >= 0;
  return (
    <text x={x + width / 2} y={y - 6} textAnchor="middle" fontSize={9} fontWeight={700} fill={isPos ? '#15803d' : '#dc2626'}>
      {isPos ? '+' : ''}{fmtK(d.resultado)}
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
        <span className="text-[10px] text-gray-400">Resultado = Ventas − Gastos</span>
      </div>
      <ResponsiveContainer width="100%" height={260}>
        <BarChart data={data} margin={{ top: 25, right: 5, left: -10, bottom: 5 }}>
          <XAxis dataKey="label" tick={{ fontSize: 11 }} />
          <YAxis tick={{ fontSize: 10 }} tickFormatter={(v) => fmtK(v)} />
          <Tooltip content={<CustomTooltip />} />
          <Legend iconSize={8} wrapperStyle={{ fontSize: 10 }} />
          <Bar dataKey="total_sales" name="Ventas" stackId="one" fill="#86EFAC">
            <LabelList content={<ResultadoLabel data={data} />} />
          </Bar>
          <Bar dataKey="total_cost" name="Costo" stackId="one" fill="#FCA5A5" />
          <Bar dataKey="total_nomina" name="Nómina" stackId="one" fill="#C4B5FD" />
          <Bar dataKey="total_delivery" name="Delivery" stackId="one" fill="#93C5FD" radius={[2, 2, 0, 0]} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
