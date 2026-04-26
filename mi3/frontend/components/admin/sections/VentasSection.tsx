'use client';

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import { Clock, CalendarDays, CalendarRange, CalendarCheck, Loader2 } from 'lucide-react';
import type { SectionHeaderConfig, TabDef } from '@/components/admin/AdminShell';

const VentasPage = lazy(() => import('@/app/admin/ventas/page'));

const tabs: TabDef[] = [
  { key: 'shift_today', label: 'Turno', icon: Clock },
  { key: 'today', label: 'Hoy', icon: CalendarDays },
  { key: 'week', label: 'Semana', icon: CalendarRange },
  { key: 'month', label: 'Mes', icon: CalendarCheck },
];

type TabKey = 'shift_today' | 'today' | 'week' | 'month';

function TabSkeleton() {
  return (
    <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
      <Loader2 className="h-6 w-6 animate-spin text-red-500" />
    </div>
  );
}

interface VentasSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

export default function VentasSection({ onHeaderConfig }: VentasSectionProps) {
  const [activeTab, setActiveTab] = useState<TabKey>('shift_today');

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as TabKey);
  }, []);

  useEffect(() => {
    onHeaderConfig?.({
      tabs,
      activeTab,
      onTabChange: handleTabChange,
      accent: 'green',
    });
  }, [activeTab, handleTabChange, onHeaderConfig]);

  return (
    <div className="pt-4">
      <Suspense fallback={<TabSkeleton />}>
        <VentasPage period={activeTab} />
      </Suspense>
    </div>
  );
}
