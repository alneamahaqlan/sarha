import { useMemo, useState } from 'react';
import { Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useSubscriptions } from '../hooks';
import { SUBSCRIPTION_STATUSES, SUBSCRIPTION_TYPES, type SubscriptionStatus, type SubscriptionType } from '../types';

const STATUS_VARIANT: Record<SubscriptionStatus, 'success' | 'danger' | 'warning' | 'muted'> = {
  active: 'success',
  expired: 'danger',
  cancelled: 'danger',
  pending_payment: 'warning',
};

const TYPE_VARIANT: Record<SubscriptionType, 'default' | 'warning'> = {
  basic: 'default',
  premium: 'warning',
};

export function SubscriptionsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [typeFilter, setTypeFilter] = useState<SubscriptionType | undefined>();
  const [statusFilter, setStatusFilter] = useState<SubscriptionStatus | undefined>();

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: { type: typeFilter, status: statusFilter },
    }),
    [page, debouncedSearch, typeFilter, statusFilter],
  );
  const { data, isLoading, isFetching } = useSubscriptions(queryParams);

  const fmtCurrency = (n: number) =>
    new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', {
      style: 'currency',
      currency: 'SAR',
      maximumFractionDigits: 0,
    }).format(n);

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('subscriptions.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('subscriptions.subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select
          value={typeFilter ?? ''}
          onChange={(e) => { setTypeFilter((e.target.value || undefined) as SubscriptionType | undefined); setPage(1); }}
          className="w-36"
        >
          <option value="">{t('subscriptions.filter_all_types')}</option>
          {SUBSCRIPTION_TYPES.map((s) => <option key={s} value={s}>{t(`subscriptions.type.${s}`)}</option>)}
        </Select>
        <Select
          value={statusFilter ?? ''}
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as SubscriptionStatus | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('subscriptions.filter_all_statuses')}</option>
          {SUBSCRIPTION_STATUSES.map((s) => <option key={s} value={s}>{t(`subscriptions.status.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('subscriptions.clinic')}</TableHead>
            <TableHead>{t('subscriptions.type_label')}</TableHead>
            <TableHead>{t('subscriptions.amount')}</TableHead>
            <TableHead>{t('subscriptions.starts_at')}</TableHead>
            <TableHead>{t('subscriptions.ends_at')}</TableHead>
            <TableHead>{t('subscriptions.status_label')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((sub) => (
              <TableRow key={sub.id}>
                <TableCell className="font-medium">{sub.clinic?.name ?? '—'}</TableCell>
                <TableCell>
                  <Badge variant={TYPE_VARIANT[sub.type]}>{t(`subscriptions.type.${sub.type}`)}</Badge>
                </TableCell>
                <TableCell className="font-medium">{fmtCurrency(sub.amount)}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(sub.starts_at)}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(sub.ends_at)}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[sub.status]}>{t(`subscriptions.status.${sub.status}`)}</Badge>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {data.meta.from}–{data.meta.to} / {data.meta.total}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.meta.last_page || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              ›
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
