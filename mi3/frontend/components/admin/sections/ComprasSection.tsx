'use client';

import { useState, useEffect, useCallback, lazy, Suspense } from 'react';
import { Plus, FileText, Package, TrendingUp, BarChart3, Loader2, Terminal, Zap } from 'lucide-react';
import { cn } from '@/lib/utils';
import { ComprasProvider } from '@/contexts/ComprasContext';
import { getToken } from '@/lib/auth';
import type { SectionHeaderConfig, TabDef } from '@/components/admin/AdminShell';

const RegistroPage = lazy(() => import('@/app/admin/compras/registro/page'));
const HistorialCompras = lazy(() => import('@/components/admin/compras/HistorialCompras'));
const StockTab = lazy(() => import('@/app/admin/compras/stock/page'));
const ProyeccionCompras = lazy(() => import('@/components/admin/compras/ProyeccionCompras'));
const KpisDashboard = lazy(() => import('@/components/admin/compras/KpisDashboard'));
const ConsolaPage = lazy(() => import('@/app/admin/compras/consola/page'));

const tabs: TabDef[] = [
  { key: 'registro', label: 'Registro', icon: Plus },
  { key: 'historial', label: 'Historial', icon: FileText },
  { key: 'stock', label: 'Stock', icon: Package },
  { key: 'proyeccion', label: 'Proyección', icon: TrendingUp },
  { key: 'kpis', label: 'KPIs', icon: BarChart3 },
  { key: 'consola', label: 'Consola', icon: Terminal },
];

type TabKey = 'registro' | 'historial' | 'stock' | 'proyeccion' | 'kpis' | 'consola';

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

function BudgetTrailing({ budget }: { budget: AiBudget | null }) {
  if (!budget) return null;
  return (
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
  );
}

interface ComprasSectionProps {
  onHeaderConfig?: (config: SectionHeaderConfig) => void;
}

export default function ComprasSection({ onHeaderConfig }: ComprasSectionProps) {
  const [activeTab, setActiveTab] = useState<TabKey>('registro');
  const [budget, setBudget] = useState<AiBudget | null>(null);

  const handleTabChange = useCallback((key: string) => {
    setActiveTab(key as TabKey);
  }, []);

  // Fetch AI budget
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

  // Push header config to AdminShell
  useEffect(() => {
    onHeaderConfig?.({
      tabs,
      activeTab,
      onTabChange: handleTabChange,
      trailing: <BudgetTrailing budget={budget} />,
      accent: 'red',
      version: 'v1.8.3',
    });
  }, [activeTab, budget, handleTabChange, onHeaderConfig]);

  return (
    <ComprasProvider>
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
    </ComprasProvider>
  );
}
