'use client';

import { useState, useCallback } from 'react';
import { ClipboardCheck, Loader2, Check, X, AlertCircle, Eye } from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';

interface Ingredient {
  id: number;
  name: string;
  current_stock: number;
  unit: string;
  cost_per_unit: number;
  category: string;
}

interface AuditDiff {
  ingredient_id: number;
  name: string;
  unit: string;
  system_stock: number;
  physical_count: number;
  difference: number;
  cost_per_unit: number;
}

interface AuditSummary {
  items_modified: number;
  valor_antes: number;
  valor_despues: number;
  diferencia: number;
}

type Phase = 'idle' | 'loading' | 'counting' | 'preview' | 'applying' | 'done';

export default function AuditoriaPanel() {
  const [phase, setPhase] = useState<Phase>('idle');
  const [ingredients, setIngredients] = useState<Ingredient[]>([]);
  const [counts, setCounts] = useState<Record<number, string>>({});
  const [diffs, setDiffs] = useState<AuditDiff[]>([]);
  const [summary, setSummary] = useState<AuditSummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  const startAudit = useCallback(async () => {
    setPhase('loading');
    setError(null);
    try {
      const res = await apiFetch<{ data: Ingredient[] }>('/admin/stock/ingredientes-activos');
      setIngredients(res.data || []);
      const initial: Record<number, string> = {};
      (res.data || []).forEach(i => { initial[i.id] = ''; });
      setCounts(initial);
      setPhase('counting');
    } catch {
      setError('Error al cargar ingredientes');
      setPhase('idle');
    }
  }, []);

  const buildPreview = useCallback(() => {
    const result: AuditDiff[] = [];
    for (const ing of ingredients) {
      const raw = counts[ing.id];
      if (raw === '' || raw === undefined) continue;
      const physical = parseFloat(raw);
      if (isNaN(physical)) continue;
      if (physical !== ing.current_stock) {
        result.push({
          ingredient_id: ing.id,
          name: ing.name,
          unit: ing.unit,
          system_stock: ing.current_stock,
          physical_count: physical,
          difference: physical - ing.current_stock,
          cost_per_unit: ing.cost_per_unit,
        });
      }
    }
    setDiffs(result);
    setPhase('preview');
  }, [ingredients, counts]);

  const applyAudit = useCallback(async () => {
    setPhase('applying');
    setError(null);
    try {
      const items = diffs.map(d => ({
        ingredient_id: d.ingredient_id,
        physical_count: d.physical_count,
      }));
      const res = await apiFetch<{ success: boolean; data: AuditSummary; warnings?: string[] }>(
        '/admin/stock/auditoria',
        { method: 'POST', body: JSON.stringify({ items }) },
      );
      setSummary(res.data);
      setPhase('done');
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Error al aplicar auditoría';
      setError(msg);
      setPhase('preview');
    }
  }, [diffs]);

  const reset = useCallback(() => {
    setPhase('idle');
    setIngredients([]);
    setCounts({});
    setDiffs([]);
    setSummary(null);
    setError(null);
  }, []);

  return (
    <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-4 py-3 bg-purple-50 border-b">
        <ClipboardCheck className="h-4 w-4 text-purple-600" />
        <h3 className="text-sm font-semibold text-gray-800">Auditoría Física</h3>
      </div>

      {error && (
        <div className="flex items-center gap-2 px-4 py-2 bg-red-50 text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{error}</span>
          <button onClick={() => setError(null)} className="ml-auto" aria-label="Cerrar error">
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      )}

      <div className="p-4">
        {/* IDLE */}
        {phase === 'idle' && (
          <button
            onClick={startAudit}
            className="flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-purple-700 min-h-[44px]"
            aria-label="Iniciar auditoría física"
          >
            <ClipboardCheck className="h-4 w-4" /> Iniciar Auditoría Física
          </button>
        )}

        {/* LOADING */}
        {phase === 'loading' && (
          <div className="flex justify-center py-6">
            <Loader2 className="h-6 w-6 animate-spin text-purple-600" />
          </div>
        )}

        {/* COUNTING */}
        {phase === 'counting' && (
          <div className="space-y-3">
            <p className="text-xs text-gray-500">Ingresa el conteo físico para cada ingrediente. Deja vacío los que no cambian.</p>
            <div className="max-h-[60vh] overflow-y-auto space-y-1">
              {ingredients.map(ing => (
                <div key={ing.id} className="flex items-center gap-3 rounded-lg border px-3 py-2">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{ing.name}</p>
                    <p className="text-xs text-gray-500">Sistema: {ing.current_stock} {ing.unit}</p>
                  </div>
                  <input
                    type="number"
                    step="any"
                    min="0"
                    value={counts[ing.id] ?? ''}
                    onChange={e => setCounts(prev => ({ ...prev, [ing.id]: e.target.value }))}
                    placeholder={String(ing.current_stock)}
                    className="w-24 rounded-lg border px-2 py-1.5 text-sm text-right focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500"
                    aria-label={`Conteo físico de ${ing.name}`}
                  />
                  <span className="text-xs text-gray-400 w-8">{ing.unit}</span>
                </div>
              ))}
            </div>
            <div className="flex gap-2 pt-2">
              <button
                onClick={buildPreview}
                className="flex items-center gap-1.5 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 min-h-[44px]"
              >
                <Eye className="h-4 w-4" /> Previsualizar
              </button>
              <button
                onClick={reset}
                className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50 min-h-[44px]"
              >
                Cancelar
              </button>
            </div>
          </div>
        )}

        {/* PREVIEW */}
        {phase === 'preview' && (
          <div className="space-y-3">
            {diffs.length === 0 ? (
              <div className="text-center py-4">
                <p className="text-sm text-gray-500">No hay diferencias entre el conteo físico y el sistema.</p>
                <button onClick={() => setPhase('counting')} className="mt-2 text-sm text-purple-600 hover:underline">
                  Volver al conteo
                </button>
              </div>
            ) : (
              <>
                <p className="text-xs text-gray-500">{diffs.length} ingrediente(s) con diferencia:</p>
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-xs text-gray-500">
                        <th className="pb-2 pr-3">Ingrediente</th>
                        <th className="pb-2 pr-3 text-right">Sistema</th>
                        <th className="pb-2 pr-3 text-right">Físico</th>
                        <th className="pb-2 text-right">Dif.</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y">
                      {diffs.map(d => (
                        <tr key={d.ingredient_id}>
                          <td className="py-2 pr-3 font-medium">{d.name}</td>
                          <td className="py-2 pr-3 text-right">{d.system_stock} {d.unit}</td>
                          <td className="py-2 pr-3 text-right">{d.physical_count} {d.unit}</td>
                          <td className={cn('py-2 text-right font-medium', d.difference >= 0 ? 'text-green-600' : 'text-red-600')}>
                            {d.difference >= 0 ? '+' : ''}{d.difference} {d.unit}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="flex gap-2 pt-2">
                  <button
                    onClick={applyAudit}
                    className="flex items-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 min-h-[44px]"
                  >
                    <Check className="h-4 w-4" /> Aplicar Auditoría
                  </button>
                  <button
                    onClick={() => setPhase('counting')}
                    className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50 min-h-[44px]"
                  >
                    Volver
                  </button>
                  <button
                    onClick={reset}
                    className="rounded-lg border px-4 py-2 text-sm text-red-600 hover:bg-red-50 min-h-[44px]"
                  >
                    Cancelar
                  </button>
                </div>
              </>
            )}
          </div>
        )}

        {/* APPLYING */}
        {phase === 'applying' && (
          <div className="flex flex-col items-center py-6 gap-2">
            <Loader2 className="h-6 w-6 animate-spin text-purple-600" />
            <p className="text-sm text-gray-500">Aplicando auditoría...</p>
          </div>
        )}

        {/* DONE */}
        {phase === 'done' && summary && (
          <div className="space-y-3">
            <div className="flex items-center gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-700">
              <Check className="h-4 w-4 shrink-0" />
              <span>Auditoría aplicada exitosamente</span>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">Ítems modificados</p>
                <p className="text-lg font-bold text-gray-900">{summary.items_modified}</p>
              </div>
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">Diferencia</p>
                <p className={cn('text-lg font-bold', summary.diferencia >= 0 ? 'text-green-700' : 'text-red-700')}>
                  {summary.diferencia >= 0 ? '+' : ''}{formatCLP(summary.diferencia)}
                </p>
              </div>
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">Valor antes</p>
                <p className="text-sm font-semibold text-gray-900">{formatCLP(summary.valor_antes)}</p>
              </div>
              <div className="rounded-lg bg-gray-50 p-3 text-center">
                <p className="text-xs text-gray-500">Valor después</p>
                <p className="text-sm font-semibold text-gray-900">{formatCLP(summary.valor_despues)}</p>
              </div>
            </div>
            <button
              onClick={reset}
              className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50 min-h-[44px]"
            >
              Nueva auditoría
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
