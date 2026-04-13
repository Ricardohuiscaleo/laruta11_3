'use client';

import { useState } from 'react';
import { apiFetch } from '@/lib/api';
import { cn, formatCLP } from '@/lib/utils';
import { Loader2, CheckCircle2, AlertTriangle, DollarSign } from 'lucide-react';
import type { ChecklistItem, ApiResponse } from '@/types';

interface Props {
  item: ChecklistItem;
  checklistId: number;
  onVerified: (itemId: number, result: 'ok' | 'discrepancia', cashActual?: number) => void;
}

export default function CashVerificationItem({ item, checklistId, onVerified }: Props) {
  const [showInput, setShowInput] = useState(false);
  const [actualAmount, setActualAmount] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const cashExpected = item.cash_expected ?? 0;

  // Completed — show result with both amounts
  if (item.is_completed) {
    const isOk = item.cash_result === 'ok';
    return (
      <div className={cn('rounded-lg border p-4', isOk ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50')}>
        <div className="flex items-center gap-3">
          {isOk ? <CheckCircle2 className="h-6 w-6 text-green-500 flex-shrink-0" /> : <AlertTriangle className="h-6 w-6 text-red-500 flex-shrink-0" />}
          <div className="flex-1">
            <p className={cn('text-sm font-semibold', isOk ? 'text-green-700' : 'text-red-700')}>
              {isOk ? '✅ Cuadrado' : '❌ Descuadrado'}
            </p>
            <p className="mt-1 text-xs text-gray-600">
              Sistema: {formatCLP(item.cash_expected ?? 0)} | Físico: {formatCLP(item.cash_actual ?? 0)}
              {!isOk && item.cash_difference != null && (
                <span className="font-medium text-red-600">
                  {' '}| {(item.cash_difference ?? 0) < 0 ? 'Faltan' : 'Sobran'} {formatCLP(Math.abs(item.cash_difference))}
                </span>
              )}
            </p>
          </div>
        </div>
      </div>
    );
  }

  // Confirm — cash matches system
  const handleConfirm = async () => {
    setSubmitting(true);
    setError('');
    try {
      await apiFetch<ApiResponse<unknown>>(
        `/worker/checklists/${checklistId}/items/${item.id}/verify-cash`,
        { method: 'POST', body: JSON.stringify({ confirmed: true, actual_amount: cashExpected }) }
      );
      onVerified(item.id, 'ok');
    } catch (err: any) { setError(err.message || 'Error'); }
    finally { setSubmitting(false); }
  };

  // Discrepancy — cash doesn't match
  const handleDiscrepancy = async () => {
    const amount = parseFloat(actualAmount);
    if (isNaN(amount) || amount < 0) { setError('Ingresa un monto válido'); return; }
    setSubmitting(true);
    setError('');
    try {
      await apiFetch<ApiResponse<unknown>>(
        `/worker/checklists/${checklistId}/items/${item.id}/verify-cash`,
        { method: 'POST', body: JSON.stringify({ confirmed: false, actual_amount: amount }) }
      );
      onVerified(item.id, 'discrepancia', amount);
    } catch (err: any) { setError(err.message || 'Error'); }
    finally { setSubmitting(false); }
  };

  const parsedAmount = parseFloat(actualAmount);
  const difference = !isNaN(parsedAmount) ? parsedAmount - cashExpected : null;

  return (
    <div className="rounded-lg border-2 border-amber-300 bg-amber-50/50 p-4 space-y-3">
      <div className="flex items-center gap-2">
        <DollarSign className="h-5 w-5 text-amber-600" />
        <p className="text-sm font-semibold text-gray-900">Verificación de Caja</p>
      </div>

      <p className="text-lg font-bold text-gray-900 text-center">
        ¿En caja hay {formatCLP(cashExpected)}?
      </p>

      {!showInput ? (
        <div className="flex gap-3">
          <button onClick={handleConfirm} disabled={submitting}
            className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-green-600 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
            {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />} Sí
          </button>
          <button onClick={() => setShowInput(true)} disabled={submitting}
            className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-red-600 py-3 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
            <AlertTriangle className="h-4 w-4" /> No
          </button>
        </div>
      ) : (
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">¿Cuánto hay en caja?</label>
            <div className="relative">
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
              <input type="number" inputMode="numeric" min="0" step="1" value={actualAmount}
                onChange={e => { setActualAmount(e.target.value); if (error) setError(''); }}
                placeholder="0" autoFocus
                className="block w-full rounded-lg border border-gray-300 pl-7 pr-3 py-2.5 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500" />
            </div>
          </div>
          {difference !== null && (
            <div className={cn('rounded-lg p-2.5 text-sm font-medium text-center',
              difference === 0 ? 'bg-green-100 text-green-700' : difference > 0 ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700')}>
              {difference === 0 ? '✅ Cuadrado' : <>{difference < 0 ? 'Faltan' : 'Sobran'} {formatCLP(Math.abs(difference))}</>}
            </div>
          )}
          <div className="flex gap-2">
            <button onClick={() => { setShowInput(false); setActualAmount(''); setError(''); }}
              className="flex-1 rounded-lg border border-gray-300 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button onClick={handleDiscrepancy} disabled={submitting || !actualAmount}
              className="flex-1 flex items-center justify-center gap-2 rounded-lg bg-amber-600 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:opacity-50">
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : null} Confirmar
            </button>
          </div>
        </div>
      )}
      {error && <p className="text-xs text-red-600 text-center">{error}</p>}
    </div>
  );
}
