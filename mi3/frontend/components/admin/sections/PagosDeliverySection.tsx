import React, { useState, useCallback, useEffect, Suspense } from 'react';
import { DollarSign, History, Upload, Sun, Moon } from 'lucide-react';

interface SectionProps {
  onHeaderConfig?: (config: any) => void;
}

function TabSkeleton() {
  return (
    <div className="animate-pulse space-y-4 p-4">
      <div className="h-8 w-48 rounded bg-white/10" />
      <div className="h-64 rounded-xl bg-white/5" />
    </div>
  );
}

type TabKey = 'liquidaciones' | 'historial';

const tabs = [
  { key: 'liquidaciones', label: 'Liquidaciones', icon: DollarSign },
  { key: 'historial', label: 'Historial Pagos', icon: History },
];

const LiquidacionesPanel = React.lazy(() => import('@/components/admin/pagos-delivery/LiquidacionesPanel'));
const HistorialPagos = React.lazy(() => import('@/components/admin/pagos-delivery/HistorialPagos'));

export default function PagosDeliverySection({ onHeaderConfig }: SectionProps) {
  const [activeTab, setActiveTab] = useState<TabKey>('liquidaciones');

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as TabKey);
  }, []);

  useEffect(() => {
    onHeaderConfig?.({
      tabs,
      activeTab,
      onTabChange: handleTabChange,
      accent: 'purple',
      version: 'v1',
    });
  }, [activeTab, handleTabChange, onHeaderConfig]);

  return (
    <div className="p-2 sm:p-4 space-y-4">
      <Suspense fallback={<TabSkeleton />}>
        {activeTab === 'liquidaciones' && <LiquidacionesPanel />}
        {activeTab === 'historial' && <HistorialPagos />}
      </Suspense>
    </div>
  );
}
