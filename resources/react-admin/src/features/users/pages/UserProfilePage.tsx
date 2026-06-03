import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import {
  Activity, AlertTriangle, ArrowLeft, Ban, Calendar, Clock, Eye, EyeOff,
  Filter, Heart, LogIn, LogOut, MessageSquare, MousePointerClick, Phone,
  Receipt, Search, Settings2, UserX,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  formatDuration, userProfileApi, type Paginated, type ProfileHeader, type TimelineEvent,
} from '../profile/api';

// Stable list of timeline event types — drives the multi-select filter
// and the icon map. Adding a type only here + on the server is enough.
const TIMELINE_TYPES = [
  'login', 'logout', 'otp_verified', 'search', 'filter', 'clinic_view',
  'compare', 'action_click', 'account_edit',
  'booking_created', 'booking_cancelled', 'complaint_created',
  'ai_conversation', 'favorite_added', 'quote_request',
] as const;

const TYPE_ICON: Record<string, JSX.Element> = {
  login: <LogIn className="h-3.5 w-3.5" />,
  logout: <LogOut className="h-3.5 w-3.5" />,
  otp_verified: <Settings2 className="h-3.5 w-3.5" />,
  search: <Search className="h-3.5 w-3.5" />,
  filter: <Filter className="h-3.5 w-3.5" />,
  clinic_view: <Eye className="h-3.5 w-3.5" />,
  compare: <Receipt className="h-3.5 w-3.5" />,
  action_click: <MousePointerClick className="h-3.5 w-3.5" />,
  account_edit: <Settings2 className="h-3.5 w-3.5" />,
  booking_created: <Calendar className="h-3.5 w-3.5" />,
  booking_cancelled: <Ban className="h-3.5 w-3.5" />,
  complaint_created: <AlertTriangle className="h-3.5 w-3.5" />,
  ai_conversation: <MessageSquare className="h-3.5 w-3.5" />,
  favorite_added: <Heart className="h-3.5 w-3.5" />,
  quote_request: <Receipt className="h-3.5 w-3.5" />,
};

const TABS = ['bookings', 'complaints', 'ai', 'sessions', 'favorites', 'quotes', 'account_edits'] as const;
type TabKey = typeof TABS[number];

export function UserProfilePage() {
  const { id } = useParams();
  const userId = Number(id);
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [reveal, setReveal] = useState(false);
  const [activeTab, setActiveTab] = useState<TabKey>('bookings');

  // Header + cards + insights + risk flags — one round-trip. The
  // `reveal` toggle re-runs the query so the server-side audit log
  // sees the "show PII" interaction.
  const headerQuery = useQuery({
    queryKey: ['userProfile', userId, reveal],
    queryFn: () => userProfileApi.show(userId, reveal),
    enabled: Number.isFinite(userId),
  });

  if (!Number.isFinite(userId)) {
    return <div className="p-6 text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>;
  }
  if (headerQuery.isLoading) {
    return <div className="p-6 text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }
  if (!headerQuery.data) {
    return <div className="p-6 text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>;
  }
  const profile = headerQuery.data;

  return (
    <div className="space-y-3 sm:space-y-4">
      {/* Privacy banner — spec: "هذه البيانات سرّية. وصولك مسجَّل". */}
      <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 flex items-center gap-2">
        <AlertTriangle className="h-4 w-4" />
        {t('user_profile.privacy_notice')}
      </div>

      <a href="/app/admin/users" className="inline-flex items-center gap-1 text-sm text-[var(--color-muted-foreground)] hover:text-[var(--color-primary)]">
        <ArrowLeft className="h-4 w-4" /> {t('user_profile.back_to_users')}
      </a>

      <ProfileHeaderCard
        profile={profile}
        reveal={reveal}
        onToggleReveal={() => setReveal((v) => !v)}
        onSuspend={async () => {
          try {
            await userProfileApi.suspend(userId);
            toast.success(t('user_profile.action_done'));
            headerQuery.refetch();
          } catch (e) { toast.error(extractMessage(e, t('errors.generic'))); }
        }}
        onForceLogout={async () => {
          try {
            await userProfileApi.forceLogout(userId);
            toast.success(t('user_profile.force_logout_done'));
          } catch (e) { toast.error(extractMessage(e, t('errors.generic'))); }
        }}
      />

      {profile.risk_flags.length > 0 && (
        <RiskFlagsRow flags={profile.risk_flags} />
      )}

      <SummaryCardsRow cards={profile.summary_cards} locale={locale} />

      <InsightsRow insights={profile.insights} />

      <ActivityHoursChart userId={userId} />

      <ActivityTimeline userId={userId} />

      <DetailTabs userId={userId} activeTab={activeTab} setActiveTab={setActiveTab} />
    </div>
  );
}

// ─────────────────────────── Header ───────────────────────────

function ProfileHeaderCard({
  profile, reveal, onToggleReveal, onSuspend, onForceLogout,
}: {
  profile: ProfileHeader;
  reveal: boolean;
  onToggleReveal: () => void;
  onSuspend: () => void;
  onForceLogout: () => void;
}) {
  const { t } = useTranslation();
  const u = profile.user;
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-4">
      <div className="flex flex-wrap items-start gap-4">
        <div className="h-14 w-14 rounded-full bg-[var(--color-primary)]/15 text-[var(--color-primary)] flex items-center justify-center font-semibold text-lg">
          {u.initials}
        </div>
        <div className="min-w-0 flex-1">
          <h1 className="text-xl sm:text-2xl font-semibold truncate">{u.name}</h1>
          <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-[var(--color-muted-foreground)]">
            <span className="inline-flex items-center gap-1.5">
              <Phone className="h-3.5 w-3.5" />
              <span dir="ltr">{u.phone ?? '—'}</span>
            </span>
            {u.email && <span dir="ltr">· {u.email}</span>}
            <Button variant="ghost" size="sm" className="h-6 px-1.5 text-xs" onClick={onToggleReveal}>
              {reveal ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
              {t(reveal ? 'user_profile.hide' : 'user_profile.reveal')}
            </Button>
          </div>
          <div className="mt-2 flex flex-wrap gap-1.5">
            <Badge variant="outline">{t('user_profile.member_since_days', { days: profile.badges.member_since_days })}</Badge>
            <Badge variant="muted">{t('user_profile.bookings_count', { count: profile.badges.bookings_count })}</Badge>
            <Badge variant="muted">{t('user_profile.complaints_count', { count: profile.badges.complaints_count })}</Badge>
            <Badge variant={profile.badges.engagement_level === 'active' ? 'success' : profile.badges.engagement_level === 'medium' ? 'info' : 'muted'}>
              {t(`user_profile.engagement_level.${profile.badges.engagement_level}`)}
            </Badge>
            {!u.is_active && <Badge variant="danger">{t('user_profile.suspended')}</Badge>}
          </div>
        </div>
        <div className="flex flex-wrap gap-1.5">
          <Button size="sm" variant="outline" onClick={onSuspend}>
            <UserX className="h-3.5 w-3.5" />
            {u.is_active ? t('user_profile.suspend') : t('user_profile.unsuspend')}
          </Button>
          <Button size="sm" variant="outline" onClick={onForceLogout}>
            <LogOut className="h-3.5 w-3.5" />
            {t('user_profile.force_logout')}
          </Button>
        </div>
      </div>
    </div>
  );
}

// ─────────────────────────── Risk flags ───────────────────────────

function RiskFlagsRow({ flags }: { flags: ProfileHeader['risk_flags'] }) {
  const { t } = useTranslation();
  return (
    <div className="rounded-xl border border-red-200 bg-red-50 p-3 flex flex-wrap items-center gap-2">
      <AlertTriangle className="h-4 w-4 text-red-700" />
      <span className="text-sm font-medium text-red-800">{t('user_profile.risk_flags_label')}</span>
      {flags.map((f) => (
        <Badge key={f.code} variant="danger">
          {t(`user_profile.risk.${f.code}`, { count: f.count })}
        </Badge>
      ))}
    </div>
  );
}

// ─────────────────────────── Summary cards ───────────────────────────

function SummaryCardsRow({
  cards, locale,
}: {
  cards: ProfileHeader['summary_cards'];
  locale: 'ar' | 'en';
}) {
  const { t } = useTranslation();
  const items: { label: string; value: string; sub?: string | null }[] = [
    { label: t('user_profile.sessions_total'), value: String(cards.sessions_total) },
    { label: t('user_profile.time_on_platform'), value: formatDuration(cards.time_on_platform_seconds, locale) },
    {
      label: t('user_profile.last_login'),
      value: cards.last_login_at ? new Date(cards.last_login_at).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—',
      sub: cards.last_login_user_agent ? cards.last_login_user_agent.slice(0, 40) : null,
    },
    { label: t('user_profile.avg_session'), value: formatDuration(cards.avg_session_seconds, locale) },
    { label: t('user_profile.top_category'), value: cards.top_category ?? '—' },
    { label: t('user_profile.top_city'), value: cards.top_city ?? '—' },
  ];
  return (
    // Stepped grid per spec: 2 cols on phones, 3 on small tablets,
    // 6 on desktop. `min-w-0` on each cell keeps long values (a 60-char
    // user-agent, a long Arabic city name) from blowing out the grid
    // track and forcing horizontal scroll.
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
      {items.map((i) => (
        <div key={i.label} className="min-w-0 rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] p-3">
          <p className="text-xs text-[var(--color-muted-foreground)]">{i.label}</p>
          <p className="mt-1 text-sm font-semibold truncate" title={i.value}>{i.value}</p>
          {i.sub && <p className="text-[10px] text-[var(--color-muted-foreground)] mt-0.5 truncate">{i.sub}</p>}
        </div>
      ))}
    </div>
  );
}

// ─────────────────────────── Insights ───────────────────────────

function InsightsRow({ insights }: { insights: ProfileHeader['insights'] }) {
  const { t } = useTranslation();
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-3">
      <p className="text-sm font-medium mb-2 flex items-center gap-1.5"><Activity className="h-4 w-4" />{t('user_profile.insights')}</p>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
        <Insight label={t('user_profile.insight_engagement')} value={t(`user_profile.engagement_level.${insights.engagement_level}`)} />
        <Insight label={t('user_profile.insight_decision_stage')} value={insights.decision_stage ? t(`user_profile.decision_stage.${insights.decision_stage}`) : '—'} />
        <Insight label={t('user_profile.insight_preferred_city')} value={insights.preferred_city ?? '—'} />
        <Insight label={t('user_profile.insight_preferred_category')} value={insights.preferred_category ?? '—'} />
      </div>
    </div>
  );
}

function Insight({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-[var(--color-muted-foreground)]">{label}</p>
      <p className="font-medium">{value}</p>
    </div>
  );
}

// ─────────────────────────── Activity hours heatmap ───────────────────────────

function ActivityHoursChart({ userId }: { userId: number }) {
  const { t } = useTranslation();
  const { data: matrix } = useQuery({
    queryKey: ['userProfileHours', userId],
    queryFn: () => userProfileApi.hours(userId),
  });
  if (!matrix) return null;
  const days = [0, 1, 2, 3, 4, 5, 6];
  const hours = Array.from({ length: 24 }, (_, h) => h);
  const max = Math.max(1, ...matrix.flat());
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-3">
      <p className="text-sm font-medium mb-2 flex items-center gap-1.5"><Clock className="h-4 w-4" />{t('user_profile.activity_hours')}</p>
      <div className="overflow-x-auto">
        <table className="text-[10px]">
          <thead>
            <tr><th className="px-1"></th>{hours.map((h) => <th key={h} className="px-1 text-[var(--color-muted-foreground)]">{h}</th>)}</tr>
          </thead>
          <tbody>
            {days.map((d) => (
              <tr key={d}>
                <td className="px-1 text-[var(--color-muted-foreground)]">{t(`user_profile.weekday.${d}`)}</td>
                {hours.map((h) => {
                  const v = matrix[d]?.[h] ?? 0;
                  const intensity = Math.round((v / max) * 6);
                  const bg = ['bg-gray-50', 'bg-sage-50', 'bg-sage-100', 'bg-sage-200', 'bg-sage-300', 'bg-sage-400', 'bg-sage-500'][intensity] || 'bg-gray-50';
                  return <td key={h} className={`${bg} w-4 h-4 border border-white`} title={`${v}`} />;
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ─────────────────────────── Activity timeline ───────────────────────────

function ActivityTimeline({ userId }: { userId: number }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [page, setPage] = useState(1);
  const [types, setTypes] = useState<string[]>([]);
  const [q, setQ] = useState('');
  const [period, setPeriod] = useState<'all' | '1d' | '7d' | '30d'>('all');

  const dateRange = useMemo(() => {
    if (period === 'all') return {};
    const days = period === '1d' ? 1 : period === '7d' ? 7 : 30;
    const from = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    return { from };
  }, [period]);

  const { data, isLoading } = useQuery({
    queryKey: ['userProfileTimeline', userId, page, types, dateRange, q],
    queryFn: () => userProfileApi.timeline(userId, { page, per_page: 30, types, q: q || undefined, ...dateRange }),
  });

  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-3">
      <p className="text-sm font-medium mb-2 flex items-center gap-1.5">
        <Activity className="h-4 w-4" />{t('user_profile.timeline.title')}
      </p>
      {/* Filter bar — search + period stack to a column on phones so each
          control gets its full row; types pills wrap below as their own
          band, with a horizontal scroll fallback if the user's locale
          makes any pill unusually wide. */}
      <div className="flex flex-col gap-2 mb-3 md:flex-row md:items-center md:flex-wrap">
        <Input
          className="w-full md:max-w-xs"
          placeholder={t('user_profile.timeline.search_placeholder')}
          value={q}
          onChange={(e) => { setQ(e.target.value); setPage(1); }}
        />
        <Select value={period} onChange={(e) => { setPeriod(e.target.value as never); setPage(1); }} className="w-full md:w-40">
          <option value="all">{t('user_profile.timeline.period_all')}</option>
          <option value="1d">{t('user_profile.timeline.period_1d')}</option>
          <option value="7d">{t('user_profile.timeline.period_7d')}</option>
          <option value="30d">{t('user_profile.timeline.period_30d')}</option>
        </Select>
        <div className="flex flex-wrap gap-1 -mx-1 px-1 overflow-x-auto md:overflow-visible">
          {TIMELINE_TYPES.map((tp) => (
            <button
              key={tp}
              type="button"
              onClick={() => { setTypes((cur) => cur.includes(tp) ? cur.filter((x) => x !== tp) : [...cur, tp]); setPage(1); }}
              className={`text-[10px] px-2 py-0.5 rounded border whitespace-nowrap ${types.includes(tp) ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]' : 'border-[var(--color-border)] text-[var(--color-muted-foreground)]'}`}
            >
              {t(`user_profile.types.${tp}`)}
            </button>
          ))}
        </div>
      </div>

      <ol className="space-y-2">
        {isLoading && <li className="text-sm text-[var(--color-muted-foreground)] py-4">{t('common.loading')}</li>}
        {data?.data.length === 0 && (
          <li className="text-sm text-[var(--color-muted-foreground)] py-4">{t('common.no_data')}</li>
        )}
        {data?.data.map((e: TimelineEvent) => (
          <li key={e.id} className="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-3 py-2">
            <span className="mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full bg-[var(--color-muted)] text-[var(--color-muted-foreground)]">
              {TYPE_ICON[e.type] ?? <Activity className="h-3.5 w-3.5" />}
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm">{e.title}</p>
              <p className="text-[10px] text-[var(--color-muted-foreground)] mt-0.5">
                {new Date(e.occurred_at).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US')}
              </p>
            </div>
            {e.related_url && (
              <a href={e.related_url} className="text-xs text-[var(--color-primary)] hover:underline whitespace-nowrap">
                {t('user_profile.open')}
              </a>
            )}
          </li>
        ))}
      </ol>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-xs mt-2">
          <span className="text-[var(--color-muted-foreground)]">{data.meta.page} / {data.meta.last_page}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage((p) => p - 1)}>{t('common.back')}</Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>›</Button>
          </div>
        </div>
      )}
    </div>
  );
}

// ─────────────────────────── Detail tabs ───────────────────────────

function DetailTabs({ userId, activeTab, setActiveTab }: { userId: number; activeTab: TabKey; setActiveTab: (t: TabKey) => void }) {
  const { t } = useTranslation();
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-3">
      <div className="flex flex-wrap gap-1 mb-3">
        {TABS.map((tab) => (
          <button
            key={tab}
            type="button"
            onClick={() => setActiveTab(tab)}
            className={`text-xs px-3 py-1.5 rounded-md border ${activeTab === tab ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white' : 'border-[var(--color-border)] text-[var(--color-muted-foreground)]'}`}
          >
            {t(`user_profile.tabs.${tab}`)}
          </button>
        ))}
      </div>
      <TabPanel userId={userId} tab={activeTab} />
    </div>
  );
}

function TabPanel({ userId, tab }: { userId: number; tab: TabKey }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [page, setPage] = useState(1);
  // Reset paging when tab changes.
  useEffect(() => { setPage(1); }, [tab]);

  const { data, isLoading } = useQuery({
    queryKey: ['userProfileTab', userId, tab, page],
    queryFn: () => userProfileApi.tab<Record<string, unknown>>(userId, tab, page, 15),
  });

  if (isLoading) return <p className="text-sm text-[var(--color-muted-foreground)] py-4">{t('common.loading')}</p>;
  const rows = (data as { rows?: Record<string, unknown>[] })?.rows ?? [];
  const meta = (data as Paginated<unknown>)?.meta;
  if (rows.length === 0) return <p className="text-sm text-[var(--color-muted-foreground)] py-4">{t('common.no_data')}</p>;

  const dateStr = (s?: string | null) => s ? new Date(s).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  const content = (() => {
    switch (tab) {
      case 'bookings':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.id} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <span className="font-mono text-[10px]" dir="ltr">{r.reference_code}</span>
                <span>{r.clinic?.name ?? '—'}</span>
                <span className="text-[var(--color-muted-foreground)]">{r.service?.name ?? ''}</span>
                {r.for_relative && <Badge variant="info" className="text-[10px]">{t('bookings.booked_by_proxy_badge')}</Badge>}
                <Badge variant="muted" className="text-[10px]">{r.status}</Badge>
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.created_at)}</span>
              </li>
            ))}
          </ul>
        );
      case 'complaints':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.id} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <span className="font-mono text-[10px]" dir="ltr">{r.reference_code}</span>
                <span>{r.subject}</span>
                <Badge variant="muted" className="text-[10px]">{r.status}</Badge>
                <Badge variant="warning" className="text-[10px]">{r.priority}</Badge>
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.created_at)}</span>
              </li>
            ))}
          </ul>
        );
      case 'ai':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.conversation_id ?? r.first_query} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <Badge variant="muted" className="text-[10px]">{t('user_profile.turns_count', { count: r.turns })}</Badge>
                <span className="truncate">{r.first_query}</span>
                {r.was_emergency && <Badge variant="danger" className="text-[10px]">{t('user_profile.ai_emergency')}</Badge>}
                {r.was_blocked && <Badge variant="warning" className="text-[10px]">{t('user_profile.ai_blocked')}</Badge>}
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.last_at)}</span>
                {r.conversation_id && (
                  <a href={`/app/admin/ai-center?conversation=${r.conversation_id}`} className="text-xs text-[var(--color-primary)] hover:underline">
                    {t('user_profile.open')}
                  </a>
                )}
              </li>
            ))}
          </ul>
        );
      case 'sessions':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.id} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <span>{dateStr(r.started_at)}</span>
                <Badge variant="muted" className="text-[10px]">{formatDuration(r.duration_seconds, locale)}</Badge>
                <span className="text-xs text-[var(--color-muted-foreground)]">{r.user_agent?.slice(0, 60) ?? ''}</span>
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]" dir="ltr">{r.ip ?? '—'}</span>
              </li>
            ))}
          </ul>
        );
      case 'favorites':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.clinic_id} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <Heart className="h-3.5 w-3.5 text-rose-500" />
                <a href={`/clinic/${r.slug}`} target="_blank" rel="noreferrer" className="text-[var(--color-primary)] hover:underline">{r.name}</a>
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.created_at)}</span>
              </li>
            ))}
          </ul>
        );
      case 'quotes':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.id} className="rounded border border-[var(--color-border)] px-3 py-2 flex flex-wrap items-center gap-2">
                <span>{r.service_name}</span>
                <Badge variant="muted" className="text-[10px]">{r.status}</Badge>
                <span className="ms-auto text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.created_at)}</span>
              </li>
            ))}
          </ul>
        );
      case 'account_edits':
        return (
          <ul className="space-y-1.5 text-sm">
            {rows.map((r: any) => (
              <li key={r.id} className="rounded border border-[var(--color-border)] px-3 py-2">
                <div className="flex items-center gap-2 mb-1">
                  <Settings2 className="h-3.5 w-3.5" />
                  <span className="text-[10px] text-[var(--color-muted-foreground)]">{dateStr(r.occurred_at)}</span>
                  <span className="text-[10px] text-[var(--color-muted-foreground)]" dir="ltr">{r.ip ?? ''}</span>
                </div>
                <div className="space-y-0.5 text-xs">
                  {Object.entries(r.changes ?? {}).map(([field, diff]: any) => (
                    <div key={field}>
                      <span className="font-medium">{field}:</span>{' '}
                      <span className="line-through text-[var(--color-muted-foreground)]">{String(diff?.from ?? '')}</span>{' → '}
                      <span>{String(diff?.to ?? '')}</span>
                    </div>
                  ))}
                </div>
              </li>
            ))}
          </ul>
        );
    }
  })();

  return (
    <div className="space-y-2">
      {content}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-xs">
          <span className="text-[var(--color-muted-foreground)]">{meta.page} / {meta.last_page}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1} onClick={() => setPage((p) => p - 1)}>{t('common.back')}</Button>
            <Button variant="outline" size="sm" disabled={page >= meta.last_page} onClick={() => setPage((p) => p + 1)}>›</Button>
          </div>
        </div>
      )}
    </div>
  );
}
