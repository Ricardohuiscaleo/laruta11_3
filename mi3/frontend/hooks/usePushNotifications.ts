'use client';

import { useCallback, useEffect, useState } from 'react';
import { apiFetch } from '@/lib/api';

function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; i++) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

export type PushStatus =
  | 'loading'
  | 'unsupported'
  | 'no-vapid'
  | 'denied'
  | 'prompt'
  | 'active'         // Subscribed locally AND synced to backend
  | 'inactive';      // Permission granted but not subscribed

export interface PushNotificationState {
  status: PushStatus;
  activate: () => Promise<boolean>;
}

const VAPID_KEY = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;

export function usePushNotifications(): PushNotificationState {
  const [status, setStatus] = useState<PushStatus>('loading');

  useEffect(() => {
    checkAndSync();
  }, []);

  /**
   * Check browser state AND sync subscription to backend if exists.
   * This ensures the backend always has the latest subscription endpoint.
   */
  async function checkAndSync() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      setStatus('unsupported');
      return;
    }
    if (!VAPID_KEY) {
      setStatus('no-vapid');
      return;
    }

    const perm = Notification.permission;
    if (perm === 'denied') { setStatus('denied'); return; }
    if (perm === 'default') { setStatus('prompt'); return; }

    // perm === 'granted' — check subscription
    try {
      const reg = await navigator.serviceWorker.getRegistration('/sw.js');
      if (!reg) { setStatus('inactive'); return; }

      const sub = await reg.pushManager.getSubscription();
      if (!sub) { setStatus('inactive'); return; }

      // Subscription exists locally — sync to backend (keeps it fresh)
      await apiFetch('/worker/push/subscribe', {
        method: 'POST',
        body: JSON.stringify({ subscription: sub.toJSON() }),
      });
      setStatus('active');
    } catch {
      // If backend call fails, still show as active locally
      setStatus('active');
    }
  }

  const activate = useCallback(async (): Promise<boolean> => {
    if (!VAPID_KEY) return false;
    try {
      // 1. Register service worker
      const registration = await navigator.serviceWorker.register('/sw.js');

      // 2. Request permission
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setStatus(permission === 'denied' ? 'denied' : 'prompt');
        return false;
      }

      // 3. Subscribe (or get existing)
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(VAPID_KEY) as BufferSource,
        });
      }

      // 4. Always send to backend (even if subscription existed)
      await apiFetch('/worker/push/subscribe', {
        method: 'POST',
        body: JSON.stringify({ subscription: subscription.toJSON() }),
      });

      setStatus('active');
      return true;
    } catch {
      await checkAndSync();
      return false;
    }
  }, []);

  return { status, activate };
}
