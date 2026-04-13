'use client';

import { useState, useEffect, useRef, useCallback } from 'react';
import { Search, ChevronLeft, ChevronRight, MessageSquare } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP, formatearFecha } from '@/lib/compras-utils';
import type { Compra } from '@/types/compras';
import DetalleCompra from './DetalleCompra';
import RendicionWhatsApp from './RendicionWhatsApp';

const METODO_LABELS: Record<string, string> = {
  cash: 'Efectivo', transfer: 'Transferencia', credit: 'Crédito', debit: 'Débito',
};

interface PaginatedResponse {
  success: boolean;
  compras: Compra[];
  page: number;
  total_pages: number;
  total_compras: number;
}

export default function HistorialCompras() {
  const [compras, setCompras] = useState<Compra[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [detalle, setDetalle] = useState<Compra | null>(null);
  const [showRendicion, setShowRendicion] = useState(false);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  const fetchCompras = useCallback(async (p: number, q: string) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(p) });
      if (q) params.set('q', q);
      const res = await comprasApi.get<PaginatedResponse>(`/compras?${params}`);
      setCompras(res.compras || []);
      setTotalPages(res.total_pages || 1);
      setTotal(res.total_compras || 0);
    } catch { setCompras([]); }
    setLoading(false);
  }, []);

  useEffect(() => { fetchCompras(page, query); }, [page, fetchCompras]);

  const handleSearch = (val: string) => {
    setQuery(val);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => { setPage(1); fetchCompras(1, val); }, 300);
  };

  const toggleSelect = (id: number) => {
    setSelected(prev => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };

  const selectedCompras = compras.filter(c => selected.has(c.id));

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input type="text" value={query} onChange={e => handleSearch(e.target.value)}
            placeholder="Buscar por proveedor o notas..."
            className="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-3 text-sm focus:border-mi3-500 focus:outline-none focus:ring-1 focus:ring-mi3-500" />
        </div>
        {selected.size > 0 && (
          <button onClick={() => setShowRendicion(true)}
            className="flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
            <MessageSquare className="h-4 w-4" /> Rendición ({selected.size})
          </button>
        )}
      </div>

      <div className="rounded-xl border bg-white shadow-sm">
        {loading ? (
          <div className="p-6 text-center text-sm text-gray-500">Cargando...</div>
        ) : compras.length === 0 ? (
          <div className="p-6 text-center text-sm text-gray-500">No hay compras</div>
        ) : (
          <div className="divide-y">
            {compras.map(c => {
              const rendida = !!(c as any).rendicion_id;
              return (
              <div key={c.id} className={`flex items-center gap-3 px-4 py-3 hover:bg-gray-50 ${rendida ? 'opacity-60' : ''}`}>
                {!rendida ? (
                  <input type="checkbox" checked={selected.has(c.id)}
                    onChange={() => toggleSelect(c.id)} className="rounded" />
                ) : (
                  <span className="inline-flex items-center rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">✓</span>
                )}
                <button onClick={() => setDetalle(c)} className="flex flex-1 items-center justify-between text-left">
                  <div>
                    <p className="text-sm font-medium text-gray-900">{c.proveedor}</p>
                    <p className="text-xs text-gray-500">
                      {formatearFecha(c.fecha_compra)} · {METODO_LABELS[c.metodo_pago] || c.metodo_pago}
                      {c.detalles && ` · ${c.detalles.length} ítems`}
                    </p>
                  </div>
                  <span className="text-sm font-semibold text-gray-900">{formatearPesosCLP(c.monto_total)}</span>
                </button>
              </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-between text-sm text-gray-500">
          <span>{total} compras</span>
          <div className="flex items-center gap-1">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
              className="rounded p-1 hover:bg-gray-100 disabled:opacity-30">
              <ChevronLeft className="h-4 w-4" />
            </button>
            <span className="px-2">{page} / {totalPages}</span>
            <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages}
              className="rounded p-1 hover:bg-gray-100 disabled:opacity-30">
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </div>
      )}

      {detalle && (
        <DetalleCompra compra={detalle} onClose={() => setDetalle(null)}
          onDeleted={() => { setDetalle(null); fetchCompras(page, query); }} />
      )}

      {showRendicion && (
        <RendicionWhatsApp compras={selectedCompras} onClose={() => setShowRendicion(false)}
          onCreated={() => { setSelected(new Set()); fetchCompras(page, query); }} />
      )}
    </div>
  );
}
