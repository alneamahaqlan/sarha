import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Heart, ShoppingBag, Users } from 'lucide-react';

import { Select } from '@/components/ui/select';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Money } from '@/lib/money';
import { clinicServicesApi } from '@/features/clinic/services/api';

import { useBestSelling, useMostInterested, useInterestedCustomers, useServiceBuyers } from '../hooks';
import type { DateRange } from '../types';

function Panel({ title, icon: Icon, children }: { title: string; icon: typeof BarChart3; children: React.ReactNode }) {
  return (
    <section className="rounded-lg border border-[var(--color-border)] bg-white">
      <div className="flex items-center gap-1.5 border-b border-[var(--color-border)] px-3 py-2 text-sm font-semibold">
        <Icon className="h-4 w-4 text-[var(--color-primary)]" /> {title}
      </div>
      <div className="p-3">{children}</div>
    </section>
  );
}

export function ClinicServiceReportsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [range, setRange] = useState<DateRange>({});
  const [serviceId, setServiceId] = useState<number | null>(null);

  const bestSelling = useBestSelling(range);
  const mostInterested = useMostInterested(range);
  const interestedCustomers = useInterestedCustomers(serviceId);
  const buyers = useServiceBuyers(serviceId, range);

  const { data: serviceList } = useQuery({
    queryKey: ['clinic', 'services', 'list', { per_page: 100 }],
    queryFn: () => clinicServicesApi.list({ per_page: 100 }),
    staleTime: 60_000,
  });

  const empty = (
    <div className="py-6 text-center text-xs text-[var(--color-muted-foreground)]">
      {t('clinic_service_reports.no_data')}
    </div>
  );

  return (
    <div className="space-y-4">
      <div>
        <h1 className="flex items-center gap-2 text-lg font-semibold">
          <BarChart3 className="h-5 w-5 text-[var(--color-primary)]" />
          {t('clinic_service_reports.title')}
        </h1>
        <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_service_reports.subtitle')}</p>
      </div>

      {/* Date range */}
      <div className="flex flex-wrap items-center gap-2 rounded-lg border border-[var(--color-border)] bg-white p-2">
        <span className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_service_reports.date_range')}:</span>
        <input
          type="date"
          value={range.date_from ?? ''}
          onChange={(e) => setRange((r) => ({ ...r, date_from: e.target.value || undefined }))}
          className="h-8 rounded-md border border-[var(--color-border)] px-2 text-xs"
        />
        <span className="text-[var(--color-muted-foreground)]">—</span>
        <input
          type="date"
          value={range.date_to ?? ''}
          onChange={(e) => setRange((r) => ({ ...r, date_to: e.target.value || undefined }))}
          className="h-8 rounded-md border border-[var(--color-border)] px-2 text-xs"
        />
        {(range.date_from || range.date_to) && (
          <button
            type="button"
            onClick={() => setRange({})}
            className="text-xs text-[var(--color-primary)] hover:underline"
          >
            {t('clinic_service_reports.clear')}
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <Panel title={t('clinic_service_reports.best_selling')} icon={ShoppingBag}>
          {(bestSelling.data ?? []).length === 0 ? empty : (
            <table className="w-full text-sm">
              <thead>
                <tr className="text-[11px] text-[var(--color-muted-foreground)]">
                  <th className="py-1 text-start font-medium">{t('clinic_service_reports.col_service')}</th>
                  <th className="py-1 text-center font-medium">{t('clinic_service_reports.col_purchases')}</th>
                  <th className="py-1 text-end font-medium">{t('clinic_service_reports.col_value')}</th>
                </tr>
              </thead>
              <tbody>
                {(bestSelling.data ?? []).map((r) => (
                  <tr key={r.service_id} className="border-t border-[var(--color-border)]">
                    <td className="py-1.5">{r.service_name ?? '—'}</td>
                    <td className="py-1.5 text-center">
                      {r.purchases.toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US')}
                    </td>
                    <td className="py-1.5 text-end font-medium"><Money value={r.value} locale={locale} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>

        <Panel title={t('clinic_service_reports.most_interested')} icon={Heart}>
          {(mostInterested.data ?? []).length === 0 ? empty : (
            <table className="w-full text-sm">
              <thead>
                <tr className="text-[11px] text-[var(--color-muted-foreground)]">
                  <th className="py-1 text-start font-medium">{t('clinic_service_reports.col_service')}</th>
                  <th className="py-1 text-end font-medium">{t('clinic_service_reports.col_interested')}</th>
                </tr>
              </thead>
              <tbody>
                {(mostInterested.data ?? []).map((r) => (
                  <tr key={r.service_id} className="border-t border-[var(--color-border)]">
                    <td className="py-1.5">{r.service_name ?? '—'}</td>
                    <td className="py-1.5 text-end font-medium">
                      {r.interested.toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>
      </div>

      {/* Per-service drilldown */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-3">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-semibold">{t('clinic_service_reports.pick_service')}:</span>
          <Select
            value={serviceId ? String(serviceId) : ''}
            onChange={(e) => setServiceId(e.target.value ? Number(e.target.value) : null)}
            className="h-8 w-64 text-xs"
          >
            <option value="">—</option>
            {(serviceList?.data ?? []).map((s) => (
              <option key={s.id} value={s.id}>{s.name}</option>
            ))}
          </Select>
        </div>
      </div>

      {serviceId && (
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          <Panel title={t('clinic_service_reports.interested_customers')} icon={Users}>
            {(interestedCustomers.data ?? []).length === 0 ? empty : (
              <ul className="divide-y divide-[var(--color-border)] text-sm">
                {(interestedCustomers.data ?? []).map((c) => (
                  <li key={c.customer_id} className="flex items-center justify-between py-1.5">
                    <span>{c.name ?? '—'}</span>
                    <span className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">{c.phone}</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel title={t('clinic_service_reports.buyers')} icon={ShoppingBag}>
            {(buyers.data ?? []).length === 0 ? empty : (
              <ul className="divide-y divide-[var(--color-border)] text-sm">
                {(buyers.data ?? []).map((b) => (
                  <li key={b.customer_id} className="flex items-center justify-between gap-2 py-1.5">
                    <span className="min-w-0 flex-1 truncate">{b.name ?? '—'}</span>
                    <span className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">{b.phone}</span>
                    <span className="shrink-0 font-medium"><Money value={b.value} locale={locale} /></span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      )}
    </div>
  );
}
