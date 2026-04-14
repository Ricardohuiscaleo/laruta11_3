'use client';

import { useState, useEffect, useMemo, useCallback } from 'react';
import { Package, Wine, ChevronDown, ChevronRight, Pencil, Minus, Check, X, CheckSquare, Square } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import type { StockItem } from '@/types/compras';

const SEMAFORO_COLORS: Record<string, string> = {
  rojo: 'bg-red-100 text-red-800 border-red-200',
  amarillo: 'bg-amber-100 text-amber-800 border-amber-200',
  verde: 'bg-green-100 text-green-800 border-green-200',
};
const SEMAFORO_DOT: Record<string, string> = { rojo: 'bg-red-500', amarillo: 'bg-amber-500', verde: 'bg-green-500' };

const CATEGORY_ORDER = [
  'Carnes', 'Vegetales', 'Salsas', 'Condimentos', 'Lácteos', 'Panes',
  'Embutidos', 'Pre-elaborados', 'Packaging', 'Limpieza', 'Bebidas', 'Gas', 'Servicios',
];

export default function StockDashboard() {
  const [tab, setTab] = useState<'ingredientes' | 'bebidas'>('ingredientes');
  const [items, setItems] = useState<StockItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
  // Edit mode
  const [editId, setEditId] = useState<number | null>(null);
  const [editStock, setEditStock] = useState('');
  const [editMin, setEditMin] = useState('');
  // Consume mode
  const [consumeId, setConsumeId] = useState<number | null>(null);
  const [consumeQty, setConsumeQty] = useState('');
  // Selection
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [bulkConsumeQty, setBulkConsumeQty] = useState('');
  const [showBulkConsume, setShowBulkConsume] = useState(false);

  const fetchItems = useCallback(() => {
    setLoading(true);
    const path = tab === 'ingredientes' ? '/stock?tipo=ingredientes' : '/stock?tipo=bebidas';
    comprasApi.get<{ success: boolean; items: StockItem[] }>(path)
      .then(r => setItems(r.items || []))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [tab]);

  useEffect(() => { fetchItems(); }, [fetchItems]);

  const grouped = useMemo(() => {
    if (tab === 'bebidas') return { 'Bebidas': items };
    const map: Record<string, StockItem[]> = {};
    for (const item of items) {
      const cat = item.category || 'Sin categoría';
      if (!map[cat]) map[cat] = [];
      map[cat].push(item);
    }
    const sorted: Record<string, StockItem[]> = {};
    for (const cat of CATEGORY_ORDER) { if (map[cat]) { sorted[cat] = map[cat]; delete map[cat]; } }
    for (const [cat, its] of Object.entries(map)) { sorted[cat] = its; }
    return sorted;
  }, [items, tab]);

  const pct = (item: StockItem) => {
    const stock = Number(item.current_stock) || 0;
    const min = Number(item.min_stock_level) || 0;
    if (min <= 0) return 100;
    return Math.round((stock / min) * 100);
  };

  const toggle = (cat: string) => setCollapsed(prev => ({ ...prev, [cat]: !prev[cat] }));

  const catSummary = (its: StockItem[]) => ({
    rojos: its.filter(i => i.semaforo === 'rojo').length,
    amarillos: its.filter(i => i.semaforo === 'amarillo').length,
    total: its.length,
  });

  const startEdit = (item: StockItem) => {
    setEditId(item.id); setEditStock(String(item.current_stock)); setEditMin(String(item.min_stock_level));
    setConsumeId(null);
  };

  const saveEdit = async () => {
    if (!editId) return;
    try {
      await comprasApi.patch(`/stock/${editId}`, {
        current_stock: parseFloat(editStock), min_stock_level: parseFloat(editMin),
      });
      setEditId(null); fetchItems();
    } catch { alert('Error al guardar'); }
  };

  const startConsume = (item: StockItem) => {
    setConsumeId(item.id); setConsumeQty(''); setEditId(null);
  };

  const saveConsume = async () => {
    if (!consumeId || !consumeQty) return;
    try {
      await comprasApi.post('/stock/consumir', {
        items: [{ id: consumeId, cantidad: parseFloat(consumeQty) }],
      });
      setConsumeId(null); fetchItems();
    } catch { alert('Error al consumir'); }
  };

  const toggleSelect = (id: number) => {
    setSelected(prev => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  };

  const selectAllInCategory = (catItems: StockItem[]) => {
    const ids = catItems.map(i => i.id);
    const allSelected = ids.every(id => selected.has(id));
    setSelected(prev => {
      const n = new Set(prev);
      ids.forEach(id => allSelected ? n.delete(id) : n.add(id));
      return n;
    });
  };

  const bulkConsume = async () => {
    if (!bulkConsumeQty || selected.size === 0) return;
    try {
      await comprasApi.post('/stock/consumir', {
        items: Array.from(selected).map(id => ({ id, cantidad: parseFloat(bulkConsumeQty) })),
      });
      setSelected(new Set()); setShowBulkConsume(false); setBulkConsumeQty(''); fetchItems();
    } catch { alert('Error al consumir'); }
  };

  return (
    <div className="space-y-3">
      {/* Tabs */}
      <div className="flex gap-1 rounded-lg bg-gray-100 p-1">
        <button onClick={() => { setTab('ingredientes'); setSelected(new Set()); }}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === 'ingredientes' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
          <Package className="h-4 w-4" /> Ingredientes
        </button>
        <button onClick={() => { setTab('bebidas'); setSelected(new Set()); }}
          className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
            tab === 'bebidas' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'}`}>
          <Wine className="h-4 w-4" /> Bebidas
        </button>
      </div>

      {/* Bulk actions bar */}
      {selected.size > 0 && (
        <div className="flex items-center gap-2 rounded-lg bg-mi3-50 border border-mi3-200 px-3 py-2">
          <span className="text-xs font-medium text-mi3-700">{selected.size} seleccionados</span>
          <button onClick={() => setShowBulkConsume(true)}
            className="rounded-md bg-mi3-500 px-3 py-1 text-xs font-medium text-white hover:bg-mi3-600">
            <Minus className="mr-1 inline h-3 w-3" />Consumir
          </button>
          <button onClick={() => setSelected(new Set())}
            className="ml-auto text-xs text-gray-500 hover:text-gray-700">Limpiar</button>
        </div>
      )}

      {/* Bulk consume modal */}
      {showBulkConsume && (
        <div className="rounded-lg border border-mi3-300 bg-white p-3 shadow-sm">
          <p className="text-sm font-medium text-gray-700 mb-2">Consumir de {selected.size} items:</p>
          <div className="flex gap-2">
            <input type="number" value={bulkConsumeQty} onChange={e => setBulkConsumeQty(e.target.value)}
              placeholder="Cantidad" className="flex-1 rounded-md border px-2 py-1.5 text-sm" autoFocus />
            <button onClick={bulkConsume} className="rounded-md bg-mi3-500 px-3 py-1.5 text-sm text-white hover:bg-mi3-600">
              <Check className="h-4 w-4" />
            </button>
            <button onClick={() => setShowBulkConsume(false)} className="rounded-md border px-3 py-1.5 text-sm hover:bg-gray-50">
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {loading ? (
        <div className="p-6 text-center text-sm text-gray-500">Cargando...</div>
      ) : items.length === 0 ? (
        <div className="p-6 text-center text-sm text-gray-500">Sin ítems</div>
      ) : (
        <div className="space-y-2">
          {Object.entries(grouped).map(([cat, catItems]) => {
            const isCollapsed = collapsed[cat];
            const { rojos, amarillos, total } = catSummary(catItems);
            const allCatSelected = catItems.every(i => selected.has(i.id));
            return (
              <div key={cat} className="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <div className="flex items-center px-4 py-3 hover:bg-gray-50">
                  <button onClick={() => selectAllInCategory(catItems)} className="mr-2 text-gray-400 hover:text-mi3-600">
                    {allCatSelected ? <CheckSquare className="h-4 w-4 text-mi3-600" /> : <Square className="h-4 w-4" />}
                  </button>
                  <button onClick={() => toggle(cat)} className="flex flex-1 items-center justify-between text-left">
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
                </div>
                {!isCollapsed && (
                  <div className="grid gap-2 px-4 pb-3 sm:grid-cols-2 lg:grid-cols-3">
                    {catItems.map(item => {
                      const semaforo = item.semaforo || 'verde';
                      const stock = Number(item.current_stock) || 0;
                      const min = Number(item.min_stock_level) || 0;
                      const percentage = pct(item);
                      const isEditing = editId === item.id;
                      const isConsuming = consumeId === item.id;
                      const isSelected = selected.has(item.id);
                      return (
                        <div key={`${item.type}-${item.id}`}
                          className={`rounded-lg border p-2.5 ${isSelected ? 'ring-2 ring-mi3-400 ' : ''}${SEMAFORO_COLORS[semaforo] || SEMAFORO_COLORS.verde}`}>
                          <div className="flex items-start justify-between">
                            <div className="flex items-center gap-1.5">
                              <button onClick={() => toggleSelect(item.id)} className="text-gray-400 hover:text-mi3-600">
                                {isSelected ? <CheckSquare className="h-3.5 w-3.5 text-mi3-600" /> : <Square className="h-3.5 w-3.5" />}
                              </button>
                              <div className={`h-2 w-2 rounded-full ${SEMAFORO_DOT[semaforo] || SEMAFORO_DOT.verde}`} />
                              <span className="text-xs font-semibold">{item.name}</span>
                            </div>
                            <div className="flex items-center gap-1">
                              <button onClick={() => startConsume(item)} title="Consumir"
                                className="rounded p-0.5 text-gray-400 hover:bg-white/50 hover:text-red-600">
                                <Minus className="h-3.5 w-3.5" />
                              </button>
                              <button onClick={() => startEdit(item)} title="Editar"
                                className="rounded p-0.5 text-gray-400 hover:bg-white/50 hover:text-mi3-600">
                                <Pencil className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          </div>

                          {isEditing ? (
                            <div className="mt-2 space-y-1">
                              <div className="flex gap-1">
                                <input type="number" value={editStock} onChange={e => setEditStock(e.target.value)}
                                  className="w-full rounded border px-1.5 py-1 text-xs" placeholder="Stock" autoFocus />
                                <input type="number" value={editMin} onChange={e => setEditMin(e.target.value)}
                                  className="w-full rounded border px-1.5 py-1 text-xs" placeholder="Mín" />
                              </div>
                              <div className="flex gap-1">
                                <button onClick={saveEdit} className="flex-1 rounded bg-mi3-500 py-1 text-xs text-white hover:bg-mi3-600">
                                  <Check className="mx-auto h-3.5 w-3.5" />
                                </button>
                                <button onClick={() => setEditId(null)} className="flex-1 rounded border py-1 text-xs hover:bg-white/50">
                                  <X className="mx-auto h-3.5 w-3.5" />
                                </button>
                              </div>
                            </div>
                          ) : isConsuming ? (
                            <div className="mt-2 flex gap-1">
                              <input type="number" value={consumeQty} onChange={e => setConsumeQty(e.target.value)}
                                className="w-full rounded border px-1.5 py-1 text-xs" placeholder={`Cant. (${item.unit})`} autoFocus />
                              <button onClick={saveConsume} className="rounded bg-red-500 px-2 py-1 text-xs text-white hover:bg-red-600">
                                <Check className="h-3.5 w-3.5" />
                              </button>
                              <button onClick={() => setConsumeId(null)} className="rounded border px-2 py-1 text-xs hover:bg-white/50">
                                <X className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          ) : (
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
                          )}
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
