'use client';

import { useEffect, useState, useCallback, useRef, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, ArrowLeft, Plus, Trash2, Save, Search, X,
  Package, ChevronRight, CircleDot,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface ComboRow {
  id: number;
  name: string;
  price: number;
  cost_price: number;
  margin: number | null;
  image_url: string | null;
  fixed_count: number;
  selectable_count: number;
  total_components: number;
}

interface FixedItem {
  product_id: number;
  product_name: string;
  quantity: number;
  image_url: string | null;
}

interface SelectionOption {
  product_id: number;
  product_name: string;
  price_adjustment: number;
  image_url: string | null;
}

interface SelectionGroup {
  max_selections: number;
  options: SelectionOption[];
}

interface ComboDetail {
  fixed_items: FixedItem[];
  selection_groups: Record<string, SelectionGroup>;
}

interface ProductSearchResult {
  id: number;
  name: string;
  price: number;
  cost_price: number;
  image_url: string | null;
  is_active: boolean;
}

/* ─── Draft types for editor ─── */

interface DraftFixedItem {
  product_id: number;
  product_name: string;
  quantity: number;
  cost_price: number;
}

interface DraftOption {
  product_id: number;
  product_name: string;
  price_adjustment: number;
  cost_price: number;
  is_active: boolean;
}

interface DraftGroup {
  name: string;
  max_selections: number;
  options: DraftOption[];
}

/* ─── Main Page ─── */

export default function CombosPage() {
  const [combos, setCombos] = useState<ComboRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editingCombo, setEditingCombo] = useState<ComboRow | null>(null);
  const [search, setSearch] = useState('');
  const [showAddForm, setShowAddForm] = useState(false);
  const [comboName, setComboName] = useState('');
  const [comboPrice, setComboPrice] = useState('');
  const [comboDesc, setComboDesc] = useState('');
  const [savingCombo, setSavingCombo] = useState(false);

  const fetchCombos = useCallback(async () => {    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<ComboRow[]>>('/admin/combos');
      setCombos(res.data || []);
    } catch (e: any) {
      setError(e.message || 'Error al cargar combos');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchCombos(); }, [fetchCombos]);

  const filteredCombos = useMemo(() => {
    const q = search.toLowerCase();
    return q ? combos.filter(c => c.name.toLowerCase().includes(q)) : combos;
  }, [combos, search]);

  const handleCreateCombo = async () => {
    if (!comboName.trim() || !comboPrice || Number(comboPrice) <= 0) return;
    setSavingCombo(true);
    setError('');
    try {
      await apiFetch<ApiResponse<{ id: number }>>('/admin/combos', {
        method: 'POST',
        body: JSON.stringify({
          name: comboName.trim(),
          price: Number(comboPrice),
          description: comboDesc.trim() || undefined,
        }),
      });
      setComboName(''); setComboPrice(''); setComboDesc('');
      setShowAddForm(false);
      await fetchCombos();
    } catch (e: any) {
      setError(e.message || 'Error al crear combo');
    } finally {
      setSavingCombo(false);
    }
  };

  if (editingCombo) {
    return (
      <ComboEditor
        combo={editingCombo}
        onBack={() => { setEditingCombo(null); fetchCombos(); }}
      />
    );
  }

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando combos">
        <Loader2 className="h-6 w-6 animate-spin text-red-500" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={e => setSearch(e.target.value)}
            placeholder="Buscar combo..."
            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
            aria-label="Buscar combo"
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
          aria-label={showAddForm ? 'Cancelar' : 'Agregar combo'}
        >
          {showAddForm ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
          {showAddForm ? 'Cancelar' : 'Agregar Combo'}
        </button>
      </div>

      {showAddForm && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3" role="form" aria-label="Agregar combo">
          <h3 className="text-sm font-medium text-gray-700">Nuevo Combo</h3>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label htmlFor="combo-name" className="block text-xs font-medium text-gray-600 mb-1">Nombre *</label>
              <input id="combo-name" type="text" value={comboName} onChange={e => setComboName(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="Ej: Combo Familiar" />
            </div>
            <div>
              <label htmlFor="combo-price" className="block text-xs font-medium text-gray-600 mb-1">Precio *</label>
              <input id="combo-price" type="number" value={comboPrice} onChange={e => setComboPrice(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="9990" min="1" />
            </div>
            <div>
              <label htmlFor="combo-desc" className="block text-xs font-medium text-gray-600 mb-1">Descripción</label>
              <input id="combo-desc" type="text" value={comboDesc} onChange={e => setComboDesc(e.target.value)}
                className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm min-h-[44px] focus:outline-none focus:ring-1 focus:ring-red-300"
                placeholder="Opcional" />
            </div>
          </div>
          <div className="flex justify-end">
            <button onClick={handleCreateCombo} disabled={savingCombo || !comboName.trim() || !comboPrice}
              className={cn('inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px]',
                savingCombo || !comboName.trim() || !comboPrice ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600')}
              aria-label="Guardar combo">
              {savingCombo && <Loader2 className="h-4 w-4 animate-spin" />}
              Guardar
            </button>
          </div>
        </div>
      )}

      <div className="text-xs text-gray-500">
        {filteredCombos.length} combo{filteredCombos.length !== 1 ? 's' : ''}
      </div>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}

      {filteredCombos.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
          <Package className="mx-auto h-8 w-8 text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">{search ? 'No se encontraron combos.' : 'No hay combos configurados.'}</p>
        </div>
      ) : (
        <>
          {/* Mobile: cards */}
          <div className="space-y-3 sm:hidden">
            {filteredCombos.map(combo => (
              <button
                key={combo.id}
                onClick={() => setEditingCombo(combo)}
                className="w-full rounded-xl border bg-white p-4 shadow-sm text-left active:bg-gray-50 transition-colors"
                aria-label={`Editar ${combo.name}`}
              >
                <div className="flex items-center justify-between">
                  <h3 className="font-semibold text-gray-900 text-sm">{combo.name}</h3>
                  <ChevronRight className="h-4 w-4 text-gray-400 flex-shrink-0" />
                </div>
                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                  <span className="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600">
                    {formatCLP(combo.price)}
                  </span>
                  <span className="rounded-full bg-blue-50 px-2 py-0.5 text-blue-600">
                    {combo.fixed_count} fijos
                  </span>
                  <span className="rounded-full bg-purple-50 px-2 py-0.5 text-purple-600">
                    {combo.selectable_count} selec.
                  </span>
                  <MarginBadge margin={combo.margin} />
                </div>
                <div className="mt-1 text-xs text-gray-500">
                  Costo: {formatCLP(combo.cost_price)}
                </div>
              </button>
            ))}
          </div>

          {/* Desktop: table */}
          <div className="hidden sm:block overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
                <tr>
                  <th className="px-4 py-3">Nombre</th>
                  <th className="px-4 py-3 text-right">Precio</th>
                  <th className="px-4 py-3 text-right">Costo</th>
                  <th className="px-4 py-3 text-right">Margen</th>
                  <th className="px-4 py-3 text-center"># Fijos</th>
                  <th className="px-4 py-3 text-center"># Selec.</th>
                  <th className="px-4 py-3 w-10"></th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {filteredCombos.map(combo => (
                  <tr
                    key={combo.id}
                    onClick={() => setEditingCombo(combo)}
                    className="cursor-pointer hover:bg-gray-50 transition-colors"
                    role="button"
                    tabIndex={0}
                    onKeyDown={e => e.key === 'Enter' && setEditingCombo(combo)}
                    aria-label={`Editar ${combo.name}`}
                  >
                    <td className="px-4 py-3 font-medium text-gray-900">{combo.name}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{formatCLP(combo.price)}</td>
                    <td className="px-4 py-3 text-right tabular-nums text-gray-500">{formatCLP(combo.cost_price)}</td>
                    <td className="px-4 py-3 text-right">
                      <MarginBadge margin={combo.margin} />
                    </td>
                    <td className="px-4 py-3 text-center tabular-nums">{combo.fixed_count}</td>
                    <td className="px-4 py-3 text-center tabular-nums">{combo.selectable_count}</td>
                    <td className="px-4 py-3">
                      <ChevronRight className="h-4 w-4 text-gray-400" />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}


/* ─── Margin Badge (T2.5) ─── */

function MarginBadge({ margin }: { margin: number | null }) {
  if (margin === null) return <span className="text-xs text-gray-400">—</span>;
  const isGood = margin >= 50;
  return (
    <span
      className={cn(
        'inline-block rounded-full px-2 py-0.5 text-xs font-medium tabular-nums',
        isGood ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'
      )}
    >
      {margin.toFixed(1)}%
    </span>
  );
}

/* ─── Combo Editor (T2.3 + T2.4 + T2.5) ─── */

function ComboEditor({ combo, onBack }: { combo: ComboRow; onBack: () => void }) {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [fixedItems, setFixedItems] = useState<DraftFixedItem[]>([]);
  const [groups, setGroups] = useState<DraftGroup[]>([]);

  const fetchDetail = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<ComboDetail>>(`/admin/combos/${combo.id}`);
      const d = res.data!;

      setFixedItems(
        d.fixed_items.map(fi => ({
          product_id: fi.product_id,
          product_name: fi.product_name,
          quantity: fi.quantity,
          cost_price: 0,
        }))
      );

      const groupList: DraftGroup[] = Object.entries(d.selection_groups).map(
        ([name, group]) => ({
          name,
          max_selections: group.max_selections,
          options: group.options.map(opt => ({
            product_id: opt.product_id,
            product_name: opt.product_name,
            price_adjustment: opt.price_adjustment,
            cost_price: 0,
            is_active: true,
          })),
        })
      );
      setGroups(groupList);
    } catch (e: any) {
      setError(e.message || 'Error al cargar detalle');
    } finally {
      setLoading(false);
    }
  }, [combo.id]);

  useEffect(() => { fetchDetail(); }, [fetchDetail]);

  /* ─── Cost calculation (T2.5) ─── */
  const calculatedCost = useMemo(() => {
    const fixedCost = fixedItems.reduce((sum, fi) => sum + fi.cost_price * fi.quantity, 0);
    let selectableCost = 0;
    for (const g of groups) {
      if (g.options.length > 0) {
        const avg = g.options.reduce((s, o) => s + o.cost_price, 0) / g.options.length;
        selectableCost += avg;
      }
    }
    return Math.round(fixedCost + selectableCost);
  }, [fixedItems, groups]);

  const calculatedMargin = useMemo(() => {
    if (combo.price <= 0) return null;
    return ((1 - calculatedCost / combo.price) * 100);
  }, [calculatedCost, combo.price]);

  /* ─── Fixed items handlers ─── */
  const addFixedItem = (product: ProductSearchResult) => {
    if (fixedItems.some(fi => fi.product_id === product.id)) {
      setError('Este producto ya está como item fijo');
      setTimeout(() => setError(''), 3000);
      return;
    }
    setFixedItems(prev => [...prev, {
      product_id: product.id,
      product_name: product.name,
      quantity: 1,
      cost_price: product.cost_price || 0,
    }]);
  };

  const updateFixedQty = (index: number, qty: number) => {
    setFixedItems(prev => prev.map((fi, i) => i === index ? { ...fi, quantity: Math.max(1, qty) } : fi));
  };

  const removeFixed = (index: number) => {
    setFixedItems(prev => prev.filter((_, i) => i !== index));
  };

  /* ─── Group handlers ─── */
  const addGroup = () => {
    const name = `Grupo ${groups.length + 1}`;
    setGroups(prev => [...prev, { name, max_selections: 1, options: [] }]);
  };

  const updateGroupName = (index: number, name: string) => {
    setGroups(prev => prev.map((g, i) => i === index ? { ...g, name } : g));
  };

  const updateGroupMax = (index: number, max: number) => {
    setGroups(prev => prev.map((g, i) => i === index ? { ...g, max_selections: Math.max(1, max) } : g));
  };

  const removeGroup = (index: number) => {
    setGroups(prev => prev.filter((_, i) => i !== index));
  };

  const addOptionToGroup = (groupIndex: number, product: ProductSearchResult) => {
    setGroups(prev => prev.map((g, i) => {
      if (i !== groupIndex) return g;
      if (g.options.some(o => o.product_id === product.id)) return g;
      return {
        ...g,
        options: [...g.options, {
          product_id: product.id,
          product_name: product.name,
          price_adjustment: 0,
          cost_price: product.cost_price || 0,
          is_active: product.is_active,
        }],
      };
    }));
  };

  const updateOptionAdjustment = (groupIndex: number, optIndex: number, adj: number) => {
    setGroups(prev => prev.map((g, gi) => {
      if (gi !== groupIndex) return g;
      return {
        ...g,
        options: g.options.map((o, oi) => oi === optIndex ? { ...o, price_adjustment: adj } : o),
      };
    }));
  };

  const removeOption = (groupIndex: number, optIndex: number) => {
    setGroups(prev => prev.map((g, gi) => {
      if (gi !== groupIndex) return g;
      return { ...g, options: g.options.filter((_, oi) => oi !== optIndex) };
    }));
  };

  /* ─── Save ─── */
  const handleSave = async () => {
    if (fixedItems.length === 0 && groups.every(g => g.options.length === 0)) {
      setError('Agrega al menos un item fijo o grupo de selección');
      setTimeout(() => setError(''), 3000);
      return;
    }
    setSaving(true);
    setError('');
    try {
      const components: any[] = [];
      let sortOrder = 0;

      for (const fi of fixedItems) {
        components.push({
          child_product_id: fi.product_id,
          quantity: fi.quantity,
          is_fixed: true,
          selection_group: null,
          max_selections: 1,
          price_adjustment: 0,
          sort_order: sortOrder++,
        });
      }

      for (const g of groups) {
        for (const opt of g.options) {
          components.push({
            child_product_id: opt.product_id,
            quantity: 1,
            is_fixed: false,
            selection_group: g.name,
            max_selections: g.max_selections,
            price_adjustment: opt.price_adjustment,
            sort_order: sortOrder++,
          });
        }
      }

      await apiFetch(`/admin/combos/${combo.id}`, {
        method: 'POST',
        body: JSON.stringify({ components }),
      });

      setSuccessMsg('Combo guardado');
      setTimeout(() => setSuccessMsg(''), 3000);
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
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            className="rounded-lg border border-gray-200 p-2 hover:bg-gray-50 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
            aria-label="Volver a combos"
          >
            <ArrowLeft className="h-4 w-4" />
          </button>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">🍔 {combo.name}</h2>
            <div className="flex flex-wrap gap-2 mt-1 text-xs">
              <span className="text-gray-500">Precio: {formatCLP(combo.price)}</span>
              <span className="text-gray-500">Costo: {formatCLP(calculatedCost)}</span>
              <MarginBadge margin={calculatedMargin} />
            </div>
          </div>
        </div>
        <button
          onClick={handleSave}
          disabled={saving}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors min-h-[44px]',
            saving ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Guardar combo"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
          Guardar
        </button>
      </div>

      {/* Messages */}
      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {/* ── Fixed Items Section (T2.3) ── */}
      <section className="rounded-xl border bg-white shadow-sm" aria-label="Items fijos">
        <div className="border-b bg-gray-50 px-4 py-3">
          <h3 className="text-sm font-medium text-gray-700">✅ Items Fijos</h3>
        </div>
        <div className="p-4 space-y-3">
          <ProductAutocomplete
            onSelect={addFixedItem}
            excludeIds={fixedItems.map(fi => fi.product_id)}
            placeholder="Buscar producto fijo..."
          />

          {fixedItems.length === 0 ? (
            <p className="text-sm text-gray-400 text-center py-4">Sin items fijos. Usa el buscador para agregar.</p>
          ) : (
            <div className="space-y-2">
              {fixedItems.map((fi, idx) => (
                <div key={fi.product_id} className="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50/50 px-3 py-2">
                  <span className="flex-1 text-sm font-medium text-gray-900 truncate">{fi.product_name}</span>
                  <label className="sr-only" htmlFor={`fixed-qty-${fi.product_id}`}>Cantidad</label>
                  <input
                    id={`fixed-qty-${fi.product_id}`}
                    type="number"
                    value={fi.quantity}
                    onChange={e => updateFixedQty(idx, Number(e.target.value))}
                    min={1}
                    className="w-16 rounded-md border px-2 py-1 text-sm tabular-nums text-center focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px]"
                  />
                  <span className="text-xs text-gray-400">×</span>
                  <button
                    onClick={() => removeFixed(idx)}
                    className="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                    aria-label={`Eliminar ${fi.product_name}`}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ── Selection Groups Section (T2.3) ── */}
      <section className="space-y-3" aria-label="Grupos de selección">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-medium text-gray-700">🔄 Grupos de Selección</h3>
          <button
            onClick={addGroup}
            className="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors min-h-[36px]"
            aria-label="Nuevo grupo de selección"
          >
            <Plus className="h-3 w-3" /> Nuevo Grupo
          </button>
        </div>

        {groups.length === 0 ? (
          <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
            <p className="text-sm text-gray-400">Sin grupos de selección.</p>
          </div>
        ) : (
          groups.map((group, gi) => (
            <div key={gi} className="rounded-xl border bg-white shadow-sm">
              <div className="flex flex-col gap-2 border-b bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2 flex-1">
                  <label className="sr-only" htmlFor={`group-name-${gi}`}>Nombre del grupo</label>
                  <input
                    id={`group-name-${gi}`}
                    type="text"
                    value={group.name}
                    onChange={e => updateGroupName(gi, e.target.value)}
                    className="rounded-md border border-gray-200 px-2 py-1 text-sm font-medium focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px] flex-1 max-w-[200px]"
                    placeholder="Nombre del grupo"
                  />
                  <span className="text-xs text-gray-500 whitespace-nowrap">elegir</span>
                  <label className="sr-only" htmlFor={`group-max-${gi}`}>Máximo selecciones</label>
                  <input
                    id={`group-max-${gi}`}
                    type="number"
                    value={group.max_selections}
                    onChange={e => updateGroupMax(gi, Number(e.target.value))}
                    min={1}
                    className="w-14 rounded-md border border-gray-200 px-2 py-1 text-sm tabular-nums text-center focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px]"
                  />
                </div>
                <button
                  onClick={() => removeGroup(gi)}
                  className="text-xs text-red-500 hover:text-red-600 font-medium min-h-[36px] px-2"
                  aria-label={`Eliminar grupo ${group.name}`}
                >
                  <Trash2 className="inline h-3 w-3 mr-1" />Eliminar
                </button>
              </div>

              <div className="p-4 space-y-3">
                <ProductAutocomplete
                  onSelect={(p) => addOptionToGroup(gi, p)}
                  excludeIds={group.options.map(o => o.product_id)}
                  placeholder="Buscar opción..."
                />

                {group.options.length === 0 ? (
                  <p className="text-sm text-gray-400 text-center py-2">Sin opciones. Usa el buscador para agregar.</p>
                ) : (
                  <div className="space-y-2">
                    {group.options.map((opt, oi) => (
                      <div
                        key={opt.product_id}
                        className={cn(
                          'flex items-center gap-3 rounded-lg border px-3 py-2',
                          opt.is_active
                            ? 'border-gray-100 bg-gray-50/50'
                            : 'border-red-100 bg-red-50/30 opacity-60'
                        )}
                      >
                        {/* T2.4: availability badge */}
                        <span
                          className="flex-shrink-0 text-base"
                          title={opt.is_active ? 'Activo' : 'Inactivo'}
                          aria-label={opt.is_active ? 'Producto activo' : 'Producto inactivo'}
                        >
                          {opt.is_active ? '🟢' : '🔴'}
                        </span>
                        <span className={cn(
                          'flex-1 text-sm truncate',
                          opt.is_active ? 'font-medium text-gray-900' : 'text-gray-400 line-through'
                        )}>
                          {opt.product_name}
                          {!opt.is_active && (
                            <span className="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-600 no-underline inline-block">
                              INACTIVO
                            </span>
                          )}
                        </span>
                        <div className="flex items-center gap-1">
                          <span className="text-xs text-gray-400">+$</span>
                          <label className="sr-only" htmlFor={`adj-${gi}-${oi}`}>Ajuste de precio</label>
                          <input
                            id={`adj-${gi}-${oi}`}
                            type="number"
                            value={opt.price_adjustment}
                            onChange={e => updateOptionAdjustment(gi, oi, Number(e.target.value))}
                            min={0}
                            step={100}
                            className="w-20 rounded-md border px-2 py-1 text-sm tabular-nums text-right focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px]"
                          />
                        </div>
                        <button
                          onClick={() => removeOption(gi, oi)}
                          className="rounded-md p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors min-h-[36px] min-w-[36px] flex items-center justify-center"
                          aria-label={`Eliminar ${opt.product_name}`}
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ))
        )}
      </section>

      {/* ── Cost Summary (T2.5) ── */}
      {(fixedItems.length > 0 || groups.some(g => g.options.length > 0)) && (
        <div className="rounded-xl border bg-white p-4 shadow-sm space-y-2">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-500">Costo fijos</span>
            <span className="tabular-nums">{formatCLP(fixedItems.reduce((s, fi) => s + fi.cost_price * fi.quantity, 0))}</span>
          </div>
          {groups.filter(g => g.options.length > 0).map((g, i) => {
            const avg = g.options.reduce((s, o) => s + o.cost_price, 0) / g.options.length;
            return (
              <div key={i} className="flex items-center justify-between text-sm">
                <span className="text-gray-500">Prom. {g.name}</span>
                <span className="tabular-nums">{formatCLP(avg)}</span>
              </div>
            );
          })}
          <div className="border-t pt-2 flex items-center justify-between">
            <span className="text-sm font-medium text-gray-700">Costo total estimado</span>
            <div className="flex items-center gap-2">
              <span className="text-lg font-semibold tabular-nums">{formatCLP(calculatedCost)}</span>
              <MarginBadge margin={calculatedMargin} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


/* ─── Product Autocomplete ─── */

function ProductAutocomplete({
  onSelect,
  excludeIds,
  placeholder = 'Buscar producto...',
}: {
  onSelect: (product: ProductSearchResult) => void;
  excludeIds: number[];
  placeholder?: string;
}) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ProductSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [open, setOpen] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const containerRef = useRef<HTMLDivElement>(null);
  const inputId = useRef(`product-search-${Math.random().toString(36).slice(2, 8)}`).current;

  useEffect(() => {
    if (query.length < 2) { setResults([]); setOpen(false); return; }
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      setSearching(true);
      try {
        const res = await apiFetch<ApiResponse<any[]>>(`/admin/recetas?search=${encodeURIComponent(query)}`);
        const products = (res.data || [])
          .filter((p: any) => !excludeIds.includes(p.id))
          .slice(0, 15)
          .map((p: any) => ({
            id: p.id,
            name: p.name,
            price: Number(p.price) || 0,
            cost_price: Number(p.cost_price) || 0,
            image_url: p.image_url || null,
            is_active: p.is_active !== false && p.is_active !== 0,
          }));
        setResults(products);
        setOpen(products.length > 0);
      } catch {
        setResults([]);
      } finally {
        setSearching(false);
      }
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

  const handleSelect = (product: ProductSearchResult) => {
    onSelect(product);
    setQuery('');
    setResults([]);
    setOpen(false);
  };

  return (
    <div ref={containerRef} className="relative">
      <label htmlFor={inputId} className="sr-only">{placeholder}</label>
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          id={inputId}
          type="text"
          value={query}
          onChange={e => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
          placeholder={placeholder}
          className="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-10 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
          autoComplete="off"
          role="combobox"
          aria-expanded={open}
          aria-controls={`${inputId}-listbox`}
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
        <ul
          id={`${inputId}-listbox`}
          role="listbox"
          className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
        >
          {results.map(product => (
            <li
              key={product.id}
              role="option"
              aria-selected={false}
              className={cn(
                'flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 min-h-[44px]',
                !product.is_active && 'opacity-50'
              )}
              onClick={() => handleSelect(product)}
              onKeyDown={e => e.key === 'Enter' && handleSelect(product)}
              tabIndex={0}
            >
              <div className="flex items-center gap-2 min-w-0">
                {!product.is_active && <span className="text-xs">🔴</span>}
                <span className="font-medium text-gray-900 truncate">{product.name}</span>
              </div>
              <span className="text-xs text-gray-500 flex-shrink-0 ml-2">{formatCLP(product.price)}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
