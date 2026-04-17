'use client';

import { useState, useEffect, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import {
  Loader2, ArrowLeft, Eye, Check, AlertTriangle, DollarSign, Percent,
} from 'lucide-react';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

type ScopeType = 'all' | 'category' | 'supplier';
type AdjustmentType = 'percentage' | 'fixed';

interface PreviewItem {
  id: number;
  name: string;
  current_cost: number;
  proposed_cost: number;
  recipe_count: number;
}

interface StockItem {
  id: number;
  name: string;
  category?: string;
  supplier?: string;
}

/* ─── Steps ─── */

type Step = 'form' | 'preview' | 'success';

/* ─── Main Component ─── */

export default function AjusteMasivoPage() {
  /* Form state */
  const [scopeType, setScopeType] = useState<ScopeType>('all');
  const [scopeValue, setScopeValue] = useState('');
  const [adjustmentType, setAdjustmentType] = useState<AdjustmentType>('percentage');
  const [value, setValue] = useState<string>('');

  /* Data */
  const [categories, setCategories] = useState<string[]>([]);
  const [suppliers, setSuppliers] = useState<string[]>([]);
  const [preview, setPreview] = useState<PreviewItem[]>([]);

  /* UI state */
  const [step, setStep] = useState<Step>('form');
  const [loading, setLoading] = useState(false);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<{ ingredients_affected: number; products_affected: number } | null>(null);

  /* Fetch categories and suppliers from stock endpoint */
  useEffect(() => {
    apiFetch<{ success: boolean; items: StockItem[] }>('/admin/stock')
      .then(res => {
        const items = res.items || [];
        const cats = new Set<string>();
        const sups = new Set<string>();
        for (const item of items) {
          if (item.category) cats.add(item.category);
          if (item.supplier) sups.add(item.supplier);
        }
        setCategories(Array.from(cats).sort((a, b) => a.localeCompare(b, 'es')));
        setSuppliers(Array.from(sups).sort((a, b) => a.localeCompare(b, 'es')));
      })
      .catch(() => { /* silently fail — dropdowns will be empty */ });
  }, []);

  /* Build scope string for API */
  const scopeString = useMemo(() => {
    if (scopeType === 'all') return 'all';
    if (scopeType === 'category') return `category:${scopeValue}`;
    return `supplier:${scopeValue}`;
  }, [scopeType, scopeValue]);

  /* Reset scope value when scope type changes */
  useEffect(() => {
    setScopeValue('');
  }, [scopeType]);

  const numericValue = parseFloat(value);
  const isFormValid =
    !isNaN(numericValue) &&
    numericValue !== 0 &&
    (scopeType === 'all' || scopeValue !== '');

  /* ── Preview ── */
  const handlePreview = async () => {
    if (!isFormValid) return;
    setLoading(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<PreviewItem[]>>(
        '/admin/recetas/bulk-adjustment/preview',
        {
          method: 'POST',
          body: JSON.stringify({
            scope: scopeString,
            type: adjustmentType,
            value: numericValue,
          }),
        }
      );
      setPreview(res.data || []);
      setStep('preview');
    } catch (e: any) {
      setError(e.message || 'Error al obtener vista previa');
    } finally {
      setLoading(false);
    }
  };

  /* ── Apply ── */
  const handleApply = async () => {
    setApplying(true);
    setError('');
    try {
      const res = await apiFetch<ApiResponse<{ ingredients_affected: number; products_affected: number }>>(
        '/admin/recetas/bulk-adjustment',
        {
          method: 'POST',
          body: JSON.stringify({
            scope: scopeString,
            type: adjustmentType,
            value: numericValue,
          }),
        }
      );
      setResult(res.data || null);
      setStep('success');
    } catch (e: any) {
      setError(e.message || 'Error al aplicar ajuste');
    } finally {
      setApplying(false);
    }
  };

  /* ── Reset ── */
  const handleReset = () => {
    setScopeType('all');
    setScopeValue('');
    setAdjustmentType('percentage');
    setValue('');
    setPreview([]);
    setError('');
    setResult(null);
    setStep('form');
  };

  /* ── Has negative proposed costs ── */
  const hasNegative = preview.some(i => i.proposed_cost < 0);

  /* Total impacted recipes from preview items */
  const totalImpactedRecipes = useMemo(() => {
    return preview.reduce((sum, i) => sum + i.recipe_count, 0);
  }, [preview]);

  return (
    <div className="space-y-4">
      {/* Error banner */}
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {step === 'form' && (
        <FormStep
          scopeType={scopeType}
          setScopeType={setScopeType}
          scopeValue={scopeValue}
          setScopeValue={setScopeValue}
          adjustmentType={adjustmentType}
          setAdjustmentType={setAdjustmentType}
          value={value}
          setValue={setValue}
          categories={categories}
          suppliers={suppliers}
          isFormValid={isFormValid}
          loading={loading}
          onPreview={handlePreview}
        />
      )}

      {step === 'preview' && (
        <PreviewStep
          preview={preview}
          adjustmentType={adjustmentType}
          value={numericValue}
          scopeType={scopeType}
          scopeValue={scopeValue}
          totalImpactedRecipes={totalImpactedRecipes}
          hasNegative={hasNegative}
          applying={applying}
          onConfirm={handleApply}
          onCancel={() => setStep('form')}
        />
      )}

      {step === 'success' && result && (
        <SuccessStep result={result} onReset={handleReset} />
      )}
    </div>
  );
}


/* ─── Form Step ─── */

function FormStep({
  scopeType,
  setScopeType,
  scopeValue,
  setScopeValue,
  adjustmentType,
  setAdjustmentType,
  value,
  setValue,
  categories,
  suppliers,
  isFormValid,
  loading,
  onPreview,
}: {
  scopeType: ScopeType;
  setScopeType: (v: ScopeType) => void;
  scopeValue: string;
  setScopeValue: (v: string) => void;
  adjustmentType: AdjustmentType;
  setAdjustmentType: (v: AdjustmentType) => void;
  value: string;
  setValue: (v: string) => void;
  categories: string[];
  suppliers: string[];
  isFormValid: boolean;
  loading: boolean;
  onPreview: () => void;
}) {
  return (
    <div className="rounded-xl border bg-white p-4 shadow-sm space-y-5">
      <div>
        <h2 className="text-base font-semibold text-gray-900">Ajuste Masivo de Costos</h2>
        <p className="mt-1 text-sm text-gray-500">
          Ajusta el costo de ingredientes por porcentaje o monto fijo. Los costos de recetas se recalculan automáticamente.
        </p>
      </div>

      {/* Scope */}
      <fieldset>
        <legend className="text-sm font-medium text-gray-700 mb-2">Alcance</legend>
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
            {([
              { key: 'all', label: 'Todos' },
              { key: 'category', label: 'Categoría' },
              { key: 'supplier', label: 'Proveedor' },
            ] as const).map(opt => (
              <button
                key={opt.key}
                type="button"
                onClick={() => setScopeType(opt.key)}
                className={cn(
                  'rounded-md px-3 py-1.5 text-sm font-medium transition-colors min-h-[36px]',
                  scopeType === opt.key
                    ? 'bg-white text-gray-900 shadow-sm'
                    : 'text-gray-600 hover:text-gray-900'
                )}
                aria-pressed={scopeType === opt.key}
              >
                {opt.label}
              </button>
            ))}
          </div>

          {scopeType === 'category' && (
            <select
              value={scopeValue}
              onChange={e => setScopeValue(e.target.value)}
              className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
              aria-label="Seleccionar categoría"
            >
              <option value="">Seleccionar categoría...</option>
              {categories.map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          )}

          {scopeType === 'supplier' && (
            <select
              value={scopeValue}
              onChange={e => setScopeValue(e.target.value)}
              className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
              aria-label="Seleccionar proveedor"
            >
              <option value="">Seleccionar proveedor...</option>
              {suppliers.map(s => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          )}
        </div>
      </fieldset>

      {/* Type + Value */}
      <fieldset>
        <legend className="text-sm font-medium text-gray-700 mb-2">Tipo de ajuste</legend>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
            <button
              type="button"
              onClick={() => setAdjustmentType('percentage')}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors min-h-[36px]',
                adjustmentType === 'percentage'
                  ? 'bg-white text-gray-900 shadow-sm'
                  : 'text-gray-600 hover:text-gray-900'
              )}
              aria-pressed={adjustmentType === 'percentage'}
            >
              <Percent className="h-3.5 w-3.5" />
              Porcentaje
            </button>
            <button
              type="button"
              onClick={() => setAdjustmentType('fixed')}
              className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors min-h-[36px]',
                adjustmentType === 'fixed'
                  ? 'bg-white text-gray-900 shadow-sm'
                  : 'text-gray-600 hover:text-gray-900'
              )}
              aria-pressed={adjustmentType === 'fixed'}
            >
              <DollarSign className="h-3.5 w-3.5" />
              Monto fijo
            </button>
          </div>

          <div className="relative flex-1 max-w-xs">
            <label htmlFor="adjustment-value" className="sr-only">
              Valor del ajuste
            </label>
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">
              {adjustmentType === 'percentage' ? '%' : '$'}
            </span>
            <input
              id="adjustment-value"
              type="number"
              value={value}
              onChange={e => setValue(e.target.value)}
              placeholder={adjustmentType === 'percentage' ? 'ej: 10 o -5' : 'ej: 500 o -200'}
              step="any"
              className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-8 pr-3 text-sm tabular-nums focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
            />
          </div>
        </div>
        <p className="mt-1.5 text-xs text-gray-400">
          {adjustmentType === 'percentage'
            ? 'Valores positivos aumentan, negativos disminuyen. Ej: 10 = +10%'
            : 'Valores positivos aumentan, negativos disminuyen el costo por unidad.'}
        </p>
      </fieldset>

      {/* Preview button */}
      <div className="flex justify-end pt-2">
        <button
          onClick={onPreview}
          disabled={!isFormValid || loading}
          className={cn(
            'inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors',
            !isFormValid || loading
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Ver vista previa del ajuste"
        >
          {loading ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Eye className="h-4 w-4" />
          )}
          Vista previa
        </button>
      </div>
    </div>
  );
}


/* ─── Preview Step ─── */

function PreviewStep({
  preview,
  adjustmentType,
  value,
  scopeType,
  scopeValue,
  totalImpactedRecipes,
  hasNegative,
  applying,
  onConfirm,
  onCancel,
}: {
  preview: PreviewItem[];
  adjustmentType: AdjustmentType;
  value: number;
  scopeType: ScopeType;
  scopeValue: string;
  totalImpactedRecipes: number;
  hasNegative: boolean;
  applying: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const scopeLabel =
    scopeType === 'all'
      ? 'Todos los ingredientes'
      : scopeType === 'category'
        ? `Categoría: ${scopeValue}`
        : `Proveedor: ${scopeValue}`;

  const adjustLabel =
    adjustmentType === 'percentage'
      ? `${value > 0 ? '+' : ''}${value}%`
      : `${value > 0 ? '+' : ''}${formatCLP(value)}`;

  return (
    <div className="space-y-4">
      {/* Summary cards */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <p className="text-xs text-gray-500">Alcance</p>
          <p className="mt-1 text-sm font-semibold text-gray-900">{scopeLabel}</p>
        </div>
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <p className="text-xs text-gray-500">Ajuste</p>
          <p className="mt-1 text-sm font-semibold text-gray-900">{adjustLabel}</p>
        </div>
        <div className="rounded-xl border bg-white p-4 shadow-sm">
          <p className="text-xs text-gray-500">Impacto</p>
          <p className="mt-1 text-sm font-semibold text-gray-900">
            {preview.length} ingrediente{preview.length !== 1 ? 's' : ''} · {totalImpactedRecipes} receta{totalImpactedRecipes !== 1 ? 's' : ''}
          </p>
        </div>
      </div>

      {/* Negative cost warning */}
      {hasNegative && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          El ajuste resultaría en costos negativos para algunos ingredientes. No se puede aplicar.
        </div>
      )}

      {/* Preview table */}
      {preview.length === 0 ? (
        <div className="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
          No hay ingredientes afectados por este ajuste.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
          <table className="w-full text-sm">
            <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
              <tr>
                <th className="px-4 py-3">Ingrediente</th>
                <th className="px-4 py-3 text-right">Costo actual</th>
                <th className="px-4 py-3 text-right">Costo propuesto</th>
                <th className="px-4 py-3 text-right hidden sm:table-cell">Diferencia</th>
                <th className="px-4 py-3 text-right">Recetas</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {preview.map(item => {
                const diff = item.proposed_cost - item.current_cost;
                const isNeg = item.proposed_cost < 0;
                return (
                  <tr
                    key={item.id}
                    className={cn(
                      'transition-colors',
                      isNeg ? 'bg-red-50' : 'hover:bg-gray-50'
                    )}
                  >
                    <td className="px-4 py-3 font-medium text-gray-900">{item.name}</td>
                    <td className="px-4 py-3 text-right tabular-nums text-gray-500">
                      {formatCLP(item.current_cost)}
                    </td>
                    <td className={cn(
                      'px-4 py-3 text-right tabular-nums font-medium',
                      isNeg ? 'text-red-600' : 'text-gray-900'
                    )}>
                      {formatCLP(item.proposed_cost)}
                    </td>
                    <td className={cn(
                      'px-4 py-3 text-right tabular-nums hidden sm:table-cell',
                      diff > 0 ? 'text-red-500' : diff < 0 ? 'text-green-600' : 'text-gray-400'
                    )}>
                      {diff > 0 ? '+' : ''}{formatCLP(diff)}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums text-gray-500">
                      {item.recipe_count}
                    </td>
                  </tr>
                );
              })}
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
          aria-label="Cancelar y volver al formulario"
        >
          <ArrowLeft className="h-4 w-4" />
          Volver
        </button>
        <button
          onClick={onConfirm}
          disabled={applying || hasNegative || preview.length === 0}
          className={cn(
            'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-colors min-h-[44px]',
            applying || hasNegative || preview.length === 0
              ? 'bg-gray-300 cursor-not-allowed'
              : 'bg-red-500 hover:bg-red-600'
          )}
          aria-label="Confirmar y aplicar ajuste"
        >
          {applying ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Check className="h-4 w-4" />
          )}
          Confirmar ajuste
        </button>
      </div>
    </div>
  );
}

/* ─── Success Step ─── */

function SuccessStep({
  result,
  onReset,
}: {
  result: { ingredients_affected: number; products_affected: number };
  onReset: () => void;
}) {
  return (
    <div className="rounded-xl border bg-white p-6 shadow-sm text-center space-y-4">
      <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
        <Check className="h-6 w-6 text-green-600" />
      </div>
      <div>
        <h2 className="text-base font-semibold text-gray-900">Ajuste aplicado correctamente</h2>
        <p className="mt-1 text-sm text-gray-500">
          Se actualizaron {result.ingredients_affected} ingrediente{result.ingredients_affected !== 1 ? 's' : ''} y
          se recalcularon {result.products_affected} receta{result.products_affected !== 1 ? 's' : ''}.
        </p>
      </div>
      <button
        onClick={onReset}
        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 min-h-[44px]"
        aria-label="Realizar otro ajuste"
      >
        Nuevo ajuste
      </button>
    </div>
  );
}
