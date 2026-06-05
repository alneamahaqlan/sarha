import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Bell, Building2, Calendar, Clock, CreditCard, Eye, MapPin, MessageSquare, Search, TrendingUp, Users } from 'lucide-react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { CopyBadge } from '@/components/ui/copy-badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { fmtMoney, RiyalSymbol } from '@/lib/money';
import { cn } from '@/lib/utils';
import { BookingStatusBadge } from '@/features/bookings/components/StatusBadge';
import type { BookingStatus } from '@/features/bookings/types';
import { StatsFilterBar } from '@/features/clinic/stats/components/StatsFilterBar';
import type { StatsRange } from '@/features/clinic/stats/api';
import { useAnalytics } from '@/features/analytics/hooks';

import { useAdminStats, useBookingsTrend, useDashboardSections, useLatestBookings } from '../hooks';
import { AiDashboardWidget } from '@/features/ai-center/phase2/components/AiDashboardWidget';

interface StatCardProps {
  label: string;
  value: string | number;
  hint?: React.ReactNode;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning' | 'info';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  info:    'bg-sky-50 text-sky-700',
};

function StatCard({ label, value, hint, icon: Icon, tone }: StatCardProps) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between">
        <div>
          <div className="text-xs text-[var(--color-muted-foreground)]">{label}</div>
          <div className="mt-1 text-2xl font-semibold">{value}</div>
          {hint && <div className="mt-1 text-xs text-[var(--color-muted-foreground)]">{hint}</div>}
        </div>
        <div className={`flex h-9 w-9 items-center justify-center rounded-md ${TONE[tone]}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

export function DashboardPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: stats } = useAdminStats();
  const { data: latest } = useLatestBookings();
  const { data: trend } = useBookingsTrend();
  const { data: sections } = useDashboardSections();

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';
  const fmtDay = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('dashboard.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('dashboard.subtitle')}</p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={t('dashboard.active_clinics')}
          value={stats ? fmt.format(stats.active_clinics) : '—'}
          hint={stats ? t('dashboard.pending_count', { count: stats.pending_clinics }) : undefined}
          icon={Building2}
          tone="success"
        />
        <StatCard
          label={t('dashboard.today_bookings')}
          value={stats ? fmt.format(stats.today_bookings) : '—'}
          hint={stats ? t('dashboard.month_bookings_count', { count: stats.month_bookings }) : undefined}
          icon={Calendar}
          tone="primary"
        />
        <StatCard
          label={t('dashboard.active_subscriptions')}
          value={stats ? fmt.format(stats.active_subscriptions) : '—'}
          hint={stats ? <>{t('dashboard.month_revenue', { amount: fmtMoney(stats.month_revenue, locale) })} <RiyalSymbol /></> : undefined}
          icon={CreditCard}
          tone="warning"
        />
        <StatCard
          label={t('dashboard.registered_users')}
          value={stats ? fmt.format(stats.total_users) : '—'}
          icon={Users}
          tone="info"
        />
      </div>

      {/* AI Assistant summary — Phase 2 widget. Same row dimensions as
          the kpi cards above so it doesn't push the bookings chart down. */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="lg:col-span-1">
          <AiDashboardWidget />
        </div>
      </div>

      {/* Visibility & engagement summary — range-filtered headline metrics
          (impressions / views / requests with period-over-period deltas).
          Pulls the platform analytics payload; the deep breakdown lives on
          the dedicated Analytics page, linked from the header. */}
      <EngagementSummary fmt={fmt} />

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('dashboard.bookings_trend')}</h2>
        <div className="h-64 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={trend ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Line type="monotone" dataKey="count" stroke="#0066cc" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Subscriptions expiring soon */}
        <div className="rounded-lg border border-[var(--color-border)] bg-white">
          <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
            <Clock className="h-4 w-4 text-amber-600" />
            <h2 className="text-sm font-semibold">{t('dashboard.expiring_soon')}</h2>
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {!sections || sections.expiring_soon.length === 0 ? (
              <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
            ) : (
              sections.expiring_soon.map((c) => (
                <div key={c.id} className="flex items-center justify-between px-4 py-2.5 text-sm">
                  <div className="min-w-0">
                    <div className="truncate font-medium">{c.name}</div>
                    <div className="text-xs text-[var(--color-muted-foreground)]">{fmtDay(c.subscription_ends_at)}</div>
                  </div>
                  <Badge variant={c.days_left <= 3 ? 'danger' : 'warning'}>
                    {t('dashboard.days_left', { count: c.days_left })}
                  </Badge>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Top clinics by bookings */}
        <div className="rounded-lg border border-[var(--color-border)] bg-white">
          <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
            <TrendingUp className="h-4 w-4 text-emerald-600" />
            <h2 className="text-sm font-semibold">{t('dashboard.top_clinics')}</h2>
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {!sections || sections.top_clinics.length === 0 ? (
              <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
            ) : (
              sections.top_clinics.map((c, i) => (
                <div key={c.id} className="flex items-center justify-between px-4 py-2.5 text-sm">
                  <div className="flex min-w-0 items-center gap-2">
                    <span className="text-xs font-semibold text-[var(--color-muted-foreground)]">{i + 1}</span>
                    <span className="truncate font-medium">{c.name}</span>
                  </div>
                  <Badge variant="muted">{t('dashboard.bookings_n', { count: c.bookings_count })}</Badge>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Clinics by city */}
        <div className="rounded-lg border border-[var(--color-border)] bg-white">
          <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
            <MapPin className="h-4 w-4 text-blue-600" />
            <h2 className="text-sm font-semibold">{t('dashboard.by_city')}</h2>
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {!sections || sections.by_city.length === 0 ? (
              <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
            ) : (
              sections.by_city.map((c) => (
                <div key={c.id} className="flex items-center justify-between px-4 py-2.5 text-sm">
                  <span className="truncate font-medium">{c.name}</span>
                  <Badge variant="muted">{c.clinics_count}</Badge>
                </div>
              ))
            )}
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <div className="border-b border-[var(--color-border)] px-4 py-3">
          <h2 className="text-sm font-semibold">{t('dashboard.latest_bookings')}</h2>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('dashboard.reference')}</TableHead>
              <TableHead>{t('dashboard.clinic')}</TableHead>
              <TableHead>{t('dashboard.customer')}</TableHead>
              <TableHead>{t('dashboard.customer_phone')}</TableHead>
              <TableHead>{t('dashboard.service')}</TableHead>
              <TableHead>{t('dashboard.status')}</TableHead>
              <TableHead>{t('dashboard.created_at')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {!latest || latest.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">
                  {t('common.no_data')}
                </TableCell>
              </TableRow>
            ) : (
              latest.map((b) => (
                <TableRow key={b.id}>
                  <TableCell><CopyBadge value={b.reference_code} /></TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{b.clinic?.name ?? '—'}</TableCell>
                  <TableCell className="font-medium">{b.customer_name}</TableCell>
                  <TableCell dir="ltr">{b.customer_phone}</TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{b.service?.name ?? '—'}</TableCell>
                  <TableCell><BookingStatusBadge status={b.status as BookingStatus} /></TableCell>
                  <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(b.created_at)}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

/**
 * Range-filtered visibility & engagement summary for the landing dashboard.
 * Reuses the platform analytics endpoint (impressions sourced from
 * clinic_impressions, so it reconciles with the per-clinic stats) and the
 * clinic stats filter bar (quick periods + custom from/to). Deep analysis
 * (funnel, leaderboard, per-source tables) stays on the Analytics page.
 */
function EngagementSummary({ fmt }: { fmt: Intl.NumberFormat }) {
  const { t } = useTranslation();
  const [range, setRange] = useState<StatsRange>({ period: 30 });
  const { data, isLoading } = useAnalytics(range);
  const s = data?.summary;
  const d = data?.deltas;

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-[var(--color-muted-foreground)]">
          <TrendingUp className="h-4 w-4" />
          {t('analytics.engagement_title')}
        </h2>
        <Link
          to="/admin/analytics"
          className="inline-flex items-center gap-1 text-xs font-medium text-[var(--color-primary)] hover:underline"
        >
          {t('dashboard.view_full_analytics')}
          <ArrowLeft className="h-3 w-3 rtl:rotate-180" />
        </Link>
      </div>

      <StatsFilterBar range={range} onChange={setRange} />

      {isLoading || !s ? (
        <div className="rounded-lg border border-[var(--color-border)] bg-white py-10 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('common.loading')}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <KpiCard icon={Search} tone="info" label={t('clinic_stats.impressions_total')} value={fmt.format(s.impressions_total)} delta={d?.impressions} />
            <KpiCard icon={Eye} tone="primary" label={t('clinic_stats.page_views')} value={fmt.format(s.page_views)} delta={d?.page_views} />
            <KpiCard icon={Bell} tone="success" label={t('clinic_stats.bookings')} value={fmt.format(s.bookings)} delta={d?.bookings} />
            <KpiCard icon={MessageSquare} tone="warning" label={t('clinic_stats.quote_requests')} value={fmt.format(s.quote_requests)} delta={d?.quote_requests} />
          </div>
          <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <KpiCard icon={TrendingUp} tone="success" label={t('clinic_stats.conversion_rate')} value={`${s.conversion_rate}%`} />
            <KpiCard icon={Eye} tone="info" label={t('analytics.engagement_rate')} value={`${s.engagement_rate}%`} hint={t('analytics.engagement_rate_hint')} />
          </div>
        </>
      )}
    </div>
  );
}

function KpiCard({
  icon: Icon, tone, label, value, delta, hint,
}: {
  icon: React.ComponentType<{ className?: string }>;
  tone: StatCardProps['tone']; label: string; value: string | number; delta?: number | null; hint?: React.ReactNode;
}) {
  const { t } = useTranslation();
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between">
        <div className="min-w-0">
          <div className="text-xs text-[var(--color-muted-foreground)]">{label}</div>
          <div className="mt-1 text-2xl font-semibold">{value}</div>
          {delta !== undefined && delta !== null && (
            <div className={cn('mt-1 text-xs font-medium', delta >= 0 ? 'text-emerald-600' : 'text-amber-600')}>
              {delta >= 0 ? '▲' : '▼'} {Math.abs(delta)}% <span className="text-[var(--color-muted-foreground)]">{t('analytics.vs_prev')}</span>
            </div>
          )}
          {hint && <div className="mt-1 text-[11px] text-[var(--color-muted-foreground)]">{hint}</div>}
        </div>
        <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-md ${TONE[tone]}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}
