'use client';

import { useState, useEffect, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, AlertTriangle, ArrowUpDown, TrendingUp, Search } from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface Recommendation {
  id: number;
  name: string;
  price: number;
  recipe_cost: number;
  margin: number;
  recommended_price: number;
  price_difference: number;
}

type SortField = 'name' | 'price' | 'recipe_cost' | 'margin' | 'recommended_price' | 'price_difference';
type SortDir = 'asc' | 'desc';

const DEFAULT_MARGIN = 65;

/* ─── Component ─── */

export default function RecomendacionesPage() {
  const [recommendations, setRecommendations] = useState<Recommendation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [targetMargin, setTargetMargin] = useState<number>(DEFAULT_MARGIN);
  const [marginInput, setMarginInput] = useState<string>(String(DEFAULT_MARGIN));
  const [search, setSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('margin');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  const fetchRecommendations = (margin: number) => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<Recommendation[]>>(`/admin/recetas/recommendations?target_margin=${margin}`)
      .then(res => setRecommendations(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchRecommendations(targetMargin);
  }, [targetMargin]);

  const handleMarginSubmit = () => {
    const parsed = parseFloat(marginInput);
    if (!isNaN(parsed) && parsed > 0 && parsed < 100) {
      setTargetMargin(parsed);
    }
  };

  /* Filter + sort */
  const filtered = useMemo(() => {
    let list = recommendations;

    if (search) {
      const q = search.toLowerCase();
      list = list.filter(r => r.name.toLowerCase().includes(q));
    }

    list = [...list].sort((a, b) => {
      let cmp = 0;
      switch (sortField) {
        case 'name':
          cmp = a.name.localeCompare(b.name, 'es');
          break;
        case 'price':
          cmp = a.price - b.price;
          break;
        case 'recipe_cost':
          cmp = a.recipe_cost - b.recipe_cost;
          break;
        case 'margin':
          cmp = a.margin - b.margin;
          break;
        case 'recommended_price':
          cmp = a.recommended_price - b.recommended_price;
          break;
        case 'price_difference':
          cmp = a.price_difference - b.price_difference;
          break;
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });

    return list;
  }, [recommendations, search, sortField, sortDir]);

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const belowTargetCount = recommendations.filter(r => r.margin < targetMargin).length;

  return (
    <div className="space-y-4">
      {/* Target margin control */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 className="text-base font-semibold text-gray-900">Recomendaciones de Precios</h2>
            <p className="mt-1 text-sm text-gray-500">
              Precios sugeridos según costo de receta y margen objetivo.
            </p>
          </div>
          <div className="flex items-end gap-2">
            <div>
              <label htmlFor="target-margin" className="block text-xs font-medium text-gray-500 mb-1">
                Margen objetivo
              </label>
              <div className="relative">
                <input
                  id="target-margin"
                  type="number"
                  min={1}
                  max={99}
                  step="any"
                  value={marginInput}
                  onChange={e => setMarginInput(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && handleMarginSubmit()}
                  className="w-24 rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-7 text-sm tabular-nums focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                  aria-label="Margen objetivo en porcentaje"
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
              </div>
            </div>
            <button
              onClick={handleMarginSubmit}
              disabled={loading}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white transition-colors min-h-[38px]',
                loading ? 'bg-gray-300 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'
              )}
              aria-label="Aplicar margen objetivo"
            >
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <TrendingUp className="h-4 w-4" />}
              Calcular
            </button>
          </div>
        </div>
      </div>

      {/* Error */}
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-label="Cargando recomendaciones">
          <Loader2 className="h-6 w-6 animate-spin text-red-500" />
        </div>
      ) : (
        <>
          {/* Search + summary */}
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Buscar producto..."
                className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                aria-label="Buscar producto"
              />
            </div>
            <div className="flex items-center gap-3 text-xs text-gray-500">
              <span>{filtered.length} producto{filtered.length !== 1 ? 's' : ''}</span>
              {belowTargetCount > 0 && (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">
                  <AlertTriangle className="h-3 w-3" />
                  {belowTargetCount} bajo margen
                </span>
              )}
            </div>
          </div>

          {/* Table */}
          <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
                <tr>
                  <SortHeader label="Producto" field="name" current={sortField} dir={sortDir} onSort={toggleSort} />
                  <SortHeader label="Precio actual" field="price" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
                  <SortHeader label="Costo receta" field="recipe_cost" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right hidden sm:table-cell" />
                  <SortHeader label="Margen actual" field="margin" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
                  <SortHeader label="Precio sugerido" field="recommended_price" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
                  <SortHeader label="Diferencia" field="price_difference" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right hidden sm:table-cell" />
                </tr>
              </thead>
              <tbody className="divide-y">
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                      No se encontraron productos con receta
                    </td>
                  </tr>
                ) : (
                  filtered.map(r => {
                    const belowTarget = r.margin < targetMargin;
                    return (
                      <tr
                        key={r.id}
                        className={cn(
                          'transition-colors hover:bg-gray-50',
                          belowTarget && 'bg-amber-50/50'
                        )}
                      >
                        <td className="px-4 py-3 font-medium text-gray-900">{r.name}</td>
                        <td className="px-4 py-3 text-right tabular-nums">{formatCLP(r.price)}</td>
                        <td className="px-4 py-3 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                          {formatCLP(r.recipe_cost)}
                        </td>
                        <td className="px-4 py-3 text-right">
                          <span className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                            belowTarget
                              ? 'bg-amber-100 text-amber-700'
                              : 'bg-green-100 text-green-700'
                          )}>
                            {belowTarget && <AlertTriangle className="h-3 w-3" />}
                            {r.margin.toFixed(1)}%
                          </span>
                        </td>
                        <td className="px-4 py-3 text-right tabular-nums font-medium">
                          {formatCLP(r.recommended_price)}
                        </td>
                        <td className={cn(
                          'px-4 py-3 text-right tabular-nums hidden sm:table-cell',
                          r.price_difference > 0 ? 'text-red-500' : r.price_difference < 0 ? 'text-green-600' : 'text-gray-400'
                        )}>
                          {r.price_difference > 0 ? '+' : ''}{formatCLP(r.price_difference)}
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

/* ─── Sortable header helper ─── */

function SortHeader({
  label,
  field,
  current,
  dir,
  onSort,
  className,
}: {
  label: string;
  field: SortField;
  current: SortField;
  dir: SortDir;
  onSort: (f: SortField) => void;
  className?: string;
}) {
  const active = current === field;
  return (
    <th className={cn('px-4 py-3', className)}>
      <button
        onClick={() => onSort(field)}
        className="inline-flex items-center gap-1 hover:text-gray-700"
        aria-label={`Ordenar por ${label}`}
      >
        {label}
        <ArrowUpDown className={cn('h-3 w-3', active ? 'text-red-500' : 'text-gray-300')} />
        {active && (
          <span className="sr-only">{dir === 'asc' ? 'ascendente' : 'descendente'}</span>
        )}
      </button>
    </th>
  );
}
