import { Activity } from 'lucide-react';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { useBookingActivities } from '../hooks';
import type { BookingActivity } from '../types';

function relativeTime(iso: string, locale: string): string {
  const ms = Date.now() - new Date(iso).getTime();
  const m = Math.round(ms / 60000);
  if (m < 1)   return locale === 'ar' ? 'الآن' : 'just now';
  if (m < 60)  return locale === 'ar' ? `قبل ${m} د` : `${m}m ago`;
  const h = Math.round(m / 60);
  if (h < 24)  return locale === 'ar' ? `قبل ${h} س` : `${h}h ago`;
  const d = Math.round(h / 24);
  if (d < 30)  return locale === 'ar' ? `قبل ${d} ي` : `${d}d ago`;
  return new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB');
}

function actionLabel(a: BookingActivity, t: (k: string, v?: any) => string): string {
  // Friendly translation, fall back to the slug
  const key = `clinic_team_activity.action.${a.action}`;
  const candidate = t(key);
  return candidate === key ? a.action : candidate;
}

function summaryLine(a: BookingActivity, t: (k: string, v?: any) => string): string | null {
  const s = (a.summary ?? {}) as any;
  if (s?.note) return s.note;
  if (s?.outcome) return t(`clinic_bookings_kanban.quick.outcome_${s.outcome}`);
  if (s?.reason) return t(`clinic_bookings_kanban.move.cancel_reason_${s.reason}`);
  if (s?.label) return s.label;
  if (s?.from && s?.to) return `${s.from} → ${s.to}`;
  return null;
}

export function ActivityTimeline({ bookingId }: { bookingId: number }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useBookingActivities(bookingId);

  if (isLoading) {
    return <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }
  if (!data || data.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 p-6 text-center text-xs text-[var(--color-muted-foreground)]">
        <Activity className="h-5 w-5" />
        {t('clinic_bookings_kanban.timeline.empty')}
      </div>
    );
  }

  return (
    <ol className="space-y-2.5 p-1">
      {data.map((a) => {
        const sub = summaryLine(a, t);
        return (
          <li key={a.id} className="rounded-md border border-[var(--color-border)] bg-white p-2.5">
            <div className="flex items-center justify-between gap-2 text-[12px]">
              <div className="font-medium">{actionLabel(a, t)}</div>
              <div className="text-[10px] text-[var(--color-muted-foreground)]">{relativeTime(a.created_at, locale)}</div>
            </div>
            <div className="mt-0.5 text-[11px] text-[var(--color-muted-foreground)]">
              {a.actor_name} · {t(`clinic_team.role.${a.actor_role}`)}
            </div>
            {sub && <div className="mt-1 text-[12px]">{sub}</div>}
          </li>
        );
      })}
    </ol>
  );
}
