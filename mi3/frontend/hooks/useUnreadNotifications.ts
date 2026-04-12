'use client';

import { useState, useEffect, useCallback } from 'react';
import { usePathname } from 'next/navigation';
import { apiFetch } from '@/lib/api';

/** Set the PWA app icon badge via the service worker */
function setAppBadge(count: number) {
  if ('setAppBadge' in navigator) {
    count > 0
      ? (navigator as any).setAppBadge(count).catch(() => {})
      : (navigator as any).clearAppBadge?.().catch(() => {});
  }
  navigator.serviceWorker?.controller?.postMessage({ type: 'SET_BADGE', count });
}

/**
 * Returns the unread notification count. Refreshes on route change
 * and when the SW sends REFRESH_NOTIFICATIONS (new push arrived).
 */
export function useUnreadNotifications(): number {
  const [count, setCount] = useState(0);
  const pathname = usePathname();

  const fetch_ = useCallback(async () => {
    try {
      const data = await apiFetch<{ no_leidas: number }>('/worker/notifications');
      setCount(data.no_leidas);
      setAppBadge(data.no_leidas);
    } catch {
      setCount(0);
    }
  }, []);

  useEffect(() => { fetch_(); }, [pathname, fetch_]);

  // Listen for SW broadcast when a push arrives while app is open
  useEffect(() => {
    function onMessage(event: MessageEvent) {
      if (event.data?.type === 'REFRESH_NOTIFICATIONS') fetch_();
    }
    navigator.serviceWorker?.addEventListener('message', onMessage);
    return () => navigator.serviceWorker?.removeEventListener('message', onMessage);
  }, [fetch_]);

  return count;
}
