import { Link } from 'react-router-dom';
import {
  Bell, Calendar, CreditCard, Sparkles, Star, Eye, Search, MousePointerClick,
  AlertTriangle, Tag, MessageSquare, ChevronLeft, ChevronRight,
} from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { Money } from '@/lib/money';
import { useAuth } from '@/app/providers/AuthProvider';
import { useClinicNavBadges } from '@/features/nav-badges/hooks';

import { useClinicStats } from '../hooks';
import type { ClinicStats } from '../api';

interface StatCardProps {
  label: string;
  value: string | number;
  hint?: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: 'success' | 'primary' | 'warning' | 'danger' | 'amber';
}

const TONE: Record<StatCardProps['tone'], string> = {
  success: 'bg-emerald-50 text-emerald-700',
  primary: 'bg-blue-50 text-blue-700',
  warning: 'bg-amber-50 text-amber-700',
  danger:  'bg-red-50 text-red-700',
  amber:   'bg-amber-50 text-amber-700',
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

type AlertTone = 'danger' | 'warning' | 'info';

interface AlertItem {
  key: string;
  tone: AlertTone;
  icon: React.ComponentType<{ className?: string }>;
  message: string;
  to: string;
}

const ALERT_TONE: Record<AlertTone, string> = {
  danger:  'border-red-200 bg-red-50 text-red-800',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  info:    'border-blue-200 bg-blue-50 text-blue-800',
};

function AlertsBanner({ stats }: { stats?: ClinicStats }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: badges } = useClinicNavBadges();
  const Chevron = locale === 'ar' ? ChevronLeft : ChevronRight;

  const subDays = stats?.subscription_ends_at
    ? Math.max(0, Math.ceil((new Date(stats.subscription_ends_at).getTime() - Date.now()) / 86_400_000))
    : null;

  const alerts: AlertItem[] = [];

  if (badges?.subscription_expiring && subDays !== null) {
    alerts.push({
      key: 'subscription',
      tone: 'danger',
      icon: CreditCard,
      message: t('clinic_dashboard.alert.subscription_expiring', { count: subDays }),
      to: '/clinic/subscription',
    });
  }
  if (badges?.offer_expiring) {
    alerts.push({
      key: 'offers',
      tone: 'warning',
      icon: Tag,
      message: t('clinic_dashboard.alert.offer_expiring', { count: badges.offer_expiring }),
      to: '/clinic/services',
    });
  }
  if (badges?.price_quotes) {
    alerts.push({
      key: 'quotes',
      tone: 'info',
      icon: MessageSquare,
      message: t('clinic_dashboard.alert.price_quotes', { count: badges.price_quotes }),
      to: '/clinic/price-quotes',
    });
  }
  if (stats?.new_bookings) {
    alerts.push({
      key: 'bookings',
      tone: 'info',
      icon: Bell,
      message: t('clinic_dashboard.alert.new_bookings', { count: stats.new_bookings }),
      to: '/clinic/bookings',
    });
  }

  if (alerts.length === 0) return null;

  return (
    <div className="space-y-2">
      {alerts.map((a) => {
        const Icon = a.icon;
        return (
          <Link
            key={a.key}
            to={a.to}
            className={`flex items-center gap-3 rounded-lg border px-4 py-3 text-sm font-medium transition-opacity hover:opacity-90 ${ALERT_TONE[a.tone]}`}
          >
            <AlertTriangle className="hidden h-4 w-4 shrink-0 sm:block" />
            <Icon className="h-4 w-4 shrink-0" />
            <span className="flex-1">{a.message}</span>
            <Chevron className="h-4 w-4 shrink-0" />
          </Link>
        );
      })}
    </div>
  );
}

function MotivationalCard() {
  const { t } = useTranslation();
  return (
    <Link
      to="/clinic/articles"
      className="flex items-start gap-3 rounded-lg border border-blue-200 bg-gradient-to-l from-blue-50 to-white p-4 transition-shadow hover:shadow-sm"
    >
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[var(--color-primary)] text-white">
        <Sparkles className="h-5 w-5" />
      </div>
      <div>
        <div className="text-sm font-semibold">{t('clinic_dashboard.motivation_title')}</div>
        <p className="mt-0.5 text-xs text-[var(--color-muted-foreground)]">{t('clinic_dashboard.motivation_body')}</p>
      </div>
    </Link>
  );
}

export function ClinicDashboardPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { user } = useAuth();
  const { data: stats } = useClinicStats();

  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';
  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_nav.dashboard')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{user?.user.name}</p>
      </div>

      <AlertsBanner stats={stats} />
      <MotivationalCard />

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
          label={t('clinic_dashboard.google_rating')}
          value={stats?.google_rating != null ? stats.google_rating.toFixed(1) : '—'}
          hint={
            stats && stats.google_reviews_count > 0
              ? t('clinic_dashboard.reviews_count', { count: stats.google_reviews_count })
              : t('clinic_dashboard.no_reviews')
          }
          icon={Star}
          tone="amber"
        />
        <StatCard
          label={t('clinic_dashboard.subscription')}
          value={
            stats?.subscription_type
              ? t(`clinics.plan.${stats.subscription_type}`, { defaultValue: stats.subscription_type })
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

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Visibility performance (trailing 30 days) */}
        <div className="rounded-lg border border-[var(--color-border)] bg-white p-5 lg:col-span-2">
          <h2 className="text-sm font-semibold">{t('clinic_dashboard.visibility_title')}</h2>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_dashboard.visibility_subtitle')}</p>
          <div className="mt-4 grid grid-cols-3 gap-3">
            <VisibilityStat
              icon={Search}
              label={t('clinic_dashboard.impressions')}
              value={stats ? fmt.format(stats.visibility.impressions) : '—'}
            />
            <VisibilityStat
              icon={Eye}
              label={t('clinic_dashboard.visits')}
              value={stats ? fmt.format(stats.visibility.visits) : '—'}
            />
            <VisibilityStat
              icon={MousePointerClick}
              label={t('clinic_dashboard.requests')}
              value={stats ? fmt.format(stats.visibility.requests) : '—'}
            />
          </div>
        </div>

        {/* Latest services added */}
        <div className="rounded-lg border border-[var(--color-border)] bg-white p-5">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold">{t('clinic_dashboard.latest_services')}</h2>
            <Link to="/clinic/services" className="text-xs text-[var(--color-primary)] hover:underline">
              {t('clinic_dashboard.view_all')}
            </Link>
          </div>
          <div className="mt-3 space-y-2">
            {!stats ? (
              <p className="py-4 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</p>
            ) : stats.latest_services.length === 0 ? (
              <p className="py-4 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</p>
            ) : (
              stats.latest_services.map((s) => (
                <div key={s.id} className="flex items-center justify-between gap-2 rounded-md bg-[var(--color-muted)] px-3 py-2">
                  <div className="min-w-0">
                    <div className="truncate text-sm font-medium">{s.name}</div>
                    <div className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(s.created_at)}</div>
                  </div>
                  <Money value={s.price} locale={locale} className="shrink-0 text-sm font-semibold" />
                </div>
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function VisibilityStat({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string | number }) {
  return (
    <div className="rounded-md border border-[var(--color-border)] p-3 text-center">
      <Icon className="mx-auto h-5 w-5 text-[var(--color-muted-foreground)]" />
      <div className="mt-2 text-xl font-semibold">{value}</div>
      <div className="mt-0.5 text-xs text-[var(--color-muted-foreground)]">{label}</div>
    </div>
  );
}
