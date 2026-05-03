'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  DollarSign, TrendingUp, ShoppingCart, Search,
  ChevronDown, ChevronUp, CreditCard, Banknote,
  ArrowLeftRight, Globe, Package, Loader2,
  ChevronLeft, ChevronRight, AlertTriangle, CheckCircle2,
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

/* ─── Order Detail Types ─── */

interface IngredientConsumption {
  ingredient_name: string;
  quantity_used: number;
  unit: string;
  stock_before: number | null;
  stock_after: number | null;
  stock_status: 'ok' | 'warning';
}

interface OrderDetailItem {
  product_name: string;
  image_url?: string | null;
  quantity: number;
  unit_price: number;
  item_cost: number;
  profit: number;
  ingredients: IngredientConsumption[];
}

interface DispatchPhoto {
  type: string;
  url: string;
  verification?: {
    aprobado: boolean;
    puntaje: number;
    feedback: string;
  } | null;
}

interface OrderDetail {
  order_number: string;
  created_at: string;
  customer_name: string | null;
  payment_method: string | null;
  dispatch_photos: DispatchPhoto[];
  items: OrderDetailItem[];
  totals: {
    subtotal: number;
    delivery_fee: number;
    total: number;
    total_cost: number;
    total_profit: number;
  };
}

/* ─── Helpers ─── */

const METHOD_META: Record<string, { label: string; icon: typeof CreditCard }> = {
  efectivo: { label: 'Efectivo', icon: Banknote },
  tarjeta: { label: 'Tarjeta', icon: CreditCard },
  transferencia: { label: 'Transferencia', icon: ArrowLeftRight },
  webpay: { label: 'Webpay', icon: Globe },
  pedidosya: { label: 'PedidosYa', icon: Package },
};

function formatChileDateTime(dateStr: string): string {
  const d = new Date(dateStr);
  return new Intl.DateTimeFormat('es-CL', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'America/Santiago',
  }).format(d);
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

/* ─── Order Detail Panel ─── */

function OrderDetailPanel({ detail }: { detail: OrderDetail }) {
  return (
    <div className="space-y-3 p-3 bg-gray-50 rounded-lg">
      {/* Header — compact */}
      <div className="flex items-center gap-2 text-xs text-gray-500">
        <span className="font-semibold text-gray-900 text-sm">#{detail.order_number}</span>
        <span>·</span>
        <span>{formatChileDateTime(detail.created_at)}</span>
        <span>·</span>
        <span>{methodLabel(detail.payment_method)}</span>
        {detail.customer_name && (
          <>
            <span>·</span>
            <span className="text-gray-700">{detail.customer_name}</span>
          </>
        )}
      </div>

      {/* Items */}
      {detail.items.length === 0 ? (
        <p className="text-sm text-gray-400">Sin ítems</p>
      ) : (
        <div className="space-y-2">
          {detail.items.map((item, idx) => (
            <div key={idx} className="bg-white rounded-lg border overflow-hidden">
              {/* Item header row */}
              <div className="flex items-center justify-between px-3 py-2 text-sm">
                <div className="flex items-center gap-2">
                  {item.image_url && (
                    <img
                      src={item.image_url}
                      alt={item.product_name}
                      className="w-8 h-8 rounded object-cover shrink-0"
                    />
                  )}
                  <span className="font-medium text-gray-800">
                    {item.product_name} <span className="text-gray-400">×{item.quantity}</span>
                  </span>
                </div>
                <div className="flex items-center gap-3 text-xs shrink-0">
                  <span className="text-gray-600">{formatCLP(item.unit_price)}</span>
                  <span className="text-gray-400">C: {formatCLP(item.item_cost)}</span>
                  <span className={cn('font-semibold', item.profit >= 0 ? 'text-green-600' : 'text-red-600')}>
                    {formatCLP(item.profit)}
                  </span>
                </div>
              </div>

              {/* Ingredients table — compact with stock columns */}
              {item.ingredients.length > 0 && (
                <div className="border-t bg-gray-50/50">
                  <table className="w-full text-xs" role="table">
                    <thead>
                      <tr className="text-gray-400 text-[10px] uppercase tracking-wider">
                        <th className="text-left px-3 py-1 font-medium">Ingrediente</th>
                        <th className="text-right px-2 py-1 font-medium">Antes</th>
                        <th className="text-right px-2 py-1 font-medium">Consumo</th>
                        <th className="text-right px-2 py-1 font-medium">Después</th>
                        <th className="text-center px-1 py-1 font-medium w-6"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {item.ingredients.map((ing, iIdx) => (
                        <tr key={iIdx} className="text-gray-600">
                          <td className="px-3 py-1 font-medium text-gray-700">{ing.ingredient_name}</td>
                          <td className="text-right px-2 py-1 text-gray-400 tabular-nums">
                            {ing.stock_before != null ? `${ing.stock_before}` : '—'}
                          </td>
                          <td className="text-right px-2 py-1 font-medium text-amber-600 tabular-nums">
                            -{ing.quantity_used} {ing.unit}
                          </td>
                          <td className="text-right px-2 py-1 tabular-nums">
                            {ing.stock_after != null ? `${ing.stock_after}` : '—'}
                          </td>
                          <td className="text-center px-1 py-1">
                            {ing.stock_status === 'warning' ? (
                              <AlertTriangle className="h-3 w-3 text-amber-500 inline" aria-label="Stock bajo" />
                            ) : (
                              <CheckCircle2 className="h-3 w-3 text-green-500 inline" aria-label="Stock OK" />
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Footer totals — compact inline */}
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs border-t pt-2">
        <span className="text-gray-500">Subtotal: <span className="font-bold text-gray-900">{formatCLP(detail.totals.subtotal)}</span></span>
        {detail.totals.delivery_fee > 0 && (
          <span className="text-gray-500">Delivery: <span className="font-bold text-gray-900">{formatCLP(detail.totals.delivery_fee)}</span></span>
        )}
        {detail.totals.delivery_fee > 0 && (
          <span className="text-gray-500">Total: <span className="font-bold text-gray-900">{formatCLP(detail.totals.total)}</span></span>
        )}
        <span className="text-gray-500">Costo: <span className="font-bold text-gray-900">{formatCLP(detail.totals.total_cost)}</span></span>
        <span className="text-gray-500">Utilidad: <span className={cn('font-bold', detail.totals.total_profit >= 0 ? 'text-green-600' : 'text-red-600')}>{formatCLP(detail.totals.total_profit)}</span></span>
      </div>

      {/* Dispatch Photos + AI Feedback */}
      {detail.dispatch_photos && detail.dispatch_photos.length > 0 && (
        <div className="border-t pt-2 mt-2">
          <p className="text-[10px] font-black text-gray-400 uppercase tracking-wider mb-2">📷 Fotos de Entrega</p>
          <div className="flex flex-wrap gap-3">
            {detail.dispatch_photos.map((photo, idx) => (
              <div key={idx} className="flex flex-col gap-1">
                <div className={cn(
                  'w-20 h-20 rounded-lg overflow-hidden border-2 shadow-sm',
                  photo.verification ? (photo.verification.aprobado ? 'border-green-400' : 'border-amber-400') : 'border-gray-200'
                )}>
                  <img src={photo.url} alt={`${photo.type}-${idx}`} className="w-full h-full object-cover" />
                </div>
                <span className="text-[9px] text-gray-400 capitalize text-center">{photo.type}</span>
              </div>
            ))}
          </div>
          {detail.dispatch_photos.some(p => p.verification) && (
            <div className="mt-2 space-y-1">
              {detail.dispatch_photos.filter(p => p.verification).map((photo, idx) => (
                <div key={idx} className={cn(
                  'text-[11px] px-2 py-1.5 rounded',
                  photo.verification!.aprobado ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'
                )}>
                  <span className="font-bold capitalize">{photo.type}:</span> {photo.verification!.feedback}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
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

  /* ── Expand/collapse detail state ── */
  const [expandedOrder, setExpandedOrder] = useState<string | null>(null);
  const [orderDetail, setOrderDetail] = useState<OrderDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);

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

  /* ── Toggle order detail expand/collapse ── */
  const handleToggleDetail = useCallback(async (orderNumber: string) => {
    if (expandedOrder === orderNumber) {
      setExpandedOrder(null);
      setOrderDetail(null);
      setDetailError(null);
      return;
    }
    setExpandedOrder(orderNumber);
    setOrderDetail(null);
    setDetailError(null);
    setDetailLoading(true);
    try {
      const res = await apiFetch<{ success: boolean; data: OrderDetail }>(
        `/admin/ventas/${orderNumber}/detail`,
      );
      if (res.success) {
        setOrderDetail(res.data);
      } else {
        setDetailError('Error al cargar el detalle');
      }
    } catch {
      setDetailError('Error al cargar el detalle');
    } finally {
      setDetailLoading(false);
    }
  }, [expandedOrder]);

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
            <div key={tx.id}>
              <div
                className="bg-white rounded-xl border p-3 space-y-1 cursor-pointer"
                onClick={() => handleToggleDetail(tx.order_number)}
                role="button"
                aria-expanded={expandedOrder === tx.order_number}
              >
                <div className="flex items-center justify-between">
                  <span className="text-xs font-mono text-gray-400 inline-flex items-center gap-1">
                    {expandedOrder === tx.order_number
                      ? <ChevronDown className="h-3 w-3" />
                      : <ChevronRight className="h-3 w-3" />}
                    #{tx.order_number}
                  </span>
                  <span className="text-xs text-gray-500">{formatChileDateTime(tx.created_at)}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-gray-800 truncate">{tx.customer_name || 'Sin nombre'}</span>
                  <span className="text-sm font-bold text-gray-900">{formatCLP(tx.net)}</span>
                </div>
                <div className="flex items-center justify-between text-xs text-gray-500">
                  <span>{methodLabel(tx.payment_method)}</span>
                </div>
              </div>
              {expandedOrder === tx.order_number && (
                <div className="bg-white rounded-b-xl border border-t-0 px-3 py-3">
                  {detailLoading && (
                    <div className="flex justify-center py-4" role="status" aria-label="Cargando detalle">
                      <Loader2 className="h-5 w-5 animate-spin text-green-500" />
                    </div>
                  )}
                  {detailError && (
                    <div className="flex items-center justify-center gap-2 py-4 text-sm text-red-500">
                      <AlertTriangle className="h-4 w-4" />
                      <span>{detailError}</span>
                    </div>
                  )}
                  {orderDetail && <OrderDetailPanel detail={orderDetail} />}
                </div>
              )}
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
                <th className="px-4 py-3 text-right">Fecha</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {transactions.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-gray-400">Sin transacciones</td>
                </tr>
              )}
              {transactions.map(tx => (
                <React.Fragment key={tx.id}>
                  <tr
                    className="hover:bg-gray-50 transition-colors cursor-pointer"
                    onClick={() => handleToggleDetail(tx.order_number)}
                    role="button"
                    aria-expanded={expandedOrder === tx.order_number}
                  >
                    <td className="px-4 py-3 font-mono text-xs text-gray-500">
                      <span className="inline-flex items-center gap-1">
                        {expandedOrder === tx.order_number
                          ? <ChevronDown className="h-3.5 w-3.5 text-gray-400" />
                          : <ChevronRight className="h-3.5 w-3.5 text-gray-400" />}
                        {tx.order_number}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-800 truncate max-w-[180px]">{tx.customer_name || 'Sin nombre'}</td>
                    <td className="px-4 py-3 text-right font-semibold text-gray-900">{formatCLP(tx.net)}</td>
                    <td className="px-4 py-3 text-right text-gray-500">{tx.delivery_fee > 0 ? formatCLP(tx.delivery_fee) : '—'}</td>
                    <td className="px-4 py-3 text-gray-600">{methodLabel(tx.payment_method)}</td>
                    <td className="px-4 py-3 text-right text-gray-500">{formatChileDateTime(tx.created_at)}</td>
                  </tr>
                  {expandedOrder === tx.order_number && (
                    <tr>
                      <td colSpan={6} className="px-4 py-3 bg-white">
                        {detailLoading && (
                          <div className="flex justify-center py-6" role="status" aria-label="Cargando detalle">
                            <Loader2 className="h-5 w-5 animate-spin text-green-500" />
                          </div>
                        )}
                        {detailError && (
                          <div className="flex items-center justify-center gap-2 py-6 text-sm text-red-500">
                            <AlertTriangle className="h-4 w-4" />
                            <span>{detailError}</span>
                          </div>
                        )}
                        {orderDetail && <OrderDetailPanel detail={orderDetail} />}
                      </td>
                    </tr>
                  )}
                </React.Fragment>
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
