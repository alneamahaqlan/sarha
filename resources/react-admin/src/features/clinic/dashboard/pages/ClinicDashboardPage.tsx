import { Bell, Calendar, CreditCard, Sparkles } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';

import { useClinicStats } from '../hooks';

interface StatCardProps {
  label: string;
  value: string | number;
  hint?: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning' | 'danger';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  danger:  'bg-red-50 text-red-700',
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

export function ClinicDashboardPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { user } = useAuth();
  const { data: stats } = useClinicStats();

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US');
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_nav.dashboard')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{user?.user.name}</p>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={t('clinic_dashboard.new_bookings')}
          value={stats ? fmt.format(stats.new_bookings) : '—'}
          hint={stats ? t('clinic_dashboard.total_count', { count: stats.total_bookings }) : undefined}
          icon={Bell}
          tone={stats && stats.new_bookings > 0 ? 'warning' : 'success'}
        />
        <StatCard
          label={t('clinic_dashboard.month_bookings')}
          value={stats ? fmt.format(stats.month_bookings) : '—'}
          icon={Calendar}
          tone="primary"
        />
        <StatCard
          label={t('clinic_dashboard.active_services')}
          value={stats ? fmt.format(stats.active_services) : '—'}
          icon={Sparkles}
          tone="success"
        />
        <StatCard
          label={t('clinic_dashboard.subscription')}
          value={
            stats?.subscription_type
              ? t(`clinics.plan.${stats.subscription_type}`)
              : t('common.no_data')
          }
          hint={
            stats?.subscription_ends_at
              ? t('clinic_dashboard.subscription_ends', { date: fmtDate(stats.subscription_ends_at) })
              : undefined
          }
          icon={CreditCard}
          tone={stats?.is_subscription_active ? 'success' : 'danger'}
        />
      </div>
    </div>
  );
}
