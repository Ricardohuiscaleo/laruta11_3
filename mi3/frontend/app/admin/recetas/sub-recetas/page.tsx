'use client';

import { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, ArrowLeft, Plus, Trash2, Save, Search, X,
  Pencil, Calculator, Package,
} from 'lucide-react';
import type { ApiResponse } from '@/types';
import { getIngredientEmoji } from '@/lib/ingredient-emoji';

/* ─── Types ─── */

interface CompositeIngredient {
  id: number;
  name: string;
  unit: string;
  cost_per_unit: number;
  composite_cost: number;
  max_stock: number;
  product_count: number;
  children_count: number;
}

interface SubRecipeDetail {
  id: number;
  name: string;
  unit: string;
  composite_cost: number;
  children: SubRecipeChild[];
}

interface SubRecipeChild {
  id: number;
  name: string;
  quantity: number;
  unit: string;
  cost_per_unit: number;
  cost_contribution: number;
}

interface IngredientOption {
  id: number;
  name: string;
  unit: string;
  cost_per_unit: number;
  type: string;
}

interface DraftChild {
  ingredient_id: number;
  name: string;
  quantity: number;
  unit: string;
  cost_per_unit: number;
}

const UNIT_OPTIONS = ['g', 'kg', 'ml', 'L', 'unidad'] as const;


/* ─── Main Component ─── */

export default function SubRecetasPage() {
  const [composites, setComposites] = useState<CompositeIngredient[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [successMsg, setSuccessMsg] = useState('');
  const [search, setSearch] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [newName, setNewName] = useState('');
  const [newUnit, setNewUnit] = useState('g');
  const [savingNew, setSavingNew] = useState(false);
  const fetchList = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<CompositeIngredient[]>>('/admin/ingredient-recipes');
      setComposites(res.data || []);
    } catch (e: any) {
      setError(e.message || 'Error al cargar sub-recetas');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchList(); }, [fetchList]);

  const handleDelete = async (id: number) => {
    setDeleting(id);
    try {
      await apiFetch(`/admin/ingredient-recipes/${id}`, { method: 'DELETE' });
      setSuccessMsg('Sub-receta eliminada');
      setTimeout(() => setSuccessMsg(''), 3000);
      await fetchList();
    } catch (e: any) {
      setError(e.message || 'Error al eliminar');
      setTimeout(() => setError(''), 3000);
    } finally {
      setDeleting(null);
    }
  };

  if (editingId !== null) {
    return (
      <SubRecipeEditor
        ingredientId={editingId}
        onBack={() => { setEditingId(null); fetchList(); }}
      />
    );
  }

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando sub-recetas">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  const filteredComposites = useMemo(() => {
    const q = search.toLowerCase();
    return q ? composites.filter(c => c.name.toLowerCase().includes(q)) : composites;
  }, [composites, search]);

  const handleCreateComposite = async () => {
    if (!newName.trim()) return;
    setSavingNew(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<{ id: number }>>('/admin/ingredient-recipes', {
        method: 'POST',
        body: JSON.stringify({ name: newName.trim(), unit: newUnit }),
      });
      const newId = res.data?.id;
      setNewName(''); setNewUnit('g');
      setShowAddForm(false);
      await fetchList();
      if (newId) setEditingId(newId);
    } catch (e: any) {
      setError(e.message || 'Error al crear sub-receta');
    } finally {
      setSavingNew(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Buscar sub-receta..."
            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
            aria-label="Buscar sub-receta"
          />
        </div>
        <button
          onClick={() => setShowAddForm(!showAddForm)}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors min-h-[44px] flex-shrink-0',
            showAddForm
              ? 'border border-gray-200 text-gray-600 hover:bg-gray-50'
              : 'bg-red-500 text-white hover:bg-red-600'
          )}
          aria-label={showAddForm ? 'Cancelar' : 'Agregar sub-receta'}
        >
          {showAddForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          {showAddForm ? 'Cancelar' : 'Agregar Sub-Receta'}
        </button>
      </div>

      {showAddForm && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3" role="form" aria-label="Agregar sub-receta">
          <h3 className="text-sm font-medium text-gray-700">Nuevo Ingrediente Compuesto</h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label htmlFor="sr-name" className="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
              <input id="sr-name" type="text" value={newName} onChange={e => setNewName(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="Ej: Carne Molida Preparada" />
            </div>
            <div>
              <label htmlFor="sr-unit" className="block text-xs font-medium text-gray-600 mb-1">Unidad *</label>
              <select id="sr-unit" value={newUnit} onChange={e => setNewUnit(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300 bg-white">
                {['g', 'kg', 'ml', 'L', 'unidad'].map(u => <option key={u} value={u}>{u}</option>)}
              </select>
            </div>
            <div className="flex items-end">
              <button onClick={handleCreateComposite} disabled={savingNew || !newName.trim()}
                className={cn('inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px] w-full justify-center',
                  savingNew || !newName.trim() ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600')}
                aria-label="Crear sub-receta">
                {savingNew && <Loader2 className="h-4 w-4 animate-spin" />}
                Crear y Editar
              </button>
            </div>
          </div>
        </div>
      )}

      <div className="text-xs text-gray-500">
        {filteredComposites.length} sub-receta{filteredComposites.length !== 1 ? 's' : ''}
      </div>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {filteredComposites.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
          <Package className="mx-auto h-8 w-8 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">{search ? 'No se encontraron sub-recetas.' : 'No hay ingredientes compuestos configurados.'}</p>
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {filteredComposites.map(c => (
            <div key={c.id} className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
              <div className="flex items-start justify-between gap-2">
                <div>
                  <h3 className="font-semibold text-gray-900">{c.name}</h3>
                  <span className="inline-block mt-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{c.unit}</span>
                </div>
                <div className="flex gap-1">
                  <button
                    onClick={() => setEditingId(c.id)}
                    className="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
                    aria-label={`Editar ${c.name}`}
                  >
                    <Pencil className="h-4 w-4 text-gray-500" />
                  </button>
                  <button
                    onClick={() => { if (confirm(`¿Eliminar sub-receta de "${c.name}"?`)) handleDelete(c.id); }}
                    disabled={deleting === c.id}
                    className="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg border border-gray-200 hover:bg-red-50 hover:text-red-500 transition-colors"
                    aria-label={`Eliminar ${c.name}`}
                  >
                    {deleting === c.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4 text-gray-400" />}
                  </button>
                </div>
              </div>
              <div className="text-sm text-gray-500">
                {c.children_count} ingrediente{c.children_count !== 1 ? 's' : ''}
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Costo compuesto</span>
                <span className="font-medium tabular-nums">{formatCLP(c.composite_cost)}</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Stock máx. producible</span>
                <span className="font-medium tabular-nums">{Math.floor(c.max_stock)}</span>
              </div>
              {c.product_count > 0 && (
                <span className="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-600">
                  Usado en {c.product_count} producto{c.product_count !== 1 ? 's' : ''}
                </span>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}


/* ─── SubRecipe Editor ─── */

function SubRecipeEditor({ ingredientId, onBack }: { ingredientId: number; onBack: () => void }) {
  const [detail, setDetail] = useState<SubRecipeDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [children, setChildren] = useState<DraftChild[]>([]);

  const fetchDetail = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<SubRecipeDetail>>(`/admin/ingredient-recipes/${ingredientId}`);
      const d = res.data!;
      setDetail(d);
      setChildren(d.children.map(c => ({
        ingredient_id: c.id,
        name: c.name,
        quantity: c.quantity,
        unit: c.unit,
        cost_per_unit: c.cost_per_unit,
      })));
    } catch (e: any) {
      setError(e.message || 'Error al cargar detalle');
    } finally {
      setLoading(false);
    }
  }, [ingredientId]);

  useEffect(() => { fetchDetail(); }, [fetchDetail]);

  const totalCost = useMemo(
    () => children.reduce((sum, c) => sum + c.cost_per_unit * c.quantity, 0),
    [children]
  );

  const handleAddChild = (opt: IngredientOption) => {
    if (children.some(c => c.ingredient_id === opt.id)) {
      setError('Este ingrediente ya está en la sub-receta');
      setTimeout(() => setError(''), 3000);
      return;
    }
    setChildren(prev => [...prev, {
      ingredient_id: opt.id,
      name: opt.name,
      quantity: 0,
      unit: opt.unit || 'g',
      cost_per_unit: opt.cost_per_unit,
    }]);
  };

  const handleUpdate = (index: number, field: 'quantity' | 'unit', value: number | string) => {
    setChildren(prev => prev.map((item, i) => (i === index ? { ...item, [field]: value } : item)));
  };

  const handleRemove = (index: number) => {
    setChildren(prev => prev.filter((_, i) => i !== index));
  };

  const handleSave = async () => {
    if (children.length === 0) {
      setError('Agrega al menos un ingrediente hijo');
      setTimeout(() => setError(''), 3000);
      return;
    }
    const invalid = children.find(c => c.quantity <= 0);
    if (invalid) {
      setError(`La cantidad de "${invalid.name}" debe ser mayor a 0`);
      setTimeout(() => setError(''), 3000);
      return;
    }
    setSaving(true);
    setError('');
    try {
      await apiFetch(`/admin/ingredient-recipes/${ingredientId}`, {
        method: 'POST',
        body: JSON.stringify({
          children: children.map(c => ({
            child_ingredient_id: c.ingredient_id,
            quantity: c.quantity,
            unit: c.unit,
          })),
        }),
      });
      setSuccessMsg('Sub-receta guardada');
      setTimeout(() => setSuccessMsg(''), 3000);
      await fetchDetail();
    } catch (e: any) {
      setError(e.message || 'Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando editor">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
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
            className="rounded-lg border border-gray-200 p-2 hover:bg-gray-50 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            aria-label="Volver a sub-recetas"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">{detail?.name || 'Sub-Receta'}</h2>
            {detail && <span className="text-sm text-gray-500">{detail.unit}</span>}
          </div>
        </div>
        <button
          onClick={handleSave}
          disabled={saving}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px]',
            saving ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Guardar sub-receta"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Guardar
        </button>
      </div>

      {/* Messages */}
      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {/* Autocomplete */}
      <ChildAutocomplete onSelect={handleAddChild} excludeIds={[ingredientId, ...children.map(c => c.ingredient_id)]} />

      {/* Children table */}
      {children.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
          <Plus className="mx-auto h-8 w-8 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">Sin ingredientes hijos. Usa el buscador para agregar.</p>
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
              {children.map((item, index) => (
                <tr key={item.ingredient_id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-gray-900">
                    <span className="mr-1">{getIngredientEmoji(item.name)}</span>{item.name}
                  </td>
                  <td className="px-4 py-3">
                    <input
                      type="number"
                      value={item.quantity || ''}
                      onChange={e => handleUpdate(index, 'quantity', Number(e.target.value))}
                      min={0}
                      step="any"
                      className="w-24 rounded-md border border-gray-200 px-2 py-1.5 text-sm tabular-nums focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                      aria-label={`Cantidad de ${item.name}`}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <select
                      value={item.unit}
                      onChange={e => handleUpdate(index, 'unit', e.target.value)}
                      className="rounded-md border border-gray-200 px-2 py-1.5 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                      aria-label={`Unidad de ${item.name}`}
                    >
                      {UNIT_OPTIONS.map(u => <option key={u} value={u}>{u}</option>)}
                    </select>
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                    {formatCLP(item.cost_per_unit)}/{item.unit}
                  </td>
                  <td className="px-4 py-3 text-right tabular-nums font-medium">
                    {item.quantity > 0 ? formatCLP(item.cost_per_unit * item.quantity) : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <button
                      onClick={() => handleRemove(index)}
                      className="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
                      aria-label={`Eliminar ${item.name}`}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Cost summary */}
      {children.length > 0 && (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-500">Costo total por unidad</span>
            <span className="text-lg font-semibold tabular-nums">{formatCLP(totalCost)}</span>
          </div>
        </div>
      )}

      {/* Production Calculator */}
      {children.length > 0 && (
        <ProductionCalculator items={children} unitName={detail?.name || 'unidad'} />
      )}
    </div>
  );
}


/* ─── Child Autocomplete ─── */

function ChildAutocomplete({ onSelect, excludeIds }: { onSelect: (opt: IngredientOption) => void; excludeIds: number[] }) {
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
      <label htmlFor="child-ingredient-search" className="sr-only">Buscar ingrediente hijo</label>
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          id="child-ingredient-search"
          type="text"
          value={query}
          onChange={e => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
          placeholder="Buscar ingrediente para agregar..."
          className="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-10 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
          autoComplete="off"
          role="combobox"
          aria-expanded={open}
          aria-controls="child-ingredient-listbox"
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
        <ul id="child-ingredient-listbox" role="listbox" className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
          {results.map(opt => (
            <li
              key={opt.id} role="option" aria-selected={false}
              className="flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 min-h-[44px]"
              onClick={() => handleSelect(opt)}
              onKeyDown={e => e.key === 'Enter' && handleSelect(opt)}
              tabIndex={0}
            >
              <span className="font-medium text-gray-900"><span className="mr-1">{getIngredientEmoji(opt.name)}</span>{opt.name}</span>
              <span className="text-xs text-gray-500">{formatCLP(opt.cost_per_unit)}/{opt.unit}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}


/* ─── Production Calculator ─── */

function ProductionCalculator({ items, unitName }: { items: DraftChild[]; unitName: string }) {
  const [qty, setQty] = useState(0);

  const rows = useMemo(() => items.map(c => {
    const needed = c.quantity * qty;
    const cost = c.cost_per_unit * needed;
    return { name: c.name, unit: c.unit, needed, cost };
  }), [items, qty]);

  const grandTotal = useMemo(() => rows.reduce((s, r) => s + r.cost, 0), [rows]);
  const costPerUnit = qty > 0 ? grandTotal / qty : 0;

  return (
    <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 border-b bg-amber-50 px-4 py-3">
        <Calculator className="h-4 w-4 text-amber-600" />
        <span className="text-sm font-medium text-amber-800">Calculadora de Producción</span>
      </div>
      <div className="p-4 space-y-4">
        <div>
          <label htmlFor="produce-qty" className="block text-sm font-medium text-gray-700 mb-1">
            Cantidad a producir
          </label>
          <input
            id="produce-qty"
            type="number"
            value={qty || ''}
            onChange={e => setQty(Math.max(0, Number(e.target.value)))}
            min={0}
            placeholder="Ej: 30"
            className="w-full max-w-[200px] rounded-lg border border-gray-200 px-3 py-2 text-sm tabular-nums focus:border-amber-300 focus:outline-none focus:ring-1 focus:ring-amber-300 min-h-[44px]"
            aria-label={`Cantidad de ${unitName} a producir`}
          />
        </div>

        {qty > 0 && (
          <>
            {/* Mobile: cards */}
            <div className="space-y-2 sm:hidden">
              {rows.map(r => (
                <div key={r.name} className="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-sm">
                  <div>
                    <span className="font-medium text-gray-900">{r.name}</span>
                    <span className="ml-2 text-gray-500">{r.needed.toFixed(3)} {r.unit}</span>
                  </div>
                  <span className="tabular-nums font-medium">{formatCLP(r.cost)}</span>
                </div>
              ))}
            </div>

            {/* Desktop: table */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
                  <tr>
                    <th className="px-4 py-2">Ingrediente</th>
                    <th className="px-4 py-2 text-right">Cantidad necesaria</th>
                    <th className="px-4 py-2 text-right">Costo</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {rows.map(r => (
                    <tr key={r.name}>
                      <td className="px-4 py-2 font-medium text-gray-900">{r.name}</td>
                      <td className="px-4 py-2 text-right tabular-nums text-gray-600">{r.needed.toFixed(3)} {r.unit}</td>
                      <td className="px-4 py-2 text-right tabular-nums font-medium">{formatCLP(r.cost)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Totals */}
            <div className="rounded-lg bg-amber-50 p-3 space-y-1 border-l-4 border-amber-400">
              <div className="flex items-center justify-between text-sm">
                <span className="text-amber-800">Costo por unidad</span>
                <span className="font-semibold tabular-nums text-amber-900">{formatCLP(costPerUnit)}</span>
              </div>
              <div className="flex items-center justify-between text-sm">
                <span className="text-amber-800 font-medium">Costo total ({qty} u)</span>
                <span className="text-lg font-bold tabular-nums text-amber-900">{formatCLP(grandTotal)}</span>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
