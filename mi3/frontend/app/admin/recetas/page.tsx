'use client';

import { useEffect, useState, useCallback, useMemo, useRef } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, Search, ArrowUpDown, ChevronDown, AlertTriangle,
  ArrowLeft, Plus, Trash2, Save, X,
} from 'lucide-react';
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

interface RecipeIngredient {
  id: number;
  name: string;
  quantity: number;
  unit: string;
  cost_per_unit: number;
  ingredient_cost: number;
}

interface RecipeDetail {
  id: number;
  name: string;
  category_id: number | null;
  price: number;
  recipe_cost: number;
  margin: number | null;
  ingredient_count: number;
  ingredients: RecipeIngredient[];
}

interface IngredientOption {
  id: number;
  name: string;
  unit: string;
  cost_per_unit: number;
  type: string;
}

interface DraftIngredient {
  ingredient_id: number;
  name: string;
  quantity: number;
  unit: string;
  cost_per_unit: number;
}

type SortField = 'name' | 'price' | 'cost' | 'margin';
type SortDir = 'asc' | 'desc';

const TARGET_MARGIN = 65;
const UNIT_OPTIONS = ['g', 'kg', 'ml', 'L', 'unidad'] as const;

/* ─── Main Page ─── */

export default function RecetasPage() {
  const [products, setProducts] = useState<RecipeProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedProductId, setSelectedProductId] = useState<number | null>(null);

  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<number | ''>('');
  const [sortField, setSortField] = useState<SortField>('name');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  const fetchProducts = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<ApiResponse<RecipeProduct[]>>('/admin/recetas');
      setProducts(res.data || []);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchProducts(); }, [fetchProducts]);

  const categories = useMemo(() => {
    const ids = new Set<number>();
    for (const p of products) {
      if (p.category_id != null) ids.add(p.category_id);
    }
    return Array.from(ids).sort((a, b) => a - b);
  }, [products]);

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
        case 'name': cmp = a.name.localeCompare(b.name, 'es'); break;
        case 'price': cmp = a.price - b.price; break;
        case 'cost': cmp = a.recipe_cost - b.recipe_cost; break;
        case 'margin': cmp = (a.margin ?? -1) - (b.margin ?? -1); break;
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

  /* ── Inline editor: show when a product is selected ── */
  if (selectedProductId !== null) {
    return (
      <RecipeEditor
        productId={selectedProductId}
        onBack={() => { setSelectedProductId(null); fetchProducts(); }}
      />
    );
  }

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
                    onClick={() => setSelectedProductId(p.id)}
                    className={cn(
                      'hover:bg-gray-50 transition-colors cursor-pointer',
                      belowTarget && 'bg-amber-50/50'
                    )}
                    role="button"
                    tabIndex={0}
                    onKeyDown={e => e.key === 'Enter' && setSelectedProductId(p.id)}
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


/* ─── Inline Recipe Editor ─── */

function RecipeEditor({ productId, onBack }: { productId: number; onBack: () => void }) {
  const [product, setProduct] = useState<RecipeDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [ingredients, setIngredients] = useState<DraftIngredient[]>([]);
  const [hasExistingRecipe, setHasExistingRecipe] = useState(false);

  const fetchDetail = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<RecipeDetail>>(`/admin/recetas/${productId}`);
      const data = res.data!;
      setProduct(data);
      setHasExistingRecipe(data.ingredient_count > 0);
      setIngredients(
        data.ingredients.map(i => ({
          ingredient_id: i.id,
          name: i.name,
          quantity: i.quantity,
          unit: i.unit,
          cost_per_unit: i.cost_per_unit,
        }))
      );
    } catch (e: any) {
      setError(e.message || 'Error al cargar receta');
    } finally {
      setLoading(false);
    }
  }, [productId]);

  useEffect(() => { fetchDetail(); }, [fetchDetail]);

  const handleAddIngredient = (opt: IngredientOption) => {
    if (ingredients.some(i => i.ingredient_id === opt.id)) {
      setError('Este ingrediente ya está en la receta');
      setTimeout(() => setError(''), 3000);
      return;
    }
    setIngredients(prev => [
      ...prev,
      { ingredient_id: opt.id, name: opt.name, quantity: 0, unit: opt.unit || 'g', cost_per_unit: opt.cost_per_unit },
    ]);
  };

  const handleRemove = (index: number) => {
    setIngredients(prev => prev.filter((_, i) => i !== index));
  };

  const handleUpdate = (index: number, field: 'quantity' | 'unit', value: number | string) => {
    setIngredients(prev =>
      prev.map((item, i) => (i === index ? { ...item, [field]: value } : item))
    );
  };

  const handleSave = async () => {
    if (ingredients.length === 0) {
      setError('Agrega al menos un ingrediente');
      setTimeout(() => setError(''), 3000);
      return;
    }
    const invalid = ingredients.find(i => i.quantity <= 0);
    if (invalid) {
      setError(`La cantidad de "${invalid.name}" debe ser mayor a 0`);
      setTimeout(() => setError(''), 3000);
      return;
    }
    setSaving(true);
    setError('');
    setSuccessMsg('');
    try {
      const payload = {
        ingredients: ingredients.map(i => ({
          ingredient_id: i.ingredient_id,
          quantity: i.quantity,
          unit: i.unit,
        })),
      };
      await apiFetch(`/admin/recetas/${productId}`, {
        method: hasExistingRecipe ? 'PUT' : 'POST',
        body: JSON.stringify(payload),
      });
      setSuccessMsg('Receta guardada correctamente');
      setTimeout(() => setSuccessMsg(''), 3000);
      await fetchDetail();
    } catch (e: any) {
      setError(e.message || 'Error al guardar receta');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteIngredient = async (ingredientId: number) => {
    if (!hasExistingRecipe) {
      setIngredients(prev => prev.filter(i => i.ingredient_id !== ingredientId));
      return;
    }
    try {
      await apiFetch(`/admin/recetas/${productId}/${ingredientId}`, { method: 'DELETE' });
      await fetchDetail();
    } catch (e: any) {
      setError(e.message || 'Error al eliminar ingrediente');
    }
  };

  const totalCost = useMemo(
    () => ingredients.reduce((sum, i) => sum + i.cost_per_unit * i.quantity, 0),
    [ingredients]
  );

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando receta">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  if (!product) {
    return (
      <div className="rounded-lg bg-red-50 p-4 text-red-600">
        {error || 'Producto no encontrado'}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            className="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-gray-200 p-2 hover:bg-gray-50 transition-colors"
            aria-label="Volver a recetas"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">{product.name}</h2>
            <p className="text-sm text-gray-500">
              Precio: {formatCLP(product.price)}
              {product.category_id != null && ` · Categoría ${product.category_id}`}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <CostBadge cost={product.recipe_cost} margin={product.margin} />
          <button
            onClick={handleSave}
            disabled={saving}
            className={cn(
              'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px]',
              saving ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'
            )}
            aria-label="Guardar receta"
          >
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
            Guardar
          </button>
        </div>
      </div>

      {/* Messages */}
      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {/* Autocomplete */}
      <IngredientAutocomplete onSelect={handleAddIngredient} excludeIds={ingredients.map(i => i.ingredient_id)} />

      {/* Ingredients table */}
      {ingredients.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
          <Plus className="mx-auto h-8 w-8 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">
            Sin ingredientes. Usa el buscador para agregar ingredientes a la receta.
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
              <tr>
                <th className="px-4 py-3">Ingrediente</th>
                <th className="px-4 py-3">Cantidad</th>
                <th className="px-4 py-3">Unidad</th>
                <th className="px-4 py-3 text-right hidden sm:table-cell">Costo/u</th>
                <th className="px-4 py-3 text-right">Costo</th>
                <th className="px-4 py-3 w-12"></th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {ingredients.map((item, index) => {
                const cost = item.cost_per_unit * item.quantity;
                return (
                  <tr key={item.ingredient_id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3 font-medium text-gray-900">{item.name}</td>
                    <td className="px-4 py-3">
                      <input
                        type="number"
                        value={item.quantity || ''}
                        onChange={e => handleUpdate(index, 'quantity', Number(e.target.value))}
                        min={0}
                        step="any"
                        className="w-24 rounded-md border border-gray-200 px-2 py-1.5 text-sm tabular-nums focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
                        aria-label={`Cantidad de ${item.name}`}
                      />
                    </td>
                    <td className="px-4 py-3">
                      <select
                        value={item.unit}
                        onChange={e => handleUpdate(index, 'unit', e.target.value)}
                        className="rounded-md border border-gray-200 px-2 py-1.5 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
                        aria-label={`Unidad de ${item.name}`}
                      >
                        {UNIT_OPTIONS.map(u => (
                          <option key={u} value={u}>{u}</option>
                        ))}
                      </select>
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                      {formatCLP(item.cost_per_unit)}/{item.unit}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums font-medium">
                      {item.quantity > 0 ? formatCLP(cost) : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <button
                        onClick={() =>
                          hasExistingRecipe
                            ? handleDeleteIngredient(item.ingredient_id)
                            : handleRemove(index)
                        }
                        className="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors"
                        aria-label={`Eliminar ${item.name}`}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Cost summary */}
      {ingredients.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-500">Costo total de receta</span>
            <span className="text-lg font-semibold tabular-nums">{formatCLP(totalCost)}</span>
          </div>
        </div>
      )}
    </div>
  );
}


/* ─── CostBadge ─── */

function CostBadge({ cost, margin }: { cost: number; margin: number | null }) {
  const belowTarget = margin != null && margin < TARGET_MARGIN;
  return (
    <div className="flex items-center gap-2">
      <span className="text-sm text-gray-500">Costo:</span>
      <span className="text-sm font-medium tabular-nums">{formatCLP(cost)}</span>
      {margin != null ? (
        <span
          className={cn(
            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
            belowTarget ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'
          )}
        >
          {belowTarget && <AlertTriangle className="h-3 w-3" />}
          {margin.toFixed(1)}%
        </span>
      ) : (
        <span className="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
          Sin receta
        </span>
      )}
    </div>
  );
}

/* ─── IngredientAutocomplete ─── */

function IngredientAutocomplete({
  onSelect,
  excludeIds,
}: {
  onSelect: (opt: IngredientOption) => void;
  excludeIds: number[];
}) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<IngredientOption[]>([]);
  const [searching, setSearching] = useState(false);
  const [open, setOpen] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (query.length < 2) { setResults([]); setOpen(false); return; }
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      setSearching(true);
      try {
        const data = await apiFetch<any[]>(`/admin/compras/items?q=${encodeURIComponent(query)}`);
        const filtered = data
          .filter((item: any) => item.type === 'ingredient' && !excludeIds.includes(item.id))
          .map((item: any) => ({
            id: item.id, name: item.name, unit: item.unit,
            cost_per_unit: Number(item.cost_per_unit) || 0, type: item.type,
          }));
        setResults(filtered);
        setOpen(filtered.length > 0);
      } catch { setResults([]); }
      finally { setSearching(false); }
    }, 300);
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, [query, excludeIds]);

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const handleSelect = (opt: IngredientOption) => {
    onSelect(opt);
    setQuery(''); setResults([]); setOpen(false);
  };

  return (
    <div ref={containerRef} className="relative">
      <label htmlFor="ingredient-search" className="sr-only">Buscar ingrediente</label>
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          id="ingredient-search"
          type="text"
          value={query}
          onChange={e => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
          placeholder="Buscar ingrediente para agregar..."
          className="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-10 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
          autoComplete="off"
          role="combobox"
          aria-expanded={open}
          aria-controls="ingredient-listbox"
          aria-autocomplete="list"
        />
        {searching && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-gray-400" />}
        {query && !searching && (
          <button
            onClick={() => { setQuery(''); setResults([]); setOpen(false); }}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
            aria-label="Limpiar búsqueda"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>
      {open && (
        <ul id="ingredient-listbox" role="listbox" className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
          {results.map(opt => (
            <li
              key={opt.id} role="option" aria-selected={false}
              className="flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 min-h-[44px]"
              onClick={() => handleSelect(opt)}
              onKeyDown={e => e.key === 'Enter' && handleSelect(opt)}
              tabIndex={0}
            >
              <span className="font-medium text-gray-900">{opt.name}</span>
              <span className="text-xs text-gray-500">{formatCLP(opt.cost_per_unit)}/{opt.unit}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

/* ─── Sortable header helper ─── */

function SortHeader({
  label, field, current, dir, onSort, className,
}: {
  label: string; field: SortField; current: SortField; dir: SortDir;
  onSort: (f: SortField) => void; className?: string;
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
        {active && <span className="sr-only">{dir === 'asc' ? 'ascendente' : 'descendente'}</span>}
      </button>
    </th>
  );
}
