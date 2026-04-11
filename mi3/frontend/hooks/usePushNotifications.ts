'use client';

import { useEffect, useRef } from 'react';
import { apiFetch } from '@/lib/api';

/**
 * Convert a base64 VAPID key to a Uint8Array for pushManager.subscribe.
 */
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

/**
 * Hook that registers the service worker, requests notification permission,
 * subscribes to push via pushManager, and sends the subscription to the backend.
 *
 * Should be called once when the dashboard layout mounts.
 */
export function usePushNotifications() {
  const initialized = useRef(false);

  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;

    const vapidPublicKey = process.env.NEXT_PUBLIC_VAPID_PUBLIC_KEY;
    if (!vapidPublicKey) return;

    // Check browser support
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    async function setup() {
      try {
        // 1. Register service worker
        const registration = await navigator.serviceWorker.register('/sw.js');

        // 2. Request notification permission
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        // 3. Subscribe to push via pushManager
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
          subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey!) as BufferSource,
          });
        }

        // 4. Send subscription to backend
        await apiFetch('/worker/push/subscribe', {
          method: 'POST',
          body: JSON.stringify({ subscription: subscription.toJSON() }),
        });
      } catch {
        // Silently fail — push is optional
      }
    }

    setup();
  }, []);
}
