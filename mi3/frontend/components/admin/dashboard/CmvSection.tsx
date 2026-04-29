'use client';

import { useState, useEffect } from 'react';
import { ChefHat, Loader2 } from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import CollapsibleSection from './CollapsibleSection';

interface Ingredient {
  ingredient_id: number;
  name: string;
  total_quantity: number;
  unit: string;
  total_cost: number;
  percentage: number;
}

interface CmvData {
  total_cmv: number;
  cmv_percentage: number;
  ingredients: Ingredient[];
}

export default function CmvSection() {
  const [data, setData] = useState<CmvData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; data: CmvData }>('/admin/ventas/cmv?period=month')
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const summary = data
    ? <span>{formatCLP(data.total_cmv)} · {data.cmv_percentage}% de ventas</span>
    : <span>Cargando...</span>;

  return (
    <CollapsibleSection
      title="Costo Ingredientes (CMV)"
      summary={summary}
      accentColor="border-orange-400"
      icon={<ChefHat className="h-4 w-4 text-orange-500" />}
    >
      {loading ? (
        <div className="flex justify-center py-6" role="status" aria-label="Cargando CMV">
          <Loader2 className="h-5 w-5 animate-spin text-orange-400" />
        </div>
      ) : !data || data.ingredients.length === 0 ? (
        <p className="text-center text-xs text-gray-400 py-6">Sin datos de ingredientes</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-xs" role="table" aria-label="Desglose CMV por ingrediente">
            <thead>
              <tr className="text-gray-400 text-[10px] uppercase tracking-wider border-b">
                <th className="text-left px-3 py-2 font-medium">Ingrediente</th>
                <th className="text-right px-2 py-2 font-medium">Consumo</th>
                <th className="text-right px-2 py-2 font-medium">Costo</th>
                <th className="text-right px-2 py-2 font-medium">%</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-50">
              {data.ingredients.map((ing) => (
                <tr
                  key={ing.ingredient_id}
                  className={cn(ing.percentage > 10 && 'bg-red-50/60')}
                >
                  <td className="px-3 py-1.5 font-medium text-gray-700">{ing.name}</td>
                  <td className="text-right px-2 py-1.5 text-gray-500 tabular-nums">
                    {ing.total_quantity} {ing.unit}
                  </td>
                  <td className="text-right px-2 py-1.5 text-gray-900 font-medium tabular-nums">
                    {formatCLP(ing.total_cost)}
                  </td>
                  <td className={cn(
                    'text-right px-2 py-1.5 tabular-nums font-medium',
                    ing.percentage > 10 ? 'text-red-600' : 'text-gray-500',
                  )}>
                    {ing.percentage}%
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t font-semibold">
                <td className="px-3 py-2 text-gray-900">Total CMV</td>
                <td />
                <td className="text-right px-2 py-2 text-gray-900 tabular-nums">{formatCLP(data.total_cmv)}</td>
                <td className="text-right px-2 py-2 text-gray-900 tabular-nums">{data.cmv_percentage}%</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </CollapsibleSection>
  );
}
