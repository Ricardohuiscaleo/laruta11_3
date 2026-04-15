'use client';

import { useState } from 'react';
import { Plus, FileText, Package, TrendingUp, BarChart3 } from 'lucide-react';
import { cn } from '@/lib/utils';

const tabs = [
  { key: 'registro', label: 'Registro', icon: Plus, href: '/admin/compras/registro' },
  { key: 'historial', label: 'Historial', icon: FileText, href: '/admin/compras/historial' },
  { key: 'stock', label: 'Stock', icon: Package, href: '/admin/compras/stock' },
  { key: 'proyeccion', label: 'Proyección', icon: TrendingUp, href: '/admin/compras/proyeccion' },
  { key: 'kpis', label: 'KPIs', icon: BarChart3, href: '/admin/compras/kpis' },
] as const;

/**
 * ComprasSection — wrapper that embeds the compras sub-pages via iframe.
 * Compras has its own sub-routing (registro, historial, stock, etc.) which
 * doesn't fit the flat SPA section model. We embed it as an iframe for now.
 */
export default function ComprasSection() {
  const [activeTab, setActiveTab] = useState('registro');

  return (
    <div className="flex flex-col h-[calc(100vh-2rem)]">
      <h1 className="text-2xl font-bold text-gray-900 mb-3">Compras</h1>
      <nav className="flex items-center gap-1 overflow-x-auto border-b bg-white px-2 py-1.5">
        {tabs.map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setActiveTab(key)}
            className={cn(
              'flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              activeTab === key
                ? 'bg-red-500 text-white'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
            )}
          >
            <Icon className="h-4 w-4" />
            <span>{label}</span>
          </button>
        ))}
      </nav>
      <div className="flex-1 mt-2">
        <iframe
          src={`/admin/compras/${activeTab}`}
          className="w-full h-full border-0 rounded-lg"
          title={`Compras - ${activeTab}`}
        />
      </div>
    </div>
  );
}
