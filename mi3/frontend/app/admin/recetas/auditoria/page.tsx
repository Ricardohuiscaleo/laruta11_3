'use client';

import { useState, useEffect, useMemo } from 'react';
import { apiFetch } from '@/lib/api';
import { cn } from '@/lib/utils';
import { Loader2, AlertTriangle, ArrowUpDown, Download, Search, PackageSearch } from 'lucide-react';
import { getToken } from '@/lib/auth';
import type { ApiResponse } from '@/types';

/* ─── Types ─── */

interface AuditItem {
  id: number;
  name: string;
  max_producible: number;
  limiting_ingredient: string;
  stock_status: 'sufficient' | 'low' | 'critical';
}

type SortField = 'name' | 'max_producible' | 'limiting_ingredient' | 'stock_status';
type SortDir = 'asc' | 'desc';

const STATUS_ORDER: Record<string, number> = { critical: 0, low: 1, sufficient: 2 };

const STATUS_CONFIG: Record<string, { label: string; className: string }> = {
  critical: { label: 'Crítico', className: 'bg-red-100 text-red-700' },
  low: { label: 'Bajo', className: 'bg-amber-100 text-amber-700' },
  sufficient: { label: 'Suficiente', className: 'bg-green-100 text-green-700' },
};

/* ─── Component ─── */

export default function AuditoriaPage() {
  const [items, setItems] = useState<AuditItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [exporting, setExporting] = useState(false);

  const [search, setSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('stock_status');
  const [sortDir, setSortDir] = useState<SortDir>('asc');

  useEffect(() => {
    setLoading(true);
    setError('');
    apiFetch<ApiResponse<AuditItem[]>>('/admin/recetas/audit')
      .then(res => setItems(res.data || []))
      .catch(e => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  /* Filter + sort */
  const filtered = useMemo(() => {
    let list = items;

    if (search) {
      const q = search.toLowerCase();
      list = list.filter(r =>
        r.name.toLowerCase().includes(q) ||
        r.limiting_ingredient.toLowerCase().includes(q)
      );
    }

    list = [...list].sort((a, b) => {
      let cmp = 0;
      switch (sortField) {
        case 'name':
          cmp = a.name.localeCompare(b.name, 'es');
          break;
        case 'max_producible':
          cmp = a.max_producible - b.max_producible;
          break;
        case 'limiting_ingredient':
          cmp = a.limiting_ingredient.localeCompare(b.limiting_ingredient, 'es');
          break;
        case 'stock_status':
          cmp = (STATUS_ORDER[a.stock_status] ?? 2) - (STATUS_ORDER[b.stock_status] ?? 2);
          break;
      }
      return sortDir === 'asc' ? cmp : -cmp;
    });

    return list;
  }, [items, search, sortField, sortDir]);

  const toggleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir(d => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const criticalCount = items.filter(i => i.stock_status === 'critical').length;
  const lowCount = items.filter(i => i.stock_status === 'low').length;

  /* CSV export via blob download */
  const handleExport = async () => {
    setExporting(true);
    try {
      const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';
      const token = getToken();
      const headers: Record<string, string> = { Accept: 'text/csv' };
      if (token) headers['Authorization'] = `Bearer ${token}`;

      const res = await fetch(`${API_URL}/api/v1/admin/recetas/audit/export`, {
        headers,
        credentials: 'include',
      });

      if (!res.ok) throw new Error('Error al exportar');

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `auditoria-stock-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (e: any) {
      setError(e.message || 'Error al exportar CSV');
    } finally {
      setExporting(false);
    }
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="rounded-xl border bg-white p-4 shadow-sm">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 className="text-base font-semibold text-gray-900">Auditoría de Stock</h2>
            <p className="mt-1 text-sm text-gray-500">
              Unidades producibles por producto según stock actual de ingredientes.
            </p>
          </div>
          <button
            onClick={handleExport}
            disabled={exporting || loading || items.length === 0}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-white transition-colors min-h-[38px]',
              exporting || loading || items.length === 0
                ? 'bg-gray-300 cursor-not-allowed'
                : 'bg-red-500 hover:bg-red-600'
            )}
            aria-label="Exportar auditoría como CSV"
          >
            {exporting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Download className="h-4 w-4" />}
            Exportar CSV
          </button>
        </div>
      </div>

      {/* Error */}
      {error && (
        <div className="flex items-center gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-600" role="alert">
          <AlertTriangle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex justify-center py-16" role="status" aria-label="Cargando auditoría">
          <Loader2 className="h-6 w-6 animate-spin text-red-500" />
        </div>
      ) : (
        <>
          {/* Search + summary */}
          <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Buscar producto o ingrediente..."
                className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-9 pr-3 text-sm focus:border-red-300 focus:outline-none focus:ring-1 focus:ring-red-300"
                aria-label="Buscar producto o ingrediente"
              />
            </div>
            <div className="flex items-center gap-3 text-xs text-gray-500">
              <span>{filtered.length} producto{filtered.length !== 1 ? 's' : ''}</span>
              {criticalCount > 0 && (
                <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-red-700">
                  <AlertTriangle className="h-3 w-3" />
                  {criticalCount} crítico{criticalCount !== 1 ? 's' : ''}
                </span>
              )}
              {lowCount > 0 && (
                <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">
                  {lowCount} bajo{lowCount !== 1 ? 's' : ''}
                </span>
              )}
            </div>
          </div>

          {/* Table */}
          <div className="overflow-x-auto rounded-xl border bg-white shadow-sm">
            <table className="w-full text-sm">
              <thead className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500">
                <tr>
                  <SortHeader label="Producto" field="name" current={sortField} dir={sortDir} onSort={toggleSort} />
                  <SortHeader label="Unidades producibles" field="max_producible" current={sortField} dir={sortDir} onSort={toggleSort} className="text-right" />
                  <SortHeader label="Ingrediente limitante" field="limiting_ingredient" current={sortField} dir={sortDir} onSort={toggleSort} className="hidden sm:table-cell" />
                  <SortHeader label="Estado stock" field="stock_status" current={sortField} dir={sortDir} onSort={toggleSort} className="text-center" />
                </tr>
              </thead>
              <tbody className="divide-y">
                {filtered.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-4 py-8 text-center text-gray-400">
                      <PackageSearch className="mx-auto mb-2 h-6 w-6" />
                      No se encontraron productos
                    </td>
                  </tr>
                ) : (
                  filtered.map(item => {
                    const cfg = STATUS_CONFIG[item.stock_status] || STATUS_CONFIG.sufficient;
                    return (
                      <tr
                        key={item.id}
                        className={cn(
                          'transition-colors hover:bg-gray-50',
                          item.stock_status === 'critical' && 'bg-red-50/50'
                        )}
                      >
                        <td className="px-4 py-3 font-medium text-gray-900">{item.name}</td>
                        <td className="px-4 py-3 text-right tabular-nums">{item.max_producible}</td>
                        <td className="px-4 py-3 text-gray-600 hidden sm:table-cell">{item.limiting_ingredient}</td>
                        <td className="px-4 py-3 text-center">
                          <span className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                            cfg.className
                          )}>
                            {item.stock_status === 'critical' && <AlertTriangle className="h-3 w-3" />}
                            {cfg.label}
                          </span>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

/* ─── Sortable header helper ─── */

function SortHeader({
  label,
  field,
  current,
  dir,
  onSort,
  className,
}: {
  label: string;
  field: SortField;
  current: SortField;
  dir: SortDir;
  onSort: (f: SortField) => void;
  className?: string;
}) {
  const active = current === field;
  return (
    <th className={cn('px-4 py-3', className)}>
      <button
        onClick={() => onSort(field)}
        className="inline-flex items-center gap-1 hover:text-gray-700"
        aria-label={`Ordenar por ${label}`}
      >
        {label}
        <ArrowUpDown className={cn('h-3 w-3', active ? 'text-red-500' : 'text-gray-300')} />
        {active && (
          <span className="sr-only">{dir === 'asc' ? 'ascendente' : 'descendente'}</span>
        )}
      </button>
    </th>
  );
}
