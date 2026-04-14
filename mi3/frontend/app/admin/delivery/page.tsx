'use client';

import { useState } from 'react';
import { ChevronDown, ChevronUp, WifiOff } from 'lucide-react';
import dynamic from 'next/dynamic';
import { useDeliveryTracking } from '@/hooks/useDeliveryTracking';
import DeliveryMetrics from '@/components/admin/delivery/DeliveryMetrics';
import OrderPanel from '@/components/admin/delivery/OrderPanel';
import SettlementPanel from '@/components/admin/delivery/SettlementPanel';

// Load map client-side only (Google Maps requires browser APIs)
const DeliveryMap = dynamic(
  () => import('@/components/admin/delivery/DeliveryMap'),
  { ssr: false, loading: () => <div className="h-full w-full rounded-xl bg-gray-100 animate-pulse" /> }
);

export default function DeliveryMonitorPage() {
  const { orders, riders, metrics, isConnected, assignRider, updateStatus } = useDeliveryTracking();
  const [settlementsOpen, setSettlementsOpen] = useState(false);

  return (
    <div className="flex flex-col gap-4 h-full min-h-screen">
      {/* Reconnecting banner */}
      {!isConnected && (
        <div className="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-700">
          <WifiOff className="h-4 w-4 shrink-0" />
          <span>Reconectando al servidor en tiempo real...</span>
        </div>
      )}

      {/* Metrics row */}
      <DeliveryMetrics metrics={metrics} isConnected={isConnected} />

      {/* Main content: map + panel */}
      <div className="flex flex-col md:flex-row gap-4 flex-1 min-h-[500px]">
        {/* Map — 2/3 width on desktop */}
        <div className="flex-1 md:w-2/3 min-h-[400px] md:min-h-0">
          <DeliveryMap
            orders={orders}
            riders={riders}
            onAssignRider={assignRider}
          />
        </div>

        {/* Order panel — 1/3 width on desktop */}
        <div className="md:w-1/3 min-h-[300px] md:min-h-0">
          <OrderPanel
            orders={orders}
            riders={riders}
            onAssignRider={assignRider}
            onUpdateStatus={updateStatus}
          />
        </div>
      </div>

      {/* Settlements collapsible section */}
      <div className="rounded-xl border bg-white shadow-sm overflow-hidden">
        <button
          onClick={() => setSettlementsOpen((v) => !v)}
          className="flex w-full items-center justify-between px-5 py-4 text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors"
        >
          <span>Liquidaciones ARIAKA</span>
          {settlementsOpen ? (
            <ChevronUp className="h-4 w-4 text-gray-500" />
          ) : (
            <ChevronDown className="h-4 w-4 text-gray-500" />
          )}
        </button>

        {settlementsOpen && (
          <div className="px-5 pb-5">
            <SettlementPanel />
          </div>
        )}
      </div>
    </div>
  );
}
