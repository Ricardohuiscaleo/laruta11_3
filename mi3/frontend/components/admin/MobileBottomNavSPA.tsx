'use client';

import { useState, useMemo, useCallback, useEffect } from 'react';
import {
  Home, Users, Calendar, Bell, MoreHorizontal, LogOut,
  Truck, Wallet, ShoppingCart, Receipt, ClipboardCheck,
  SlidersHorizontal, CreditCard, ArrowLeftRight, Clock,
  ChefHat,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { logout } from '@/lib/auth';
import ViewSwitcher from '@/components/ViewSwitcher';
import type { SectionKey } from '@/components/admin/AdminShell';

interface NavItem {
  key: SectionKey;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
}

const primaryItems: NavItem[] = [
  { key: 'inicio', label: 'Inicio', icon: Home },
  { key: 'personal', label: 'Personal', icon: Users },
  { key: 'turnos', label: 'Turnos', icon: Calendar },
  { key: 'notificaciones', label: 'Alertas', icon: Bell },
];

const secondaryItems: NavItem[] = [
  { key: 'delivery', label: 'Delivery', icon: Truck },
  { key: 'adelantos', label: 'Adelantos', icon: Wallet },
  { key: 'compras', label: 'Compras', icon: ShoppingCart },
  { key: 'recetas', label: 'Recetas', icon: ChefHat },
  { key: 'nomina', label: 'Nómina', icon: Receipt },
  { key: 'checklists', label: 'Checklists', icon: ClipboardCheck },
  { key: 'ajustes', label: 'Ajustes', icon: SlidersHorizontal },
  { key: 'creditos', label: 'Créditos R11', icon: CreditCard },
  { key: 'cambios', label: 'Cambios', icon: ArrowLeftRight },
  { key: 'cronjobs', label: 'Cronjobs', icon: Clock },
];

interface MobileBottomNavSPAProps {
  activeSection: string;
  onSectionChange: (section: string) => void;
  badges?: Record<string, number>;
}

export default function MobileBottomNavSPA({ activeSection, onSectionChange, badges = {} }: MobileBottomNavSPAProps) {
  const [sheetOpen, setSheetOpen] = useState(false);
  const [sheetVisible, setSheetVisible] = useState(false);
  const moreActive = secondaryItems.some(item => activeSection === item.key);

  const hasSecondaryBadge = useMemo(
    () => secondaryItems.some(item => (badges[item.key] || 0) > 0),
    [badges]
  );

  // Animate bottom sheet in/out
  useEffect(() => {
    if (sheetOpen) {
      requestAnimationFrame(() => setSheetVisible(true));
    }
  }, [sheetOpen]);

  const closeSheet = useCallback(() => {
    setSheetVisible(false);
    setTimeout(() => setSheetOpen(false), 200);
  }, []);

  const handleNav = useCallback((key: string) => {
    navigator.vibrate?.(10);
    onSectionChange(key);
    if (sheetOpen) closeSheet();
  }, [onSectionChange, sheetOpen, closeSheet]);

  const openSheet = useCallback(() => {
    navigator.vibrate?.(10);
    setSheetOpen(true);
  }, []);

  return (
    <>
      <nav className="fixed bottom-0 left-0 right-0 z-50 md:hidden bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]" role="navigation" aria-label="Navegación admin">
        <div className="flex items-center justify-around h-16">
          {primaryItems.map(({ key, label, icon: Icon }) => {
            const active = activeSection === key;
            const badgeCount = badges[key] || 0;
            return (
              <button
                key={key}
                type="button"
                onClick={() => handleNav(key)}
                className={cn(
                  'flex items-center justify-center h-full gap-1.5 relative min-h-[44px] transition-all duration-300',
                  active ? 'text-red-500 bg-red-50 rounded-full px-4 mx-0.5' : 'text-gray-400 flex-col px-2'
                )}
                aria-current={active ? 'page' : undefined}
                aria-label={label}
              >
                <div className="relative">
                  <Icon className={cn('transition-all duration-300', active ? 'w-5 h-5' : 'w-5 h-5')} />
                  {badgeCount > 0 && (
                    <span className="absolute -top-1.5 -right-2.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full ring-2 ring-white">
                      {badgeCount > 99 ? '99+' : badgeCount}
                    </span>
                  )}
                </div>
                {active ? (
                  <span className="text-xs font-semibold">{label}</span>
                ) : (
                  <span className="text-[10px]">{label}</span>
                )}
              </button>
            );
          })}

          {/* "Más" button */}
          <button
            type="button"
            onClick={openSheet}
            className={cn(
              'flex flex-col items-center justify-center flex-1 h-full gap-0.5 min-w-[44px] min-h-[44px] relative',
              moreActive ? 'text-red-500' : 'text-gray-400'
            )}
            aria-label="Más opciones"
            aria-expanded={sheetOpen}
          >
            <div className="relative">
              <MoreHorizontal className="w-5 h-5" />
              {hasSecondaryBadge && (
                <span className="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white" />
              )}
            </div>
            <span className="text-xs">Más</span>
          </button>
        </div>
      </nav>

      {/* Bottom sheet */}
      {sheetOpen && (
        <div className="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true" aria-label="Menú de navegación">
          <div
            className={cn(
              'absolute inset-0 backdrop-blur-sm transition-colors duration-200',
              sheetVisible ? 'bg-black/40' : 'bg-black/0'
            )}
            onClick={closeSheet}
          />
          <div className={cn(
            'absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl pb-[env(safe-area-inset-bottom)] transition-transform duration-200',
            sheetVisible ? 'translate-y-0' : 'translate-y-full'
          )}>
            <div className="flex justify-center pt-3 pb-2">
              <div className="w-10 h-1 rounded-full bg-gray-300" />
            </div>
            <div className="px-4 pb-4 space-y-1">
              {secondaryItems.map(({ key, label, icon: Icon }) => {
                const active = activeSection === key;
                const badgeCount = badges[key] || 0;
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => handleNav(key)}
                    className={cn(
                      'flex w-full items-center gap-3 px-3 py-3 rounded-lg min-h-[44px]',
                      active ? 'text-red-500 bg-red-50' : 'text-gray-500 hover:bg-gray-50'
                    )}
                    aria-current={active ? 'page' : undefined}
                  >
                    <div className="relative">
                      <Icon className="w-5 h-5" />
                      {badgeCount > 0 && (
                        <span className="absolute -top-1 -right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white" />
                      )}
                    </div>
                    <span className="text-sm font-medium flex-1 text-left">{label}</span>
                    {badgeCount > 0 && (
                      <span className="text-xs font-bold text-red-500">{badgeCount > 99 ? '99+' : badgeCount}</span>
                    )}
                  </button>
                );
              })}
            </div>
            <div className="px-4 pb-6 border-t border-gray-100 pt-3 space-y-1">
              <ViewSwitcher className="text-gray-500 hover:bg-gray-50" />
              <button
                type="button"
                onClick={logout}
                className="flex w-full items-center gap-3 px-3 py-3 rounded-lg text-red-600 hover:bg-red-50 min-h-[44px]"
              >
                <LogOut className="w-5 h-5" />
                <span className="text-sm font-medium">Cerrar sesión</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
