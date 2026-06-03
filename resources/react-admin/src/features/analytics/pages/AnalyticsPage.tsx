import { useState } from 'react';
import {
  BarChart3, Bell, DollarSign, Eye, MousePointerClick, MessageCircle, MessageSquare,
  Navigation, Phone, Search, Sparkles, TrendingUp, Users, Building2, CreditCard, Filter,
} from 'lucide-react';
import {
  Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';

import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';
import { StatsFilterBar } from '@/features/clinic/stats/components/StatsFilterBar';
import type { StatsRange, ImpressionSource } from '@/features/clinic/stats/api';

import { useAnalytics } from '../hooks';
import type { AnalyticsData } from '../api';

const BOOKING_STATUSES = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
const QUOTE_STATUSES = ['new', 'replied', 'closed'];
const IMPRESSION_SOURCES: ImpressionSource[] = ['search', 'filter', 'home', 'similar_services', 'compare', 'ai'];

type Tone = 'success' | 'primary' | 'warning' | 'info' | 'muted';
const TONE: Record<Tone, string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  info: 'bg-sky-50 text-sky-700',
  muted: 'bg-slate-100 text-slate-600',
};

export function AnalyticsPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [range, setRange] = useState<StatsRange>({ period: 30 });
  const { data, isLoading } = useAnalytics(range);

  const nf = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const cf = (n: number) =>
    new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(n);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('analytics.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('analytics.subtitle')}</p>
      </div>

      <StatsFilterBar range={range} onChange={setRange} />

      {isLoading || !data ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : (
        <AnalyticsContent data={data} nf={nf} cf={cf} />
      )}
    </div>
  );
}

function AnalyticsContent({ data, nf, cf }: { data: AnalyticsData; nf: Intl.NumberFormat; cf: (n: number) => string }) {
  const { t } = useTranslation();
  const s = data.summary;
  const d = data.deltas;
  const p = data.platform;

  return (
    <div className="space-y-6">
      {/* Platform overview — revenue & growth */}
      <SectionTitle icon={Building2}>{t('analytics.platform_overview')}</SectionTitle>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Card icon={DollarSign} tone="success" label={t('analytics.revenue')} value={cf(p.revenue)} delta={d.revenue} />
        <Card icon={CreditCard} tone="primary" label={t('analytics.active_subscriptions')} value={nf.format(p.active_subscriptions)}
          hint={t('analytics.new_subscriptions_hint', { count: p.new_subscriptions })} />
        <Card icon={Building2} tone="info" label={t('analytics.active_clinics')} value={nf.format(p.active_clinics)}
          hint={t('analytics.total_clinics_hint', { count: p.total_clinics })} />
        <Card icon={Users} tone="warning" label={t('analytics.new_clinics')} value={nf.format(p.new_clinics)} />
      </div>

      {/* Visibility & engagement KPIs */}
      <SectionTitle icon={TrendingUp}>{t('analytics.engagement_title')}</SectionTitle>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Card icon={Search} tone="info" label={t('clinic_stats.impressions_total')} value={nf.format(s.impressions_total)} delta={d.impressions} />
        <Card icon={Eye} tone="primary" label={t('clinic_stats.page_views')} value={nf.format(s.page_views)} delta={d.page_views} />
        <Card icon={Bell} tone="success" label={t('clinic_stats.bookings')} value={nf.format(s.bookings)} delta={d.bookings} />
        <Card icon={MessageSquare} tone="warning" label={t('clinic_stats.quote_requests')} value={nf.format(s.quote_requests)} delta={d.quote_requests} />
      </div>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Card icon={TrendingUp} tone="success" label={t('clinic_stats.conversion_rate')} value={`${s.conversion_rate}%`} />
        <Card icon={Eye} tone="info" label={t('analytics.engagement_rate')} value={`${s.engagement_rate}%`}
          hint={t('analytics.engagement_rate_hint')} />
        <Card icon={MessageCircle} tone="success" label={t('clinic_stats.whatsapp_clicks')} value={nf.format(s.whatsapp_clicks)} />
        <Card icon={Phone} tone="primary" label={t('clinic_stats.call_clicks')} value={nf.format(s.call_clicks)} />
      </div>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Card icon={Navigation} tone="info" label={t('clinic_stats.directions_clicks')} value={nf.format(s.directions_clicks)} />
        <Card icon={MousePointerClick} tone="warning" label={t('clinic_stats.booking_page_opens', 'فتح صفحة الحجز')} value={nf.format(s.booking_clicks)} />
      </div>

      {/* Acquisition funnel */}
      <Funnel funnel={data.funnel} nf={nf} />

      {/* Trend chart */}
      <Panel title={t('clinic_stats.trend_title')}>
        <div className="h-72 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data.trend}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="impressions" name={t('clinic_stats.impressions_total')} stroke="#8b5cf6" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="page_views" name={t('clinic_stats.page_views')} stroke="#0ea5e9" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="bookings" name={t('clinic_stats.bookings')} stroke="#0066cc" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="quote_requests" name={t('clinic_stats.quote_requests')} stroke="#f59e0b" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </Panel>

      {/* Impressions by source + distributions */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <SourceBars rows={data.impressions_by_source} nf={nf} />
        <DistributionBars
          title={t('analytics.bookings_by_source_title')}
          rows={Object.entries(data.bookings_by_source).map(([k, v]) => ({ label: t(`analytics.booking_source.${k}`, k), value: v }))}
          empty={t('common.no_data')}
        />
      </div>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <DistributionBars
          title={t('clinic_stats.bookings_by_status')}
          rows={BOOKING_STATUSES.map((k) => ({ label: t(`clinic_stats.bk_status.${k}`), value: data.bookings_by_status[k] ?? 0 }))}
          empty={t('common.no_data')}
        />
        <DistributionBars
          title={t('clinic_stats.quotes_by_status')}
          rows={QUOTE_STATUSES.map((k) => ({ label: t(`clinic_stats.q_status.${k}`), value: data.quotes_by_status[k] ?? 0 }))}
          empty={t('common.no_data')}
        />
      </div>

      {/* Best days */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <BestDay label={t('clinic_stats.best_visit_day')} weekday={data.best_days.top_visits_weekday} />
        <BestDay label={t('clinic_stats.best_request_day')} weekday={data.best_days.top_requests_weekday} />
      </div>

      {/* Top clinics leaderboard */}
      <Panel title={t('analytics.top_clinics_title')}>
        <div className="max-h-96 overflow-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('analytics.col_clinic')}</TableHead>
                <TableHead>{t('clinic_stats.impressions_total')}</TableHead>
                <TableHead>{t('clinic_stats.page_views')}</TableHead>
                <TableHead>{t('clinic_stats.bookings')}</TableHead>
                <TableHead>{t('clinic_stats.conversion_rate')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.top_clinics.length === 0 ? (
                <TableRow><TableCell colSpan={5} className="py-6 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
              ) : (
                data.top_clinics.map((c, i) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">
                      {c.name}
                      {i === 0 && <Badge variant="gold" className="ms-2 text-xs">{t('clinic_stats.top')}</Badge>}
                    </TableCell>
                    <TableCell className="font-semibold">{nf.format(c.impressions)}</TableCell>
                    <TableCell>{nf.format(c.page_views)}</TableCell>
                    <TableCell>{nf.format(c.bookings)}</TableCell>
                    <TableCell>{c.conversion_rate}%</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </Panel>

      {/* Top services by impressions — per-source breakdown */}
      <Panel title={t('clinic_stats.top_services_by_views', 'أكثر الخدمات ظهوراً')}>
        <div className="max-h-96 overflow-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('clinic_stats.service')}</TableHead>
                <TableHead>{t('analytics.col_owner')}</TableHead>
                <TableHead>{t('clinic_stats.impressions_total')}</TableHead>
                {IMPRESSION_SOURCES.map((src) => (
                  <TableHead key={src} className="text-xs">{t(`clinic_stats.source_${src}`, src)}</TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.top_services.length === 0 ? (
                <TableRow><TableCell colSpan={3 + IMPRESSION_SOURCES.length} className="py-6 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
              ) : (
                data.top_services.map((r) => (
                  <TableRow key={r.service_id}>
                    <TableCell className="font-medium">{r.name}</TableCell>
                    <TableCell className="text-xs text-[var(--color-muted-foreground)]">{r.clinic_name}</TableCell>
                    <TableCell className="font-semibold">{nf.format(r.total)}</TableCell>
                    {IMPRESSION_SOURCES.map((src) => (
                      <TableCell key={src} className="text-xs text-[var(--color-muted-foreground)]">{nf.format(r.by_source[src] ?? 0)}</TableCell>
                    ))}
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </Panel>

      {/* Monthly growth */}
      <Panel title={t('analytics.monthly')}>
        <div className="mb-4 h-56 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data.monthly}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="month" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Bar dataKey="bookings" name={t('analytics.bookings')} fill="#0066cc" radius={[4, 4, 0, 0]} />
              <Bar dataKey="clinics" name={t('analytics.clinics')} fill="#0ea5e9" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('analytics.month')}</TableHead>
              <TableHead>{t('analytics.clinics')}</TableHead>
              <TableHead>{t('analytics.bookings')}</TableHead>
              <TableHead>{t('analytics.revenue_col')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.monthly.length === 0 ? (
              <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
            ) : (
              data.monthly.map((m) => (
                <TableRow key={m.month}>
                  <TableCell className="font-medium">{m.month}</TableCell>
                  <TableCell>{nf.format(m.clinics)}</TableCell>
                  <TableCell>{nf.format(m.bookings)}</TableCell>
                  <TableCell>{cf(m.revenue)}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </Panel>

      {/* By specialty */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
          <BarChart3 className="h-4 w-4 text-blue-600" />
          <h2 className="text-sm font-semibold">{t('analytics.by_specialty')}</h2>
        </div>
        <div className="divide-y divide-[var(--color-border)]">
          {data.by_specialty.length === 0 ? (
            <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
          ) : (
            data.by_specialty.map((sp) => (
              <div key={sp.name} className="flex items-center justify-between px-4 py-2.5 text-sm">
                <span className="truncate font-medium">{sp.name}</span>
                <Badge variant="muted">{nf.format(sp.clinics_count)}</Badge>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}

function SectionTitle({ icon: Icon, children }: { icon: React.ComponentType<{ className?: string }>; children: React.ReactNode }) {
  return (
    <h2 className="flex items-center gap-2 text-sm font-semibold text-[var(--color-muted-foreground)]">
      <Icon className="h-4 w-4" />
      {children}
    </h2>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <h2 className="mb-3 text-sm font-semibold">{title}</h2>
      {children}
    </div>
  );
}

function Card({
  icon: Icon, tone, label, value, delta, hint,
}: {
  icon: React.ComponentType<{ className?: string }>;
  tone: Tone; label: string; value: string | number; delta?: number | null; hint?: string;
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

/** Acquisition funnel: impressions → views → contacts → conversions, with stage conversion rates. */
function Funnel({ funnel, nf }: { funnel: AnalyticsData['funnel']; nf: Intl.NumberFormat }) {
  const { t } = useTranslation();
  const stages = [
    { key: 'impressions', label: t('clinic_stats.impressions_total'), value: funnel.impressions, tone: 'bg-violet-500' },
    { key: 'page_views', label: t('clinic_stats.page_views'), value: funnel.page_views, tone: 'bg-sky-500', rate: funnel.view_rate },
    { key: 'contacts', label: t('analytics.funnel_contacts'), value: funnel.contacts, tone: 'bg-blue-600', rate: funnel.contact_rate },
    { key: 'conversions', label: t('analytics.funnel_conversions'), value: funnel.conversions, tone: 'bg-emerald-600', rate: funnel.close_rate },
  ];
  const max = Math.max(funnel.impressions, 1);

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="mb-1 flex items-center gap-2">
        <Filter className="h-4 w-4 text-[var(--color-primary)]" />
        <h2 className="text-sm font-semibold">{t('analytics.funnel_title')}</h2>
      </div>
      <p className="mb-4 text-xs text-[var(--color-muted-foreground)]">{t('analytics.funnel_hint')}</p>
      <div className="space-y-3">
        {stages.map((st) => (
          <div key={st.key}>
            <div className="mb-1 flex items-center justify-between text-xs">
              <span className="font-medium">{st.label}</span>
              <span className="flex items-center gap-2">
                {st.rate !== undefined && (
                  <span className="rounded bg-[var(--color-muted)] px-1.5 py-0.5 text-[10px] text-[var(--color-muted-foreground)]">
                    {st.rate}% {t('analytics.funnel_step_rate')}
                  </span>
                )}
                <span className="font-mono font-semibold">{nf.format(st.value)}</span>
              </span>
            </div>
            <div className="h-3 w-full overflow-hidden rounded-full bg-[var(--color-muted)]">
              <div className={cn('h-full rounded-full', st.tone)} style={{ width: `${(st.value / max) * 100}%` }} />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/** Impressions split by source, as share-of-total bars. */
function SourceBars({ rows, nf }: { rows: AnalyticsData['impressions_by_source']; nf: Intl.NumberFormat }) {
  const { t } = useTranslation();
  const total = rows.reduce((sum, r) => sum + r.count, 0);
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="mb-3 flex items-center gap-2">
        <Sparkles className="h-4 w-4 text-[var(--color-primary)]" />
        <h2 className="text-sm font-semibold">{t('analytics.impressions_by_source_title')}</h2>
      </div>
      {total === 0 ? (
        <p className="py-4 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</p>
      ) : (
        <div className="space-y-2">
          {rows.map((r) => (
            <div key={r.source} className="flex items-center gap-2 text-xs">
              <span className="w-28 shrink-0 text-[var(--color-muted-foreground)]">{t(`clinic_stats.source_${r.source}`, r.source)}</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--color-muted)]">
                <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${r.pct}%` }} />
              </div>
              <span className="w-10 shrink-0 text-end font-medium">{r.pct}%</span>
              <span className="w-12 shrink-0 text-end font-mono text-[var(--color-muted-foreground)]">{nf.format(r.count)}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function DistributionBars({ title, rows, empty }: { title: string; rows: { label: string; value: number }[]; empty: string }) {
  const total = rows.reduce((sum, r) => sum + r.value, 0);
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <h2 className="mb-3 text-sm font-semibold">{title}</h2>
      {total === 0 ? (
        <p className="py-4 text-center text-xs text-[var(--color-muted-foreground)]">{empty}</p>
      ) : (
        <div className="space-y-2">
          {rows.map((r) => (
            <div key={r.label} className="flex items-center gap-2 text-xs">
              <span className="w-28 shrink-0 truncate text-[var(--color-muted-foreground)]">{r.label}</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--color-muted)]">
                <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${total > 0 ? (r.value / total) * 100 : 0}%` }} />
              </div>
              <span className="w-10 shrink-0 text-end font-medium">{r.value}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function BestDay({ label, weekday }: { label: string; weekday: number | null }) {
  const { t } = useTranslation();
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="text-xs text-[var(--color-muted-foreground)]">{label}</div>
      <div className="mt-1 text-lg font-semibold">
        {weekday === null ? '—' : t(`clinic_profile.days.${weekday}`)}
      </div>
    </div>
  );
}
