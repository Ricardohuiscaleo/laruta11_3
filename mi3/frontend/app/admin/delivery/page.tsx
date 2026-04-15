'use client';

import { useState } from 'react';
import { ChevronDown, ChevronUp, WifiOff, ChevronRight } from 'lucide-react';
import dynamic from 'next/dynamic';
import { useDeliveryTracking } from '@/hooks/useDeliveryTracking';
import DeliveryMetrics from '@/components/admin/delivery/DeliveryMetrics';
import OrderPanel from '@/components/admin/delivery/OrderPanel';
import SettlementPanel from '@/components/admin/delivery/SettlementPanel';

const DeliveryMap = dynamic(
  () => import('@/components/admin/delivery/DeliveryMap'),
  { ssr: false, loading: () => <div className="h-full w-full bg-gray-100 animate-pulse" /> }
);

export default function DeliveryMonitorPage() {
  const { orders, riders, metrics, isConnected, assignRider, updateStatus } = useDeliveryTracking();
  const [panelOpen, setPanelOpen] = useState(false);
  const [settlementsOpen, setSettlementsOpen] = useState(false);

  return (
    <>
      {/* ── MOBILE LAYOUT ── */}
      <div className="md:hidden flex flex-col h-[calc(100vh-7rem)]">
        {/* Reconnecting banner */}
        {!isConnected && (
          <div className="flex items-center gap-2 bg-amber-50 border-b border-amber-200 px-3 py-1.5 text-xs text-amber-700 shrink-0">
            <WifiOff className="h-3 w-3 shrink-0" />
            <span>Reconectando...</span>
          </div>
        )}

        {/* Compact metrics bar */}
        <DeliveryMetrics metrics={metrics} isConnected={isConnected} compact />

        {/* Map — fills remaining space */}
        <div className="flex-1 relative">
          <DeliveryMap orders={orders} riders={riders} onAssignRider={assignRider} />

          {/* Floating orders button */}
          <button
            onClick={() => setPanelOpen(true)}
            className="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-white rounded-full shadow-lg px-4 py-2.5 text-sm font-semibold text-gray-800 border border-gray-200"
          >
            <span>Pedidos ({orders.length})</span>
            <ChevronRight className="h-4 w-4 text-gray-500" />
          </button>
        </div>

        {/* Bottom sheet overlay */}
        {panelOpen && (
          <div className="fixed inset-0 z-50 flex flex-col justify-end">
            <div className="absolute inset-0 bg-black/40" onClick={() => setPanelOpen(false)} />
            <div className="relative bg-white rounded-t-2xl max-h-[70vh] flex flex-col">
              <div className="flex justify-center pt-3 pb-1 shrink-0">
                <div className="w-10 h-1 rounded-full bg-gray-300" />
              </div>
              <div className="flex-1 overflow-hidden">
                <OrderPanel
                  orders={orders}
                  riders={riders}
                  onAssignRider={assignRider}
                  onUpdateStatus={updateStatus}
                />
              </div>
            </div>
          </div>
        )}
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
            <OrderPanel
              orders={orders}
              riders={riders}
              onAssignRider={assignRider}
              onUpdateStatus={updateStatus}
            />
          </div>
        </div>

        <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
          <button
            onClick={() => setSettlementsOpen((v) => !v)}
            className="flex w-full items-center justify-between px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors"
          >
            <span>Liquidaciones ARIAKA</span>
            {settlementsOpen ? <ChevronUp className="h-4 w-4 text-gray-500" /> : <ChevronDown className="h-4 w-4 text-gray-500" />}
          </button>
          {settlementsOpen && (
            <div className="px-5 pb-5">
              <SettlementPanel />
            </div>
          )}
        </div>
      </div>
    </>
  );
}
