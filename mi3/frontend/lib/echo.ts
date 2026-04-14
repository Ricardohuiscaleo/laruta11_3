'use client';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;

  if (!echoInstance) {
    const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
    const host = process.env.NEXT_PUBLIC_REVERB_HOST;
    if (!key || !host) {
      console.warn('[Echo] Missing REVERB env vars:', { key: !!key, host: !!host });
      return null;
    }

    // Pusher must be on window for Echo
    (window as any).Pusher = Pusher;

    echoInstance = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: host,
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      authEndpoint: `https://${host}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${typeof localStorage !== 'undefined' ? localStorage.getItem('mi3_token') || '' : ''}`,
        },
      },
    });

    console.log('[Echo] Connected to', `wss://${host}/app/${key}`);
  }

  return echoInstance;
}
