import { useMemo, useState } from 'react';
import { Search, CheckCircle2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useLandingCustomers } from '../hooks';

interface Props {
  pageId: number;
}

const STATUSES = ['new', 'contacted', 'appointment_set', 'completed', 'no_show', 'cancelled'];
const STATUS_VARIANT: Record<string, 'success' | 'muted' | 'warning' | 'info' | 'danger'> = {
  new: 'info',
  contacted: 'warning',
  appointment_set: 'warning',
  completed: 'success',
  no_show: 'muted',
  cancelled: 'danger',
};

export function LandingCustomersTab({ pageId }: Props) {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const debounced = useDebouncedValue(search, 300);
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const params = useMemo(
    () => ({ page, per_page: 30, search: debounced.trim() || undefined, status: status || undefined }),
    [page, debounced, status],
  );
  const { data, isLoading, isFetching } = useLandingCustomers(pageId, params);
  const fmt = (n: number) => n.toLocaleString('ar-SA-u-nu-latn');

  return (
    <div className="space-y-4">
      {data && (
        <div className="grid grid-cols-3 gap-3">
          <Stat label={t('landing_pages.cust_total')} value={fmt(data.totals.total)} />
          <Stat label={t('landing_pages.cust_registered')} value={fmt(data.totals.registered)} />
          <Stat label={t('landing_pages.cust_completed')} value={fmt(data.totals.completed)} />
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input className="ps-9" placeholder={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
        <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="w-44">
          <option value="">{t('landing_pages.cust_all_statuses')}</option>
          {STATUSES.map((s) => <option key={s} value={s}>{t(`landing_pages.cust_status.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('landing_pages.cust_name')}</TableHead>
            <TableHead>{t('landing_pages.cust_phone')}</TableHead>
            <TableHead>{t('landing_pages.cust_service')}</TableHead>
            <TableHead>{t('landing_pages.cust_status_col')}</TableHead>
            <TableHead>{t('landing_pages.cust_source')}</TableHead>
            <TableHead>{t('landing_pages.cust_date')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('landing_pages.cust_empty')}</TableCell></TableRow>
          ) : (
            data.data.map((c) => (
              <TableRow key={c.id}>
                <TableCell className="font-medium">
                  <span className="inline-flex items-center gap-1.5">
                    {c.is_registered && <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" aria-label={t('landing_pages.cust_registered')} />}
                    {c.customer_name}
                  </span>
                  <div className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">{c.reference_code}</div>
                </TableCell>
                <TableCell dir="ltr" className="tabular-nums">{c.customer_phone}</TableCell>
                <TableCell>{c.service ?? '—'}</TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[c.status] ?? 'muted'}>{t(`landing_pages.cust_status.${c.status}`)}</Badge></TableCell>
                <TableCell className="text-sm" dir="ltr">{c.utm_source || c.utm_campaign || t('landing_pages.cust_source_direct')}</TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">{c.created_at ? new Date(c.created_at).toLocaleDateString('ar-SA-u-nu-latn') : '—'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">{data.meta.from}–{data.meta.to} / {data.meta.total}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>{t('common.back')}</Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>›</Button>
          </div>
        </div>
      )}
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-card)] p-4">
      <p className="text-xs text-[var(--color-muted-foreground)]">{label}</p>
      <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
    </div>
  );
}
