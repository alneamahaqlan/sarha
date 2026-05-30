import { Link, useParams } from 'react-router-dom';
import { ArrowRight, ArrowLeft } from 'lucide-react';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useCustomer } from '../hooks';
import { CustomerProfileHeader } from '../components/CustomerProfileHeader';
import { CustomerTabs } from '../components/CustomerTabs';

function fmtDate(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}

export function ClinicCustomerProfile() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const params = useParams<{ id: string }>();
  const id = params.id ? Number(params.id) : null;
  const { data: customer, isLoading } = useCustomer(id);

  if (isLoading) {
    return <div className="p-6 text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }
  if (!customer) {
    return <div className="p-6 text-sm text-rose-700">{t('clinic_customers.profile.not_found')}</div>;
  }

  const ArrowBack = locale === 'ar' ? ArrowRight : ArrowLeft;

  return (
    <div className="space-y-4">
      <Link to="/clinic/customers" className="inline-flex items-center gap-1.5 text-sm text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]">
        <ArrowBack className="h-4 w-4" />
        {t('clinic_customers.profile.back_to_list')}
      </Link>

      <CustomerProfileHeader customer={customer} />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <SummaryCard label={t('clinic_customers.summary.bookings')} value={String(customer.totals.bookings)} hint={t('clinic_customers.summary.bookings_breakdown', { completed: customer.totals.completed_bookings, cancelled: customer.totals.cancelled_bookings })} />
        <SummaryCard
          label={t('clinic_customers.summary.last_booking')}
          value={customer.last_booking?.service_name ?? '—'}
          hint={customer.last_booking?.appointment_at ? fmtDate(customer.last_booking.appointment_at, locale) : '—'}
        />
        <SummaryCard label={t('clinic_customers.summary.complaints')} value={String(customer.totals.complaints)} hint={t('clinic_customers.summary.quote_requests_value', { value: customer.totals.quote_requests })} />
        <SummaryCard label={t('clinic_customers.summary.service_value')} value={`${customer.totals.service_value.toFixed(0)} ر.س`} hint={t('clinic_customers.summary.first_seen', { date: fmtDate(customer.first_seen_at, locale) })} />
      </div>

      <CustomerTabs customerId={customer.id} />
    </div>
  );
}

function SummaryCard({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-3">
      <div className="text-[10px] uppercase text-[var(--color-muted-foreground)]">{label}</div>
      <div className="mt-1 truncate text-lg font-semibold">{value}</div>
      {hint && <div className="mt-0.5 truncate text-[11px] text-[var(--color-muted-foreground)]">{hint}</div>}
    </div>
  );
}
