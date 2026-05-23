import { BarChart3, Eye, DollarSign, MessageSquare } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useAnalytics } from '../hooks';

interface StatCardProps {
  label: string;
  value: string | number;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
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

export function AnalyticsPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data } = useAnalytics(30);

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US');
  const fmtCurrency = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', {
    style: 'currency',
    currency: 'SAR',
    maximumFractionDigits: 0,
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('analytics.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('analytics.subtitle')}</p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label={t('analytics.total_views')} value={data ? fmt.format(data.cards.total_views) : '—'} icon={Eye} tone="primary" />
        <StatCard label={t('analytics.contact_requests')} value={data ? fmt.format(data.cards.contact_requests) : '—'} icon={MessageSquare} tone="warning" />
        <StatCard label={t('analytics.revenue')} value={data ? fmtCurrency.format(data.cards.revenue) : '—'} icon={DollarSign} tone="success" />
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <h2 className="mb-3 text-sm font-semibold">{t('analytics.monthly')}</h2>
        <div className="mb-4 h-56 w-full">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data?.monthly ?? []}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
              <XAxis dataKey="month" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
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
            {!data || data.monthly.length === 0 ? (
              <TableRow>
                <TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">
                  {t('common.no_data')}
                </TableCell>
              </TableRow>
            ) : (
              data.monthly.map((m) => (
                <TableRow key={m.month}>
                  <TableCell className="font-medium">{m.month}</TableCell>
                  <TableCell>{fmt.format(m.clinics)}</TableCell>
                  <TableCell>{fmt.format(m.bookings)}</TableCell>
                  <TableCell>{fmtCurrency.format(m.revenue)}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
          <BarChart3 className="h-4 w-4 text-blue-600" />
          <h2 className="text-sm font-semibold">{t('analytics.by_specialty')}</h2>
        </div>
        <div className="divide-y divide-[var(--color-border)]">
          {!data || data.by_specialty.length === 0 ? (
            <div className="px-4 py-6 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
          ) : (
            data.by_specialty.map((s) => (
              <div key={s.name} className="flex items-center justify-between px-4 py-2.5 text-sm">
                <span className="truncate font-medium">{s.name}</span>
                <Badge variant="muted">{fmt.format(s.clinics_count)}</Badge>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
