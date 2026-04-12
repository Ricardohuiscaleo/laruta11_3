import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally (required by Laravel Echo)
if (typeof window !== 'undefined') {
  (window as any).Pusher = Pusher;
}

let echoInstance: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> | null {
  if (typeof window === 'undefined') return null;

  if (!echoInstance) {
    const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
    const host = process.env.NEXT_PUBLIC_REVERB_HOST;
    if (!key || !host) return null;

    echoInstance = new Echo({
      broadcaster: 'reverb',
      key,
      wsHost: host,
      wsPort: 443,
      wssPort: 443,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
    });
  }

  return echoInstance;
}
