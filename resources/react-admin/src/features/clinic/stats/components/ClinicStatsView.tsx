import { useState } from 'react';
import { Bell, CalendarCheck, DollarSign, Eye, Info, MessageCircle, MousePointerClick, Navigation, Phone, Search, TrendingUp, Sparkles, ChevronDown, ChevronUp, UserCheck, UserPlus, Users, Users2, Coins, Wallet, CheckCircle2, PackageCheck, Hourglass, XCircle, Clapperboard } from 'lucide-react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Money, fmtMoney } from '@/lib/money';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

import type { ClinicStatsFull, ImpressionSource } from '../api';
import { ACQUISITION_SOURCES } from '@/features/clinic/bookings/kanban/types';

const BOOKING_STATUSES = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
const QUOTE_STATUSES = ['new', 'replied', 'closed'];

export function ClinicStatsView({ data }: { data: ClinicStatsFull }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const nf = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');

  const s = data.summary;
  const c = data.comparison;

  const lifetime = data.lifetime;

  return (
    <div className="space-y-6">
      {/* Audience — all-time tallies mirrored on the public header */}
      {lifetime && (
        <div>
          <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold text-[var(--color-muted-foreground)]">
            <Users className="h-4 w-4" />
            {t('clinic_stats.audience_title')}
          </h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Card icon={UserPlus} tone="primary" label={t('clinic_stats.followers')} value={nf.format(lifetime.followers)} tooltip={t('clinic_stats.followers_tooltip')} />
            <Card icon={Users} tone="info" label={t('clinic_stats.customers')} value={nf.format(lifetime.customers)} tooltip={t('clinic_stats.customers_tooltip')} />
            <Card icon={CalendarCheck} tone="success" label={t('clinic_stats.bookings_total')} value={nf.format(lifetime.bookings)} tooltip={t('clinic_stats.bookings_total_tooltip')} />
          </div>
        </div>
      )}

      {/* ── 1) الظهور والوصول — how many people reached the clinic ── */}
      <section>
        <h2 className="mb-3 text-base font-bold">{t('clinic_stats.section_reach')}</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <ImpressionsCard summary={s} delta={c.appearances_vs_avg_pct} nf={nf} />
          <Card icon={UserCheck} tone="success" label={t('clinic_stats.unique_views')} value={nf.format(s.unique_views)}
            tooltip={t('clinic_stats.unique_views_tooltip')} />
          <Card icon={Eye} tone="info" label={t('clinic_stats.page_views')} value={nf.format(s.page_views)} delta={c.visits_vs_avg_pct}
            tooltip={t('clinic_stats.page_views_tooltip')} />
          <Card icon={Clapperboard} tone="info" label={t('clinic_stats.story_views')} value={nf.format(s.story_views)}
            tooltip={t('clinic_stats.story_views_tooltip')} />
        </div>
      </section>

      {/* ── 2) التفاعل والنقرات — what visitors did on the page ── */}
      <section>
        <h2 className="mb-3 text-base font-bold">{t('clinic_stats.section_engagement')}</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {/* "نقرات زر الحجز" → "فتح صفحة الحجز": same metric, new label,
              with a tooltip clarifying it is NOT a completed booking. */}
          <Card
            icon={MousePointerClick}
            tone="warning"
            label={t('clinic_stats.booking_page_opens', 'فتح صفحة الحجز')}
            value={nf.format(s.booking_clicks)}
            tooltip={t('clinic_stats.booking_page_opens_tooltip',
              'عدد المرات التي فُتحت فيها صفحة الحجز — لا تَعني أن الحجز تم.')}
          />
          <Card icon={Navigation} tone="info" label={t('clinic_stats.directions_clicks')} value={nf.format(s.directions_clicks)}
            tooltip={t('clinic_stats.directions_clicks_tooltip')} />
          <Card icon={Phone} tone="primary" label={t('clinic_stats.call_clicks')} value={nf.format(s.call_clicks)}
            tooltip={t('clinic_stats.call_clicks_tooltip')} />
          <Card icon={MessageCircle} tone="success" label={t('clinic_stats.whatsapp_clicks')} value={nf.format(s.whatsapp_clicks)}
            tooltip={t('clinic_stats.whatsapp_clicks_tooltip')} />
        </div>
      </section>

      {/* ── 3) الطلبات والتحويل — leads generated ── */}
      <section>
        <h2 className="mb-3 text-base font-bold">{t('clinic_stats.section_leads')}</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Card icon={Bell} tone="primary" label={t('clinic_stats.bookings')} value={nf.format(s.bookings)} delta={c.bookings_vs_avg_pct}
            tooltip={t('clinic_stats.bookings_tooltip')} />
          <Card icon={DollarSign} tone="warning" label={t('clinic_stats.quote_requests')} value={nf.format(s.quote_requests)}
            tooltip={t('clinic_stats.quote_requests_tooltip')} />
          <Card icon={TrendingUp} tone="success" label={t('clinic_stats.conversion_rate')} value={`${s.conversion_rate}%`}
            tooltip={t('clinic_stats.conversion_rate_tooltip')} />
        </div>
      </section>

      {/* ── 4) الإيرادات والخدمات — financial results & service execution ── */}
      <section>
        <h2 className="mb-3 text-base font-bold">{t('clinic_stats.section_revenue')}</h2>
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Card icon={CheckCircle2} tone="info" label={t('clinic_stats.completed_bookings')}
            value={nf.format(s.completed_bookings)}
            tooltip={t('clinic_stats.completed_bookings_tooltip')} />
          <Card icon={Wallet} tone="primary" label={t('clinic_stats.avg_ticket')}
            value={<Money value={s.avg_ticket} locale={locale} />}
            tooltip={t('clinic_stats.avg_ticket_tooltip')} />
          <Card icon={Coins} tone="success" label={t('clinic_stats.service_income')}
            value={<Money value={s.service_income} locale={locale} />}
            tooltip={t('clinic_stats.service_income_tooltip')} />
          <Card icon={PackageCheck} tone="success" label={t('clinic_stats.services_taken')}
            value={nf.format(s.services_taken)}
            tooltip={t('clinic_stats.services_taken_tooltip')} />
          <Card icon={Users2} tone="info" label={t('clinic_stats.customers_served')}
            value={nf.format(s.customers_served)}
            tooltip={t('clinic_stats.customers_served_tooltip')} />
          <Card icon={Hourglass} tone="warning" label={t('clinic_stats.pending_services')}
            value={nf.format(s.pending_services)}
            hint={t('clinic_stats.pending_income_hint', { value: fmtMoney(s.pending_income, locale) })}
            tooltip={t('clinic_stats.pending_tooltip')} />
          <Card icon={XCircle} tone="warning" label={t('clinic_stats.lost_income')}
            value={<Money value={s.lost_income} locale={locale} />}
            hint={t('clinic_stats.lost_services_hint', { count: s.lost_services })}
            tooltip={t('clinic_stats.lost_income_tooltip')} />
        </div>
      </section>

      {/* Comparison */}
      <div className="rounded-lg border border-[var(--color-border)] bg-gradient-to-l from-[color-mix(in_oklab,var(--color-primary),white_92%)] to-white p-5">
        <h2 className="text-sm font-semibold">{t('clinic_stats.comparison_title')}</h2>
        {c.rank ? (
          <p className="mt-2 text-sm leading-relaxed">
            {t('clinic_stats.rank_line', { rank: c.rank, total: c.total })}
            {' · '}
            <span className="font-semibold">{t('clinic_stats.your_appearances', { count: c.clinic_appearances })}</span>
            {' · '}
            {t('clinic_stats.platform_avg', { avg: c.avg_appearances })}
            {c.appearances_vs_avg_pct !== null && (
              <span className={cn('ms-1 font-semibold', c.appearances_vs_avg_pct >= 0 ? 'text-emerald-600' : 'text-amber-600')}>
                {c.appearances_vs_avg_pct >= 0
                  ? t('clinic_stats.above_avg', { pct: Math.abs(c.appearances_vs_avg_pct) })
                  : t('clinic_stats.below_avg', { pct: Math.abs(c.appearances_vs_avg_pct) })}
              </span>
            )}
          </p>
        ) : (
          <p className="mt-2 text-sm text-[var(--color-muted-foreground)]">{t('clinic_stats.no_rank')}</p>
        )}
      </div>

      {/* Trend chart */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('clinic_stats.trend_title')}</h2>
        <div className="h-64 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data.trend}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="unique_views" name={t('clinic_stats.unique_views')} stroke="#10b981" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="page_views" name={t('clinic_stats.page_views')} stroke="#0ea5e9" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="bookings" name={t('clinic_stats.bookings')} stroke="#0066cc" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="quote_requests" name={t('clinic_stats.quote_requests')} stroke="#f59e0b" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Income trend — its own chart (money scale differs from counts) */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('clinic_stats.income_trend')}</h2>
        <div className="h-56 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data.trend}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis tick={{ fontSize: 11 }} width={70}
                tickFormatter={(v) => nf.format(v as number)} />
              <Tooltip formatter={(v) => nf.format(v as number)} />
              <Line type="monotone" dataKey="income" name={t('clinic_stats.service_income')} stroke="#10b981" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Distributions */}
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

      {/* Bookings + income by acquisition source */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <DistributionBars
          title={t('clinic_stats.bookings_by_acquisition_source')}
          rows={ACQUISITION_SOURCES
            .map((k) => ({ label: t(`clinic_bookings_kanban.source.opt.${k}`), value: data.bookings_by_acquisition_source?.[k] ?? 0 }))
            .filter((r) => r.value > 0)}
          empty={t('common.no_data')}
        />
        <DistributionBars
          title={t('clinic_stats.income_by_source')}
          rows={ACQUISITION_SOURCES
            .map((k) => ({ label: t(`clinic_bookings_kanban.source.opt.${k}`), value: data.income_by_acquisition_source?.[k] ?? 0 }))
            .filter((r) => r.value > 0)}
          empty={t('common.no_data')}
          money
        />
      </div>

      {/* Best days */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <BestDay label={t('clinic_stats.best_visit_day')} weekday={data.best_days.top_visits_weekday} />
        <BestDay label={t('clinic_stats.best_request_day')} weekday={data.best_days.top_requests_weekday} />
      </div>

      {/* Top services by impressions — per-source breakdown */}
      <TopServicesByImpressions rows={data.top_services_by_views} />

      {/* Services performance */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('clinic_stats.services_performance')}</h2>
        <div className="max-h-80 overflow-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('clinic_stats.service')}</TableHead>
                <TableHead>{t('clinic_stats.price')}</TableHead>
                <TableHead>{t('clinic_stats.bookings')}</TableHead>
                <TableHead>{t('clinic_stats.income')}</TableHead>
                <TableHead>{t('clinic_stats.quote_requests')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.services_performance.length === 0 ? (
                <TableRow><TableCell colSpan={5} className="py-6 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
              ) : (
                data.services_performance.map((row, i) => (
                  <TableRow key={row.id}>
                    <TableCell className="font-medium">
                      {row.name}
                      {i === 0 && row.bookings > 0 && <Badge variant="gold" className="ms-2 text-xs">{t('clinic_stats.top')}</Badge>}
                    </TableCell>
                    <TableCell><Money value={row.price} locale={locale} /></TableCell>
                    <TableCell>{nf.format(row.bookings)}</TableCell>
                    <TableCell className="font-medium"><Money value={row.income} locale={locale} /></TableCell>
                    <TableCell>{nf.format(row.quote_requests)}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* Articles performance */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('clinic_stats.articles_performance')}</h2>
        <div className="max-h-80 overflow-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('clinic_stats.article')}</TableHead>
                <TableHead>{t('clinic_stats.views')}</TableHead>
                <TableHead>{t('clinic_stats.source')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.articles_performance.length === 0 ? (
                <TableRow><TableCell colSpan={3} className="py-6 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
              ) : (
                data.articles_performance.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="max-w-xs truncate font-medium">{a.title}</TableCell>
                    <TableCell>{nf.format(a.views)}</TableCell>
                    <TableCell>
                      <Badge variant={a.ai_generated ? 'info' : 'muted'} className="text-xs">
                        {a.ai_generated ? t('clinic_stats.ai') : t('clinic_stats.manual')}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* Recommendation */}
      <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4">
        <Sparkles className="mt-0.5 h-5 w-5 shrink-0 text-[var(--color-primary)]" />
        <p className="text-sm text-blue-900">
          {t(`clinic_stats.rec.${data.recommendation}`, { rank: c.rank ?? 0, total: c.total })}
        </p>
      </div>
    </div>
  );
}

type Tone = 'success' | 'primary' | 'warning' | 'info';
const TONE: Record<Tone, string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  info: 'bg-sky-50 text-sky-700',
};

function Card({
  icon: Icon, tone, label, value, delta, tooltip, hint,
}: {
  icon: React.ComponentType<{ className?: string }>;
  tone: Tone; label: string; value: React.ReactNode; delta?: number | null; tooltip?: string; hint?: string;
}) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between">
        <div>
          <div className="flex items-center gap-1 text-xs text-[var(--color-muted-foreground)]">
            <span>{label}</span>
            {tooltip && (
              <span title={tooltip} className="cursor-help text-[var(--color-muted-foreground)]">
                <Info className="h-3 w-3" />
              </span>
            )}
          </div>
          <div className="mt-1 text-2xl font-semibold">{value}</div>
          {hint && <div className="mt-1 text-xs text-[var(--color-muted-foreground)]">{hint}</div>}
          {delta !== undefined && delta !== null && (
            <div className={cn('mt-1 text-xs font-medium', delta >= 0 ? 'text-emerald-600' : 'text-amber-600')}>
              {delta >= 0 ? '▲' : '▼'} {Math.abs(delta)}%
            </div>
          )}
        </div>
        <div className={`flex h-9 w-9 items-center justify-center rounded-md ${TONE[tone]}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

/**
 * Total-impressions card with collapsible per-source breakdown. The
 * server hides AI from the breakdown for the clinic-facing payload,
 * which is why the total may exceed the sum of visible rows.
 */
function ImpressionsCard({
  summary, delta, nf,
}: { summary: ClinicStatsFull['summary']; delta: number | null; nf: Intl.NumberFormat }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const entries = Object.entries(summary.impressions ?? {}).filter(([, v]) => v !== undefined) as [ImpressionSource, number][];
  const visibleSum = entries.reduce((sum, [, v]) => sum + (v ?? 0), 0);
  const hasHiddenContribution = summary.impressions_total > visibleSum;

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1 text-xs text-[var(--color-muted-foreground)]">
            <span>{t('clinic_stats.impressions_total', 'إجمالي الظهور')}</span>
            <span title={t('clinic_stats.impressions_total_tooltip')} className="cursor-help">
              <Info className="h-3 w-3" />
            </span>
          </div>
          <div className="mt-1 text-2xl font-semibold">{nf.format(summary.impressions_total)}</div>
          {delta !== undefined && delta !== null && (
            <div className={cn('mt-1 text-xs font-medium', delta >= 0 ? 'text-emerald-600' : 'text-amber-600')}>
              {delta >= 0 ? '▲' : '▼'} {Math.abs(delta)}%
            </div>
          )}
        </div>
        <div className={`flex h-9 w-9 items-center justify-center rounded-md ${TONE.info}`}>
          <Search className="h-5 w-5" />
        </div>
      </div>

      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="mt-3 inline-flex items-center gap-1 text-[11px] font-medium text-[var(--color-primary)] hover:underline"
      >
        {open ? <ChevronUp className="h-3 w-3" /> : <ChevronDown className="h-3 w-3" />}
        {t('clinic_stats.show_sources', 'تفصيل المصادر')}
      </button>

      {open && (
        <div className="mt-2 space-y-1 border-t border-[var(--color-border)] pt-2 text-[11px]">
          {entries.map(([source, count]) => (
            <div key={source} className="flex items-center justify-between">
              <span className="text-[var(--color-muted-foreground)]">
                {t(`clinic_stats.source_${source}`, source)}
              </span>
              <span className="font-mono font-semibold">{nf.format(count ?? 0)}</span>
            </div>
          ))}
          {hasHiddenContribution && (
            <p className="mt-2 rounded-md bg-[var(--color-muted)] px-2 py-1 text-[10px] italic text-[var(--color-muted-foreground)]">
              {t('clinic_stats.hidden_contribution_note', 'الإجمالي يَتضمّن مصادر داخلية للمنصة لا تُعرَض كصفّ منفصل.')}
            </p>
          )}
        </div>
      )}
    </div>
  );
}

/**
 * Top services by impressions — table with per-source breakdown. The
 * `by_source` keys come straight from the server, so the AI column
 * appears in the admin view and is absent in the clinic view.
 */
function TopServicesByImpressions({ rows }: { rows: ClinicStatsFull['top_services_by_views'] }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const nf = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');

  // Derive the visible source set from the first row (server already
  // hides AI from clinic-facing payloads, so this list is correct
  // for whichever audience we're rendering for).
  const sources = (rows[0] ? (Object.keys(rows[0].by_source) as ImpressionSource[]) : []) as ImpressionSource[];

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <h2 className="mb-3 text-sm font-semibold">
        {t('clinic_stats.top_services_by_views', 'أكثر الخدمات ظهوراً')}
      </h2>
      <div className="max-h-80 overflow-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('clinic_stats.service')}</TableHead>
              <TableHead>{t('clinic_stats.impressions_total', 'إجمالي الظهور')}</TableHead>
              {sources.map((s) => (
                <TableHead key={s} className="text-xs">{t(`clinic_stats.source_${s}`, s)}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={2 + sources.length} className="py-6 text-center text-[var(--color-muted-foreground)]">
                  {t('common.no_data')}
                </TableCell>
              </TableRow>
            ) : (
              rows.map((r) => (
                <TableRow key={r.service_id}>
                  <TableCell className="font-medium">{r.name}</TableCell>
                  <TableCell className="font-semibold">{nf.format(r.total)}</TableCell>
                  {sources.map((s) => (
                    <TableCell key={s} className="text-xs text-[var(--color-muted-foreground)]">
                      {nf.format(r.by_source[s] ?? 0)}
                    </TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function DistributionBars({ title, rows, empty, money }: { title: string; rows: { label: string; value: number }[]; empty: string; money?: boolean }) {
  const { locale } = useLocale();
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
              <span className="w-24 shrink-0 text-[var(--color-muted-foreground)]">{r.label}</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--color-muted)]">
                <div className="h-full rounded-full bg-[var(--color-primary)]" style={{ width: `${total > 0 ? (r.value / total) * 100 : 0}%` }} />
              </div>
              <span className={`${money ? 'w-20' : 'w-8'} shrink-0 text-end font-medium`}>
                {money ? <Money value={r.value} locale={locale} /> : r.value}
              </span>
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
