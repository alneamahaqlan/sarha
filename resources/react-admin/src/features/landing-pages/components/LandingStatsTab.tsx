import {
  CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { useLandingStats } from '../hooks';

interface Props {
  pageId: number;
}

function StatCard({ label, value, suffix }: { label: string; value: string | number; suffix?: string }) {
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-4">
      <p className="text-xs text-[var(--color-muted-foreground)]">{label}</p>
      <p className="mt-1 text-2xl font-bold tabular-nums">
        {value}{suffix ? <span className="text-sm font-normal text-[var(--color-muted-foreground)]"> {suffix}</span> : null}
      </p>
    </div>
  );
}

export function LandingStatsTab({ pageId }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useLandingStats(pageId);

  if (isLoading || !data) {
    return <p className="py-12 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</p>;
  }

  const fmt = (n: number) => n.toLocaleString('ar-SA-u-nu-latn');
  const k = data.totals;
  const maxSource = Math.max(1, ...data.sources.map((s) => s.total));

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
        <StatCard label={t('landing_pages.stat_views')} value={fmt(k.page_views)} />
        <StatCard label={t('landing_pages.stat_uniques')} value={fmt(k.unique_visitors)} />
        <StatCard label={t('landing_pages.stat_bookings')} value={fmt(k.bookings)} />
        <StatCard label={t('landing_pages.stat_conv_rate')} value={k.conversion_rate} suffix="%" />
        <StatCard label={t('landing_pages.stat_bounce_rate')} value={k.bounce_rate} suffix="%" />
        <StatCard label={t('landing_pages.stat_whatsapp')} value={fmt(k.whatsapp_clicks)} />
        <StatCard label={t('landing_pages.stat_calls')} value={fmt(k.calls)} />
        <StatCard label={t('landing_pages.stat_clicks')} value={fmt(k.clicks)} />
        <StatCard label={t('landing_pages.stat_conversions')} value={fmt(k.conversions)} />
        <StatCard label={t('landing_pages.stat_avg_session')} value={`${Math.floor(k.avg_session_sec / 60)}:${String(k.avg_session_sec % 60).padStart(2, '0')}`} />
      </div>

      <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-4">
        <h3 className="mb-4 text-sm font-medium">{t('landing_pages.trend_title')}</h3>
        <div className="h-72">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data.trend}>
              <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
              <Tooltip />
              <Line type="monotone" dataKey="page_views" name={t('landing_pages.stat_views')} stroke="#0f766e" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="unique" name={t('landing_pages.stat_uniques')} stroke="#6366f1" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="conversions" name={t('landing_pages.stat_conversions')} stroke="#f59e0b" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-4">
        <h3 className="mb-4 text-sm font-medium">{t('landing_pages.sources_title')}</h3>
        {data.sources.length === 0 ? (
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</p>
        ) : (
          <div className="space-y-2">
            {data.sources.map((s) => (
              <div key={s.source} className="flex items-center gap-3">
                <span className="w-28 shrink-0 truncate text-sm" dir="ltr">{s.source}</span>
                <div className="h-3 flex-1 overflow-hidden rounded-full bg-[var(--color-muted)]">
                  <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${(s.total / maxSource) * 100}%` }} />
                </div>
                <span className="w-12 shrink-0 text-end text-sm tabular-nums">{fmt(s.total)}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
