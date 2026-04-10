'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP, formatDateES } from '@/lib/utils';
import { CreditCard, Loader2, AlertTriangle, ArrowDownCircle, ArrowUpCircle, RotateCcw } from 'lucide-react';
import type { CreditoR11, CreditTransaction, ApiResponse } from '@/types';

export default function CreditoPage() {
  const [credit, setCredit] = useState<CreditoR11 | null>(null);
  const [transactions, setTransactions] = useState<CreditTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.allSettled([
      apiFetch<ApiResponse<CreditoR11>>('/worker/credit'),
      apiFetch<ApiResponse<CreditTransaction[]>>('/worker/credit/transactions'),
    ]).then(([creditRes, txRes]) => {
      if (creditRes.status === 'fulfilled' && creditRes.value.data) setCredit(creditRes.value.data);
      if (txRes.status === 'fulfilled' && txRes.value.data) setTransactions(txRes.value.data);
      setLoading(false);
    }).catch(() => { setError('Error cargando datos'); setLoading(false); });
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;
  if (error) return <div className="rounded-lg bg-red-50 p-4 text-red-600">{error}</div>;

  if (!credit || !credit.activo) {
    return (
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">Crédito R11</h1>
        <div className="rounded-xl border bg-white p-8 text-center shadow-sm">
          <CreditCard className="mx-auto h-12 w-12 text-gray-300" />
          <p className="mt-3 text-gray-500">No tienes crédito R11 activo</p>
        </div>
      </div>
    );
  }

  const txIcon = (type: string) => {
    if (type === 'debit') return <ArrowDownCircle className="h-4 w-4 text-red-500" />;
    if (type === 'credit') return <ArrowUpCircle className="h-4 w-4 text-green-500" />;
    return <RotateCcw className="h-4 w-4 text-blue-500" />;
  };

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold text-gray-900">Crédito R11</h1>

      {credit.bloqueado && (
        <div className="flex items-center gap-2 rounded-xl bg-red-50 border border-red-200 p-4">
          <AlertTriangle className="h-5 w-5 text-red-500" />
          <p className="text-sm font-medium text-red-700">Tu crédito está bloqueado. Contacta al administrador.</p>
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="rounded-xl border bg-white p-4 shadow-sm text-center">
          <p className="text-xs text-gray-500">Límite</p>
          <p className="text-xl font-bold">{formatCLP(credit.limite)}</p>
        </div>
        <div className="rounded-xl border bg-white p-4 shadow-sm text-center">
          <p className="text-xs text-gray-500">Usado</p>
          <p className="text-xl font-bold text-red-600">{formatCLP(credit.usado)}</p>
        </div>
        <div className="rounded-xl border bg-green-50 p-4 shadow-sm text-center">
          <p className="text-xs text-gray-500">Disponible</p>
          <p className="text-xl font-bold text-green-600">{formatCLP(credit.disponible)}</p>
        </div>
        <div className="rounded-xl border bg-white p-4 shadow-sm text-center">
          <p className="text-xs text-gray-500">Relación R11</p>
          <p className="text-sm font-medium capitalize">{credit.relacion_r11}</p>
          {credit.fecha_aprobacion && <p className="text-xs text-gray-400 mt-1">Aprobado: {formatDateES(credit.fecha_aprobacion)}</p>}
        </div>
      </div>

      <div className="rounded-xl border bg-white p-5 shadow-sm">
        <h2 className="font-semibold text-gray-900">Historial de Transacciones</h2>
        {transactions.length === 0 ? (
          <p className="mt-3 text-sm text-gray-500">Sin transacciones</p>
        ) : (
          <ul className="mt-3 divide-y">
            {transactions.map(tx => (
              <li key={tx.id} className="flex items-center gap-3 py-2.5">
                {txIcon(tx.type)}
                <div className="flex-1 min-w-0">
                  <p className="text-sm truncate">{tx.description}</p>
                  <p className="text-xs text-gray-400">{new Date(tx.created_at).toLocaleDateString('es-CL')}</p>
                </div>
                <span className={`text-sm font-semibold ${tx.type === 'debit' ? 'text-red-600' : 'text-green-600'}`}>
                  {tx.type === 'debit' ? '−' : '+'}{formatCLP(tx.amount)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
