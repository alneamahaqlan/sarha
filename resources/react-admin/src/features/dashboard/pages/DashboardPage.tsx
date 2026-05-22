import { Building2, Calendar, CreditCard, Users } from 'lucide-react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { BookingStatusBadge } from '@/features/bookings/components/StatusBadge';
import type { BookingStatus } from '@/features/bookings/types';

import { useAdminStats, useBookingsTrend, useLatestBookings } from '../hooks';

interface StatCardProps {
  label: string;
  value: string | number;
  hint?: string;
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

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US');
  const fmtCurrency = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 });
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

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
          hint={stats ? t('dashboard.month_revenue', { amount: fmtCurrency.format(stats.month_revenue) }) : undefined}
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
                  <TableCell><Badge variant="muted" className="font-mono">{b.reference_code}</Badge></TableCell>
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
