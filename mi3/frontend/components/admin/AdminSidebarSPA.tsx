'use client';

import {
  Home, Users, Calendar, Receipt, SlidersHorizontal,
  CreditCard, ArrowLeftRight, LogOut, Clock, Truck,
  Bell, Wallet, ShoppingCart, ClipboardCheck,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { logout } from '@/lib/auth';
import ViewSwitcher from '@/components/ViewSwitcher';
import { usePendingSettlementBadge } from '@/hooks/usePendingSettlementBadge';
import type { SectionKey } from '@/components/admin/AdminShell';

interface SidebarLink {
  key: SectionKey;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
}

const links: SidebarLink[] = [
  { key: 'inicio', label: 'Inicio', icon: Home },
  { key: 'personal', label: 'Personal', icon: Users },
  { key: 'turnos', label: 'Turnos', icon: Calendar },
  { key: 'notificaciones', label: 'Alertas', icon: Bell },
  { key: 'nomina', label: 'Nómina', icon: Receipt },
  { key: 'ajustes', label: 'Ajustes', icon: SlidersHorizontal },
  { key: 'creditos', label: 'Créditos R11', icon: CreditCard },
  { key: 'cambios', label: 'Cambios', icon: ArrowLeftRight },
  { key: 'cronjobs', label: 'Cronjobs', icon: Clock },
];

interface AdminSidebarSPAProps {
  activeSection: string;
  onSectionChange: (section: string) => void;
  badges?: Record<string, number>;
}

export default function AdminSidebarSPA({ activeSection, onSectionChange, badges = {} }: AdminSidebarSPAProps) {
  const hasPendingSettlement = usePendingSettlementBadge();

  return (
    <aside className="hidden md:flex md:flex-col w-64 bg-red-600 text-white" role="navigation" aria-label="Admin sidebar">
      <div className="flex items-center border-b border-red-500 px-4 py-4">
        <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />
      </div>

      <nav className="mt-2 flex-1 space-y-1 px-2">
        {links.map(({ key, label, icon: Icon }) => {
          const active = activeSection === key;
          const badgeCount = badges[key] || 0;
          return (
            <button
              key={key}
              type="button"
              onClick={() => onSectionChange(key)}
              className={cn(
                'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                active ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
              )}
              aria-current={active ? 'page' : undefined}
            >
              <Icon className="h-5 w-5" />
              <span className="flex-1 text-left">{label}</span>
              {badgeCount > 0 && (
                <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-400 px-1 text-xs font-bold text-amber-900">
                  {badgeCount > 99 ? '99+' : badgeCount}
                </span>
              )}
            </button>
          );
        })}

        {/* Delivery with pending settlement badge */}
        <button
          type="button"
          onClick={() => onSectionChange('delivery')}
          className={cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
            activeSection === 'delivery' ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
          )}
          aria-current={activeSection === 'delivery' ? 'page' : undefined}
        >
          <Truck className="h-5 w-5" />
          <span className="flex-1 text-left">Delivery</span>
          {(hasPendingSettlement || (badges.delivery || 0) > 0) && (
            <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-amber-900">
              !
            </span>
          )}
        </button>

        {/* Adelantos */}
        <button
          type="button"
          onClick={() => onSectionChange('adelantos')}
          className={cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
            activeSection === 'adelantos' ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
          )}
          aria-current={activeSection === 'adelantos' ? 'page' : undefined}
        >
          <Wallet className="h-5 w-5" />
          <span className="flex-1 text-left">Adelantos</span>
          {(badges.adelantos || 0) > 0 && (
            <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-400 px-1 text-xs font-bold text-amber-900">
              {badges.adelantos > 99 ? '99+' : badges.adelantos}
            </span>
          )}
        </button>

        {/* Compras */}
        <button
          type="button"
          onClick={() => onSectionChange('compras')}
          className={cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
            activeSection === 'compras' ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
          )}
          aria-current={activeSection === 'compras' ? 'page' : undefined}
        >
          <ShoppingCart className="h-5 w-5" />
          <span className="flex-1 text-left">Compras</span>
        </button>

        {/* Checklists */}
        <button
          type="button"
          onClick={() => onSectionChange('checklists')}
          className={cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
            activeSection === 'checklists' ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50'
          )}
          aria-current={activeSection === 'checklists' ? 'page' : undefined}
        >
          <ClipboardCheck className="h-5 w-5" />
          <span className="flex-1 text-left">Checklists</span>
        </button>
      </nav>

      <div className="border-t border-red-500 px-2 py-3 space-y-1">
        <ViewSwitcher className="text-red-100 hover:bg-red-500/50 hover:text-white" />
        <button
          type="button"
          onClick={logout}
          className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-200 transition-colors hover:bg-red-500/50 hover:text-white"
        >
          <LogOut className="h-5 w-5" />
          Cerrar sesión
        </button>
      </div>
    </aside>
  );
}
