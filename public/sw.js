/* eslint-disable no-restricted-globals */
/**
 * sarha unified-notification service worker.
 *
 * Lives at /sw.js (public/ — Laravel doesn't need a route since
 * Apache/nginx serves it directly). Registered with a version query
 * string from the page (`navigator.serviceWorker.register('/sw.js?v=...')`)
 * so a deploy that bumps the version triggers an update.
 *
 * Responsibilities:
 *   1. push events — render the notification (title + privacy-safe body)
 *   2. notificationclick — focus an existing tab on the target url, or
 *      open a new one if none exists
 *
 * This SW intentionally does NOT do offline caching — it's purely a
 * notification relay. Adding caching would change cache invalidation
 * semantics and bring its own bugs.
 */

const SW_VERSION = '1.0.0';

self.addEventListener('install', (event) => {
  // Activate immediately so a fresh deploy doesn't wait for all open
  // tabs to close before push starts working.
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Take control of all clients without requiring a refresh.
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: 'sarha', body: event.data.text() };
  }

  const title = payload.title || 'sarha';
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/favicon.svg',
    badge: payload.badge || '/favicon.svg',
    // tag dedupes — a re-send of the same notification id replaces
    // any existing notification in the user's tray instead of stacking.
    tag: payload.tag || `sarha-${Date.now()}`,
    data: {
      url: payload.url || '/',
      eventId: payload.event_id,
      priority: payload.priority,
    },
    // urgent notifications skip the "silent" flag so the OS plays
    // the system sound + flashes the LED if the device supports it.
    silent: payload.priority === 'info',
    // Re-notify even if a tagged predecessor is still visible so the
    // user sees the latest count.
    renotify: true,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || '/';
  // Focus an existing tab on the target URL if there is one — saves
  // the user from juggling multiple sarha tabs.
  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({
      type: 'window',
      includeUncontrolled: true,
    });
    for (const client of allClients) {
      const url = new URL(client.url);
      if (url.pathname === targetUrl.split('?')[0]) {
        await client.focus();
        if (client.navigate) await client.navigate(targetUrl);
        return;
      }
    }
    // No existing tab — open a new one.
    if (self.clients.openWindow) {
      await self.clients.openWindow(targetUrl);
    }
  })());
});
