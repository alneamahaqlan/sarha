import { useMemo, useState } from 'react';
import { BarChart3, MessageCircle, Users, Percent, Bot, AlertTriangle, ShieldOff, CheckCircle } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAiAnalytics } from '../hooks';

type Range = 7 | 30 | 90;

interface StatCardProps {
  label: string;
  value: string | number;
  hint?: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
};

function StatCard({ label, value, hint, icon: Icon, tone }: StatCardProps) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="truncate text-xs text-[var(--color-muted-foreground)]">{label}</div>
          <div className="mt-1 text-2xl font-semibold">{value}</div>
          {hint && <div className="mt-1 text-[11px] text-[var(--color-muted-foreground)]">{hint}</div>}
        </div>
        <div className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-md ${TONE[tone]}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

const PIE_COLORS: Record<string, string> = {
  normal:    '#10b981',
  blocked:   '#f59e0b',
  emergency: '#ef4444',
};

export function AiAnalyticsTab() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [range, setRange] = useState<Range>(30);
  const { data, isLoading } = useAiAnalytics(range);

  const fmt = useMemo(() => new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US'), [locale]);

  const kinds = data?.kind_breakdown ?? [];

  return (
    <div className="space-y-5">
      {/* Range chips */}
      <div className="flex flex-wrap gap-2">
        {([7, 30, 90] as Range[]).map((d) => (
          <button
            key={d}
            type="button"
            onClick={() => setRange(d)}
            className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
              range === d
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
            }`}
          >
            {t(`ai_center.range_${d}d`, `${d} يوم`)}
          </button>
        ))}
        {isLoading && (
          <span className="text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}…</span>
        )}
      </div>

      {/* KPI cards */}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <StatCard
          label={t('ai_center.kpi_conversations', 'المحادثات')}
          value={data ? fmt.format(data.kpis.conversations) : '—'}
          icon={MessageCircle}
          tone="primary"
        />
        <StatCard
          label={t('ai_center.kpi_unique_users', 'مستخدمون فريدون')}
          value={data ? fmt.format(data.kpis.unique_users) : '—'}
          icon={Users}
          tone="primary"
        />
        <StatCard
          label={t('ai_center.kpi_avg_length', 'متوسط طول المحادثة')}
          value={data ? `${data.kpis.avg_length_turns} ${t('ai_center.turns_unit', 'رسائل')}` : '—'}
          icon={Bot}
          tone="success"
        />
        <StatCard
          label={t('ai_center.kpi_conversion', 'معدل التحويل')}
          value={data ? `${data.kpis.conversion_rate}%` : '—'}
          hint={t('ai_center.kpi_conversion_hint', 'محادثات ظهر بها مجمع')}
          icon={Percent}
          tone="warning"
        />
      </div>

      {/* Trend line */}
      <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_trend', 'اتجاه المحادثات')}</h2>
        <div className="h-56 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data?.trend ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 10 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Line type="monotone" dataKey="count" stroke="#0ea5e9" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </section>

      {/* Two-column bar charts */}
      <div className="grid gap-4 lg:grid-cols-2">
        <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_top_topics', 'أكثر المواضيع')}</h2>
          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data?.top_topics ?? []} layout="vertical" margin={{ left: 80 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis type="number" tick={{ fontSize: 11 }} />
                <YAxis type="category" dataKey="topic" tick={{ fontSize: 11 }} width={140} />
                <Tooltip />
                <Bar dataKey="count" fill="#6366f1" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_top_clinics', 'أكثر المجمعات ظهوراً')}</h2>
          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data?.top_clinics ?? []} layout="vertical" margin={{ left: 80 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis type="number" tick={{ fontSize: 11 }} />
                <YAxis type="category" dataKey="name" tick={{ fontSize: 11 }} width={140} />
                <Tooltip />
                <Bar dataKey="count" fill="#0ea5e9" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_top_categories', 'أكثر التخصصات طلباً')}</h2>
          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data?.top_categories ?? []} layout="vertical" margin={{ left: 100 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis type="number" tick={{ fontSize: 11 }} />
                <YAxis type="category" dataKey="name" tick={{ fontSize: 11 }} width={150} />
                <Tooltip />
                <Bar dataKey="count" fill="#10b981" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </section>

        <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
          <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_kind_breakdown', 'توزّع الردود')}</h2>
          <div className="flex items-center gap-4">
            <div className="h-48 w-48 shrink-0">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={kinds} dataKey="count" nameKey="kind" outerRadius={70} innerRadius={40}>
                    {kinds.map((k) => (
                      <Cell key={k.kind} fill={PIE_COLORS[k.kind] ?? '#9ca3af'} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <ul className="space-y-2 text-sm">
              {kinds.map((k) => (
                <li key={k.kind} className="flex items-center gap-2">
                  <span className="inline-block h-3 w-3 rounded-sm" style={{ backgroundColor: PIE_COLORS[k.kind] }} />
                  <span className="text-[var(--color-foreground)]">
                    {t(`ai_center.kind_${k.kind}`, k.kind)}
                  </span>
                  <span className="font-semibold">{fmt.format(k.count)}</span>
                </li>
              ))}
            </ul>
          </div>
        </section>
      </div>

      {/* Provider performance */}
      <section className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('ai_center.chart_provider_perf', 'أداء المزوّدات')}</h2>
        {(!data || data.provider_perf.length === 0) ? (
          <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
            {t('ai_center.no_provider_data', 'لا توجد بيانات أداء بعد — يظهر بعد بضع محادثات حقيقية.')}
          </div>
        ) : (
          <div className="flex flex-wrap gap-3">
            {data.provider_perf.map((p) => (
              <div key={p.provider} className="rounded-md border border-[var(--color-border)] px-4 py-2">
                <div className="text-xs uppercase text-[var(--color-muted-foreground)]">{p.provider}</div>
                <div className="font-semibold">{p.avg_ms} ms</div>
                <div className="text-[11px] text-[var(--color-muted-foreground)]">
                  {fmt.format(p.count)} {t('ai_center.calls', 'مكالمة')}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  );
}
