import { useEffect, useState } from 'react';
import { Bell, BellOff, BellRing, CheckCheck, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';
import { useRealtimeNotifications } from '@/lib/use-realtime-notifications';
import { useWebPush } from '@/lib/use-web-push';

import { useMarkAllRead, useMarkRead, useNotifications } from './hooks';

const PRIORITY_DOT: Record<'low' | 'normal' | 'high' | 'urgent' | 'info', string> = {
  info:   'bg-gray-400',
  low:    'bg-gray-400',
  normal: 'bg-blue-500',
  high:   'bg-amber-500',
  urgent: 'bg-red-500',
};

const IOS_HINT_KEY = 'sarha.ios-push-hint-dismissed-at';
const IOS_HINT_COOLDOWN_MS = 30 * 24 * 60 * 60 * 1000; // 30 days

function isIosSafari(): boolean {
  if (typeof navigator === 'undefined') return false;
  const ua = navigator.userAgent;
  const isIos = /iPad|iPhone|iPod/.test(ua);
  const isSafari = /^((?!chrome|android|crios|fxios).)*safari/i.test(ua);
  return isIos && isSafari;
}

function pushPermissionStatus(): 'granted' | 'denied' | 'default' | 'unsupported' {
  if (typeof Notification === 'undefined') return 'unsupported';
  return Notification.permission;
}

export function NotificationBell() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data } = useNotifications();
  const markRead = useMarkRead();
  const markAllRead = useMarkAllRead();
  const [pulse, setPulse] = useState(false);
  const [permission, setPermission] = useState(pushPermissionStatus());
  const [iosHintVisible, setIosHintVisible] = useState(false);
  const webPush = useWebPush();

  // Subscribe to the auth user's private channel for realtime updates.
  // The hook handles invalidation + sound; we just listen here so the
  // bell can flash its icon when a new notification lands.
  useRealtimeNotifications((payload) => {
    // Tiny pulse animation — pure visual feedback.
    setPulse(true);
    setTimeout(() => setPulse(false), 1_200);
    // Update permission state on every event in case the user just allowed.
    setPermission(pushPermissionStatus());
    void payload; // unused here; the hook already invalidated the cache
  });

  // iOS Safari banner: shown once when the dropdown opens, with 30-day cooldown.
  const handleOpenChange = (open: boolean) => {
    if (!open || !isIosSafari()) return;
    let last = 0;
    try { last = Number(localStorage.getItem(IOS_HINT_KEY) ?? '0'); } catch { /* ignore */ }
    if (Date.now() - last > IOS_HINT_COOLDOWN_MS) {
      setIosHintVisible(true);
    }
  };

  const dismissIosHint = () => {
    setIosHintVisible(false);
    try { localStorage.setItem(IOS_HINT_KEY, String(Date.now())); } catch { /* ignore */ }
  };

  // Re-read permission status whenever the dropdown opens — keeps the
  // "blocked" badge honest if the user toggles browser settings.
  useEffect(() => {
    const onFocus = () => setPermission(pushPermissionStatus());
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, []);

  const unreadCount = data?.meta.unread_count ?? 0;
  const items = data?.data ?? [];

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '';

  return (
    <DropdownMenu onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label={t('notifications.title')}>
          <Bell className={cn('h-4 w-4 transition-transform', pulse && 'animate-bounce')} />
          {unreadCount > 0 && (
            <span className="absolute -top-0.5 -end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80 p-0">
        <div className="flex items-center justify-between border-b border-[var(--color-border)] px-3 py-2">
          <div className="text-sm font-semibold">{t('notifications.title')}</div>
          {unreadCount > 0 && (
            <button
              type="button"
              onClick={() => markAllRead.mutate()}
              disabled={markAllRead.isPending}
              className="inline-flex items-center gap-1 text-xs text-[var(--color-primary)] hover:underline"
            >
              <CheckCheck className="h-3 w-3" />
              {t('notifications.mark_all_read')}
            </button>
          )}
        </div>

        {/* Enable browser push — deferred prompt. Shows ONLY when the
            browser supports push AND the user hasn't asked/granted yet.
            The actual permission popup fires from the Enable click —
            a user gesture, which is what browsers require. */}
        {webPush.status === 'default' && (
          <div className="flex items-start gap-2 border-b border-[var(--color-border)] bg-sage-50 px-3 py-2 text-xs text-sage-800">
            <BellRing className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <div className="flex-1">
              <div className="font-medium">{t('notifications.push_enable_title')}</div>
              <div className="text-[11px] text-sage-700">{t('notifications.push_enable_body')}</div>
              <button
                type="button"
                onClick={() => webPush.subscribe()}
                disabled={webPush.busy}
                className="mt-1.5 inline-flex items-center justify-center min-h-touch md:min-h-8 rounded-md bg-sage-600 px-3 text-xs font-medium text-white hover:bg-sage-700 disabled:opacity-50"
              >
                {webPush.busy ? t('common.loading') : t('notifications.push_enable_button')}
              </button>
            </div>
          </div>
        )}

        {/* Subscribed — quiet "you're set" with a tiny disable link. */}
        {webPush.status === 'subscribed' && (
          <div className="flex items-center justify-between gap-2 border-b border-[var(--color-border)] bg-sage-50/60 px-3 py-1.5 text-[11px] text-sage-700">
            <span className="inline-flex items-center gap-1">
              <BellRing className="h-3 w-3" /> {t('notifications.push_enabled')}
            </span>
            <button
              type="button"
              onClick={() => webPush.unsubscribe()}
              disabled={webPush.busy}
              className="text-sage-700 hover:underline"
            >
              {t('notifications.push_disable')}
            </button>
          </div>
        )}

        {/* Permission-denied hint — bell still gets realtime updates,
            but we let the user know Web Push can't reach them until
            they unblock the browser permission. */}
        {permission === 'denied' && (
          <div className="flex items-start gap-2 border-b border-[var(--color-border)] bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <BellOff className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <div>
              <div className="font-medium">{t('notifications.push_blocked')}</div>
              <a
                href="https://support.google.com/chrome/answer/3220216"
                target="_blank"
                rel="noopener noreferrer"
                className="underline hover:text-amber-900"
              >
                {t('notifications.push_blocked_help')}
              </a>
            </div>
          </div>
        )}

        {/* iOS Safari hint — once per 30 days, only on iOS Safari. */}
        {iosHintVisible && (
          <div className="flex items-start gap-2 border-b border-[var(--color-border)] bg-blue-50 px-3 py-2 text-xs text-blue-800">
            <div className="flex-1">
              <div className="font-medium">{t('notifications.ios_hint_title')}</div>
              <div>{t('notifications.ios_hint_body')}</div>
            </div>
            <button
              type="button"
              onClick={dismissIosHint}
              aria-label={t('common.close')}
              className="text-blue-700 hover:text-blue-900"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          </div>
        )}

        <div className="max-h-96 overflow-y-auto">
          {items.length === 0 ? (
            <div className="py-8 text-center text-xs text-[var(--color-muted-foreground)]">
              {t('notifications.empty')}
            </div>
          ) : (
            items.map((n) => (
              <button
                key={n.id}
                type="button"
                onClick={() => {
                  if (!n.is_read) markRead.mutate(n.id);
                  if (n.url) window.location.href = n.url;
                }}
                className={cn(
                  'flex w-full items-start gap-2 border-b border-[var(--color-border)] px-3 py-2 text-start last:border-b-0 hover:bg-[var(--color-muted)]',
                  !n.is_read && 'bg-blue-50/40',
                )}
              >
                <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', PRIORITY_DOT[n.priority] ?? PRIORITY_DOT.normal)} />
                <div className="min-w-0 flex-1">
                  <div className="truncate text-sm font-medium">{n.title}</div>
                  {n.body && <div className="line-clamp-2 text-xs text-[var(--color-muted-foreground)]">{n.body}</div>}
                  <div className="mt-1 text-[10px] text-[var(--color-muted-foreground)]">{fmt(n.created_at)}</div>
                </div>
              </button>
            ))
          )}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
