'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { MoreHorizontal, LogOut } from 'lucide-react';
import {
  primaryNavItems, secondaryNavItems,
  adminPrimaryNavItems, adminSecondaryNavItems,
  isNavItemActive,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/lib/auth';
import { usePendingLoanBadge } from '@/hooks/usePendingLoanBadge';
import { usePendingChecklistBadge } from '@/hooks/usePendingChecklistBadge';
import ViewSwitcher from '@/components/ViewSwitcher';

export default function MobileBottomNav({ variant = 'worker' }: { variant?: 'worker' | 'admin' }) {
  const pathname = usePathname();
  const [sheetOpen, setSheetOpen] = useState(false);
  const hasPendingLoan = usePendingLoanBadge();
  const hasPendingChecklist = usePendingChecklistBadge();

  const primary = variant === 'admin' ? adminPrimaryNavItems : primaryNavItems;
  const secondary = variant === 'admin' ? adminSecondaryNavItems : secondaryNavItems;
  const moreActive = secondary.some((item) => isNavItemActive(pathname, item.href));

  return (
    <>
      <nav className="fixed bottom-0 left-0 right-0 z-50 md:hidden bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
        <div className="flex items-center justify-around h-16">
          {primary.map((item) => {
            const active = isNavItemActive(pathname, item.href);
            const Icon = item.icon;
            const showBadge = variant === 'worker' && (
              (item.badgeKey === 'prestamo-pendiente' && hasPendingLoan) ||
              (item.badgeKey === 'checklist-pendiente' && hasPendingChecklist)
            );
            return (
              <Link key={item.href} href={item.href}
                className={cn('flex flex-col items-center justify-center flex-1 h-full gap-0.5 relative',
                  active ? 'text-red-500' : 'text-gray-400')}>
                <div className="relative">
                  <Icon className="w-5 h-5" />
                  {showBadge && (
                    <span className="absolute -top-1 -right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white" />
                  )}
                </div>
                <span className="text-xs">{item.label}</span>
              </Link>
            );
          })}
          <button type="button" onClick={() => setSheetOpen(true)}
            className={cn('flex flex-col items-center justify-center flex-1 h-full gap-0.5',
              moreActive ? 'text-red-500' : 'text-gray-400')}>
            <MoreHorizontal className="w-5 h-5" />
            <span className="text-xs">Más</span>
          </button>
        </div>
      </nav>

      {sheetOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          <div className="absolute inset-0 bg-black/40" onClick={() => setSheetOpen(false)} />
          <div className="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl pb-[env(safe-area-inset-bottom)]">
            <div className="flex justify-center pt-3 pb-2">
              <div className="w-10 h-1 rounded-full bg-gray-300" />
            </div>
            <div className="px-4 pb-4 space-y-1">
              {secondary.map((item) => {
                const active = isNavItemActive(pathname, item.href);
                const Icon = item.icon;
                const showBadge = variant === 'worker' && (
                  (item.badgeKey === 'prestamo-pendiente' && hasPendingLoan) ||
                  (item.badgeKey === 'checklist-pendiente' && hasPendingChecklist)
                );
                return (
                  <Link key={item.href} href={item.href} onClick={() => setSheetOpen(false)}
                    className={cn('flex items-center gap-3 px-3 py-3 rounded-lg',
                      active ? 'text-red-500 bg-red-50' : 'text-gray-500 hover:bg-gray-50')}>
                    <div className="relative">
                      <Icon className="w-5 h-5" />
                      {showBadge && (
                        <span className="absolute -top-1 -right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 ring-2 ring-white" />
                      )}
                    </div>
                    <span className="text-sm font-medium">{item.label}</span>
                  </Link>
                );
              })}
            </div>
            <div className="px-4 pb-6 border-t border-gray-100 pt-3 space-y-1">
              <ViewSwitcher className="text-gray-500 hover:bg-gray-50" />
              <button onClick={logout}
                className="flex w-full items-center gap-3 px-3 py-3 rounded-lg text-red-600 hover:bg-red-50">
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
