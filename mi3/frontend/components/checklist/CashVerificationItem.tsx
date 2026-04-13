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
  const [actualAmount, setActualAmount] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const cashExpected = item.cash_expected ?? 0;

  // Already completed — show result
  if (item.is_completed) {
    const isOk = item.cash_result === 'ok';
    return (
      <div className={cn(
        'rounded-lg border p-4',
        isOk ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'
      )}>
        <div className="flex items-center gap-3">
          {isOk ? (
            <CheckCircle2 className="h-6 w-6 text-green-500 flex-shrink-0" />
          ) : (
            <AlertTriangle className="h-6 w-6 text-red-500 flex-shrink-0" />
          )}
          <div className="flex-1">
            <p className={cn('text-sm font-semibold', isOk ? 'text-green-700' : 'text-red-700')}>
              {isOk ? '✅ Caja cuadrada' : '⚠️ Discrepancia'}
            </p>
            <div className="mt-1 grid grid-cols-2 gap-x-4 text-xs text-gray-600">
              <span>Sistema: {formatCLP(item.cash_expected ?? 0)}</span>
              <span>Físico: {formatCLP(item.cash_actual ?? 0)}</span>
            </div>
            {!isOk && item.cash_difference != null && (
              <p className="mt-1 text-xs font-medium text-red-600">
                Diferencia: {formatCLP(Math.abs(item.cash_difference))} ({item.cash_difference > 0 ? 'sobrante' : 'faltante'})
              </p>
            )}
          </div>
        </div>
      </div>
    );
  }

  const parsedAmount = parseFloat(actualAmount);
  const difference = !isNaN(parsedAmount) ? parsedAmount - cashExpected : null;
  const isMatch = difference !== null && difference === 0;

  const handleSubmit = async () => {
    const amount = parseFloat(actualAmount);
    if (isNaN(amount) || amount < 0) {
      setError('Ingresa un monto válido');
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      const confirmed = amount === cashExpected;
      await apiFetch<ApiResponse<unknown>>(
        `/worker/checklists/${checklistId}/items/${item.id}/verify-cash`,
        {
          method: 'POST',
          body: JSON.stringify({ confirmed, actual_amount: amount }),
        }
      );
      onVerified(item.id, confirmed ? 'ok' : 'discrepancia', amount);
    } catch (err: any) {
      setError(err.message || 'Error al verificar');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="rounded-lg border-2 border-amber-300 bg-amber-50/50 p-4 space-y-3">
      <div className="flex items-center gap-2">
        <DollarSign className="h-5 w-5 text-amber-600" />
        <p className="text-sm font-semibold text-gray-900">Verificación de Caja</p>
      </div>

      {/* System balance */}
      <div className="rounded-lg bg-white border border-gray-200 p-3 text-center">
        <p className="text-xs text-gray-500">Saldo en sistema</p>
        <p className="text-2xl font-bold text-gray-900">{formatCLP(cashExpected)}</p>
      </div>

      {/* Physical amount input */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          ¿Cuánto hay en caja física?
        </label>
        <div className="relative">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
          <input
            type="number"
            inputMode="numeric"
            min="0"
            step="1"
            value={actualAmount}
            onChange={e => { setActualAmount(e.target.value); if (error) setError(''); }}
            placeholder="Ingresa el monto"
            className="block w-full rounded-lg border border-gray-300 pl-7 pr-3 py-3 text-lg font-semibold focus:border-amber-500 focus:ring-1 focus:ring-amber-500"
          />
        </div>
      </div>

      {/* Real-time comparison */}
      {difference !== null && (
        <div className={cn(
          'rounded-lg p-3 text-center font-medium',
          isMatch ? 'bg-green-100 text-green-700' :
          difference > 0 ? 'bg-blue-100 text-blue-700' :
          'bg-red-100 text-red-700'
        )}>
          {isMatch ? (
            <span className="flex items-center justify-center gap-2">
              <CheckCircle2 className="h-4 w-4" /> Cuadrado ✅
            </span>
          ) : (
            <span>Diferencia: {formatCLP(Math.abs(difference))} ({difference > 0 ? 'sobrante' : 'faltante'})</span>
          )}
        </div>
      )}

      <button
        onClick={handleSubmit}
        disabled={submitting || !actualAmount}
        className={cn(
          'w-full flex items-center justify-center gap-2 rounded-lg py-3 text-sm font-semibold text-white transition-colors disabled:opacity-50',
          isMatch ? 'bg-green-600 hover:bg-green-700' : 'bg-amber-600 hover:bg-amber-700'
        )}
      >
        {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
        {isMatch ? 'Confirmar — Cuadrado' : 'Confirmar verificación'}
      </button>

      {error && <p className="text-xs text-red-600 text-center">{error}</p>}
    </div>
  );
}
