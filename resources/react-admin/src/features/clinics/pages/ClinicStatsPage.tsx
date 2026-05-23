import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, BarChart3, Calendar, DollarSign, Eye, Search } from 'lucide-react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useClinic, useClinicStats } from '../hooks';
import type { ClinicStatsPeriod } from '../api/clinics.api';

interface StatCardProps {
  label: string;
  value: string | number;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning' | 'info';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  info:    'bg-sky-50 text-sky-700',
};

function StatCard({ label, value, icon: Icon, tone }: StatCardProps) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between">
        <div>
          <div className="text-xs text-[var(--color-muted-foreground)]">{label}</div>
          <div className="mt-1 text-2xl font-semibold">{value}</div>
        </div>
        <div className={`flex h-9 w-9 items-center justify-center rounded-md ${TONE[tone]}`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
    </div>
  );
}

const PERIODS: ClinicStatsPeriod[] = [7, 14, 30];

export function ClinicStatsPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { id } = useParams<{ id: string }>();
  const clinicId = id ? Number(id) : undefined;
  const [period, setPeriod] = useState<ClinicStatsPeriod>(30);

  const { data: clinic } = useClinic(clinicId);
  const { data: stats } = useClinicStats(clinicId, period);

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US');

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link
          to="/admin/clinics"
          className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[var(--color-border)] hover:bg-[var(--color-muted)]"
          aria-label={t('common.back')}
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
        </Link>
        <div>
          <h1 className="text-2xl font-semibold">{clinic?.name ?? t('clinics.stats.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.stats.title')}</p>
        </div>
      </div>

      <div className="flex gap-2">
        {PERIODS.map((p) => (
          <button
            key={p}
            type="button"
            onClick={() => setPeriod(p)}
            className={
              'inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium transition-colors ' +
              (period === p
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]')
            }
          >
            {t(`clinics.stats.period_${p}`)}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t('clinics.stats.page_views')} value={stats ? fmt.format(stats.cards.page_views) : '—'} icon={Eye} tone="info" />
        <StatCard label={t('clinics.stats.bookings')} value={stats ? fmt.format(stats.cards.bookings) : '—'} icon={Calendar} tone="primary" />
        <StatCard label={t('clinics.stats.quote_requests')} value={stats ? fmt.format(stats.cards.quote_requests) : '—'} icon={DollarSign} tone="warning" />
        <StatCard label={t('clinics.stats.search_appearances')} value={stats ? fmt.format(stats.cards.search_appearances) : '—'} icon={Search} tone="success" />
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('clinics.stats.title')}</h2>
        <div className="h-64 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={stats?.trend ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Legend />
              <Line type="monotone" dataKey="page_views" name={t('clinics.stats.page_views')} stroke="#0ea5e9" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="bookings" name={t('clinics.stats.bookings')} stroke="#0066cc" strokeWidth={2} dot={false} />
              <Line type="monotone" dataKey="quote_requests" name={t('clinics.stats.quote_requests')} stroke="#f59e0b" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <div className="flex items-center gap-2">
          <BarChart3 className="h-4 w-4 text-[var(--color-muted-foreground)]" />
          <span className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.stats.vs_platform_avg')}</span>
        </div>
        <div className="mt-2 flex items-baseline gap-3">
          <span className="text-2xl font-semibold">{stats ? fmt.format(stats.kpis.this_clinic_bookings) : '—'}</span>
          <span className="text-sm text-[var(--color-muted-foreground)]">
            {t('clinics.stats.this_clinic')} · {stats ? fmt.format(stats.kpis.avg_bookings_platform) : '—'}
          </span>
        </div>
      </div>
    </div>
  );
}
