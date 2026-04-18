'use client';

import { useState, lazy, Suspense } from 'react';
import { Plus, FileText, Package, TrendingUp, BarChart3, Loader2, Terminal } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ComprasProvider } from '@/contexts/ComprasContext';

const RegistroPage = lazy(() => import('@/app/admin/compras/registro/page'));
const HistorialCompras = lazy(() => import('@/components/admin/compras/HistorialCompras'));
const StockTab = lazy(() => import('@/app/admin/compras/stock/page'));
const ProyeccionCompras = lazy(() => import('@/components/admin/compras/ProyeccionCompras'));
const KpisDashboard = lazy(() => import('@/components/admin/compras/KpisDashboard'));
const ConsolaPage = lazy(() => import('@/app/admin/compras/consola/page'));

const tabs = [
  { key: 'registro', label: 'Registro', icon: Plus },
  { key: 'historial', label: 'Historial', icon: FileText },
  { key: 'stock', label: 'Stock', icon: Package },
  { key: 'proyeccion', label: 'Proyección', icon: TrendingUp },
  { key: 'kpis', label: 'KPIs', icon: BarChart3 },
  { key: 'consola', label: 'Consola', icon: Terminal },
] as const;

type TabKey = typeof tabs[number]['key'];

function TabSkeleton() {
  return (
    <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
      <Loader2 className="h-6 w-6 animate-spin text-red-500" />
    </div>
  );
}

export default function ComprasSection() {
  const [activeTab, setActiveTab] = useState<TabKey>('registro');

  return (
    <ComprasProvider>
      <div className="space-y-4">
        <h1 className="text-2xl font-bold text-gray-900">Compras <span className="text-xs text-gray-400 font-normal">v1.5</span></h1>
        <nav className="flex items-center gap-1 overflow-x-auto rounded-lg bg-gray-100 p-1" role="tablist" aria-label="Secciones de compras">
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
          {activeTab === 'registro' && <RegistroPage />}
          {activeTab === 'historial' && <HistorialCompras />}
          {activeTab === 'stock' && <StockTab />}
          {activeTab === 'proyeccion' && <ProyeccionCompras />}
          {activeTab === 'kpis' && <KpisDashboard />}
          {activeTab === 'consola' && <ConsolaPage />}
        </Suspense>
      </div>
    </ComprasProvider>
  );
}
