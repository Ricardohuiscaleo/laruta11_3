'use client';

import { useEffect, useState, useMemo, useCallback } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Pencil, Check, X, Search } from 'lucide-react';
import { getIngredientEmoji } from '@/lib/ingredient-emoji';
import type { ApiResponse } from '@/types';

interface PortionRow {
  id: number;
  category_id: number;
  category_name: string;
  ingredient_id: number;
  ingredient_name: string;
  ingredient_unit: string;
  cost_per_unit: number;
  quantity: number;
  unit: string;
}

export default function PorcionesPage() {
  const [rows, setRows] = useState<PortionRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editingCat, setEditingCat] = useState<number | null>(null);
  const [draft, setDraft] = useState<PortionRow[]>([]);
  const [saving, setSaving] = useState(false);
  const [successMsg, setSuccessMsg] = useState('');
  const [search, setSearch] = useState('');
  const fetchAll = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<ApiResponse<PortionRow[]>>('/admin/portions');
      setRows(res.data || []);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  const grouped = useMemo(() => {
    const q = search.toLowerCase();
    const filtered = q ? rows.filter(r => r.ingredient_name.toLowerCase().includes(q) || r.category_name.toLowerCase().includes(q)) : rows;
    const map = new Map<number, { name: string; items: PortionRow[] }>();
    for (const r of filtered) {
      if (!map.has(r.category_id)) map.set(r.category_id, { name: r.category_name, items: [] });
      map.get(r.category_id)!.items.push(r);
    }
    return Array.from(map.entries()).sort((a, b) => a[1].name.localeCompare(b[1].name, 'es'));
  }, [rows, search]);

  const startEdit = (catId: number) => {
    setEditingCat(catId);
    setDraft(rows.filter(r => r.category_id === catId).map(r => ({ ...r })));
  };

  const cancelEdit = () => { setEditingCat(null); setDraft([]); };

  const updateDraft = (index: number, field: 'quantity' | 'unit', value: number | string) => {
    setDraft(prev => prev.map((r, i) => i === index ? { ...r, [field]: value } : r));
  };

  const handleSave = async () => {
    if (!editingCat || draft.length === 0) return;
    setSaving(true);
    setError('');
    try {
      await apiFetch(`/admin/portions/${editingCat}`, {
        method: 'PUT',
        body: JSON.stringify({
          portions: draft.map(d => ({ ingredient_id: d.ingredient_id, quantity: d.quantity, unit: d.unit })),
        }),
      });
      setSuccessMsg('Porciones guardadas');
      setTimeout(() => setSuccessMsg(''), 3000);
      setEditingCat(null);
      await fetchAll();
    } catch (e: any) {
      setError(e.message || 'Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16" role="status" aria-label="Cargando porciones">
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
            placeholder="Buscar ingrediente..."
            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
            aria-label="Buscar ingrediente"
          />
        </div>
      </div>

      <p className="text-sm text-gray-500">
        Porciones estándar por categoría. Al agregar un ingrediente a una receta, se sugiere la porción de su categoría.
      </p>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {grouped.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-400">
          No hay porciones estándar configuradas.
        </div>
      ) : (
        grouped.map(([catId, { name, items }]) => {
          const isEditing = editingCat === catId;
          const displayItems = isEditing ? draft : items;
          return (
            <section key={catId} className="rounded-xl border bg-white shadow-sm" aria-label={`Porciones ${name}`}>
              <div className="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                <h3 className="text-sm font-medium text-gray-700">{name}</h3>
                {isEditing ? (
                  <div className="flex gap-2">
                    <button onClick={handleSave} disabled={saving} className="inline-flex items-center gap-1 rounded-md bg-red-500 px-3 py-1 text-xs font-medium text-white hover:bg-red-600 min-h-[32px]">
                      {saving ? <Loader2 className="h-3 w-3 animate-spin" /> : <Check className="h-3 w-3" />} Guardar
                    </button>
                    <button onClick={cancelEdit} className="rounded-md border px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 min-h-[32px]">
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ) : (
                  <button onClick={() => startEdit(catId)} className="text-xs text-red-500 hover:text-red-600 font-medium">
                    <Pencil className="inline h-3 w-3 mr-1" />Editar
                  </button>
                )}
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b bg-gray-50/50 text-left text-xs font-medium text-gray-500">
                    <tr>
                      <th className="px-4 py-2">Ingrediente</th>
                      <th className="px-4 py-2">Porción</th>
                      <th className="px-4 py-2">Unidad</th>
                      <th className="px-4 py-2 text-right hidden sm:table-cell">Costo/u</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {displayItems.map((r, idx) => (
                      <tr key={r.ingredient_id} className="hover:bg-gray-50">
                        <td className="px-4 py-2 font-medium text-gray-900">
                          {getIngredientEmoji(r.ingredient_name)} {r.ingredient_name}
                        </td>
                        <td className="px-4 py-2">
                          {isEditing ? (
                            <input type="number" value={r.quantity || ''} onChange={e => updateDraft(idx, 'quantity', Number(e.target.value))} min={0} step="any"
                              className="w-20 rounded-md border px-2 py-1 text-sm tabular-nums focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px]"
                              aria-label={`Porción de ${r.ingredient_name}`} />
                          ) : <span className="tabular-nums">{r.quantity}</span>}
                        </td>
                        <td className="px-4 py-2">
                          {isEditing ? (
                            <select value={r.unit} onChange={e => updateDraft(idx, 'unit', e.target.value)}
                              className="rounded-md border px-2 py-1 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[36px]"
                              aria-label={`Unidad de ${r.ingredient_name}`}>
                              {['g', 'kg', 'ml', 'L', 'unidad'].map(u => <option key={u} value={u}>{u}</option>)}
                            </select>
                          ) : <span className="text-gray-500">{r.unit}</span>}
                        </td>
                        <td className="px-4 py-2 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                          {formatCLP(r.cost_per_unit)}/{r.ingredient_unit}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          );
        })
      )}
    </div>
  );
}
