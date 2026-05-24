import { Check, CreditCard, Crown, Clock, MessageCircle, Phone, Mail } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';
import { useClinicSubscription } from '@/features/clinic/profile/hooks';
import type { ClinicSubscription } from '@/features/clinic/profile/api';

function ContactActions({ contact, message }: { contact: ClinicSubscription['contact']; message: string }) {
  const { t } = useTranslation();
  const hasAny = contact.whatsapp || contact.phone || contact.email;

  if (!hasAny) {
    return <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_subscription.contact_admin')}</p>;
  }

  return (
    <div className="flex flex-wrap gap-2">
      {contact.whatsapp && (
        <a
          href={`https://wa.me/${contact.whatsapp}?text=${encodeURIComponent(message)}`}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex h-9 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700"
        >
          <MessageCircle className="h-4 w-4" />
          {t('clinic_subscription.contact_whatsapp')}
        </a>
      )}
      {contact.phone && (
        <a
          href={`tel:${contact.phone}`}
          dir="ltr"
          className="inline-flex h-9 items-center gap-2 rounded-md border border-[var(--color-border)] px-4 text-sm font-medium hover:bg-[var(--color-muted)]"
        >
          <Phone className="h-4 w-4" />
          {t('clinic_subscription.contact_call')}
        </a>
      )}
      {contact.email && (
        <a
          href={`mailto:${contact.email}?subject=${encodeURIComponent(message)}`}
          className="inline-flex h-9 items-center gap-2 rounded-md border border-[var(--color-border)] px-4 text-sm font-medium hover:bg-[var(--color-muted)]"
        >
          <Mail className="h-4 w-4" />
          {t('clinic_subscription.contact_email')}
        </a>
      )}
    </div>
  );
}

export function ClinicSubscriptionPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useClinicSubscription();

  const fmtCurrency = (n: number) =>
    new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', {
      style: 'currency', currency: 'SAR', maximumFractionDigits: 0,
    }).format(n);
  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  if (isLoading || !data) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  const isPremium = data.subscription_type === 'premium';

  const basicFeatures = [
    t('clinic_subscription.feature.services'),
    t('clinic_subscription.feature.bookings'),
    t('clinic_subscription.feature.basic_articles'),
  ];
  const premiumFeatures = [
    t('clinic_subscription.feature.everything_basic'),
    t('clinic_subscription.feature.featured'),
    t('clinic_subscription.feature.unlimited_articles'),
    t('clinic_subscription.feature.priority_support'),
  ];

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_subscription.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_subscription.subtitle')}</p>
      </div>

      {/* Current plan card */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className={cn('flex h-10 w-10 items-center justify-center rounded-md', isPremium ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700')}>
              {isPremium ? <Crown className="h-5 w-5" /> : <CreditCard className="h-5 w-5" />}
            </div>
            <div>
              <div className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_subscription.current_plan')}</div>
              <div className="text-xl font-semibold">
                {data.subscription_type ? t(`clinics.plan.${data.subscription_type}`) : t('common.no_data')}
              </div>
              <div className="mt-1 text-xs text-[var(--color-muted-foreground)]">
                {t('clinic_subscription.ends_at', { date: fmtDate(data.subscription_ends_at) })}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2 rounded-md bg-[var(--color-muted)] px-3 py-2">
            <Clock className="h-4 w-4 text-[var(--color-muted-foreground)]" />
            <span className="text-sm font-medium">
              {t('clinic_subscription.days_remaining', { count: data.days_remaining })}
            </span>
          </div>
        </div>
      </div>

      {/* Renew / contact admin — always available so any plan can act */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-6">
        <h3 className="font-semibold">{t('clinic_subscription.renew_title')}</h3>
        <p className="mb-3 mt-1 text-sm text-[var(--color-muted-foreground)]">{t('clinic_subscription.renew_body')}</p>
        <ContactActions contact={data.contact} message={t('clinic_subscription.contact_message')} />
      </div>

      {/* Plan comparison */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <PlanCard
          title={t('clinics.plan.basic')}
          price={fmtCurrency(data.plans.basic)}
          period={t('clinic_subscription.per_year')}
          features={basicFeatures}
          active={!isPremium}
          activeLabel={t('clinic_subscription.your_plan')}
          tone="basic"
        />
        <PlanCard
          title={t('clinics.plan.premium')}
          price={fmtCurrency(data.plans.premium)}
          period={t('clinic_subscription.per_year')}
          features={premiumFeatures}
          active={isPremium}
          activeLabel={t('clinic_subscription.your_plan')}
          tone="premium"
        />
      </div>

      {/* Promo block when on basic */}
      {!isPremium && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-6">
          <div className="flex items-start gap-3">
            <Crown className="h-6 w-6 shrink-0 text-amber-600" />
            <div>
              <h3 className="font-semibold text-amber-900">{t('clinic_subscription.upgrade_title')}</h3>
              <p className="mt-1 text-sm text-amber-800">{t('clinic_subscription.upgrade_body')}</p>
              <div className="mt-3">
                <ContactActions contact={data.contact} message={t('clinic_subscription.upgrade_message')} />
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

interface PlanCardProps {
  title: string;
  price: string;
  period: string;
  features: string[];
  active: boolean;
  activeLabel: string;
  tone: 'basic' | 'premium';
}

function PlanCard({ title, price, period, features, active, activeLabel, tone }: PlanCardProps) {
  return (
    <div
      className={cn(
        'rounded-lg border bg-white p-6',
        active ? 'border-[var(--color-primary)] ring-1 ring-[var(--color-primary)]' : 'border-[var(--color-border)]',
      )}
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          {tone === 'premium' && <Crown className="h-5 w-5 text-amber-600" />}
          <h3 className="text-lg font-semibold">{title}</h3>
        </div>
        {active && (
          <span className="rounded-full bg-[var(--color-primary)] px-2.5 py-0.5 text-xs font-medium text-white">
            {activeLabel}
          </span>
        )}
      </div>
      <div className="mt-3">
        <span className="text-2xl font-bold">{price}</span>
        <span className="text-sm text-[var(--color-muted-foreground)]"> / {period}</span>
      </div>
      <ul className="mt-4 space-y-2">
        {features.map((f) => (
          <li key={f} className="flex items-center gap-2 text-sm">
            <Check className="h-4 w-4 shrink-0 text-emerald-600" />
            <span>{f}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
