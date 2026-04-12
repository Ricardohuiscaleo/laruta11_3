'use client';

import { useState, useEffect } from 'react';
import { Package, Wine } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';
import type { StockItem } from '@/types/compras';

const SEMAFORO_COLORS: Record<string, string> = {
  rojo: 'bg-red-100 text-red-800 border-red-200',
  amarillo: 'bg-amber-100 text-amber-800 border-amber-200',
  verde: 'bg-green-100 text-green-800 border-green-200',
};

const SEMAFORO_DOT: Record<string, string> = {
  rojo: 'bg-red-500',
  amarillo: 'bg-amber-500',
  verde: 'bg-green-500',
};

export default function StockDashboard() {
  const [tab, setTab] = useState<'ingredientes' | 'bebidas'>('ingredientes');
  const [items, setItems] = useState<StockItem[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setLoading(true);
    const path = tab === 'ingredientes' ? '/stock?tipo=ingredientes' : '/stock?tipo=bebidas';
    comprasApi.get<{ success: boolean; items: StockItem[] }>(path)
      .then(r => setItems(r.items || []))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [tab]);

  const pct = (item: StockItem) => {
    const stock = Number(item.current_stock) || 0;
    const min = Number(item.min_stock_level) || 0;
    if (min <= 0) return 100;
    return Math.round((stock / min) * 100);
  };

  return (
    <div className="space-y-3">
      <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
        <button onClick={() => setTab('ingredientes')}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === 'ingredientes' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
          }`}>
          <Package className="h-4 w-4" /> Ingredientes
        </button>
        <button onClick={() => setTab('bebidas')}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === 'bebidas' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
          }`}>
          <Wine className="h-4 w-4" /> Bebidas
        </button>
      </div>

      {loading ? (
        <div className="p-6 text-center text-sm text-gray-500">Cargando...</div>
      ) : items.length === 0 ? (
        <div className="p-6 text-center text-sm text-gray-500">Sin ítems</div>
      ) : (
        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {items.map(item => {
            const semaforo = item.semaforo || 'verde';
            const stock = Number(item.current_stock) || 0;
            const min = Number(item.min_stock_level) || 0;
            const percentage = pct(item);
            return (
              <div key={`${item.type}-${item.id}`}
                className={`rounded-xl border p-3 ${SEMAFORO_COLORS[semaforo] || SEMAFORO_COLORS.verde}`}>
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-2">
                    <div className={`h-2.5 w-2.5 rounded-full ${SEMAFORO_DOT[semaforo] || SEMAFORO_DOT.verde}`} />
                    <span className="text-sm font-semibold">{item.name}</span>
                  </div>
                  <span className="text-xs font-medium">{percentage}%</span>
                </div>
                <div className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                  <div>Stock: <span className="font-medium">{stock} {item.unit}</span></div>
                  <div>Mín: <span className="font-medium">{min} {item.unit}</span></div>
                  {item.ultima_compra_cantidad != null && (
                    <div>Últ. compra: <span className="font-medium">{item.ultima_compra_cantidad}</span></div>
                  )}
                  {item.vendido_desde_compra != null && Number(item.vendido_desde_compra) > 0 && (
                    <div>Vendido: <span className="font-medium">{item.vendido_desde_compra}</span></div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
