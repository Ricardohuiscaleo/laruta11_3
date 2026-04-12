'use client';

import { useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';
import { formatCLP } from '@/lib/utils';
import {
  Loader2, TrendingUp, ShoppingCart, Users, Calculator,
  ShoppingBag, ArrowLeftRight, CreditCard, ClipboardCheck,
} from 'lucide-react';

interface DashboardData {
  ventas_mes: number;
  compras_mes: number;
  nomina_mes: number;
  resultado_bruto: number;
}

const apps = [
  { label: 'Compras', icon: ShoppingBag, color: 'bg-blue-500', href: '/admin/compras' },
  { label: 'Checklists', icon: ClipboardCheck, color: 'bg-amber-500', href: '/admin/checklists' },
  { label: 'Cambios', icon: ArrowLeftRight, color: 'bg-purple-500', href: '/admin/cambios' },
  { label: 'Créditos', icon: CreditCard, color: 'bg-green-600', href: '/admin/creditos' },
];

export default function AdminPage() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch<{ success: boolean; data: DashboardData }>('/admin/dashboard')
      .then(res => { if (res.data) setData(res.data); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center py-20"><Loader2 className="h-8 w-8 animate-spin text-amber-600" /></div>;

  const kpis = [
    { label: 'Ventas Mes', value: data?.ventas_mes ?? 0, icon: TrendingUp, color: 'text-green-600', bg: 'bg-green-50' },
    { label: 'Compras Mes', value: data?.compras_mes ?? 0, icon: ShoppingCart, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: 'Nómina', value: data?.nomina_mes ?? 0, icon: Users, color: 'text-amber-600', bg: 'bg-amber-50' },
    { label: 'Resultado Bruto', value: data?.resultado_bruto ?? 0, icon: Calculator,
      color: (data?.resultado_bruto ?? 0) >= 0 ? 'text-green-600' : 'text-red-600',
      bg: (data?.resultado_bruto ?? 0) >= 0 ? 'bg-green-50' : 'bg-red-50' },
  ];

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-gray-900">Panel Admin</h1>

      {/* KPI Cards */}
      <div className="grid grid-cols-2 gap-3 sm:gap-4">
        {kpis.map((kpi, i) => {
          const Icon = kpi.icon;
          return (
            <div key={i} className={`rounded-2xl ${kpi.bg} p-4 sm:p-5`}>
              <div className="flex items-center gap-2 mb-2">
                <Icon className={`h-4 w-4 ${kpi.color}`} />
                <span className="text-xs font-medium text-gray-600">{kpi.label}</span>
              </div>
              <p className={`text-lg sm:text-2xl font-bold ${kpi.color}`}>
                {formatCLP(Math.abs(kpi.value))}
              </p>
              {kpi.label === 'Resultado Bruto' && (
                <p className="text-xs text-gray-500 mt-1">Ventas − Compras − Nómina</p>
              )}
            </div>
          );
        })}
      </div>

      {/* Apps internas */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 mb-3">Aplicaciones</h2>
        <div className="grid grid-cols-4 gap-3">
          {apps.map((app, i) => {
            const Icon = app.icon;
            const isExternal = app.href.startsWith('http');
            const Tag = isExternal ? 'a' : 'a';
            return (
              <a
                key={i}
                href={app.href}
                target={isExternal ? '_blank' : undefined}
                rel={isExternal ? 'noopener noreferrer' : undefined}
                className="flex flex-col items-center gap-2 py-3 rounded-xl hover:bg-gray-50 transition-colors"
              >
                <div className={`${app.color} h-12 w-12 rounded-2xl flex items-center justify-center shadow-sm`}>
                  <Icon className="h-6 w-6 text-white" />
                </div>
                <span className="text-xs font-medium text-gray-700">{app.label}</span>
              </a>
            );
          })}
        </div>
      </div>
    </div>
  );
}
