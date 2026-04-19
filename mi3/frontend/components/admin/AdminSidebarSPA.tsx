'use client';

import React, { useState, useEffect, useMemo, useCallback } from 'react';
import {
  Home, Users, Calendar, Receipt, SlidersHorizontal,
  CreditCard, ArrowLeftRight, LogOut, Clock, Truck,
  Bell, Wallet, ShoppingCart, ClipboardCheck, ChevronLeft, ChevronRight,
  ChefHat,
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
  { key: 'delivery', label: 'Delivery', icon: Truck },
  { key: 'adelantos', label: 'Adelantos', icon: Wallet },
  { key: 'compras', label: 'Compras', icon: ShoppingCart },
  { key: 'recetas', label: 'Recetas', icon: ChefHat },
  { key: 'checklists', label: 'Checklists', icon: ClipboardCheck },
];

interface NavItemProps {
  link: SidebarLink;
  active: boolean;
  collapsed: boolean;
  badgeCount: number;
  showAlert: boolean;
  onClick: () => void;
}

const NavItem = React.memo(function NavItem({ link, active, collapsed, badgeCount, showAlert, onClick }: NavItemProps) {
  const { key, label, icon: Icon } = link;
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors relative',
        active ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50',
        collapsed && 'justify-center px-0'
      )}
      aria-current={active ? 'page' : undefined}
      aria-label={collapsed ? label : undefined}
      title={collapsed ? label : undefined}
    >
      <Icon className="h-5 w-5 shrink-0" />
      {!collapsed && <span className="flex-1 text-left">{label}</span>}
      {!collapsed && badgeCount > 0 && (
        <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-400 px-1 text-xs font-bold text-amber-900">
          {badgeCount > 99 ? '99+' : badgeCount}
        </span>
      )}
      {!collapsed && showAlert && (
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-amber-900">
          !
        </span>
      )}
      {collapsed && (badgeCount > 0 || showAlert) && (
        <span className="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-red-600" />
      )}
    </button>
  );
});

interface AdminSidebarSPAProps {
  activeSection: string;
  onSectionChange: (section: string) => void;
  badges?: Record<string, number>;
}

const STORAGE_KEY = 'admin_sidebar_collapsed';

export default function AdminSidebarSPA({ activeSection, onSectionChange, badges = {} }: AdminSidebarSPAProps) {
  const hasPendingSettlement = usePendingSettlementBadge();
  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(STORAGE_KEY) === 'true';
  });

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, String(collapsed));
  }, [collapsed]);

  const toggleCollapsed = useCallback(() => setCollapsed(prev => !prev), []);

  const handleSectionChange = useCallback((key: string) => {
    onSectionChange(key);
  }, [onSectionChange]);

  const navItems = useMemo(() => links.map(link => {
    const badgeCount = badges[link.key] || 0;
    const showAlert = link.key === 'delivery' && hasPendingSettlement && badgeCount === 0;
    return { link, badgeCount, showAlert };
  }), [badges, hasPendingSettlement]);

  return (
    <aside
      className={cn(
        'hidden md:flex md:flex-col h-full bg-red-600 text-white transition-all duration-200',
        collapsed ? 'w-16' : 'w-64'
      )}
      role="navigation"
      aria-label="Admin sidebar"
    >
      <div className={cn(
        'flex items-center border-b border-red-500 px-4 py-4',
        collapsed && 'justify-center px-2'
      )}>
        <img
          src="/R11HEADER.jpg"
          alt="La Ruta 11"
          className={cn('h-8 w-auto transition-all duration-200', collapsed && 'h-6')}
        />
      </div>

      <nav className="mt-2 flex-1 space-y-1 px-2 overflow-y-auto">
        {navItems.map(({ link, badgeCount, showAlert }) => (
          <NavItem
            key={link.key}
            link={link}
            active={activeSection === link.key}
            collapsed={collapsed}
            badgeCount={badgeCount}
            showAlert={showAlert}
            onClick={() => handleSectionChange(link.key)}
          />
        ))}
      </nav>

      <div className="border-t border-red-500 px-2 py-3 space-y-1">
        {!collapsed && <ViewSwitcher className="text-red-100 hover:bg-red-500/50 hover:text-white" />}
        <button
          type="button"
          onClick={logout}
          className={cn(
            'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-200 transition-colors hover:bg-red-500/50 hover:text-white',
            collapsed && 'justify-center px-0'
          )}
          aria-label={collapsed ? 'Cerrar sesión' : undefined}
          title={collapsed ? 'Cerrar sesión' : undefined}
        >
          <LogOut className="h-5 w-5 shrink-0" />
          {!collapsed && 'Cerrar sesión'}
        </button>
        <button
          type="button"
          onClick={toggleCollapsed}
          className="flex w-full items-center justify-center rounded-lg px-3 py-2 text-sm text-red-200 transition-colors hover:bg-red-500/50 hover:text-white"
          aria-label={collapsed ? 'Expandir sidebar' : 'Colapsar sidebar'}
        >
          {collapsed ? <ChevronRight className="h-5 w-5" /> : <ChevronLeft className="h-5 w-5" />}
        </button>
      </div>
    </aside>
  );
}
