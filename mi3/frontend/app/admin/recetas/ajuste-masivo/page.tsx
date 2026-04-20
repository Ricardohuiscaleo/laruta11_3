'use client';

import { useState, useEffect, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, ArrowRight, Check, AlertTriangle, Search, X,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface IngredientOption {
  id: number;
  name: string;
  unit: string;
  cost_per_unit: number;
  category?: string;
}

interface AffectedProduct {
  product_id: number;
  product_name: string;
  quantity: number;
  unit: string;
  target_exists: boolean;
}

interface PreviewData {
  source: { id: number; name: string; unit: string; cost_per_unit: number };
  target: { id: number; name: string; unit: string; cost_per_unit: number };
  affected_products: AffectedProduct[];
}

type Step = 'select' | 'preview' | 'success';

/* ─── Main Component ─── */

export default function ReemplazoMasivoPage() {
  const [ingredients, setIngredients] = useState<IngredientOption[]>([]);
  const [sourceId, setSourceId] = useState<number | null>(null);
  const [targetId, setTargetId] = useState<number | null>(null);

  const [preview, setPreview] = useState<PreviewData | null>(null);
  const [step, setStep] = useState<Step>('select');
  const [loading, setLoading] = useState(false);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<{ products_affected: number; cost_prices_updated: number } | null>(null);

  /* Fetch all ingredients */
  useEffect(() => {
    apiFetch<{ success: boolean; items: IngredientOption[] }>('/admin/stock')
      .then(res => {
        const items = (res.items || []).sort((a, b) => a.name.localeCompare(b.name, 'es'));
        setIngredients(items);
      })
      .catch(() => {});
  }, []);

  const sourceName = useMemo(() => ingredients.find(i => i.id === sourceId)?.name ?? '', [ingredients, sourceId]);
  const targetName = useMemo(() => ingredients.find(i => i.id === targetId)?.name ?? '', [ingredients, targetId]);

  const canPreview = sourceId !== null && targetId !== null && sourceId !== targetId;

  /* Preview */
  const handlePreview = async () => {
    if (!canPreview) return;
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<PreviewData>>(
        '/admin/recetas/replace-ingredient/preview',
        { method: 'POST', body: JSON.stringify({ source_id: sourceId, target_id: targetId }) }
      );
      setPreview(res.data || null);
      setStep('preview');
    } catch (e: any) {
      setError(e.message || 'Error al obtener vista previa');
    } finally {
      setLoading(false);
    }
  };

  /* Apply */
  const handleApply = async () => {
    setApplying(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<{ products_affected: number; cost_prices_updated: number }>>(
        '/admin/recetas/replace-ingredient',
        { method: 'POST', body: JSON.stringify({ source_id: sourceId, target_id: targetId }) }
      );
      setResult(res.data || null);
      setStep('success');
    } catch (e: any) {
      setError(e.message || 'Error al aplicar reemplazo');
    } finally {
      setApplying(false);
    }
  };

  /* Reset */
  const handleReset = () => {
    setSourceId(null);
    setTargetId(null);
    setPreview(null);
    setError('');
    setResult(null);
    setStep('select');
  };

  return (
    <div className="space-y-4">
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {step === 'select' && (
        <SelectStep
          ingredients={ingredients}
          sourceId={sourceId}
          targetId={targetId}
          onSourceChange={setSourceId}
          onTargetChange={setTargetId}
          canPreview={canPreview}
          loading={loading}
          onPreview={handlePreview}
        />
      )}

      {step === 'preview' && preview && (
        <PreviewStep
          preview={preview}
          sourceName={sourceName}
          targetName={targetName}
          applying={applying}
          onConfirm={handleApply}
          onCancel={() => setStep('select')}
        />
      )}

      {step === 'success' && result && (
        <SuccessStep
          result={result}
          sourceName={sourceName}
          targetName={targetName}
          onReset={handleReset}
        />
      )}
    </div>
  );
}


/* ─── Autocomplete Ingredient Picker ─── */

function IngredientPicker({
  label,
  ingredients,
  selectedId,
  excludeId,
  onChange,
}: {
  label: string;
  ingredients: IngredientOption[];
  selectedId: number | null;
  excludeId: number | null;
  onChange: (id: number | null) => void;
}) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);

  const filtered = useMemo(() => {
    const q = query.toLowerCase().trim();
    return ingredients
      .filter(i => i.id !== excludeId)
      .filter(i => !q || i.name.toLowerCase().includes(q))
      .slice(0, 20);
  }, [ingredients, query, excludeId]);

  const selected = ingredients.find(i => i.id === selectedId);

  return (
    <div className="space-y-1.5">
      <label className="text-sm font-medium text-gray-700">{label}</label>
      <div className="relative">
        {selected && !open ? (
          <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2.5">
            <span className="flex-1 text-sm font-medium text-gray-900 truncate">{selected.name}</span>
            <span className="text-xs text-gray-400">{formatCLP(selected.cost_per_unit)}/{selected.unit}</span>
            <button
              type="button"
              onClick={() => { onChange(null); setQuery(''); }}
              className="p-0.5 rounded hover:bg-gray-100 min-h-[28px] min-w-[28px] flex items-center justify-center"
              aria-label={`Quitar ${selected.name}`}
            >
              <X className="h-3.5 w-3.5 text-gray-400" />
            </button>
          </div>
        ) : (
          <>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                value={query}
                onChange={e => { setQuery(e.target.value); setOpen(true); }}
                onFocus={() => setOpen(true)}
                placeholder="Buscar ingrediente..."
                className="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                aria-label={label}
              />
            </div>
            {open && (
              <ul
                className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                role="listbox"
                aria-label={`Opciones de ${label}`}
              >
                {filtered.length === 0 ? (
                  <li className="px-3 py-2 text-sm text-gray-400">Sin resultados</li>
                ) : (
                  filtered.map(ing => (
                    <li key={ing.id}>
                      <button
                        type="button"
                        onClick={() => { onChange(ing.id); setQuery(''); setOpen(false); }}
                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 min-h-[40px]"
                        role="option"
                        aria-selected={ing.id === selectedId}
                      >
                        <span className="flex-1 truncate">{ing.name}</span>
                        <span className="text-xs text-gray-400 shrink-0">{formatCLP(ing.cost_per_unit)}/{ing.unit}</span>
                      </button>
                    </li>
                  ))
                )}
              </ul>
            )}
          </>
        )}
      </div>
    </div>
  );
}


/* ─── Select Step ─── */

function SelectStep({
  ingredients,
  sourceId,
  targetId,
  onSourceChange,
  onTargetChange,
  canPreview,
  loading,
  onPreview,
}: {
  ingredients: IngredientOption[];
  sourceId: number | null;
  targetId: number | null;
  onSourceChange: (id: number | null) => void;
  onTargetChange: (id: number | null) => void;
  canPreview: boolean;
  loading: boolean;
  onPreview: () => void;
}) {
  return (
    <div className="rounded-xl border bg-white p-4 shadow-sm space-y-5">
      <div>
        <h2 className="text-base font-semibold text-gray-900">Reemplazo Masivo de Ingredientes</h2>
        <p className="mt-1 text-sm text-gray-500">
          Reemplaza un ingrediente por otro en todas las recetas que lo usen. Los costos se recalculan automáticamente.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
        <IngredientPicker
          label="Ingrediente actual"
          ingredients={ingredients}
          selectedId={sourceId}
          excludeId={targetId}
          onChange={onSourceChange}
        />

        <div className="hidden sm:flex items-center justify-center pb-1">
          <ArrowRight className="h-5 w-5 text-gray-300" />
        </div>

        <IngredientPicker
          label="Reemplazar por"
          ingredients={ingredients}
          selectedId={targetId}
          excludeId={sourceId}
          onChange={onTargetChange}
        />
      </div>

      <div className="flex justify-end pt-2">
        <button
          onClick={onPreview}
          disabled={!canPreview || loading}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors min-h-[44px]',
            !canPreview || loading
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Ver productos afectados"
        >
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
          Ver afectados
        </button>
      </div>
    </div>
  );
}


/* ─── Preview Step ─── */

function PreviewStep({
  preview,
  sourceName,
  targetName,
  applying,
  onConfirm,
  onCancel,
}: {
  preview: PreviewData;
  sourceName: string;
  targetName: string;
  applying: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const products = preview.affected_products;
  const hasConflicts = products.some(p => p.target_exists);

  return (
    <div className="space-y-4">
      {/* Summary */}
      <div className="rounded-xl border bg-white p-4 shadow-sm space-y-3">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
          <div className="flex-1 rounded-lg bg-red-50 px-3 py-2">
            <p className="text-xs text-red-500">Quitar</p>
            <p className="text-sm font-semibold text-red-700 truncate">{sourceName}</p>
            <p className="text-xs text-red-400">{formatCLP(preview.source.cost_per_unit)}/{preview.source.unit}</p>
          </div>
          <ArrowRight className="h-5 w-5 text-gray-300 shrink-0 hidden sm:block" />
          <div className="flex-1 rounded-lg bg-green-50 px-3 py-2">
            <p className="text-xs text-green-500">Poner</p>
            <p className="text-sm font-semibold text-green-700 truncate">{targetName}</p>
            <p className="text-xs text-green-400">{formatCLP(preview.target.cost_per_unit)}/{preview.target.unit}</p>
          </div>
        </div>

        <p className="text-sm text-gray-600">
          Cambiarás <span className="font-semibold">{sourceName}</span> → <span className="font-semibold">{targetName}</span>,
          afectando <span className="font-semibold text-red-600">{products.length} producto{products.length !== 1 ? 's' : ''}</span>.
          ¿Confirmas?
        </p>
      </div>

      {/* Conflict warning */}
      {hasConflicts && (
        <div className="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-700" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
          <span>Algunos productos ya tienen el ingrediente destino. En esos casos se eliminará el ingrediente origen sin duplicar.</span>
        </div>
      )}

      {/* Products list */}
      {products.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
          Este ingrediente no se usa en ninguna receta activa.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
              <tr>
                <th className="px-4 py-3">Producto</th>
                <th className="px-4 py-3 text-right">Cantidad</th>
                <th className="px-4 py-3 text-right hidden sm:table-cell">Unidad</th>
                <th className="px-4 py-3 text-center">Estado</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {products.map(p => (
                <tr key={p.product_id} className={cn('transition-colors', p.target_exists ? 'bg-amber-50' : 'hover:bg-gray-50')}>
                  <td className="px-4 py-3 font-medium text-gray-900">{p.product_name}</td>
                  <td className="px-4 py-3 text-right tabular-nums text-gray-500">{p.quantity}</td>
                  <td className="px-4 py-3 text-right text-gray-500 hidden sm:table-cell">{p.unit}</td>
                  <td className="px-4 py-3 text-center">
                    {p.target_exists ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
                        <AlertTriangle className="h-3 w-3" /> Duplicado
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">
                        <Check className="h-3 w-3" /> OK
                      </span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Actions */}
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button
          onClick={onCancel}
          disabled={applying}
          className="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 min-h-[44px]"
          aria-label="Cancelar"
        >
          Cancelar
        </button>
        <button
          onClick={onConfirm}
          disabled={applying || products.length === 0}
          className={cn(
            'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors min-h-[44px]',
            applying || products.length === 0
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Confirmar reemplazo"
        >
          {applying ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          Confirmar reemplazo
        </button>
      </div>
    </div>
  );
}


/* ─── Success Step ─── */

function SuccessStep({
  result,
  sourceName,
  targetName,
  onReset,
}: {
  result: { products_affected: number; cost_prices_updated: number };
  sourceName: string;
  targetName: string;
  onReset: () => void;
}) {
  return (
    <div className="rounded-xl border bg-white p-6 shadow-sm text-center space-y-4">
      <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
        <Check className="h-6 w-6 text-green-600" />
      </div>
      <div>
        <h2 className="text-base font-semibold text-gray-900">Reemplazo completado</h2>
        <p className="mt-1 text-sm text-gray-500">
          <span className="font-medium">{sourceName}</span> → <span className="font-medium">{targetName}</span>
          {' '}en {result.products_affected} producto{result.products_affected !== 1 ? 's' : ''}.
          Se recalcularon {result.cost_prices_updated} costo{result.cost_prices_updated !== 1 ? 's' : ''}.
        </p>
      </div>
      <button
        onClick={onReset}
        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 min-h-[44px]"
        aria-label="Hacer otro reemplazo"
      >
        Nuevo reemplazo
      </button>
    </div>
  );
}
