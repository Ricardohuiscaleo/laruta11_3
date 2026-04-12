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
  | 'loading'        // Checking status
  | 'unsupported'    // Browser doesn't support push
  | 'no-vapid'       // VAPID key not configured (server-side issue)
  | 'denied'         // User blocked notifications in browser
  | 'prompt'         // User hasn't decided yet — can activate
  | 'active'         // Subscribed and working
  | 'inactive';      // Permission granted but not subscribed

export interface PushNotificationState {
  status: PushStatus;
  activate: () => Promise<boolean>;
}

const VAPID_KEY = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;

export function usePushNotifications(): PushNotificationState {
  const [status, setStatus] = useState<PushStatus>('loading');

  // Check current status on mount
  useEffect(() => {
    checkStatus();
  }, []);

  async function checkStatus() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
      setStatus('unsupported');
      return;
    }
    if (!VAPID_KEY) {
      setStatus('no-vapid');
      return;
    }
    const perm = Notification.permission;
    if (perm === 'denied') {
      setStatus('denied');
      return;
    }
    if (perm === 'default') {
      setStatus('prompt');
      return;
    }
    // perm === 'granted' — check if actually subscribed
    try {
      const reg = await navigator.serviceWorker.getRegistration('/sw.js');
      if (reg) {
        const sub = await reg.pushManager.getSubscription();
        setStatus(sub ? 'active' : 'inactive');
      } else {
        setStatus('inactive');
      }
    } catch {
      setStatus('inactive');
    }
  }

  const activate = useCallback(async (): Promise<boolean> => {
    if (!VAPID_KEY) return false;
    try {
      // 1. Register service worker
      const registration = await navigator.serviceWorker.register('/sw.js');

      // 2. Request permission (will show browser prompt if 'default')
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        setStatus(permission === 'denied' ? 'denied' : 'prompt');
        return false;
      }

      // 3. Subscribe
      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(VAPID_KEY) as BufferSource,
        });
      }

      // 4. Send to backend
      await apiFetch('/worker/push/subscribe', {
        method: 'POST',
        body: JSON.stringify({ subscription: subscription.toJSON() }),
      });

      setStatus('active');
      return true;
    } catch {
      await checkStatus(); // Re-check to get accurate status
      return false;
    }
  }, []);

  return { status, activate };
}
