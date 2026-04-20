'use client';

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import { List, DollarSign, TrendingUp, PackageSearch, Layers, Loader2 } from 'lucide-react';
import type { SectionHeaderConfig, TabDef } from '@/components/admin/AdminShell';

const RecetasListTab = lazy(() => import('@/app/admin/recetas/page'));
const AjusteMasivoTab = lazy(() => import('@/app/admin/recetas/ajuste-masivo/page'));
const RecomendacionesTab = lazy(() => import('@/app/admin/recetas/recomendaciones/page'));
const AuditoriaTab = lazy(() => import('@/app/admin/recetas/auditoria/page'));
const SubRecetasTab = lazy(() => import('@/app/admin/recetas/sub-recetas/page'));

const tabs: TabDef[] = [
  { key: 'listado', label: 'Recetas', icon: List },
  { key: 'ajuste-masivo', label: 'Ajuste Masivo', icon: DollarSign },
  { key: 'recomendaciones', label: 'Recomendaciones', icon: TrendingUp },
  { key: 'auditoria', label: 'Auditoría Stock', icon: PackageSearch },
  { key: 'sub-recetas', label: 'Sub-Recetas', icon: Layers },
];

type TabKey = 'listado' | 'ajuste-masivo' | 'recomendaciones' | 'auditoria' | 'sub-recetas';

function TabSkeleton() {
  return (
    <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
      <Loader2 className="h-6 w-6 animate-spin text-red-500" />
    </div>
  );
}

interface RecetasSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

export default function RecetasSection({ onHeaderConfig }: RecetasSectionProps) {
  const [activeTab, setActiveTab] = useState<TabKey>('listado');

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as TabKey);
  }, []);

  useEffect(() => {
    onHeaderConfig?.({
      tabs,
      activeTab,
      onTabChange: handleTabChange,
      accent: 'red',
    });
  }, [activeTab, handleTabChange, onHeaderConfig]);

  return (
    <div className="pt-4">
      <Suspense fallback={<TabSkeleton />}>
        {activeTab === 'listado' && <RecetasListTab />}
        {activeTab === 'ajuste-masivo' && <AjusteMasivoTab />}
        {activeTab === 'recomendaciones' && <RecomendacionesTab />}
        {activeTab === 'auditoria' && <AuditoriaTab />}
        {activeTab === 'sub-recetas' && <SubRecetasTab />}
      </Suspense>
    </div>
  );
}
