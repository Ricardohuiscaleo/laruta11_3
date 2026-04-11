'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { MoreHorizontal } from 'lucide-react';
import { primaryNavItems, secondaryNavItems, isNavItemActive } from '@/lib/navigation';
import { cn } from '@/lib/utils';

export default function MobileBottomNav() {
  const pathname = usePathname();
  const [sheetOpen, setSheetOpen] = useState(false);

  // "Más" is active when current route matches any secondary item
  const moreActive = secondaryNavItems.some((item) =>
    isNavItemActive(pathname, item.href),
  );

  return (
    <>
      {/* Bottom nav bar */}
      <nav className="fixed bottom-0 left-0 right-0 z-50 md:hidden bg-white border-t border-gray-200 pb-[env(safe-area-inset-bottom)]">
        <div className="flex items-center justify-around h-16">
          {primaryNavItems.map((item) => {
            const active = isNavItemActive(pathname, item.href);
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex flex-col items-center justify-center flex-1 h-full gap-0.5',
                  active ? 'text-amber-600' : 'text-gray-400',
                )}
              >
                <Icon className="w-5 h-5" />
                <span className="text-xs">{item.label}</span>
              </Link>
            );
          })}

          {/* "Más" button */}
          <button
            type="button"
            onClick={() => setSheetOpen(true)}
            className={cn(
              'flex flex-col items-center justify-center flex-1 h-full gap-0.5',
              moreActive ? 'text-amber-600' : 'text-gray-400',
            )}
          >
            <MoreHorizontal className="w-5 h-5" />
            <span className="text-xs">Más</span>
          </button>
        </div>
      </nav>

      {/* Bottom sheet overlay + panel */}
      {sheetOpen && (
        <div className="fixed inset-0 z-50 md:hidden">
          {/* Overlay */}
          <div
            className="absolute inset-0 bg-black/40"
            onClick={() => setSheetOpen(false)}
          />

          {/* Sheet */}
          <div className="absolute bottom-0 left-0 right-0 bg-white rounded-t-2xl pb-[env(safe-area-inset-bottom)]">
            {/* Handle */}
            <div className="flex justify-center pt-3 pb-2">
              <div className="w-10 h-1 rounded-full bg-gray-300" />
            </div>

            {/* Secondary nav items */}
            <div className="px-4 pb-6 space-y-1">
              {secondaryNavItems.map((item) => {
                const active = isNavItemActive(pathname, item.href);
                const Icon = item.icon;
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => setSheetOpen(false)}
                    className={cn(
                      'flex items-center gap-3 px-3 py-3 rounded-lg',
                      active
                        ? 'text-amber-600 bg-amber-50'
                        : 'text-gray-500 hover:bg-gray-50',
                    )}
                  >
                    <Icon className="w-5 h-5" />
                    <span className="text-sm font-medium">{item.label}</span>
                  </Link>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
