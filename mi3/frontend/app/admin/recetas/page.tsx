'use client';

import { useEffect, useState, useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Search, ArrowUpDown, ChevronDown, AlertTriangle } from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface RecipeProduct {
  id: number;
  name: string;
  category_id: number | null;
  price: number;
  recipe_cost: number;
  margin: number | null;
  ingredient_count: number;
}

type SortField = 'name' | 'price' | 'cost' | 'margin';
type SortDir = 'asc' | 'desc';

const TARGET_MARGIN = 65;

/* ─── Component ─── */

export default function RecetasPage() {
  const router = useRouter();
  const [products, setProducts] = useState<RecipeProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<number | ''>('');
  const [sortField, setSortField] = useState<SortField>('name');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  useEffect(() => {
    setLoading(true);
    apiFetch<ApiResponse<RecipeProduct[]>>('/admin/recetas')
      .then(res => setProducts(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  /* Unique categories from data */
  const categories = useMemo(() => {
    const ids = new Set<number>();
    for (const p of products) {
      if (p.category_id != null) ids.add(p.category_id);
    }
    return Array.from(ids).sort((a, b) => a - b);
  }, [products]);

  /* Filter + sort */
  const filtered = useMemo(() => {
    let list = products;

    if (search) {
      const q = search.toLowerCase();
      list = list.filter(p => p.name.toLowerCase().includes(q));
    }

    if (categoryFilter !== '') {
      list = list.filter(p => p.category_id === categoryFilter);
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
        case 'cost':
          cmp = a.recipe_cost - b.recipe_cost;
          break;
        case 'margin':
          cmp = (a.margin ?? -1) - (b.margin ?? -1);
          break;
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });

    return list;
  }, [products, search, categoryFilter, sortField, sortDir]);

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando recetas">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  if (error) {
    return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;
  }

  return (
    <div className="space-y-3">
      {/* Search + Category filter */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <div className="relative flex-1">
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
        <div className="relative">
          <select
            value={categoryFilter}
            onChange={e => setCategoryFilter(e.target.value === '' ? '' : Number(e.target.value))}
            className="appearance-none rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
            aria-label="Filtrar por categoría"
          >
            <option value="">Todas las categorías</option>
            {categories.map(id => (
              <option key={id} value={id}>Categoría {id}</option>
            ))}
          </select>
          <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        </div>
      </div>

      {/* Summary */}
      <div className="text-xs text-gray-500">
        {filtered.length} producto{filtered.length !== 1 ? 's' : ''}
        {search || categoryFilter !== '' ? ` (de ${products.length} total)` : ''}
      </div>

      {/* Table */}
      <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table className="w-full text-sm">
          <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
            <tr>
              <SortHeader label="Producto" field="name" current={sortField} dir={sortDir} onSort={toggleSort} />
              <th className="px-4 py-3 hidden sm:table-cell">Categoría</th>
              <SortHeader label="Precio" field="price" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
              <SortHeader label="Costo Receta" field="cost" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
              <SortHeader label="Margen" field="margin" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
              <th className="px-4 py-3 text-right">Ingredientes</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                  No se encontraron productos
                </td>
              </tr>
            ) : (
              filtered.map(p => {
                const hasRecipe = p.ingredient_count > 0;
                const belowTarget = hasRecipe && p.margin != null && p.margin < TARGET_MARGIN;

                return (
                  <tr
                    key={p.id}
                    onClick={() => router.push(`/admin/recetas/${p.id}`)}
                    className={cn(
                      'hover:bg-gray-50 transition-colors cursor-pointer',
                      belowTarget && 'bg-amber-50/50'
                    )}
                    role="link"
                    tabIndex={0}
                    onKeyDown={e => e.key === 'Enter' && router.push(`/admin/recetas/${p.id}`)}
                    aria-label={`Ver receta de ${p.name}`}
                  >
                    <td className="px-4 py-3 font-medium text-gray-900">{p.name}</td>
                    <td className="px-4 py-3 hidden sm:table-cell text-gray-500">
                      {p.category_id ?? '—'}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums">{formatCLP(p.price)}</td>
                    <td className="px-4 py-3 text-right tabular-nums">
                      {hasRecipe ? formatCLP(p.recipe_cost) : (
                        <span className="text-gray-400">$0</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {hasRecipe ? (
                        <span className={cn(
                          'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                          belowTarget
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-green-100 text-green-700'
                        )}>
                          {belowTarget && <AlertTriangle className="h-3 w-3" />}
                          {p.margin != null ? `${p.margin.toFixed(1)}%` : '—'}
                        </span>
                      ) : (
                        <span className="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                          Sin receta
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums text-gray-500">
                      {hasRecipe ? p.ingredient_count : '—'}
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
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
