'use client';

import { useState, useRef, useCallback } from 'react';
import { ChevronDown, ChevronUp, WifiOff } from 'lucide-react';
import dynamic from 'next/dynamic';
import { useDeliveryTracking } from '@/hooks/useDeliveryTracking';
import DeliveryMetrics from '@/components/admin/delivery/DeliveryMetrics';
import OrderPanel from '@/components/admin/delivery/OrderPanel';
import SettlementPanel from '@/components/admin/delivery/SettlementPanel';

const DeliveryMap = dynamic(
  () => import('@/components/admin/delivery/DeliveryMap'),
  { ssr: false, loading: () => <div className="h-full w-full bg-gray-100 animate-pulse" /> }
);

type ModalType = 'orders' | 'riders' | 'onroute' | 'cashflow' | null;

function BottomSheet({ open, onClose, children, title }: {
  open: boolean; onClose: () => void; children: React.ReactNode; title: string;
}) {
  const sheetRef = useRef<HTMLDivElement>(null);
  const startY = useRef(0);
  const currentY = useRef(0);

  const onTouchStart = useCallback((e: React.TouchEvent) => {
    startY.current = e.touches[0].clientY;
    currentY.current = 0;
  }, []);

  const onTouchMove = useCallback((e: React.TouchEvent) => {
    const dy = e.touches[0].clientY - startY.current;
    if (dy > 0 && sheetRef.current) {
      currentY.current = dy;
      sheetRef.current.style.transform = `translateY(${dy}px)`;
    }
  }, []);

  const onTouchEnd = useCallback(() => {
    if (currentY.current > 100) {
      onClose();
    }
    if (sheetRef.current) {
      sheetRef.current.style.transform = '';
    }
    currentY.current = 0;
  }, [onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[60] flex flex-col justify-end">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div
        ref={sheetRef}
        className="relative bg-white rounded-t-2xl max-h-[75vh] flex flex-col transition-transform"
        onTouchStart={onTouchStart}
        onTouchMove={onTouchMove}
        onTouchEnd={onTouchEnd}
      >
        <div className="flex flex-col items-center pt-3 pb-2 shrink-0 cursor-grab">
          <div className="w-10 h-1 rounded-full bg-gray-300 mb-2" />
          <p className="text-sm font-semibold text-gray-800">{title}</p>
        </div>
        <div className="flex-1 overflow-y-auto">{children}</div>
      </div>
    </div>
  );
}

export default function DeliverySection() {
  const { orders, riders, metrics, isConnected, assignRider, updateStatus } = useDeliveryTracking();
  const [modal, setModal] = useState<ModalType>(null);
  const [settlementsOpen, setSettlementsOpen] = useState(false);

  const [simRunning, setSimRunning] = useState(false);
  const ridersOnRoute = orders.filter(o => o.order_status === 'out_for_delivery');

  return (
    <>
      {/* ── MOBILE LAYOUT ── */}
      <div className="md:hidden flex flex-col h-[calc(100vh-7rem)]">
        {!isConnected && (
          <div className="flex items-center gap-2 bg-amber-50 border-b border-amber-200 px-3 py-1.5 text-xs text-amber-700 shrink-0">
            <WifiOff className="h-3 w-3 shrink-0" />
            <span>Reconectando...</span>
          </div>
        )}

        {/* Compact metrics bar */}
        <div className="flex items-center justify-around bg-white border-b border-gray-100 px-1 py-2 shrink-0">
          <button onClick={() => setModal('orders')} className="flex flex-col items-center gap-0.5 active:scale-95 transition-transform">
            <span className="text-sm font-bold text-blue-600">{metrics.totalActive}</span>
            <span className="text-[10px] text-gray-400">Pedidos</span>
          </button>
          <div className="w-px h-6 bg-gray-200" />
          <button onClick={() => setModal('riders')} className="flex flex-col items-center gap-0.5 active:scale-95 transition-transform">
            <span className="text-sm font-bold text-green-600">{metrics.availableRiders}</span>
            <span className="text-[10px] text-gray-400">Disponibles</span>
          </button>
          <div className="w-px h-6 bg-gray-200" />
          <button onClick={() => setModal('onroute')} className="flex flex-col items-center gap-0.5 active:scale-95 transition-transform">
            <span className="text-sm font-bold text-amber-600">{metrics.ridersOnRoute}</span>
            <span className="text-[10px] text-gray-400">En ruta</span>
          </button>
          <div className="w-px h-6 bg-gray-200" />
          <button onClick={() => setModal('cashflow')} className="flex flex-col items-center gap-0.5 active:scale-95 transition-transform">
            <span className="text-sm font-bold text-purple-600">$</span>
            <span className="text-[10px] text-gray-400">Cashflow</span>
          </button>
          <div className="w-px h-6 bg-gray-200" />
          <div className="flex flex-col items-center gap-0.5">
            <span className={`text-sm font-bold ${isConnected ? 'text-green-500' : 'text-red-500'}`}>●</span>
            <span className="text-[10px] text-gray-400">{isConnected ? 'En línea' : 'Offline'}</span>
          </div>
          <div className="w-px h-6 bg-gray-200" />
          <button
            onClick={async () => {
              if (simRunning) return;
              setSimRunning(true);
              try {
                const { apiFetch } = await import('@/lib/api');
                await apiFetch('/admin/delivery/simulate', { method: 'POST' });
                setTimeout(() => setSimRunning(false), 65000);
              } catch { setSimRunning(false); }
            }}
            disabled={simRunning}
            className="flex flex-col items-center gap-0.5 active:scale-95 transition-transform disabled:opacity-50"
          >
            <span className={`text-sm font-bold ${simRunning ? 'text-gray-400 animate-pulse' : 'text-red-500'}`}>{simRunning ? '⏳' : '▶'}</span>
            <span className="text-[10px] text-gray-400">{simRunning ? 'Sim...' : 'Demo'}</span>
          </button>
        </div>

        {/* Map */}
        <div className="flex-1 relative">
          <DeliveryMap orders={orders} riders={riders} onAssignRider={assignRider} />
        </div>

        {/* Bottom sheets */}
        <BottomSheet open={modal === 'orders'} onClose={() => setModal(null)} title="Pedidos activos">
          <OrderPanel orders={orders} riders={riders} onAssignRider={assignRider} onUpdateStatus={updateStatus} />
        </BottomSheet>

        <BottomSheet open={modal === 'riders'} onClose={() => setModal(null)} title="Riders disponibles">
          <div className="px-4 pb-4 space-y-2">
            {riders.length === 0 ? (
              <p className="text-center text-sm text-gray-400 py-6">Sin riders disponibles</p>
            ) : riders.map(r => (
              <div key={r.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-lg">🛵</div>
                <div>
                  <p className="text-sm font-semibold text-gray-900">{r.nombre}</p>
                  <p className="text-xs text-gray-500">
                    {r.last_lat ? `GPS: ${Number(r.last_lat).toFixed(4)}, ${Number(r.last_lng).toFixed(4)}` : 'Sin GPS'}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </BottomSheet>

        <BottomSheet open={modal === 'onroute'} onClose={() => setModal(null)} title="Riders en ruta">
          <div className="px-4 pb-4 space-y-2">
            {ridersOnRoute.length === 0 ? (
              <p className="text-center text-sm text-gray-400 py-6">Sin riders en ruta</p>
            ) : ridersOnRoute.map(o => (
              <div key={o.id} className="p-3 bg-blue-50 rounded-xl space-y-1">
                <div className="flex justify-between items-center">
                  <span className="text-sm font-semibold text-gray-900">#{o.order_number}</span>
                  <span className="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">En camino</span>
                </div>
                <p className="text-xs text-gray-600">{o.customer_name}</p>
                <p className="text-xs text-gray-500">{o.delivery_address}</p>
                {o.rider_nombre && <p className="text-xs text-blue-600">Rider: {o.rider_nombre}</p>}
                {(o.delivery_distance_km != null || o.delivery_duration_min != null) && (
                  <p className="text-xs text-gray-500">
                    {o.delivery_distance_km != null && `📏 ${o.delivery_distance_km} km`}
                    {o.delivery_distance_km != null && o.delivery_duration_min != null && ' · '}
                    {o.delivery_duration_min != null && `⏱️ ${o.delivery_duration_min} min`}
                  </p>
                )}
                {o.product_price != null && (
                  <p className="text-xs font-semibold text-green-700">
                    💰 {new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(o.product_price)}
                  </p>
                )}
                {o.customer_phone && (
                  <a href={`tel:${o.customer_phone}`} className="inline-flex items-center gap-1 text-xs text-green-600 font-medium" aria-label={`Llamar a ${o.customer_name}`}>
                    📞 Llamar
                  </a>
                )}
              </div>
            ))}
          </div>
        </BottomSheet>

        <BottomSheet open={modal === 'cashflow'} onClose={() => setModal(null)} title="Cashflow — Liquidaciones ARIAKA">
          <div className="px-1 pb-4">
            <SettlementPanel />
          </div>
        </BottomSheet>
      </div>

      {/* ── DESKTOP LAYOUT ── */}
      <div className="hidden md:flex flex-col gap-4 h-full min-h-screen">
        {!isConnected && (
          <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-700">
            <WifiOff className="h-4 w-4 shrink-0" />
            <span>Reconectando al servidor en tiempo real...</span>
          </div>
        )}

        <DeliveryMetrics metrics={metrics} isConnected={isConnected} />

        <div className="flex gap-4 flex-1 min-h-[500px]">
          <div className="flex-1 w-2/3">
            <DeliveryMap orders={orders} riders={riders} onAssignRider={assignRider} />
          </div>
          <div className="w-1/3 min-h-[300px]">
            <OrderPanel orders={orders} riders={riders} onAssignRider={assignRider} onUpdateStatus={updateStatus} />
          </div>
        </div>

        <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
          <button
            onClick={() => setSettlementsOpen(v => !v)}
            className="flex w-full items-center justify-between px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors"
          >
            <span>Cashflow — Liquidaciones ARIAKA</span>
            {settlementsOpen ? <ChevronUp className="h-4 w-4 text-gray-500" /> : <ChevronDown className="h-4 w-4 text-gray-500" />}
          </button>
          {settlementsOpen && (
            <div className="px-5 pb-5"><SettlementPanel /></div>
          )}
        </div>
      </div>
    </>
  );
}
