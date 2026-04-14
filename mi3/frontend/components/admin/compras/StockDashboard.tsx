'use client';

import { useState, useEffect, useMemo } from 'react';
import { Package, Wine, ChevronDown, ChevronRight } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
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

const CATEGORY_ORDER = [
  'Carnes', 'Vegetales', 'Salsas', 'Condimentos', 'Lácteos', 'Panes',
  'Embutidos', 'Pre-elaborados', 'Packaging', 'Limpieza', 'Bebidas',
  'Gas', 'Servicios',
];

export default function StockDashboard() {
  const [tab, setTab] = useState<'ingredientes' | 'bebidas'>('ingredientes');
  const [items, setItems] = useState<StockItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setLoading(true);
    const path = tab === 'ingredientes' ? '/stock?tipo=ingredientes' : '/stock?tipo=bebidas';
    comprasApi.get<{ success: boolean; items: StockItem[] }>(path)
      .then(r => setItems(r.items || []))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [tab]);

  const grouped = useMemo(() => {
    if (tab === 'bebidas') return { 'Bebidas': items };
    const map: Record<string, StockItem[]> = {};
    for (const item of items) {
      const cat = item.category || 'Sin categoría';
      if (!map[cat]) map[cat] = [];
      map[cat].push(item);
    }
    // Sort by predefined order
    const sorted: Record<string, StockItem[]> = {};
    for (const cat of CATEGORY_ORDER) {
      if (map[cat]) { sorted[cat] = map[cat]; delete map[cat]; }
    }
    // Append any remaining categories
    for (const [cat, its] of Object.entries(map)) {
      sorted[cat] = its;
    }
    return sorted;
  }, [items, tab]);

  const pct = (item: StockItem) => {
    const stock = Number(item.current_stock) || 0;
    const min = Number(item.min_stock_level) || 0;
    if (min <= 0) return 100;
    return Math.round((stock / min) * 100);
  };

  const toggle = (cat: string) => setCollapsed(prev => ({ ...prev, [cat]: !prev[cat] }));

  const catSummary = (its: StockItem[]) => {
    const rojos = its.filter(i => i.semaforo === 'rojo').length;
    const amarillos = its.filter(i => i.semaforo === 'amarillo').length;
    return { rojos, amarillos, total: its.length };
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
        <div className="space-y-2">
          {Object.entries(grouped).map(([cat, catItems]) => {
            const isCollapsed = collapsed[cat];
            const { rojos, amarillos, total } = catSummary(catItems);
            return (
              <div key={cat} className="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <button onClick={() => toggle(cat)}
                  className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50">
                  <div className="flex items-center gap-2">
                    {isCollapsed ? <ChevronRight className="h-4 w-4 text-gray-400" /> : <ChevronDown className="h-4 w-4 text-gray-400" />}
                    <span className="text-sm font-semibold text-gray-800">{cat}</span>
                    <span className="text-xs text-gray-400">{total}</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    {rojos > 0 && <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{rojos}</span>}
                    {amarillos > 0 && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">{amarillos}</span>}
                  </div>
                </button>
                {!isCollapsed && (
                  <div className="grid gap-2 px-4 pb-3 sm:grid-cols-2 lg:grid-cols-3">
                    {catItems.map(item => {
                      const semaforo = item.semaforo || 'verde';
                      const stock = Number(item.current_stock) || 0;
                      const min = Number(item.min_stock_level) || 0;
                      const percentage = pct(item);
                      return (
                        <div key={`${item.type}-${item.id}`}
                          className={`rounded-lg border p-2.5 ${SEMAFORO_COLORS[semaforo] || SEMAFORO_COLORS.verde}`}>
                          <div className="flex items-start justify-between">
                            <div className="flex items-center gap-1.5">
                              <div className={`h-2 w-2 rounded-full ${SEMAFORO_DOT[semaforo] || SEMAFORO_DOT.verde}`} />
                              <span className="text-xs font-semibold">{item.name}</span>
                            </div>
                            <span className="text-[10px] font-medium">{percentage}%</span>
                          </div>
                          <div className="mt-1.5 grid grid-cols-2 gap-x-2 text-[11px]">
                            <div>Stock: <span className="font-medium">{stock} {item.unit}</span></div>
                            <div>Mín: <span className="font-medium">{min} {item.unit}</span></div>
                            {item.ultima_compra_cantidad != null && (
                              <div>Últ: <span className="font-medium">{item.ultima_compra_cantidad}</span></div>
                            )}
                            {item.vendido_desde_compra != null && Number(item.vendido_desde_compra) > 0 && (
                              <div>Vend: <span className="font-medium">{item.vendido_desde_compra}</span></div>
                            )}
                          </div>
                        </div>
                      );
                    })}
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
