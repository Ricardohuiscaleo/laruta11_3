'use client';

import { useState, useEffect, useCallback } from 'react';
import { Flame, Loader2, Minus, Check, X, AlertCircle } from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';

interface Consumible {
  id: number;
  name: string;
  current_stock: number;
  unit: string;
  cost_per_unit: number;
  valor_inventario: number;
  category: string;
}

interface ConsumiResponse {
  success: boolean;
  message?: string;
}

export default function ConsumiblesPanel() {
  const [items, setItems] = useState<Consumible[]>([]);
  const [loading, setLoading] = useState(true);
  const [consumeId, setConsumeId] = useState<number | null>(null);
  const [consumeQty, setConsumeQty] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const fetchItems = useCallback(() => {
    setLoading(true);
    apiFetch<{ success: boolean; data: Consumible[] }>('/admin/stock/consumibles')
      .then(res => { if (res.data) setItems(res.data); })
      .catch(() => setError('Error al cargar consumibles'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { fetchItems(); }, [fetchItems]);

  const handleConsume = async () => {
    if (!consumeId || !consumeQty) return;
    const qty = parseFloat(consumeQty);
    const item = items.find(i => i.id === consumeId);
    if (!item) return;
    if (qty <= 0) { setError('La cantidad debe ser mayor a 0'); return; }
    if (qty > item.current_stock) {
      setError(`Stock insuficiente: disponible ${item.current_stock} ${item.unit}`);
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await apiFetch<ConsumiResponse>('/admin/stock/consumir', {
        method: 'POST',
        body: JSON.stringify({ items: [{ id: consumeId, cantidad: qty }] }),
      });
      setSuccess(`${qty} ${item.unit} de ${item.name} consumido`);
      setConsumeId(null);
      setConsumeQty('');
      fetchItems();
      setTimeout(() => setSuccess(null), 3000);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Error al consumir';
      setError(msg);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-10">
        <Loader2 className="h-6 w-6 animate-spin text-amber-600" />
      </div>
    );
  }

  return (
    <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
      <div className="flex items-center gap-2 px-4 py-3 bg-amber-50 border-b">
        <Flame className="h-4 w-4 text-amber-600" />
        <h3 className="text-sm font-semibold text-gray-800">Consumibles</h3>
        <span className="text-xs text-gray-500">({items.length})</span>
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

      {success && (
        <div className="flex items-center gap-2 px-4 py-2 bg-green-50 text-sm text-green-700">
          <Check className="h-4 w-4 shrink-0" />
          <span>{success}</span>
        </div>
      )}

      {items.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-gray-500">Sin consumibles registrados</p>
      ) : (
        <div className="divide-y">
          {items.map(item => {
            const isConsuming = consumeId === item.id;
            return (
              <div key={item.id} className="px-4 py-3">
                <div className="flex items-center justify-between">
                  <div className="min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">{item.name}</p>
                    <p className="text-xs text-gray-500">{item.category}</p>
                  </div>
                  {!isConsuming && (
                    <button
                      onClick={() => { setConsumeId(item.id); setConsumeQty(''); setError(null); }}
                      className="shrink-0 flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 min-h-[36px]"
                      aria-label={`Consumir ${item.name}`}
                    >
                      <Minus className="h-3.5 w-3.5" /> Consumir
                    </button>
                  )}
                </div>

                <div className="mt-1.5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-1 text-xs text-gray-600">
                  <div>Stock: <span className="font-medium text-gray-900">{item.current_stock} {item.unit}</span></div>
                  <div>Costo: <span className="font-medium text-gray-900">{formatCLP(item.cost_per_unit)}/{item.unit}</span></div>
                  <div className="col-span-2 sm:col-span-2">
                    Valor inv.: <span className="font-medium text-gray-900">{formatCLP(item.valor_inventario)}</span>
                  </div>
                </div>

                {isConsuming && (
                  <div className="mt-2 flex items-center gap-2">
                    <input
                      type="number"
                      step="any"
                      min="0"
                      max={item.current_stock}
                      value={consumeQty}
                      onChange={e => setConsumeQty(e.target.value)}
                      placeholder={`Cantidad (${item.unit})`}
                      className="flex-1 rounded-lg border px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                      autoFocus
                      aria-label={`Cantidad a consumir de ${item.name}`}
                    />
                    <button
                      onClick={handleConsume}
                      disabled={saving || !consumeQty}
                      className="rounded-lg bg-amber-500 px-3 py-2 text-sm text-white hover:bg-amber-600 disabled:opacity-50 min-h-[36px]"
                      aria-label="Confirmar consumo"
                    >
                      {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
                    </button>
                    <button
                      onClick={() => { setConsumeId(null); setError(null); }}
                      className="rounded-lg border px-3 py-2 text-sm hover:bg-gray-50 min-h-[36px]"
                      aria-label="Cancelar consumo"
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
