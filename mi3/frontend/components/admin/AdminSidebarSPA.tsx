'use client';

import React, { useState, useEffect, useMemo, useCallback } from 'react';
import {
  Home, Users, Calendar, Receipt, SlidersHorizontal,
  CreditCard, ArrowLeftRight, LogOut, Clock, Truck,
  Bell, Wallet, ShoppingCart, ClipboardCheck, ChevronLeft, ChevronRight,
  ChefHat, DollarSign, Settings, ChevronDown,
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

interface SidebarGroup {
  id: string;
  label: string;
  emoji: string;
  links: SidebarLink[];
}

const sidebarGroups: SidebarGroup[] = [
  {
    id: 'general',
    label: 'General',
    emoji: '📊',
    links: [
      { key: 'inicio', label: 'Inicio', icon: Home },
      { key: 'notificaciones', label: 'Alertas', icon: Bell },
    ],
  },
  {
    id: 'personas',
    label: 'Personas',
    emoji: '👥',
    links: [
      { key: 'personal', label: 'Usuarios', icon: Users },
      { key: 'turnos', label: 'Turnos', icon: Calendar },
      { key: 'nomina', label: 'Nómina', icon: Receipt },
      { key: 'ajustes', label: 'Ajustes', icon: SlidersHorizontal },
      { key: 'adelantos', label: 'Adelantos', icon: Wallet },
    ],
  },
  {
    id: 'finanzas',
    label: 'Finanzas',
    emoji: '💰',
    links: [
      { key: 'ventas', label: 'Ventas', icon: DollarSign },
      { key: 'compras', label: 'Compras', icon: ShoppingCart },
      { key: 'creditos', label: 'Créditos', icon: CreditCard },
      { key: 'cambios', label: 'Cambios', icon: ArrowLeftRight },
      { key: 'capital', label: 'Capital', icon: Wallet },
    ],
  },
  {
    id: 'operacion',
    label: 'Operación',
    emoji: '🍔',
    links: [
      { key: 'recetas', label: 'Recetas', icon: ChefHat },
      { key: 'delivery', label: 'Delivery', icon: Truck },
      { key: 'delivery-config', label: 'Config', icon: Settings },
      { key: 'checklists', label: 'Checklists', icon: ClipboardCheck },
    ],
  },
  {
    id: 'sistema',
    label: 'Sistema',
    emoji: '⚙️',
    links: [
      { key: 'cronjobs', label: 'Cronjobs', icon: Clock },
    ],
  },
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
  const { label, icon: Icon } = link;
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors relative',
        active ? 'bg-red-500 text-white' : 'text-red-100 hover:bg-red-500/50',
        collapsed && 'justify-center px-0'
      )}
      aria-current={active ? 'page' : undefined}
      aria-label={collapsed ? label : undefined}
      title={collapsed ? label : undefined}
    >
      <Icon className="h-[18px] w-[18px] shrink-0" />
      {!collapsed && <span className="flex-1 text-left">{label}</span>}
      {!collapsed && badgeCount > 0 && (
        <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-400 px-1 text-xs font-bold text-amber-900">
          {badgeCount > 99 ? '99+' : badgeCount}
        </span>
      )}
      {!collapsed && showAlert && (
        <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-amber-900">!</span>
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
const GROUPS_KEY = 'admin_sidebar_groups_open';

export default function AdminSidebarSPA({ activeSection, onSectionChange, badges = {} }: AdminSidebarSPAProps) {
  const hasPendingSettlement = usePendingSettlementBadge();
  const [collapsed, setCollapsed] = useState(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(STORAGE_KEY) === 'true';
  });

  // Track which groups are open (all open by default)
  const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
    if (typeof window === 'undefined') return {};
    try {
      const saved = localStorage.getItem(GROUPS_KEY);
      return saved ? JSON.parse(saved) : {};
    } catch { return {}; }
  });

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, String(collapsed));
  }, [collapsed]);

  useEffect(() => {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(openGroups));
  }, [openGroups]);

  const toggleCollapsed = useCallback(() => setCollapsed(prev => !prev), []);

  const toggleGroup = useCallback((groupId: string) => {
    setOpenGroups(prev => ({ ...prev, [groupId]: prev[groupId] === false ? true : false }));
  }, []);

  const handleSectionChange = useCallback((key: string) => {
    onSectionChange(key);
  }, [onSectionChange]);

  // Find which group contains the active section (to auto-expand it)
  const activeGroupId = useMemo(() => {
    for (const group of sidebarGroups) {
      if (group.links.some(l => l.key === activeSection)) return group.id;
    }
    return null;
  }, [activeSection]);

  const isGroupOpen = useCallback((groupId: string) => {
    // If explicitly set, use that; otherwise default open
    if (openGroups[groupId] !== undefined) return openGroups[groupId];
    return true; // all open by default
  }, [openGroups]);

  return (
    <aside
      className={cn(
        'hidden md:flex md:flex-col h-full bg-red-600 text-white transition-all duration-200',
        collapsed ? 'w-16' : 'w-56'
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

      <nav className="mt-1 flex-1 px-2 overflow-y-auto">
        {sidebarGroups.map((group) => {
          const groupOpen = isGroupOpen(group.id) || activeGroupId === group.id;
          const groupHasBadge = group.links.some(l => (badges[l.key] || 0) > 0);

          return (
            <div key={group.id} className="mb-0.5">
              {/* Group header */}
              {!collapsed ? (
                <button
                  type="button"
                  onClick={() => toggleGroup(group.id)}
                  className="flex w-full items-center gap-2 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-red-300 hover:text-white transition-colors"
                  aria-expanded={groupOpen}
                >
                  <span>{group.emoji}</span>
                  <span className="flex-1 text-left">{group.label}</span>
                  {groupHasBadge && !groupOpen && (
                    <span className="h-2 w-2 rounded-full bg-amber-400" />
                  )}
                  <ChevronDown className={cn(
                    'h-3.5 w-3.5 transition-transform duration-200',
                    !groupOpen && '-rotate-90'
                  )} />
                </button>
              ) : (
                <div className="flex justify-center py-1">
                  <span className="text-xs" title={group.label}>{group.emoji}</span>
                </div>
              )}

              {/* Group links */}
              {(collapsed || groupOpen) && (
                <div className={cn('space-y-0.5', !collapsed && 'ml-1')}>
                  {group.links.map((link) => {
                    const badgeCount = badges[link.key] || 0;
                    const showAlert = link.key === 'delivery' && hasPendingSettlement && badgeCount === 0;
                    return (
                      <NavItem
                        key={link.key}
                        link={link}
                        active={activeSection === link.key}
                        collapsed={collapsed}
                        badgeCount={badgeCount}
                        showAlert={showAlert}
                        onClick={() => handleSectionChange(link.key)}
                      />
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
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
