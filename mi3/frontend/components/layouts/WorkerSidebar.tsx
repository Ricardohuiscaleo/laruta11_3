'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { LogOut } from 'lucide-react';
import { cn } from '@/lib/utils';
import { logout } from '@/lib/auth';
import { primaryNavItems, secondaryNavItems } from '@/lib/navigation';
import { usePendingLoanBadge } from '@/hooks/usePendingLoanBadge';
import ViewSwitcher from '@/components/ViewSwitcher';

const links = [...primaryNavItems, ...secondaryNavItems];

export default function WorkerSidebar() {
  const pathname = usePathname();
  const hasPendingLoan = usePendingLoanBadge();

  return (
    <aside className="hidden md:flex md:flex-col w-64 bg-red-600 text-white">
      <div className="flex items-center border-b border-red-500 px-4 py-4">
        <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />
      </div>
      <nav className="mt-2 flex-1 space-y-1 px-2">
        {links.map(({ href, label, icon: Icon, badgeKey }) => {
          const active = pathname === href;
          const showBadge = badgeKey === 'prestamo-pendiente' && hasPendingLoan;
          return (
            <Link
              key={href}
              href={href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                active
                  ? 'bg-red-500 text-white'
                  : 'text-red-100 hover:bg-red-500/50'
              )}
            >
              <div className="relative">
                <Icon className="h-5 w-5" />
                {showBadge && (
                  <span className="absolute -top-1 -right-1.5 h-2.5 w-2.5 rounded-full bg-yellow-400 ring-2 ring-red-600" />
                )}
              </div>
              {label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-red-500 px-2 py-3 space-y-1">
        <ViewSwitcher className="text-red-100 hover:bg-red-500/50 hover:text-white" />
        <button
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
