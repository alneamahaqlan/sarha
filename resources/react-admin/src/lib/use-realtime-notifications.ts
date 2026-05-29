import { useEffect, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/app/providers/AuthProvider';
import { getEcho } from './echo';
import { useNotificationSound } from './use-notification-sound';

/**
 * Listens on the auth user's private channel for `notification.created`
 * events and:
 *  1. invalidates the notifications cache (the bell re-fetches the head)
 *  2. plays a priority-aware sound (high / urgent only)
 *
 * The hook is fired once per app from the NotificationBell mount — not
 * from each page — so the subscription lives as long as the panel is
 * open and unsubscribes on logout (when AuthProvider clears the user).
 *
 * Channel name is derived from `user.guard` exactly the way
 * routes/channels.php expects: admin / clinic / user (web).
 */

interface IncomingPayload {
  id: number;
  event_type: string | null;
  priority: 'info' | 'normal' | 'high' | 'urgent';
  title: string;
  body: string;
  url: string | null;
  icon: string | null;
  created_at: string | null;
  read_at: null;
}

export function useRealtimeNotifications(
  onIncoming?: (n: IncomingPayload) => void,
): void {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const { play } = useNotificationSound();
  const subscribedRef = useRef<string | null>(null);

  useEffect(() => {
    if (!user?.user?.id) return;

    const channelName =
      user.guard === 'admin'  ? `admin.${user.user.id}`
    : user.guard === 'clinic' ? `clinic.${user.user.id}`
    :                           `user.${user.user.id}`;

    // Idempotent: if we re-render and the channel is the same, skip.
    if (subscribedRef.current === channelName) return;

    const echo = getEcho();
    const channel = echo.private(channelName);

    channel.listen('.notification.created', (payload: IncomingPayload) => {
      // 1. Refresh the bell head + counts. React Query handles dedupe
      //    if the same query is already in-flight.
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] });

      // 2. Sound, scoped to priority (cross-tab lock inside the hook).
      play(payload.priority, payload.id);

      // 3. Optional consumer hook (e.g., to show a toast).
      onIncoming?.(payload);
    });

    subscribedRef.current = channelName;

    return () => {
      try {
        echo.leave(channelName);
      } catch {
        /* swallow — disconnect already happened */
      }
      subscribedRef.current = null;
    };
  }, [user?.user?.id, user?.guard, queryClient, play, onIncoming]);
}
