'use client';

import { useState, useEffect } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Cell,
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

function formatCLP(v: number) {
  return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(v);
}

function CustomTooltip({ active, payload }: any) {
  if (!active || !payload?.length) return null;
  const d = payload[0]?.payload as ProductData;
  if (!d) return null;
  return (
    <div className="rounded-lg bg-white border shadow-lg p-3 text-xs space-y-1 max-w-[200px]">
      <p className="font-semibold text-gray-900 truncate">{d.product_name}</p>
      <p className="text-gray-600">Vendidos: <span className="font-medium">{d.quantity_sold}</span></p>
      <p className="text-gray-600">Ingreso: <span className="font-medium">{formatCLP(d.total_revenue)}</span></p>
      <p className="text-gray-600">Costo: <span className="font-medium text-red-600">{formatCLP(d.total_cost)}</span></p>
      <p className="text-gray-600">Utilidad: <span className="font-medium text-green-600">{formatCLP(d.total_profit)}</span></p>
      <p className="text-gray-600">Margen: <span className="font-bold">{d.margin_pct}%</span></p>
    </div>
  );
}

export default function TopProductsChart() {
  const [data, setData] = useState<ProductData[]>([]);
  const [loading, setLoading] = useState(true);
  const [sort, setSort] = useState<'quantity' | 'profit'>('quantity');

  useEffect(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: ProductData[] }>(`/admin/ventas/top-products?period=month&limit=10&sort=${sort}`)
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [sort]);

  return (
    <div className="rounded-xl border bg-white shadow-sm p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-gray-900">Top Productos</h3>
        <div className="flex rounded-lg bg-gray-100 p-0.5">
          <button
            type="button"
            onClick={() => setSort('quantity')}
            className={cn(
              'px-2 py-1 text-[10px] font-medium rounded-md transition-colors',
              sort === 'quantity' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500',
            )}
            aria-label="Ordenar por más vendidos"
          >
            Vendidos
          </button>
          <button
            type="button"
            onClick={() => setSort('profit')}
            className={cn(
              'px-2 py-1 text-[10px] font-medium rounded-md transition-colors',
              sort === 'profit' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500',
            )}
            aria-label="Ordenar por más rentables"
          >
            Rentables
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-48" role="status" aria-label="Cargando">
          <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
        </div>
      ) : data.length === 0 ? (
        <p className="text-center text-xs text-gray-400 py-8">Sin datos</p>
      ) : (
        <ResponsiveContainer width="100%" height={Math.max(180, data.length * 32)}>
          <BarChart data={data} layout="vertical" margin={{ top: 0, right: 40, left: 0, bottom: 0 }}>
            <XAxis type="number" tick={{ fontSize: 10 }} tickFormatter={(v) => `${Math.round(v / 1000)}k`} />
            <YAxis
              type="category"
              dataKey="product_name"
              tick={{ fontSize: 10 }}
              width={100}
              tickFormatter={(v: string) => v.length > 14 ? v.slice(0, 14) + '…' : v}
            />
            <Tooltip content={<CustomTooltip />} />
            <Bar dataKey="total_cost" name="Costo" stackId="a" fill="#FCA5A5">
              {data.map((_, i) => <Cell key={i} fill="#FCA5A5" />)}
            </Bar>
            <Bar dataKey="total_profit" name="Utilidad" stackId="a" fill="#86EFAC">
              {data.map((entry, i) => <Cell key={i} fill="#86EFAC" />)}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
