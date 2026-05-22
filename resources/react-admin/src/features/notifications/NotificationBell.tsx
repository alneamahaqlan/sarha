import { Bell, CheckCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';

import { useMarkAllRead, useMarkRead, useNotifications } from './hooks';

const PRIORITY_DOT: Record<'low' | 'normal' | 'high' | 'urgent', string> = {
  low:    'bg-gray-400',
  normal: 'bg-blue-500',
  high:   'bg-amber-500',
  urgent: 'bg-red-500',
};

export function NotificationBell() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data } = useNotifications();
  const markRead = useMarkRead();
  const markAllRead = useMarkAllRead();

  const unreadCount = data?.meta.unread_count ?? 0;
  const items = data?.data ?? [];

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label={t('notifications.title')}>
          <Bell className="h-4 w-4" />
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
                <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', PRIORITY_DOT[n.priority])} />
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
