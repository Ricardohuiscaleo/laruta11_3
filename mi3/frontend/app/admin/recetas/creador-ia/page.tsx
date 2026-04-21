'use client';

import { useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { Loader2, Sparkles, ChevronDown, AlertTriangle, Check, ShoppingCart } from 'lucide-react';
import { getIngredientEmoji } from '@/lib/ingredient-emoji';
import type { ApiResponse } from '@/types';

interface SuggestedIngredient {
  ingredient_id: number;
  name: string;
  quantity: number;
  unit: string;
  reason?: string;
  cost_per_unit?: number;
  ingredient_unit?: string;
  estimated_cost?: number;
  in_stock?: boolean;
}

interface RecipeSuggestion {
  name: string;
  description: string;
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
  const [error, setError] = useState('');
  const [result, setResult] = useState<RecipeSuggestion | null>(null);

  const handleGenerate = async () => {
    if (!description.trim()) return;
    setLoading(true);
    setError('');
    setResult(null);
    try {
      const res = await apiFetch<ApiResponse<RecipeSuggestion>>('/admin/portions/suggest-recipe', {
        method: 'POST',
        body: JSON.stringify({ description: description.trim(), category_id: categoryId || null }),
      });
      setResult(res.data || null);
    } catch (e: any) {
      setError(e.message || 'Error al generar receta');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <section className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
        <div className="flex items-center gap-2 text-sm font-medium text-gray-700">
          <Sparkles className="h-4 w-4 text-amber-500" />
          Describe el producto que quieres crear
        </div>
        <div className="flex flex-col gap-3 sm:flex-row">
          <input
            type="text"
            value={description}
            onChange={e => setDescription(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && !loading && handleGenerate()}
            placeholder="Ej: Chacarero con porotos verdes y ají verde..."
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
              <option value="">Categoría (opcional)</option>
              {CATEGORIES.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <ChevronDown className="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          </div>
          <button
            onClick={handleGenerate}
            disabled={loading || !description.trim()}
            className={cn(
              'inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium text-white transition-colors min-h-[44px]',
              loading || !description.trim() ? 'bg-gray-400 cursor-not-allowed' : 'bg-amber-500 hover:bg-amber-600'
            )}
          >
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
            {loading ? 'Generando...' : 'Generar Receta'}
          </button>
        </div>
      </section>

      {error && <div className="rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">{error}</div>}

      {result && (
        <div className="space-y-4">
          <section className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">{result.name}</h3>
                <p className="text-sm text-gray-500">{result.description}</p>
              </div>
              <div className="flex items-center gap-3">
                <div className="text-right">
                  <div className="text-xs text-gray-400">Costo</div>
                  <div className="text-sm font-medium tabular-nums">{formatCLP(result.total_cost)}</div>
                </div>
                <div className="text-right">
                  <div className="text-xs text-gray-400">Precio sugerido</div>
                  <div className="text-lg font-bold tabular-nums text-green-600">{formatCLP(result.suggested_price)}</div>
                </div>
                <span className={cn(
                  'rounded-full px-2.5 py-1 text-xs font-medium',
                  result.margin >= 65 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'
                )}>
                  {result.margin}%
                </span>
              </div>
            </div>
          </section>

          <section className="rounded-xl border bg-white shadow-sm">
            <div className="border-b bg-gray-50 px-4 py-3">
              <h4 className="text-sm font-medium text-gray-700">Ingredientes sugeridos</h4>
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
                  {result.ingredients.map(ing => (
                    <tr key={ing.ingredient_id} className={cn('hover:bg-gray-50', !ing.in_stock && 'bg-red-50/50')}>
                      <td className="px-4 py-2 font-medium text-gray-900">
                        {getIngredientEmoji(ing.name)} {ing.name}
                        {ing.reason && <span className="ml-2 text-xs text-gray-400">({ing.reason})</span>}
                      </td>
                      <td className="px-4 py-2 tabular-nums">{ing.quantity} {ing.unit}</td>
                      <td className="px-4 py-2 text-right tabular-nums text-gray-500 hidden sm:table-cell">
                        {ing.estimated_cost != null ? formatCLP(ing.estimated_cost) : '—'}
                      </td>
                      <td className="px-4 py-2 text-right">
                        {ing.in_stock ? (
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

          {(result.missing_ingredients?.length || result.tips) && (
            <section className="rounded-xl border bg-white p-4 shadow-sm space-y-2">
              {result.missing_ingredients && result.missing_ingredients.length > 0 && (
                <div>
                  <h4 className="text-xs font-medium text-amber-600 flex items-center gap-1">
                    <ShoppingCart className="h-3 w-3" /> Ingredientes que faltan
                  </h4>
                  <p className="text-sm text-gray-600 mt-1">{result.missing_ingredients.join(', ')}</p>
                </div>
              )}
              {result.tips && (
                <div>
                  <h4 className="text-xs font-medium text-gray-500">💡 Tips del chef</h4>
                  <p className="text-sm text-gray-600 mt-1">{result.tips}</p>
                </div>
              )}
            </section>
          )}
        </div>
      )}
    </div>
  );
}
