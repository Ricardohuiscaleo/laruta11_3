'use client';

import { useEffect, useState, useCallback } from 'react';
import { usePathname } from 'next/navigation';
import { Bell } from 'lucide-react';
import { getPageTitle } from '@/lib/navigation';
import { apiFetch } from '@/lib/api';
import { NotificationTagIndicator } from '@/components/NotificationStatusModal';

/** Set the PWA app icon badge via the service worker */
function setAppBadge(count: number) {
  if ('setAppBadge' in navigator) {
    count > 0
      ? (navigator as any).setAppBadge(count).catch(() => {})
      : (navigator as any).clearAppBadge?.().catch(() => {});
  }
  // Also tell SW (needed for iOS PWA)
  navigator.serviceWorker?.controller?.postMessage({ type: 'SET_BADGE', count });
}

export default function MobileHeader({ variant = 'worker' }: { variant?: 'worker' | 'admin' }) {
  const pathname = usePathname();
  const title = getPageTitle(pathname);
  const [unreadCount, setUnreadCount] = useState<number>(0);

  const fetchNotifications = useCallback(async () => {
    try {
      const data = await apiFetch<{ no_leidas: number }>('/worker/notifications');
      setUnreadCount(data.no_leidas);
      setAppBadge(data.no_leidas);
    } catch {
      setUnreadCount(0);
    }
  }, []);

  // Fetch on mount and route change
  useEffect(() => { fetchNotifications(); }, [pathname, fetchNotifications]);

  // Listen for SW telling us to refresh (new push arrived while app is open)
  useEffect(() => {
    function onMessage(event: MessageEvent) {
      if (event.data?.type === 'REFRESH_NOTIFICATIONS') {
        fetchNotifications();
      }
    }
    navigator.serviceWorker?.addEventListener('message', onMessage);
    return () => navigator.serviceWorker?.removeEventListener('message', onMessage);
  }, [fetchNotifications]);

  return (
    <header className="fixed top-0 left-0 right-0 z-40 md:hidden h-14 bg-red-500 shadow-sm">
      <div className="flex items-center justify-between h-full px-4">
        <div className="flex items-center gap-2">
          <img src="https://laruta11-images.s3.amazonaws.com/menu/logo-work.png" alt="La Ruta 11 Work" className="h-8 w-auto" />
          <NotificationTagIndicator />
        </div>
        <span className="text-sm font-semibold text-white">{title}</span>
        <div className="relative">
          <Bell className="w-5 h-5 text-white/80" />
          {unreadCount > 0 && (
            <span className="absolute -top-1.5 -right-1.5 flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-red-600 bg-white rounded-full">
              {unreadCount}
            </span>
          )}
        </div>
      </div>
    </header>
  );
}
