import { Calendar, AlertOctagon, FileQuestion, Activity, BellRing } from 'lucide-react';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { useCustomerTimeline } from '../hooks';
import type { TimelineEvent } from '../types';

interface Props {
  customerId: number;
}

const ICONS: Record<string, any> = {
  booking:       Calendar,
  complaint:     AlertOctagon,
  quote_request: FileQuestion,
  reminder:      BellRing,
  team_action:   Activity,
};

const TONES: Record<string, string> = {
  booking:       'text-blue-700 bg-blue-50 border-blue-200',
  complaint:     'text-rose-700 bg-rose-50 border-rose-200',
  quote_request: 'text-amber-700 bg-amber-50 border-amber-200',
  reminder:      'text-violet-700 bg-violet-50 border-violet-200',
  team_action:   'text-slate-700 bg-slate-50 border-slate-200',
};

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

export function CustomerActivityFeed({ customerId }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useCustomerTimeline(customerId);

  if (isLoading) return <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  if (!data || data.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 p-6 text-center text-xs text-[var(--color-muted-foreground)]">
        <Activity className="h-5 w-5" />
        {t('clinic_customers.timeline.empty')}
      </div>
    );
  }

  return (
    <ol className="space-y-2">
      {data.map((event: TimelineEvent, idx) => {
        const Icon = ICONS[event.kind] ?? Activity;
        const titleText = (() => {
          const candidate = t(event.title_key);
          if (candidate !== event.title_key) return candidate;
          if (event.kind === 'team_action') return event.action_slug;
          return event.title_key;
        })();
        return (
          <li key={idx} className={`flex items-start gap-3 rounded-md border p-2.5 ${TONES[event.kind] ?? TONES.team_action}`}>
            <Icon className="h-4 w-4 shrink-0 mt-0.5" />
            <div className="flex-1 min-w-0">
              <div className="flex items-center justify-between gap-2 text-[12px]">
                <span className="font-medium">{titleText}</span>
                <span className="text-[10px] opacity-80">{relativeTime(event.at, locale)}</span>
              </div>
              {event.kind === 'team_action' ? (
                <div className="mt-0.5 text-[11px] opacity-90">{event.actor_name}</div>
              ) : (
                <div className="mt-0.5 text-[11px] opacity-90">
                  {event.reference && <span dir="ltr" className="font-mono">{event.reference} · </span>}
                  {event.detail ?? event.status}
                </div>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
