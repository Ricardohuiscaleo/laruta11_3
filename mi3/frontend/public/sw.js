// Service Worker for Push Notifications + App Badge — La Ruta 11 Work (mi3)

// ── Message handler (badge from frontend, skip waiting) ──
self.addEventListener('message', function (event) {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
    return;
  }
  if (event.data?.type === 'SET_BADGE') {
    var count = event.data.count || 0;
    if ('setAppBadge' in self.registration) {
      count > 0
        ? self.registration.setAppBadge(count).catch(function () {})
        : self.registration.clearAppBadge().catch(function () {});
    }
  }
});

// ── Push notification received ──
self.addEventListener('push', function (event) {
  if (!event.data) return;

  var data;
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: 'La Ruta 11 Work', body: event.data.text(), url: '/dashboard' };
  }

  var badgeCount = data.badgeCount || 1;

  var options = {
    body: data.body || '',
    icon: data.icon || 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
    badge: 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
    vibrate: [200, 100, 200],
    data: { url: data.url || '/dashboard' },
    tag: data.tag || 'mi3-notification',
    renotify: true,
    requireInteraction: true,
  };

  // Show notification
  var showPromise = self.registration.showNotification(
    data.title || 'La Ruta 11 Work', options
  );

  // Set app badge count
  var badgePromise = Promise.resolve();
  if ('setAppBadge' in self.registration && badgeCount > 0) {
    badgePromise = self.registration.setAppBadge(badgeCount).catch(function () {});
  }

  // Tell open tabs to refresh their notification count
  var broadcastPromise = self.clients
    .matchAll({ type: 'window', includeUncontrolled: true })
    .then(function (clientList) {
      clientList.forEach(function (client) {
        client.postMessage({ type: 'REFRESH_NOTIFICATIONS' });
      });
    });

  event.waitUntil(Promise.all([showPromise, badgePromise, broadcastPromise]));
});

// ── Notification click — open/focus app ──
self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  // Clear badge on click
  if ('clearAppBadge' in self.registration) {
    self.registration.clearAppBadge().catch(function () {});
  }

  var url = event.notification.data?.url || '/dashboard';

  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then(function (clientList) {
        for (var i = 0; i < clientList.length; i++) {
          var client = clientList[i];
          if (client.url.includes(self.location.origin) && 'focus' in client) {
            client.navigate(url);
            return client.focus();
          }
        }
        if (self.clients.openWindow) {
          return self.clients.openWindow(url);
        }
      })
  );
});

// ── Re-subscribe on subscription change ──
self.addEventListener('pushsubscriptionchange', function (event) {
  event.waitUntil(
    self.registration.pushManager
      .subscribe(event.oldSubscription.options)
      .then(function (subscription) {
        return fetch('/api/v1/worker/push/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ subscription: subscription.toJSON() }),
        });
      })
  );
});
