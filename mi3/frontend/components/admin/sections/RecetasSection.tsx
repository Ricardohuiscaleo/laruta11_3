'use client';

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import { List, Replace, TrendingUp, PackageSearch, Layers, Loader2, Scale, Sparkles, Package, Wine } from 'lucide-react';
import type { SectionHeaderConfig, TabDef } from '@/components/admin/AdminShell';

const RecetasListTab = lazy(() => import('@/app/admin/recetas/page'));
const AjusteMasivoTab = lazy(() => import('@/app/admin/recetas/ajuste-masivo/page'));
const RecomendacionesTab = lazy(() => import('@/app/admin/recetas/recomendaciones/page'));
const AuditoriaTab = lazy(() => import('@/app/admin/recetas/auditoria/page'));
const SubRecetasTab = lazy(() => import('@/app/admin/recetas/sub-recetas/page'));
const PorcionesTab = lazy(() => import('@/app/admin/recetas/porciones/page'));
const CreadorIATab = lazy(() => import('@/app/admin/recetas/creador-ia/page'));
const CombosTab = lazy(() => import('@/app/admin/recetas/combos/page'));
const BebidasTab = lazy(() => import('@/app/admin/recetas/bebidas/page'));

const tabs: TabDef[] = [
  { key: 'listado', label: 'Recetas', icon: List },
  { key: 'bebidas', label: 'Bebidas', icon: Wine },
  { key: 'combos', label: 'Combos', icon: Package },
  { key: 'porciones', label: 'Porciones', icon: Scale },
  { key: 'creador-ia', label: 'Creador IA', icon: Sparkles },
  { key: 'ajuste-masivo', label: 'Reemplazo', icon: Replace },
  { key: 'recomendaciones', label: 'Recomendaciones', icon: TrendingUp },
  { key: 'auditoria', label: 'Auditoría Stock', icon: PackageSearch },
  { key: 'sub-recetas', label: 'Sub-Recetas', icon: Layers },
];

type TabKey = 'listado' | 'bebidas' | 'combos' | 'porciones' | 'creador-ia' | 'ajuste-masivo' | 'recomendaciones' | 'auditoria' | 'sub-recetas';

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
        {activeTab === 'bebidas' && <BebidasTab />}
        {activeTab === 'combos' && <CombosTab />}
        {activeTab === 'porciones' && <PorcionesTab />}
        {activeTab === 'creador-ia' && <CreadorIATab />}
        {activeTab === 'ajuste-masivo' && <AjusteMasivoTab />}
        {activeTab === 'recomendaciones' && <RecomendacionesTab />}
        {activeTab === 'auditoria' && <AuditoriaTab />}
        {activeTab === 'sub-recetas' && <SubRecetasTab />}
      </Suspense>
    </div>
  );
}
