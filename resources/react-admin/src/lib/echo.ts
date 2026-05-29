import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Laravel Echo singleton wired to Reverb (Pusher-protocol compatible).
 *
 * Auth flow:
 *  - The /broadcasting/auth endpoint sits behind the `web` middleware
 *    and reads cookies, so we just need to send credentials with the
 *    XHR. Echo's `authorizer` option lets us override the default
 *    Pusher auth flow to use fetch with credentials: 'include'.
 *  - Each channel in routes/channels.php declares its target guard,
 *    so the server resolves the user from the right session.
 *
 * Connection sharing:
 *  - One Echo per app. Multiple subscriptions reuse the same WebSocket.
 *  - The singleton lives at module scope; re-importing returns the
 *    same instance.
 */

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

// Pusher needs to live on `window` for laravel-echo's Pusher driver to find it.
window.Pusher = Pusher;

const ENV = import.meta.env as Record<string, string | undefined>;
const KEY = ENV.VITE_REVERB_APP_KEY ?? '';
const HOST = ENV.VITE_REVERB_HOST ?? 'localhost';
const PORT = Number(ENV.VITE_REVERB_PORT ?? 8080);
const SCHEME = ENV.VITE_REVERB_SCHEME ?? 'http';
const FORCE_TLS = SCHEME === 'https';

let echo: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> {
  if (echo) return echo;

  echo = new Echo({
    broadcaster: 'reverb',
    key: KEY,
    wsHost: HOST,
    wsPort: PORT,
    wssPort: PORT,
    forceTLS: FORCE_TLS,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      // Custom authorizer so we can hit /broadcasting/auth with cookies.
      // Without this, Pusher driver defaults to XHR without credentials
      // and the session-based auth fails silently.
      authorize: async (socketId: string, callback: (error: Error | null, data?: unknown) => void) => {
        try {
          const csrf = document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];
          const res = await fetch('/broadcasting/auth', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
              'Accept': 'application/json',
              ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
            },
            body: new URLSearchParams({
              socket_id: socketId,
              channel_name: channel.name,
            }).toString(),
          });
          if (!res.ok) {
            callback(new Error(`auth failed: ${res.status}`));
            return;
          }
          callback(null, await res.json());
        } catch (e) {
          callback(e instanceof Error ? e : new Error(String(e)));
        }
      },
    }),
  });

  return echo;
}

/** Tear down the WebSocket (e.g., on logout) so the next call gets a fresh socket. */
export function disconnectEcho(): void {
  if (echo) {
    echo.disconnect();
    echo = null;
  }
}
