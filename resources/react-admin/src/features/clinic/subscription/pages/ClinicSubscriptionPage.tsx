import {
  AlertTriangle,
  Bot,
  BadgeCheck,
  BarChart3,
  Calendar,
  CheckCircle2,
  Clock,
  Crown,
  Image as ImageIcon,
  Lock,
  Mail,
  MessageCircle,
  Phone,
  PenSquare,
  RefreshCw,
  Search,
  Sparkles,
  Stethoscope,
} from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { Money } from '@/lib/money';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { useClinicSubscription } from '@/features/clinic/profile/hooks';
import type {
  ClinicSubscription,
  ClinicSubscriptionPackage,
  PackageColorToken,
  SubscriptionStatus,
} from '@/features/clinic/profile/api';

/** Maps the color_token coming back from the API to a Badge variant. */
const COLOR_VARIANT: Record<PackageColorToken, NonNullable<BadgeProps['variant']>> = {
  gray:    'muted',
  sage:    'success',
  gold:    'gold',
  plum:    'ai',
  info:    'info',
  warning: 'warning',
};

/** Coloured strip across the top of the hero / mini-cards. */
const COLOR_STRIP: Record<PackageColorToken, string> = {
  gray:    'bg-gray-300',
  sage:    'bg-sage-500',
  gold:    'bg-gold-primary',
  plum:    'bg-plum-primary',
  info:    'bg-blue-400',
  warning: 'bg-amber-400',
};

/** Coloured progress-bar fill matching the package's accent. */
const METER_FILL: Record<PackageColorToken, string> = {
  gray:    'bg-gray-400',
  sage:    'bg-sage-500',
  gold:    'bg-gold-primary',
  plum:    'bg-plum-primary',
  info:    'bg-blue-500',
  warning: 'bg-amber-500',
};

const STATUS_VARIANT: Record<SubscriptionStatus, NonNullable<BadgeProps['variant']>> = {
  active: 'success',
  expired: 'danger',
  cancelled: 'muted',
  pending_payment: 'warning',
};

export function ClinicSubscriptionPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useClinicSubscription();

  const fmtDate = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  if (isLoading || !data) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  const pkgInline = data.snapshot.package;
  const currentColor: PackageColorToken = (pkgInline?.color_token ?? 'gray') as PackageColorToken;
  const currentPkgName = pkgInline
    ? (locale === 'ar' ? pkgInline.name_ar : pkgInline.name_en)
    : t('clinic_subscription.no_active');
  const currentPkgPrice = pkgInline?.monthly_price ?? 0;
  const current = data.current_subscription;
  const daysRemaining = data.days_remaining;
  // Day-zero (expires today) and any past-end day count as "expired"
  // — otherwise the hero pill goes neutral grey on the exact day the
  // subscription ends, which is the worst moment to drop the warning.
  const isExpired = daysRemaining <= 0 || current?.status === 'expired';
  const isExpiringSoon = !isExpired && daysRemaining <= 7;

  return (
    <div className="max-w-5xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_subscription.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_subscription.subtitle')}</p>
      </div>

      {/* Hero — current plan */}
      <div className="relative overflow-hidden rounded-xl border border-[var(--color-border)] bg-white shadow-sm">
        <div className={cn('h-1.5', COLOR_STRIP[currentColor])} />
        <div className="p-5 sm:p-6 space-y-4">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="flex items-start gap-3 min-w-0">
              <div className={cn(
                'flex h-12 w-12 shrink-0 items-center justify-center rounded-lg',
                currentColor === 'gold' ? 'bg-gold-whisper text-gold-deep'
                  : currentColor === 'sage' ? 'bg-sage-mist text-sage-deep'
                  : 'bg-[var(--color-muted)] text-[var(--color-muted-foreground)]',
              )}>
                {currentColor === 'gold' ? <Crown className="h-6 w-6" /> : <Sparkles className="h-6 w-6" />}
              </div>
              <div className="min-w-0">
                <div className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_subscription.current_plan')}</div>
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-xl sm:text-2xl font-semibold truncate">{currentPkgName}</h2>
                  {pkgInline && (
                    <Badge variant={COLOR_VARIANT[currentColor]} className="text-[10px]">{pkgInline.slug}</Badge>
                  )}
                  {current && (
                    <Badge variant={STATUS_VARIANT[current.status]} className="text-[10px]">
                      {t(`clinic_subscription.status.${current.status}`)}
                    </Badge>
                  )}
                </div>
                <div className="mt-1 text-sm text-[var(--color-muted-foreground)]">
                  {currentPkgPrice === 0
                    ? t('packages.free')
                    : <><Money value={currentPkgPrice} locale={locale} /> <span className="text-xs">/ {t('clinic_subscription.per_month')}</span></>}
                </div>
              </div>
            </div>

            {/* Days remaining pill */}
            <div className={cn(
              'flex items-center gap-2 rounded-md px-3 py-2',
              isExpired ? 'bg-red-50 text-red-700'
                : isExpiringSoon ? 'bg-amber-50 text-amber-700'
                : 'bg-[var(--color-muted)] text-[var(--color-foreground)]',
            )}>
              <Clock className="h-4 w-4" />
              <span className="text-sm font-medium">
                {isExpired
                  ? t('clinic_subscription.days_expired', { count: Math.abs(daysRemaining) })
                  : t('clinic_subscription.days_remaining', { count: daysRemaining })}
              </span>
            </div>
          </div>

          {/* Sub-details: dates + cycle + bonus */}
          {current && (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-3 border-t border-[var(--color-border)]">
              <DetailCell icon={Calendar} label={t('clinic_subscription.starts_at_label')} value={fmtDate(current.starts_at)} />
              <DetailCell icon={Calendar} label={t('clinic_subscription.ends_at_label')} value={fmtDate(current.ends_at)} />
              <DetailCell
                icon={RefreshCw}
                label={t('clinic_subscription.cycle_label')}
                value={current.billing_cycle ? t(`clinic_subscription.cycle.${current.billing_cycle}`) : '—'}
              />
              <DetailCell
                icon={Sparkles}
                label={t('clinic_subscription.bonus_months_label')}
                value={current.bonus_months > 0 ? `+${current.bonus_months}` : '—'}
              />
            </div>
          )}

          {/* Expiry warnings */}
          {isExpired && (
            <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
              <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
              <span>{t('clinic_subscription.expired_warning')}</span>
            </div>
          )}
          {isExpiringSoon && !isExpired && (
            <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
              <span>{t('clinic_subscription.expires_soon_warning', { days: daysRemaining })}</span>
            </div>
          )}
        </div>
      </div>

      {/* Usage meters */}
      <Section title={t('clinic_subscription.usage.title')} subtitle={t('clinic_subscription.usage.subtitle')}>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <UsageMeter
            icon={Stethoscope}
            label={t('clinic_subscription.usage.services')}
            used={data.snapshot.usage.services}
            limit={data.snapshot.limits.services}
            color={currentColor}
            t={t}
          />
          <UsageMeter
            icon={PenSquare}
            label={t('clinic_subscription.usage.articles')}
            used={data.snapshot.usage.articles_month}
            limit={data.snapshot.limits.articles_month}
            color={currentColor}
            t={t}
          />
          <UsageMeter
            icon={MessageCircle}
            label={t('clinic_subscription.usage.replies')}
            used={data.snapshot.usage.quote_replies}
            limit={data.snapshot.limits.quote_replies}
            color={currentColor}
            t={t}
          />
        </div>
      </Section>

      {/* Feature flags */}
      <Section title={t('clinic_subscription.features.title')} subtitle={t('clinic_subscription.features.subtitle')}>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <FeatureLine ok={data.snapshot.flags.ai_article_generation} icon={Bot} label={t('clinic_subscription.features.ai_article_generation')} />
          <FeatureLine ok={data.snapshot.flags.featured_in_search} icon={Search} label={t('clinic_subscription.features.featured_in_search')} />
          <FeatureLine
            ok={data.snapshot.flags.ai_assistant_priority > 0}
            icon={Sparkles}
            label={t(`clinic_subscription.features.ai_assistant_priority_${data.snapshot.flags.ai_assistant_priority}`)}
          />
          <FeatureLine ok={data.snapshot.flags.google_reviews_sync} icon={RefreshCw} label={t('clinic_subscription.features.google_reviews_sync')} />
          <FeatureLine ok={data.snapshot.flags.verified_badge} icon={BadgeCheck} label={t('clinic_subscription.features.verified_badge')} />
          {/* Analytics is always included — the package only decides
              whether it's the basic or advanced dashboard. Both are
              "ok" so they render in the enabled (✓) style; the label
              just changes. */}
          <FeatureLine
            ok={true}
            icon={BarChart3}
            label={data.snapshot.flags.analytics_level === 'full'
              ? t('clinic_subscription.features.analytics_full')
              : t('clinic_subscription.features.analytics_basic')}
          />
          <FeatureLine ok={data.snapshot.flags.allow_offers_packages} icon={Sparkles} label={t('clinic_subscription.features.allow_offers_packages')} />
          <FeatureLine ok={data.snapshot.flags.allow_doctors_before_after} icon={Stethoscope} label={t('clinic_subscription.features.allow_doctors_before_after')} />
          <FeatureLine
            ok={data.snapshot.limits.banner_slots > 0}
            icon={ImageIcon}
            label={t('clinic_subscription.features.banner_slots', { n: data.snapshot.limits.banner_slots })}
          />
        </div>
      </Section>

      {/* Plan comparison */}
      {data.packages.length > 0 && (
        <Section title={t('clinic_subscription.packages.title')} subtitle={t('clinic_subscription.packages.subtitle')}>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            {data.packages.map((p) => (
              <PackageMiniCard
                key={p.id}
                pkg={p}
                isCurrent={pkgInline?.id === p.id}
                locale={locale}
                t={t}
              />
            ))}
          </div>
        </Section>
      )}

      {/* Contact CTA — admin handles upgrades out-of-band per product spec */}
      <div className={cn(
        'rounded-xl border p-5 sm:p-6',
        isExpired || isExpiringSoon ? 'border-amber-200 bg-amber-50' : 'border-[var(--color-border)] bg-white',
      )}>
        <div className="flex items-start gap-3">
          <Crown className={cn('h-6 w-6 shrink-0', isExpired || isExpiringSoon ? 'text-amber-600' : 'text-gold-primary')} />
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold">{t('clinic_subscription.contact_cta.title')}</h3>
            <p className="mt-1 text-sm text-[var(--color-muted-foreground)]">{t('clinic_subscription.contact_cta.body')}</p>
            <div className="mt-3">
              <ContactActions
                contact={data.contact}
                message={t('clinic_subscription.contact_message', { plan: currentPkgName })}
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ───────── helpers ─────────

function Section({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-white p-5 sm:p-6">
      <div className="mb-4">
        <h3 className="font-semibold">{title}</h3>
        {subtitle && <p className="mt-0.5 text-xs text-[var(--color-muted-foreground)]">{subtitle}</p>}
      </div>
      {children}
    </div>
  );
}

function DetailCell({
  icon: Icon, label, value,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-start gap-2">
      <Icon className="h-4 w-4 mt-0.5 shrink-0 text-[var(--color-muted-foreground)]" />
      <div className="min-w-0">
        <div className="text-[11px] text-[var(--color-muted-foreground)]">{label}</div>
        <div className="text-sm font-medium truncate">{value}</div>
      </div>
    </div>
  );
}

function UsageMeter({
  icon: Icon, label, used, limit, color, t,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  used: number;
  limit: number | null;
  color: PackageColorToken;
  t: (k: string, opts?: Record<string, unknown>) => string;
}) {
  const isUnlimited = limit === null;
  const pct = isUnlimited ? 0 : Math.min(100, Math.round((used / Math.max(1, limit)) * 100));
  const reached = !isUnlimited && used >= limit;
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)]/30 p-3 space-y-2">
      <div className="flex items-center gap-2">
        <Icon className="h-4 w-4 text-[var(--color-muted-foreground)]" />
        <div className="text-xs font-medium">{label}</div>
      </div>
      <div className="flex items-baseline gap-1">
        <span className="text-lg font-bold tabular-nums">{used}</span>
        <span className="text-xs text-[var(--color-muted-foreground)]">/ {isUnlimited ? '∞' : limit}</span>
      </div>
      {!isUnlimited && (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-border)]/50">
          <div
            className={cn('h-full transition-all', reached ? 'bg-red-500' : METER_FILL[color])}
            style={{ width: `${pct}%` }}
          />
        </div>
      )}
      <div className={cn('text-[11px]', reached ? 'text-red-600 font-medium' : 'text-[var(--color-muted-foreground)]')}>
        {isUnlimited
          ? t('clinic_subscription.usage.unlimited_hint')
          : reached
            ? t('clinic_subscription.usage.reached_limit')
            : t('clinic_subscription.usage.remaining', { n: limit - used })}
      </div>
    </div>
  );
}

function FeatureLine({
  ok, icon: Icon, label,
}: {
  ok: boolean;
  icon: React.ComponentType<{ className?: string }>;
  label: string;
}) {
  return (
    <div className={cn(
      'flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
      ok ? 'border-sage-mist bg-sage-mist/30 text-sage-deep'
        : 'border-[var(--color-border)] bg-[var(--color-muted)]/30 text-[var(--color-muted-foreground)]',
    )}>
      {ok ? <CheckCircle2 className="h-4 w-4 shrink-0 text-sage-deep" /> : <Lock className="h-4 w-4 shrink-0" />}
      <Icon className="h-3.5 w-3.5 shrink-0 opacity-60" />
      <span className={cn('truncate', !ok && 'line-through opacity-70')}>{label}</span>
    </div>
  );
}

function PackageMiniCard({
  pkg, isCurrent, locale, t,
}: {
  pkg: ClinicSubscriptionPackage;
  isCurrent: boolean;
  locale: string;
  t: (k: string, opts?: Record<string, unknown>) => string;
}) {
  const name = locale === 'ar' ? pkg.name_ar : pkg.name_en;
  const tagline = locale === 'ar' ? pkg.tagline_ar : pkg.tagline_en;
  const color = pkg.color_token;
  const highlights: string[] = [];
  highlights.push(pkg.services_limit === null
    ? t('clinic_subscription.h.unlimited_services')
    : t('clinic_subscription.h.services_count', { n: pkg.services_limit }));
  highlights.push(pkg.articles_monthly_limit === null
    ? t('clinic_subscription.h.unlimited_articles_month')
    : t('clinic_subscription.h.articles_count', { n: pkg.articles_monthly_limit }));
  if (pkg.ai_article_generation) highlights.push(t('clinic_subscription.features.ai_article_generation'));
  if (pkg.featured_in_search) highlights.push(t('clinic_subscription.features.featured_in_search'));
  if (pkg.verified_badge) highlights.push(t('clinic_subscription.features.verified_badge'));
  if (pkg.allow_offers_packages) highlights.push(t('clinic_subscription.features.allow_offers_packages'));
  if (pkg.allow_doctors_before_after) highlights.push(t('clinic_subscription.features.allow_doctors_before_after'));

  return (
    <div className={cn(
      'relative overflow-hidden rounded-xl border bg-white shadow-sm',
      isCurrent ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary)]/30' : 'border-[var(--color-border)]',
    )}>
      <div className={cn('h-1.5', COLOR_STRIP[color])} />
      <div className="p-4 space-y-3">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0 flex-1">
            <h4 className="text-base font-semibold truncate">{name}</h4>
            {tagline && <p className="text-xs text-[var(--color-muted-foreground)] line-clamp-2">{tagline}</p>}
          </div>
          {isCurrent && (
            <Badge variant="default" className="shrink-0 text-[10px]">
              {t('clinic_subscription.packages.current_badge')}
            </Badge>
          )}
        </div>
        <div className="flex items-baseline gap-1">
          {pkg.monthly_price === 0 ? (
            <span className="text-xl font-bold">{t('packages.free')}</span>
          ) : (
            <>
              <Money value={pkg.monthly_price} locale={locale} className="text-xl font-bold" />
              <span className="text-[11px] text-[var(--color-muted-foreground)]">/ {t('clinic_subscription.per_month')}</span>
            </>
          )}
        </div>
        <ul className="space-y-1 text-xs">
          {highlights.slice(0, 5).map((h, i) => (
            <li key={i} className="flex items-start gap-1.5">
              <CheckCircle2 className="h-3 w-3 mt-0.5 shrink-0 text-sage-deep" />
              <span className="truncate">{h}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function ContactActions({
  contact, message,
}: {
  contact: ClinicSubscription['contact'];
  message: string;
}) {
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
