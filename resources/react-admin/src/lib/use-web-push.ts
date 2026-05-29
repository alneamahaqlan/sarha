import { useCallback, useEffect, useState } from 'react';
import { apiClient } from './api-client';

/**
 * Browser-side Web Push lifecycle hook.
 *
 *  status: 'unsupported' | 'denied' | 'default' | 'granted' | 'subscribed'
 *
 *  - 'unsupported' : SW or Notification API missing (iOS Safari outside PWA)
 *  - 'denied'      : user blocked at the browser level
 *  - 'default'     : permission not yet asked
 *  - 'granted'     : permission given but no subscription saved server-side yet
 *  - 'subscribed'  : everything wired; server knows about this browser
 *
 * Deliberate UX: this hook NEVER triggers Notification.requestPermission
 * on its own — only when `subscribe()` is called from a user gesture
 * (button click). That keeps us out of the "intrusive prompt" doghouse.
 */

const SW_PATH = '/sw.js';
const SW_VERSION = '1.0.0';

type PushStatus = 'unsupported' | 'denied' | 'default' | 'granted' | 'subscribed';

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4);
  const cleaned = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(cleaned);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

function isSupported(): boolean {
  return typeof navigator !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && typeof Notification !== 'undefined';
}

export function useWebPush() {
  const [status, setStatus] = useState<PushStatus>('default');
  const [busy, setBusy] = useState(false);

  // Resolve initial status: combine SW support, permission, and whether
  // the SW already has a subscription on this browser.
  const refresh = useCallback(async () => {
    if (!isSupported()) {
      setStatus('unsupported');
      return;
    }
    if (Notification.permission === 'denied') {
      setStatus('denied');
      return;
    }
    if (Notification.permission === 'default') {
      setStatus('default');
      return;
    }
    // Permission granted — check if a subscription exists on this browser.
    try {
      const reg = await navigator.serviceWorker.ready;
      const existing = await reg.pushManager.getSubscription();
      setStatus(existing ? 'subscribed' : 'granted');
    } catch {
      setStatus('granted');
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  /**
   * Asks the browser for permission, registers the SW, subscribes
   * with the server's VAPID public key, and POSTs the subscription
   * to /api/v1/push/subscribe. Must be called from a user gesture.
   */
  const subscribe = useCallback(async (): Promise<boolean> => {
    if (!isSupported()) return false;
    setBusy(true);
    try {
      // 1. Permission. Browsers only allow this from a user gesture —
      //    the bell's "Enable" button is the gesture in our flow.
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') {
        await refresh();
        return false;
      }

      // 2. Register the SW with a version query so future bumps trigger
      //    a re-install. Browser ignores the query for scope.
      const reg = await navigator.serviceWorker.register(`${SW_PATH}?v=${SW_VERSION}`);
      await navigator.serviceWorker.ready;

      // 3. Fetch the server's VAPID public key + subscribe.
      const { data } = await apiClient.get<{ key: string }>('/push/vapid-public-key');
      if (!data?.key) throw new Error('VAPID key missing');

      let sub = await reg.pushManager.getSubscription();
      if (!sub) {
        sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(data.key),
        });
      }

      // 4. Tell our backend about the new subscription.
      const json = sub.toJSON() as { endpoint?: string; keys?: { p256dh?: string; auth?: string } };
      await apiClient.post('/push/subscribe', {
        endpoint: json.endpoint,
        keys: { p256dh: json.keys?.p256dh, auth: json.keys?.auth },
        content_encoding: 'aesgcm',
      });

      setStatus('subscribed');
      return true;
    } catch (e) {
      console.warn('web-push subscribe failed', e);
      await refresh();
      return false;
    } finally {
      setBusy(false);
    }
  }, [refresh]);

  /** Tear down both browser-side + server-side. */
  const unsubscribe = useCallback(async (): Promise<void> => {
    if (!isSupported()) return;
    setBusy(true);
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        const endpoint = sub.endpoint;
        await sub.unsubscribe();
        await apiClient.delete('/push/unsubscribe', { data: { endpoint } });
      }
      setStatus(Notification.permission === 'granted' ? 'granted' : 'default');
    } catch (e) {
      console.warn('web-push unsubscribe failed', e);
    } finally {
      setBusy(false);
    }
  }, []);

  return { status, busy, subscribe, unsubscribe };
}
