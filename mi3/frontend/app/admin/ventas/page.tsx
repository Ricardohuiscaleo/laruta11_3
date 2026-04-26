'use client';

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  DollarSign, TrendingUp, ShoppingCart, Search,
  ChevronDown, ChevronUp, CreditCard, Banknote,
  ArrowLeftRight, Globe, Package, Loader2,
  ChevronLeft, ChevronRight,
} from 'lucide-react';
import { apiFetch } from '@/lib/api';
import { formatCLP, cn } from '@/lib/utils';
import { getEcho } from '@/lib/echo';

/* ─── Types ─── */

interface Kpis {
  total_sales: number;
  total_delivery: number;
  total_cost: number;
  total_profit: number;
  order_count: number;
  avg_ticket: number;
}

interface PaymentBreakdown {
  method: string;
  order_count: number;
  total_sales: number;
  total_cost: number;
  profit: number;
}

interface Transaction {
  id: number;
  order_number: string;
  customer_name: string | null;
  total: number;
  delivery_fee: number;
  net: number;
  payment_method: string | null;
  product_name: string | null;
  source: string;
  created_at: string;
}


interface TransactionsResponse {
  data: Transaction[];
  total: number;
  page: number;
  per_page: number;
  last_page: number;
}

interface VentasPageProps {
  period?: string;
}

/* ─── Helpers ─── */

const METHOD_META: Record<string, { label: string; icon: typeof CreditCard }> = {
  efectivo: { label: 'Efectivo', icon: Banknote },
  tarjeta: { label: 'Tarjeta', icon: CreditCard },
  transferencia: { label: 'Transferencia', icon: ArrowLeftRight },
  webpay: { label: 'Webpay', icon: Globe },
  pedidosya: { label: 'PedidosYa', icon: Package },
};

const SOURCE_BADGES: Record<string, string> = {
  app: 'bg-blue-100 text-blue-700',
  caja: 'bg-amber-100 text-amber-700',
  pedidosya: 'bg-pink-100 text-pink-700',
};

function formatTime(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
}

function methodLabel(method: string | null): string {
  if (!method) return 'Otro';
  return METHOD_META[method.toLowerCase()]?.label ?? method;
}

/* ─── KPI Card ─── */

function KpiCard({
  label,
  value,
  icon: Icon,
  color,
}: {
  label: string;
  value: string;
  icon: typeof DollarSign;
  color: string;
}) {
  return (
    <div className="bg-white rounded-xl border p-4 flex items-center gap-3">
      <div className={cn('flex items-center justify-center h-10 w-10 rounded-lg shrink-0', color)}>
        <Icon className="h-5 w-5 text-white" />
      </div>
      <div className="min-w-0">
        <p className="text-xs text-gray-500 truncate">{label}</p>
        <p className="text-lg font-bold text-gray-900 truncate">{value}</p>
      </div>
    </div>
  );
}

/* ─── Payment Breakdown ─── */

function PaymentBreakdownPanel({ breakdown }: { breakdown: PaymentBreakdown[] }) {
  const [open, setOpen] = useState(false);

  if (!breakdown.length) return null;

  return (
    <div className="bg-white rounded-xl border">
      <button
        type="button"
        onClick={() => setOpen(v => !v)}
        className="flex items-center justify-between w-full px-4 py-3 text-sm font-medium text-gray-700 min-h-[44px]"
        aria-expanded={open}
      >
        <span>Desglose por método de pago</span>
        {open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>
      {open && (
        <div className="border-t divide-y">
          {breakdown.map(b => {
            const meta = METHOD_META[b.method?.toLowerCase()] ?? { label: b.method || 'Otro', icon: CreditCard };
            const Icon = meta.icon;
            return (
              <div key={b.method} className="flex items-center gap-3 px-4 py-3 text-sm">
                <Icon className="h-4 w-4 text-gray-400 shrink-0" />
                <span className="flex-1 font-medium text-gray-700">{meta.label}</span>
                <span className="text-gray-500">{b.order_count} pedidos</span>
                <span className="font-semibold text-gray-900 min-w-[80px] text-right">{formatCLP(b.total_sales)}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

/* ─── Pagination ─── */

function Pagination({
  page,
  lastPage,
  total,
  onPage,
}: {
  page: number;
  lastPage: number;
  total: number;
  onPage: (p: number) => void;
}) {
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-between px-1 py-3 text-sm">
      <span className="text-gray-500">{total} transacciones</span>
      <div className="flex items-center gap-1">
        <button
          type="button"
          disabled={page <= 1}
          onClick={() => onPage(page - 1)}
          className="p-2 rounded-lg disabled:opacity-30 hover:bg-gray-100 min-h-[44px] min-w-[44px] flex items-center justify-center"
          aria-label="Página anterior"
        >
          <ChevronLeft className="h-4 w-4" />
        </button>
        <span className="px-2 font-medium text-gray-700">{page} / {lastPage}</span>
        <button
          type="button"
          disabled={page >= lastPage}
          onClick={() => onPage(page + 1)}
          className="p-2 rounded-lg disabled:opacity-30 hover:bg-gray-100 min-h-[44px] min-w-[44px] flex items-center justify-center"
          aria-label="Página siguiente"
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}

/* ─── Main Component ─── */

export default function VentasPage({ period = 'shift_today' }: VentasPageProps) {
  const [kpis, setKpis] = useState<Kpis | null>(null);
  const [breakdown, setBreakdown] = useState<PaymentBreakdown[]>([]);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const searchTimer = useRef<ReturnType<typeof setTimeout>>();
  const debouncedSearch = useRef(search);

  /* ── Fetch KPIs ── */
  const fetchKpis = useCallback(async () => {
    try {
      const res = await apiFetch<{ success: boolean; data: { kpis: Kpis; payment_breakdown: PaymentBreakdown[] } }>(
        `/admin/ventas/kpis?period=${period}`,
      );
      if (res.success) {
        setKpis(res.data.kpis);
        setBreakdown(res.data.payment_breakdown);
      }
    } catch {
      /* silent — KPIs are non-critical */
    }
  }, [period]);

  /* ── Fetch Transactions ── */
  const fetchTransactions = useCallback(async (p: number, q: string) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        period,
        page: String(p),
        per_page: '50',
      });
      if (q) params.set('search', q);
      const res = await apiFetch<{ success: boolean; data: TransactionsResponse }>(
        `/admin/ventas?${params}`,
      );
      if (res.success) {
        setTransactions(res.data.data);
        setTotal(res.data.total);
        setPage(res.data.page);
        setLastPage(res.data.last_page);
      }
    } catch {
      /* silent */
    } finally {
      setLoading(false);
    }
  }, [period]);

  /* ── Initial load + period change ── */
  useEffect(() => {
    setPage(1);
    debouncedSearch.current = '';
    setSearch('');
    fetchKpis();
    fetchTransactions(1, '');
  }, [period, fetchKpis, fetchTransactions]);

  /* ── Debounced search ── */
  const handleSearch = useCallback((value: string) => {
    setSearch(value);
    clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      debouncedSearch.current = value;
      setPage(1);
      fetchTransactions(1, value);
    }, 400);
  }, [fetchTransactions]);

  /* ── Page change ── */
  const handlePage = useCallback((p: number) => {
    fetchTransactions(p, debouncedSearch.current);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [fetchTransactions]);

  /* ── Task 7.3: Realtime via Echo/Reverb ── */
  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    const channel = echo.channel('admin.ventas');

    channel.listen('.venta.nueva', (payload: { kpis?: Kpis; timestamp?: string }) => {
      // Refresh KPIs from payload or refetch
      if (payload.kpis) {
        setKpis(payload.kpis);
      } else {
        fetchKpis();
      }
      // Refetch transactions to prepend new sale (page 1 only)
      if (page === 1 && !debouncedSearch.current) {
        fetchTransactions(1, '');
      }
    });

    return () => {
      echo.leave('admin.ventas');
    };
  }, [fetchKpis, fetchTransactions, page]);

  /* ── Render ── */
  return (
    <div className="space-y-4">
      {/* KPI Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <KpiCard
          label="Ventas"
          value={kpis ? formatCLP(kpis.total_sales) : '—'}
          icon={DollarSign}
          color="bg-green-500"
        />
        <KpiCard
          label="Costo"
          value={kpis ? formatCLP(kpis.total_cost) : '—'}
          icon={ShoppingCart}
          color="bg-red-500"
        />
        <KpiCard
          label="Utilidad"
          value={kpis ? formatCLP(kpis.total_profit) : '—'}
          icon={TrendingUp}
          color={kpis && kpis.total_profit >= 0 ? 'bg-emerald-500' : 'bg-red-500'}
        />
        <KpiCard
          label="Pedidos"
          value={kpis ? String(kpis.order_count) : '—'}
          icon={Package}
          color="bg-blue-500"
        />
      </div>

      {/* Payment Breakdown */}
      <PaymentBreakdownPanel breakdown={breakdown} />

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <input
          type="search"
          value={search}
          onChange={e => handleSearch(e.target.value)}
          placeholder="Buscar por nombre o #orden..."
          className="w-full pl-10 pr-4 py-2.5 rounded-xl border bg-white text-sm focus:outline-none focus:ring-2 focus:ring-green-500 min-h-[44px]"
          aria-label="Buscar transacciones"
        />
      </div>

      {/* Loading */}
      {loading && (
        <div className="flex justify-center py-8" role="status" aria-label="Cargando transacciones">
          <Loader2 className="h-6 w-6 animate-spin text-green-500" />
        </div>
      )}

      {/* Transactions — Mobile Cards */}
      {!loading && (
        <div className="md:hidden space-y-2">
          {transactions.length === 0 && (
            <p className="text-center text-sm text-gray-400 py-8">Sin transacciones</p>
          )}
          {transactions.map(tx => (
            <div key={tx.id} className="bg-white rounded-xl border p-3 space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-xs font-mono text-gray-400">#{tx.order_number}</span>
                <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', SOURCE_BADGES[tx.source] || 'bg-gray-100 text-gray-600')}>
                  {tx.source}
                </span>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-gray-800 truncate">{tx.customer_name || 'Sin nombre'}</span>
                <span className="text-sm font-bold text-gray-900">{formatCLP(tx.net)}</span>
              </div>
              <div className="flex items-center justify-between text-xs text-gray-500">
                <span>{methodLabel(tx.payment_method)}</span>
                <span>{formatTime(tx.created_at)}</span>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Transactions — Desktop Table */}
      {!loading && (
        <div className="hidden md:block bg-white rounded-xl border overflow-hidden">
          <table className="w-full text-sm" role="table">
            <thead>
              <tr className="border-b bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                <th className="px-4 py-3">#Orden</th>
                <th className="px-4 py-3">Cliente</th>
                <th className="px-4 py-3 text-right">Monto</th>
                <th className="px-4 py-3 text-right">Delivery</th>
                <th className="px-4 py-3">Pago</th>
                <th className="px-4 py-3">Fuente</th>
                <th className="px-4 py-3 text-right">Hora</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {transactions.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-gray-400">Sin transacciones</td>
                </tr>
              )}
              {transactions.map(tx => (
                <tr key={tx.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs text-gray-500">{tx.order_number}</td>
                  <td className="px-4 py-3 text-gray-800 truncate max-w-[180px]">{tx.customer_name || 'Sin nombre'}</td>
                  <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCLP(tx.net)}</td>
                  <td className="px-4 py-3 text-right text-gray-500">{tx.delivery_fee > 0 ? formatCLP(tx.delivery_fee) : '—'}</td>
                  <td className="px-4 py-3 text-gray-600">{methodLabel(tx.payment_method)}</td>
                  <td className="px-4 py-3">
                    <span className={cn('text-xs px-2 py-0.5 rounded-full font-medium', SOURCE_BADGES[tx.source] || 'bg-gray-100 text-gray-600')}>
                      {tx.source}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right text-gray-500">{formatTime(tx.created_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      <Pagination page={page} lastPage={lastPage} total={total} onPage={handlePage} />
    </div>
  );
}
