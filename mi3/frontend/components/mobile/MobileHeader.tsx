'use client';

import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { Bell } from 'lucide-react';
import { getPageTitle } from '@/lib/navigation';
import { apiFetch } from '@/lib/api';

export default function MobileHeader() {
  const pathname = usePathname();
  const title = getPageTitle(pathname);
  const [unreadCount, setUnreadCount] = useState<number>(0);

  useEffect(() => {
    async function fetchNotifications() {
      try {
        const data = await apiFetch<{ no_leidas: number }>('/worker/notifications');
        setUnreadCount(data.no_leidas);
      } catch {
        // Graceful degradation — hide badge on failure
        setUnreadCount(0);
      }
    }
    fetchNotifications();
  }, []);

  return (
    <header className="fixed top-0 left-0 right-0 z-40 md:hidden h-14 bg-white border-b border-gray-200 shadow-sm">
      <div className="flex items-center justify-between h-full px-4">
        {/* Branding */}
        <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />

        {/* Page title */}
        <span className="text-sm font-medium text-gray-700">{title}</span>

        {/* Notifications */}
        <div className="relative">
          <Bell className="w-5 h-5 text-gray-500" />
          {unreadCount > 0 && (
            <span className="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-red-500 rounded-full">
              {unreadCount}
            </span>
          )}
        </div>
      </div>
    </header>
  );
}
