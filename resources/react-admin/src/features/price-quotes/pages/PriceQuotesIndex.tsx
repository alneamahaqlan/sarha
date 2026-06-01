import { useMemo, useState } from 'react';
import { Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useDeletePriceQuote, usePriceQuotes } from '../hooks';
import { PRICE_QUOTE_STATUSES, type PriceQuote, type PriceQuoteStatus } from '../types';

const STATUS_VARIANT: Record<PriceQuoteStatus, 'warning' | 'success' | 'muted'> = {
  new: 'warning',
  replied: 'success',
  closed: 'muted',
};

export function PriceQuotesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<PriceQuoteStatus | undefined>();
  const [deleting, setDeleting] = useState<PriceQuote | null>(null);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: { status: statusFilter },
    }),
    [page, debouncedSearch, statusFilter],
  );
  const { data, isLoading, isFetching } = usePriceQuotes(queryParams);
  const del = useDeletePriceQuote();

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('price_quotes.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('price_quotes.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('price_quotes.subtitle')}</p>
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
          value={statusFilter ?? ''}
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as PriceQuoteStatus | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('price_quotes.filter_all_statuses')}</option>
          {PRICE_QUOTE_STATUSES.map((s) => <option key={s} value={s}>{t(`price_quotes.status.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('price_quotes.clinic')}</TableHead>
            <TableHead>{t('price_quotes.customer_name')}</TableHead>
            <TableHead>{t('price_quotes.customer_phone')}</TableHead>
            <TableHead>{t('price_quotes.service_name')}</TableHead>
            <TableHead>{t('price_quotes.status_label')}</TableHead>
            <TableHead>{t('price_quotes.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((q) => (
              <TableRow key={q.id}>
                <TableCell className="text-[var(--color-muted-foreground)]">{q.clinic?.name ?? '—'}</TableCell>
                <TableCell className="font-medium">{q.customer_name}</TableCell>
                <TableCell dir="ltr">{q.customer_phone}</TableCell>
                <TableCell className="max-w-xs truncate">{q.service_name}</TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[q.status]}>{t(`price_quotes.status.${q.status}`)}</Badge>
                </TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(q.created_at)}</TableCell>
                <TableCell className="text-end">
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setDeleting(q)}
                    aria-label={t('common.delete')}
                    className="text-[var(--color-destructive)]"
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
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

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('price_quotes.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('price_quotes.delete_confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
