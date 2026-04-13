'use client';

import { useState } from 'react';
import { apiFetch } from '@/lib/api';
import { cn, formatCLP } from '@/lib/utils';
import {
  Loader2,
  CheckCircle2,
  AlertTriangle,
  DollarSign,
} from 'lucide-react';
import type { ChecklistItem, ApiResponse } from '@/types';

interface CashVerificationItemProps {
  item: ChecklistItem;
  checklistId: number;
  onVerified: (itemId: number, result: 'ok' | 'discrepancia', cashActual?: number) => void;
}

export default function CashVerificationItem({
  item,
  checklistId,
  onVerified,
}: CashVerificationItemProps) {
  const [showInput, setShowInput] = useState(false);
  const [actualAmount, setActualAmount] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const cashExpected = item.cash_expected ?? 0;
  const formattedExpected = formatCLP(cashExpected);

  // Already completed
  if (item.is_completed) {
    const isOk = item.cash_result === 'ok';
    return (
      <div className={cn(
        'rounded-lg border p-4',
        isOk ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'
      )}>
        <div className="flex items-center gap-3">
          {isOk ? (
            <CheckCircle2 className="h-6 w-6 text-green-500 flex-shrink-0" />
          ) : (
            <AlertTriangle className="h-6 w-6 text-amber-500 flex-shrink-0" />
          )}
          <div>
            <p className={cn('text-sm font-semibold', isOk ? 'text-green-700' : 'text-amber-700')}>
              {isOk ? 'Caja verificada ✅' : 'Discrepancia reportada ⚠️'}
            </p>
            <p className="text-xs text-gray-500 mt-0.5">
              Esperado: {formattedExpected}
              {!isOk && item.cash_actual != null && (
                <> · Real: {formatCLP(item.cash_actual)} · Diferencia: {formatCLP(Math.abs(item.cash_difference ?? 0))} ({(item.cash_difference ?? 0) > 0 ? 'sobrante' : 'faltante'})</>
              )}
            </p>
          </div>
        </div>
      </div>
    );
  }

  const handleConfirm = async () => {
    setSubmitting(true);
    setError('');
    try {
      await apiFetch<ApiResponse<unknown>>(
        `/worker/checklists/${checklistId}/items/${item.id}/verify-cash`,
        {
          method: 'POST',
          body: JSON.stringify({ confirmed: true }),
        }
      );
      onVerified(item.id, 'ok');
    } catch (err: any) {
      setError(err.message || 'Error al verificar');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDiscrepancy = async () => {
    const amount = parseFloat(actualAmount);
    if (isNaN(amount) || amount < 0) {
      setError('Ingresa un monto válido');
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      await apiFetch<ApiResponse<unknown>>(
        `/worker/checklists/${checklistId}/items/${item.id}/verify-cash`,
        {
          method: 'POST',
          body: JSON.stringify({ confirmed: false, actual_amount: amount }),
        }
      );
      onVerified(item.id, 'discrepancia', amount);
    } catch (err: any) {
      setError(err.message || 'Error al reportar');
    } finally {
      setSubmitting(false);
    }
  };

  const parsedAmount = parseFloat(actualAmount);
  const difference = !isNaN(parsedAmount) ? parsedAmount - cashExpected : null;

  return (
    <div className="rounded-lg border-2 border-amber-300 bg-amber-50/50 p-4 space-y-3">
      {/* Header */}
      <div className="flex items-center gap-2">
        <DollarSign className="h-5 w-5 text-amber-600" />
        <p className="text-sm font-semibold text-gray-900">Verificación de Caja</p>
      </div>

      {/* Question */}
      <p className="text-lg font-bold text-gray-900 text-center">
        ¿En caja hay {formattedExpected}?
      </p>

      {!showInput ? (
        /* Yes/No buttons */
        <div className="flex gap-3">
          <button
            onClick={handleConfirm}
            disabled={submitting}
            className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
          >
            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
            Sí
          </button>
          <button
            onClick={() => setShowInput(true)}
            disabled={submitting}
            className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-red-600 py-3 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50 transition-colors"
          >
            <AlertTriangle className="h-4 w-4" />
            No
          </button>
        </div>
      ) : (
        /* Discrepancy input */
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              ¿Cuánto hay realmente en caja?
            </label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input
                type="number"
                inputMode="numeric"
                min="0"
                step="1"
                value={actualAmount}
                onChange={e => {
                  setActualAmount(e.target.value);
                  if (error) setError('');
                }}
                placeholder="0"
                className="block w-full rounded-lg border border-gray-300 pl-7 pr-3 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
                autoFocus
              />
            </div>
          </div>

          {/* Real-time discrepancy */}
          {difference !== null && (
            <div className={cn(
              'rounded-lg p-2.5 text-sm font-medium text-center',
              difference === 0 ? 'bg-green-100 text-green-700' :
              difference > 0 ? 'bg-blue-100 text-blue-700' :
              'bg-red-100 text-red-700'
            )}>
              {difference === 0 ? (
                'Sin diferencia'
              ) : (
                <>Diferencia: {formatCLP(Math.abs(difference))} ({difference > 0 ? 'sobrante' : 'faltante'})</>
              )}
            </div>
          )}

          <div className="flex gap-2">
            <button
              onClick={() => { setShowInput(false); setActualAmount(''); setError(''); }}
              className="flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
            >
              Cancelar
            </button>
            <button
              onClick={handleDiscrepancy}
              disabled={submitting || !actualAmount}
              className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-amber-600 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50 transition-colors"
            >
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              Confirmar
            </button>
          </div>
        </div>
      )}

      {error && (
        <p className="text-xs text-red-600 text-center">{error}</p>
      )}
    </div>
  );
}
