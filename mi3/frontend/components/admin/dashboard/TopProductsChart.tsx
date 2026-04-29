'use client';

import { useState, useEffect, useMemo } from 'react';
import {
  ComposedChart, Bar, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell,
} from 'recharts';
import { Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';

interface ProductData {
  product_name: string;
  quantity_sold: number;
  total_revenue: number;
  total_cost: number;
  total_profit: number;
  margin_pct: number;
}

interface ParetoItem extends ProductData {
  cumulative_pct: number;
}

function fmtCLP(v: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v);
}

function CustomTooltip({ active, payload }: any) {
  if (!active || !payload?.length) return null;
  const d = payload[0]?.payload as ParetoItem;
  if (!d) return null;
  return (
    <div className="rounded-lg bg-white border shadow-lg p-3 text-xs space-y-1 max-w-[200px]">
      <p className="font-semibold text-gray-900 truncate">{d.product_name}</p>
      <p className="text-gray-600">Vendidos: <span className="font-medium">{d.quantity_sold}</span></p>
      <p className="text-gray-600">Ingreso: <span className="font-medium">{fmtCLP(d.total_revenue)}</span></p>
      <p className="text-red-600">Costo: <span className="font-medium">{fmtCLP(d.total_cost)}</span></p>
      <p className="text-green-600">Utilidad: <span className="font-medium">{fmtCLP(d.total_profit)}</span></p>
      <p className="text-gray-600">Margen: <span className="font-bold">{d.margin_pct}%</span></p>
      <p className="text-amber-600">Acumulado: <span className="font-bold">{d.cumulative_pct.toFixed(0)}%</span></p>
    </div>
  );
}

export default function TopProductsChart() {
  const [rawData, setRawData] = useState<ProductData[]>([]);
  const [loading, setLoading] = useState(true);
  const [sort, setSort] = useState<'quantity' | 'profit'>('quantity');

  useEffect(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: ProductData[] }>(`/admin/ventas/top-products?period=month&limit=10&sort=${sort}`)
      .then(res => { if (res.data) setRawData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [sort]);

  const data: ParetoItem[] = useMemo(() => {
    const total = rawData.reduce((s, d) => s + d.total_revenue, 0);
    let cumulative = 0;
    return rawData.map(d => {
      cumulative += d.total_revenue;
      return { ...d, cumulative_pct: total > 0 ? (cumulative / total) * 100 : 0 };
    });
  }, [rawData]);

  return (
    <div className="rounded-xl border bg-white shadow-sm p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-gray-900">Top Productos <span className="text-[10px] text-gray-400 font-normal">(Pareto 80/20)</span></h3>
        <div className="flex rounded-lg bg-gray-100 p-0.5">
          <button type="button" onClick={() => setSort('quantity')} className={cn('px-2 py-1 text-[10px] font-medium rounded-md transition-colors', sort === 'quantity' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500')} aria-label="Más vendidos">Vendidos</button>
          <button type="button" onClick={() => setSort('profit')} className={cn('px-2 py-1 text-[10px] font-medium rounded-md transition-colors', sort === 'profit' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500')} aria-label="Más rentables">Rentables</button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-48" role="status" aria-label="Cargando"><Loader2 className="h-5 w-5 animate-spin text-gray-400" /></div>
      ) : data.length === 0 ? (
        <p className="text-center text-xs text-gray-400 py-8">Sin datos</p>
      ) : (
        <ResponsiveContainer width="100%" height={Math.max(220, data.length * 36)}>
          <ComposedChart data={data} layout="vertical" margin={{ top: 5, right: 30, left: 0, bottom: 5 }}>
            <XAxis xAxisId="revenue" type="number" tick={{ fontSize: 10 }} tickFormatter={(v) => `${Math.round(v / 1000)}k`} orientation="bottom" />
            <XAxis xAxisId="pct" type="number" domain={[0, 100]} tick={{ fontSize: 9 }} tickFormatter={(v) => `${v}%`} orientation="top" axisLine={false} tickLine={false} />
            <YAxis type="category" dataKey="product_name" tick={{ fontSize: 10 }} width={100} tickFormatter={(v: string) => v.length > 14 ? v.slice(0, 14) + '…' : v} />
            <Tooltip content={<CustomTooltip />} />
            <Bar xAxisId="revenue" dataKey="total_cost" name="Costo" stackId="rev" fill="#FCA5A5" barSize={18}>
              {data.map((_, i) => <Cell key={i} fill="#FCA5A5" />)}
            </Bar>
            <Bar xAxisId="revenue" dataKey="total_profit" name="Utilidad" stackId="rev" fill="#86EFAC" barSize={18}>
              {data.map((_, i) => <Cell key={i} fill="#86EFAC" />)}
            </Bar>
            <Line xAxisId="pct" type="monotone" dataKey="cumulative_pct" name="Pareto %" stroke="#F59E0B" strokeWidth={2} dot={{ r: 3, fill: '#F59E0B', stroke: '#fff', strokeWidth: 1 }} />
          </ComposedChart>
        </ResponsiveContainer>
      )}
      {data.length > 0 && (
        <div className="flex items-center justify-center gap-3 mt-2 text-[10px] text-gray-400">
          <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[#FCA5A5]" />Costo</span>
          <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-[#86EFAC]" />Utilidad</span>
          <span className="flex items-center gap-1"><span className="h-2 w-4 bg-[#F59E0B] rounded-full" style={{ height: 2 }} />Pareto %</span>
        </div>
      )}
    </div>
  );
}
