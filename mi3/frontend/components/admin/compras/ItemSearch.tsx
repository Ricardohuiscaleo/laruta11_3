'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import { Search, Plus } from 'lucide-react';
import { comprasApi } from '@/lib/compras-api';
import { formatearPesosCLP } from '@/lib/compras-utils';

interface SearchResult {
  id: number;
  name: string;
  current_stock: number;
  unit: string;
  cost_per_unit: number | null;
  type: 'ingredient' | 'product';
  category?: string;
  min_stock_level?: number;
}

interface ItemSearchProps {
  onSelect: (item: SearchResult) => void;
  onCreateNew?: () => void;
  placeholder?: string;
}

export default function ItemSearch({ onSelect, onCreateNew, placeholder = 'Buscar ingrediente o producto...' }: ItemSearchProps) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  const search = useCallback(async (q: string) => {
    if (q.length < 2) { setResults([]); setOpen(false); return; }
    setLoading(true);
    try {
      const data = await comprasApi.get<SearchResult[]>(`/compras/items?q=${encodeURIComponent(q)}`);
      setResults(data);
      setOpen(true);
    } catch { setResults([]); }
    setLoading(false);
  }, []);

  const handleChange = (val: string) => {
    setQuery(val);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => search(val), 300);
  };

  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const handleSelect = (item: SearchResult) => {
    onSelect(item);
    setQuery('');
    setResults([]);
    setOpen(false);
  };

  return (
    <div ref={wrapperRef} className="relative">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
          type="text"
          value={query}
          onChange={e => handleChange(e.target.value)}
          placeholder={placeholder}
          className="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-3 text-sm focus:border-mi3-500 focus:outline-none focus:ring-1 focus:ring-mi3-500"
        />
        {loading && <div className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-gray-300 border-t-mi3-500" />}
      </div>

      {open && (
        <div className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-lg border bg-white shadow-lg">
          {results.length > 0 ? (
            results.map(item => (
              <button
                key={`${item.type}-${item.id}`}
                onClick={() => handleSelect(item)}
                className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-50"
              >
                <div>
                  <span className="font-medium text-gray-900">{item.name}</span>
                  <span className="ml-2 text-xs text-gray-500">
                    Stock: {item.current_stock} {item.unit}
                  </span>
                </div>
                {item.cost_per_unit != null && (
                  <span className="text-xs text-gray-500">{formatearPesosCLP(item.cost_per_unit)}</span>
                )}
              </button>
            ))
          ) : (
            <div className="px-3 py-2 text-sm text-gray-500">Sin resultados</div>
          )}
          {onCreateNew && (
            <button
              onClick={() => { onCreateNew(); setOpen(false); }}
              className="flex w-full items-center gap-2 border-t px-3 py-2 text-sm text-mi3-600 hover:bg-mi3-50"
            >
              <Plus className="h-4 w-4" /> Crear nuevo ingrediente
            </button>
          )}
        </div>
      )}
    </div>
  );
}
