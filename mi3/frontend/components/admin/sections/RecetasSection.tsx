'use client';

import { useState, lazy, Suspense } from 'react';
import { List, DollarSign, TrendingUp, PackageSearch, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const RecetasListTab = lazy(() => import('@/app/admin/recetas/page'));
const AjusteMasivoTab = lazy(() => import('@/app/admin/recetas/ajuste-masivo/page'));
const RecomendacionesTab = lazy(() => import('@/app/admin/recetas/recomendaciones/page'));
const AuditoriaTab = lazy(() => import('@/app/admin/recetas/auditoria/page'));

const tabs = [
  { key: 'listado', label: 'Recetas', icon: List },
  { key: 'ajuste-masivo', label: 'Ajuste Masivo', icon: DollarSign },
  { key: 'recomendaciones', label: 'Recomendaciones', icon: TrendingUp },
  { key: 'auditoria', label: 'Auditoría Stock', icon: PackageSearch },
] as const;

type TabKey = typeof tabs[number]['key'];

function TabSkeleton() {
  return (
    <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
      <Loader2 className="h-6 w-6 animate-spin text-red-500" />
    </div>
  );
}

export default function RecetasSection() {
  const [activeTab, setActiveTab] = useState<TabKey>('listado');

  return (
    <div className="space-y-4">
      <h1 className="hidden md:block text-2xl font-bold text-gray-900">Recetas</h1>
      <nav className="flex items-center gap-1 overflow-x-auto rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Secciones de recetas">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            role="tab"
            aria-selected={activeTab === key}
            onClick={() => setActiveTab(key)}
            className={cn(
              'flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors min-h-[44px]',
              activeTab === key
                ? 'bg-red-500 text-white shadow-sm'
                : 'text-gray-600 hover:bg-white hover:text-gray-900'
            )}
          >
            <Icon className="h-4 w-4" />
            <span>{label}</span>
          </button>
        ))}
      </nav>
      <Suspense fallback={<TabSkeleton />}>
        {activeTab === 'listado' && <RecetasListTab />}
        {activeTab === 'ajuste-masivo' && <AjusteMasivoTab />}
        {activeTab === 'recomendaciones' && <RecomendacionesTab />}
        {activeTab === 'auditoria' && <AuditoriaTab />}
      </Suspense>
    </div>
  );
}
