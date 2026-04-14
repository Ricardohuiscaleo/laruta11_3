'use client';

import { createContext, useContext, useState, useEffect, useCallback, useRef, type ReactNode } from 'react';
import { comprasApi } from '@/lib/compras-api';
import { getEcho } from '@/lib/echo';
import type { StockItem, Compra, Kpi } from '@/types/compras';

interface ComprasState {
  stockIngredientes: StockItem[];
  stockBebidas: StockItem[];
  kpis: Kpi | null;
  loading: Record<string, boolean>;
  lastEvent: { type: string; data: any; ts: number } | null;
  refreshStock: () => void;
  refreshKpis: () => void;
  refreshAll: () => void;
}

const ComprasContext = createContext<ComprasState | null>(null);

export function useCompras() {
  const ctx = useContext(ComprasContext);
  if (!ctx) throw new Error('useCompras must be inside ComprasProvider');
  return ctx;
}

export function ComprasProvider({ children }: { children: ReactNode }) {
  const [stockIngredientes, setStockIngredientes] = useState<StockItem[]>([]);
  const [stockBebidas, setStockBebidas] = useState<StockItem[]>([]);
  const [kpis, setKpis] = useState<Kpi | null>(null);
  const [loading, setLoading] = useState<Record<string, boolean>>({});
  const [lastEvent, setLastEvent] = useState<{ type: string; data: any; ts: number } | null>(null);
  const mounted = useRef(true);

  const refreshStock = useCallback(async () => {
    if (!mounted.current) return;
    setLoading(prev => ({ ...prev, stock: true }));
    try {
      const [ing, beb] = await Promise.all([
        comprasApi.get<{ success: boolean; items: StockItem[] }>('/stock?tipo=ingredientes'),
        comprasApi.get<{ success: boolean; items: StockItem[] }>('/stock?tipo=bebidas'),
      ]);
      if (mounted.current) {
        setStockIngredientes(ing.items || []);
        setStockBebidas(beb.items || []);
      }
    } catch {}
    if (mounted.current) setLoading(prev => ({ ...prev, stock: false }));
  }, []);

  const refreshKpis = useCallback(async () => {
    if (!mounted.current) return;
    setLoading(prev => ({ ...prev, kpis: true }));
    try {
      const r = await comprasApi.get<{ success: boolean; data: Kpi }>('/kpis');
      if (mounted.current) setKpis(r.data || null);
    } catch {}
    if (mounted.current) setLoading(prev => ({ ...prev, kpis: false }));
  }, []);

  const refreshAll = useCallback(() => {
    refreshStock();
    refreshKpis();
  }, [refreshStock, refreshKpis]);

  // Initial load
  useEffect(() => {
    mounted.current = true;
    refreshAll();
    return () => { mounted.current = false; };
  }, [refreshAll]);

  // WebSocket: listen for realtime events
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.channel('compras');

    channel.listen('.compra.registrada', (data: any) => {
      setLastEvent({ type: 'compra', data, ts: Date.now() });
      refreshAll();
    });

    channel.listen('.venta.registrada', (data: any) => {
      setLastEvent({ type: 'venta', data, ts: Date.now() });
      refreshStock();
      refreshKpis();
    });

    channel.listen('.stock.actualizado', (data: any) => {
      setLastEvent({ type: 'stock', data, ts: Date.now() });
      refreshStock();
    });

    return () => { echo.leave('compras'); };
  }, [refreshAll, refreshStock, refreshKpis]);

  return (
    <ComprasContext.Provider value={{
      stockIngredientes, stockBebidas, kpis, loading, lastEvent,
      refreshStock, refreshKpis, refreshAll,
    }}>
      {children}
    </ComprasContext.Provider>
  );
}
