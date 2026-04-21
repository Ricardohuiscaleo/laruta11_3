'use client';

import { useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, Sparkles, ChevronDown, AlertTriangle, Check,
  ShoppingCart, Save, RefreshCw, Star,
} from 'lucide-react';
import { getIngredientEmoji } from '@/lib/ingredient-emoji';
import type { ApiResponse } from '@/types';

interface SuggestedIngredient {
  ingredient_id: number;
  name: string;
  quantity: number;
  unit: string;
  cost_per_unit?: number;
  ingredient_unit?: string;
  estimated_cost?: number;
  in_stock?: boolean;
}

interface RecipeVariant {
  name: string;
  description: string;
  style: string;
  suggested_price: number;
  total_cost: number;
  margin: number;
  ingredients: SuggestedIngredient[];
  missing_ingredients?: string[];
  tips?: string;
}

const CATEGORIES = [
  { id: 3, name: 'Hamburguesas' },
  { id: 2, name: 'Sandwiches' },
  { id: 4, name: 'Completos' },
  { id: 12, name: 'Papas' },
  { id: 8, name: 'Combos' },
  { id: 5, name: 'Snacks' },
];

export default function CreadorIAPage() {
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState<number | ''>('');
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [variants, setVariants] = useState<RecipeVariant[]>([]);
  const [selectedIdx, setSelectedIdx] = useState<number | null>(null);

  const handleGenerate = async () => {
    if (!description.trim()) return;
    setLoading(true);
    setError('');
    setSuccessMsg('');
    setVariants([]);
    setSelectedIdx(null);
    try {
      const res = await apiFetch<ApiResponse<{ variants: RecipeVariant[] }>>('/admin/portions/suggest-recipe', {
        method: 'POST',
        body: JSON.stringify({ description: description.trim(), category_id: categoryId || null }),
      });
      setVariants(res.data?.variants || []);
    } catch (e: any) {
      setError(e.message || 'Error al generar recetas');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    if (selectedIdx === null || !categoryId) return;
    const variant = variants[selectedIdx];
    setSaving(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<{ product_id: number; name: string }>>('/admin/portions/save-variant', {
        method: 'POST',
        body: JSON.stringify({ category_id: categoryId, variant }),
      });
      setSuccessMsg(`✅ "${res.data?.name}" guardado como producto #${res.data?.product_id} (inactivo, actívalo en el menú)`);
      setVariants([]);
      setSelectedIdx(null);
      setDescription('');
    } catch (e: any) {
      setError(e.message || 'Error al guardar');
    } finally {
      setSaving(false);
    }
  };

  const getStyleLabel = (v: RecipeVariant, idx: number) => v.style || (idx === 0 ? 'Clásica' : idx === 1 ? 'Premium' : 'Creativa');
  const getStyleBadge = (style: string) => {
    if (style.includes('lásica') || style.includes('stándar')) return 'bg-blue-100 text-blue-700';
    if (style.includes('remium') || style.includes('special')) return 'bg-amber-100 text-amber-700';
    return 'bg-purple-100 text-purple-700';
  };

  return (
    <div className="space-y-4">
      <section className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium text-gray-700">
          <Sparkles className="h-4 w-4 text-amber-500" />
          ¿Qué producto quieres crear?
        </div>
        <div className="flex flex-col gap-3 sm:flex-row">
          <input
            type="text"
            value={description}
            onChange={e => setDescription(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && !loading && handleGenerate()}
            placeholder="Ej: bandeja para compartir, chacarero, combo familiar..."
            className="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
            aria-label="Descripción del producto"
          />
          <div className="relative">
            <select
              value={categoryId}
              onChange={e => setCategoryId(e.target.value === '' ? '' : Number(e.target.value))}
              className="appearance-none rounded-lg border border-gray-200 bg-white py-2.5 pl-3 pr-8 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300 min-h-[44px]"
              aria-label="Categoría"
            >
              <option value="">Categoría</option>
              {CATEGORIES.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          </div>
          <button
            onClick={handleGenerate}
            disabled={loading || !description.trim()}
            className={cn(
              'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium text-white transition-colors min-h-[44px] whitespace-nowrap',
              loading || !description.trim() ? 'bg-gray-400 cursor-not-allowed' : 'bg-amber-500 hover:bg-amber-600'
            )}
          >
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : variants.length > 0 ? <RefreshCw className="h-4 w-4" /> : <Sparkles className="h-4 w-4" />}
            {loading ? 'Generando...' : variants.length > 0 ? 'Regenerar' : 'Generar 3 Variantes'}
          </button>
        </div>
      </section>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}
      {successMsg && <div className="rounded-lg bg-green-50 p-3 text-sm text-green-600" role="status">{successMsg}</div>}

      {variants.length > 0 && (
        <>
          <div className="text-xs text-gray-500">Selecciona una variante para ver detalle y guardar como producto</div>
          <div className="grid gap-4 sm:grid-cols-3">
            {variants.map((v, idx) => {
              const isSelected = selectedIdx === idx;
              const style = getStyleLabel(v, idx);
              return (
                <button
                  key={idx}
                  onClick={() => setSelectedIdx(isSelected ? null : idx)}
                  className={cn(
                    'rounded-xl border-2 bg-white p-4 text-left shadow-sm transition-all hover:shadow-md',
                    isSelected ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-200'
                  )}
                  aria-label={`Variante: ${v.name}`}
                  aria-pressed={isSelected}
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                      <span className={cn('inline-block rounded-full px-2 py-0.5 text-[10px] font-medium mb-1', getStyleBadge(style))}>
                        {style}
                      </span>
                      <h4 className="text-sm font-semibold text-gray-900 leading-tight">{v.name}</h4>
                      <p className="text-xs text-gray-500 mt-0.5">{v.description}</p>
                    </div>
                    {isSelected && <Check className="h-5 w-5 text-red-500 flex-shrink-0" />}
                  </div>
                  <div className="mt-3 flex items-center justify-between">
                    <span className="text-lg font-bold tabular-nums text-green-600">{formatCLP(v.suggested_price)}</span>
                    <div className="text-right">
                      <div className="text-[10px] text-gray-400">Costo {formatCLP(v.total_cost)}</div>
                      <span className={cn(
                        'inline-block rounded-full px-1.5 py-0.5 text-[10px] font-medium',
                        v.margin >= 65 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                      )}>
                        {v.margin}%
                      </span>
                    </div>
                  </div>
                  <div className="mt-2 text-[10px] text-gray-400">
                    {v.ingredients.length} ingredientes
                    {v.missing_ingredients && v.missing_ingredients.length > 0 && (
                      <span className="text-amber-500 ml-1">· {v.missing_ingredients.length} faltantes</span>
                    )}
                  </div>
                </button>
              );
            })}
          </div>
        </>
      )}

      {selectedIdx !== null && variants[selectedIdx] && (
        <div className="space-y-4">
          <section className="rounded-xl border bg-white shadow-sm">
            <div className="border-b bg-gray-50 px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <h4 className="text-sm font-medium text-gray-700">{variants[selectedIdx].name} — Ingredientes</h4>
              <button
                onClick={handleSave}
                disabled={saving || !categoryId}
                className={cn(
                  'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-medium text-white transition-colors min-h-[36px]',
                  saving || !categoryId ? 'bg-gray-400 cursor-not-allowed' : 'bg-red-500 hover:bg-red-600'
                )}
              >
                {saving ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
                Guardar como Producto
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b bg-gray-50/50 text-left text-xs font-medium text-gray-500">
                  <tr>
                    <th className="px-4 py-2">Ingrediente</th>
                    <th className="px-4 py-2">Cantidad</th>
                    <th className="px-4 py-2 text-right hidden sm:table-cell">Costo</th>
                    <th className="px-4 py-2 text-right">Stock</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {variants[selectedIdx].ingredients.map(ing => (
                    <tr key={ing.ingredient_id} className={cn('hover:bg-gray-50', ing.in_stock === false && 'bg-red-50/50')}>
                      <td className="px-4 py-2 font-medium text-gray-900">
                        {getIngredientEmoji(ing.name)} {ing.name}
                      </td>
                      <td className="px-4 py-2 tabular-nums">{ing.quantity} {ing.unit}</td>
                      <td className="px-4 py-2 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                        {ing.estimated_cost != null ? formatCLP(ing.estimated_cost) : '—'}
                      </td>
                      <td className="px-4 py-2 text-right">
                        {ing.in_stock !== false ? (
                          <Check className="inline h-4 w-4 text-green-500" />
                        ) : (
                          <span className="inline-flex items-center gap-1 text-xs text-red-500">
                            <AlertTriangle className="h-3 w-3" /> Sin stock
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          {variants[selectedIdx].tips && (
            <section className="rounded-xl border bg-white p-4 shadow-sm">
              <h4 className="text-xs font-medium text-gray-500">💡 Tips</h4>
              <p className="text-sm text-gray-600 mt-1">{variants[selectedIdx].tips}</p>
            </section>
          )}

          {!categoryId && (
            <div className="rounded-lg bg-amber-50 p-3 text-sm text-amber-600" role="alert">
              Selecciona una categoría arriba para poder guardar el producto.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
