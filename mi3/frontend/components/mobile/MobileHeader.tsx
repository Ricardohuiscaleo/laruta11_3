'use client';

import { usePathname } from 'next/navigation';
import { getPageTitle } from '@/lib/navigation';
import { NotificationTagIndicator } from '@/components/NotificationStatusModal';

export default function MobileHeader({ variant = 'worker' }: { variant?: 'worker' | 'admin' }) {
  const pathname = usePathname();
  const title = getPageTitle(pathname);

  return (
    <header className="fixed top-0 left-0 right-0 z-40 md:hidden h-14 bg-red-500 shadow-sm">
      <div className="flex items-center justify-between h-full px-4">
        <div className="flex items-center gap-2">
          <img src="/R11HEADER.jpg" alt="La Ruta 11" className="h-8 w-auto" />
          <NotificationTagIndicator />
        </div>
        <span className="text-sm font-semibold text-white">{title}</span>
        <div className="w-8" />
      </div>
    </header>
  );
}
