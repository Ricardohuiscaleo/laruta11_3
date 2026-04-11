// Service Worker for Push Notifications — La Ruta 11 Work (mi3)

self.addEventListener('push', function (event) {
  if (!event.data) return;

  let data;
  try {
    data = event.data.json();
  } catch (e) {
    data = {
      title: 'La Ruta 11 Work',
      body: event.data.text(),
      url: '/dashboard',
    };
  }

  const options = {
    body: data.body || '',
    icon: data.icon || 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
    badge: data.badge || 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/dashboard',
    },
    tag: data.tag || 'mi3-notification',
    renotify: true,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'La Ruta 11 Work', options)
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  const url = event.notification.data?.url || '/dashboard';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      // Focus existing window if available
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i];
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      // Open new window
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

self.addEventListener('pushsubscriptionchange', function (event) {
  // Re-subscribe if subscription expires
  event.waitUntil(
    self.registration.pushManager
      .subscribe(event.oldSubscription.options)
      .then(function (subscription) {
        // Send new subscription to backend
        return fetch('/api/v1/worker/push/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ subscription: subscription.toJSON() }),
        });
      })
  );
});
