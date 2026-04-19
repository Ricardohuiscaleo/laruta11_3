'use client';

import { useState, useEffect, lazy, Suspense } from 'react';
import { Plus, FileText, Package, TrendingUp, BarChart3, Loader2, Terminal, Zap } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ComprasProvider } from '@/contexts/ComprasContext';
import { getToken } from '@/lib/auth';

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

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api-mi3.laruta11.cl';

interface AiBudget {
  budget_clp: number;
  spent_clp: number;
  remaining_clp: number;
  budget_pct: number;
  total_cost_usd: number;
  total_extractions: number;
  total_tokens: number;
}

function TabSkeleton() {
  return (
    <div className="flex items-center justify-center py-16" role="status" aria-label="Cargando">
      <Loader2 className="h-6 w-6 animate-spin text-red-500" />
    </div>
  );
}

export default function ComprasSection() {
  const [activeTab, setActiveTab] = useState<TabKey>('registro');
  const [budget, setBudget] = useState<AiBudget | null>(null);

  useEffect(() => {
    const token = getToken();
    fetch(`${API_URL}/api/v1/admin/compras/ai-budget`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
      credentials: 'include',
    })
      .then(r => r.ok ? r.json() : null)
      .then(d => { if (d?.success) setBudget(d); })
      .catch(() => {});
  }, [activeTab]);

  return (
    <ComprasProvider>
      <div>
        {/* ── Sticky header (desktop) ── */}
        <div className="sticky top-0 z-20 bg-white/95 backdrop-blur-sm border-b pb-2 -mx-4 px-4 pt-2 lg:-mx-6 lg:px-6">
          {/* Row 1: Title + Budget */}
          <div className="flex items-center justify-between gap-4 mb-2">
            <h1 className="text-xl font-bold text-gray-900 shrink-0">
              Compras <span className="text-xs text-gray-400 font-normal">v1.7</span>
            </h1>

            {budget && (
              <div className="flex items-center gap-2 text-xs text-gray-500">
                <Zap className="h-3.5 w-3.5 text-amber-500 shrink-0" />
                <span className="hidden sm:inline">Presupuesto IA</span>
                <span className="font-semibold text-gray-800">
                  ${budget.remaining_clp.toLocaleString('es-CL')}
                </span>
                <span className="text-gray-400">/ ${budget.budget_clp.toLocaleString('es-CL')}</span>
                <div className="w-12 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                  <div
                    className={cn(
                      'h-full rounded-full transition-all',
                      budget.budget_pct > 80 ? 'bg-red-500' : budget.budget_pct > 50 ? 'bg-amber-500' : 'bg-green-500',
                    )}
                    style={{ width: `${Math.min(100, budget.budget_pct)}%` }}
                  />
                </div>
                <span className="hidden md:inline text-gray-400">
                  {budget.total_tokens.toLocaleString('es-CL')} tokens · {budget.total_extractions} usos
                </span>
              </div>
            )}
          </div>

          {/* Row 2: Tabs */}
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
                    : 'text-gray-600 hover:bg-white hover:text-gray-900',
                )}
              >
                <Icon className="h-4 w-4" />
                <span>{label}</span>
              </button>
            ))}
          </nav>
        </div>

        {/* ── Tab content ── */}
        <div className="pt-4">
          <Suspense fallback={<TabSkeleton />}>
            {activeTab === 'registro' && <RegistroPage />}
            {activeTab === 'historial' && <HistorialCompras />}
            {activeTab === 'stock' && <StockTab />}
            {activeTab === 'proyeccion' && <ProyeccionCompras />}
            {activeTab === 'kpis' && <KpisDashboard />}
            {activeTab === 'consola' && <ConsolaPage />}
          </Suspense>
        </div>
      </div>
    </ComprasProvider>
  );
}
