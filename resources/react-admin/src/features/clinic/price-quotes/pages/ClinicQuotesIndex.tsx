import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { MessageSquare, Phone, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { PRICE_QUOTE_STATUSES, type PriceQuote, type PriceQuoteStatus } from '@/features/price-quotes/types';

import { useClinicQuotes, useUpdateClinicQuote } from '../hooks';

const STATUS_VARIANT: Record<PriceQuoteStatus, 'warning' | 'success' | 'muted'> = {
  new: 'warning',
  replied: 'success',
  closed: 'muted',
};

function ReplyDialog({ quote, onClose }: { quote: PriceQuote; onClose: () => void }) {
  const { t } = useTranslation();
  const [status, setStatus] = useState<PriceQuoteStatus>(quote.status);
  const [reply, setReply] = useState(quote.clinic_reply ?? '');
  const [err, setErr] = useState<string | null>(null);
  const mut = useUpdateClinicQuote(quote.id);

  const onSubmit = async () => {
    if (status === 'replied' && !reply.trim()) { setErr(t('errors.validation')); return; }
    try {
      await mut.mutateAsync({ status, clinic_reply: reply || null });
      toast.success(t('clinic_quotes.updated'));
      onClose();
    } catch (e) {
      const ve = extractValidationErrors(e);
      if (ve?.clinic_reply) setErr(ve.clinic_reply[0]);
      else toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_quotes.reply_title')}</DialogTitle>
          <DialogDescription>{quote.service_name}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-3 rounded-md border border-[var(--color-border)] bg-[var(--color-muted)] px-3 py-2 text-sm">
            <span className="font-medium">{quote.customer_name}</span>
            <a
              href={`tel:${quote.customer_phone}`}
              className="inline-flex items-center gap-1 text-[var(--color-primary)]"
              dir="ltr"
            >
              <Phone className="h-3 w-3" />{quote.customer_phone}
            </a>
          </div>
          {quote.description && (
            <div className="rounded-md bg-[var(--color-muted)] p-3 text-sm text-[var(--color-muted-foreground)]">
              {quote.description}
            </div>
          )}
          <div className="space-y-1.5">
            <Label htmlFor="status">{t('clinic_quotes.status_label')}</Label>
            <Select id="status" value={status} onChange={(e) => { setStatus(e.target.value as PriceQuoteStatus); setErr(null); }}>
              {PRICE_QUOTE_STATUSES.map((s) => <option key={s} value={s}>{t(`price_quotes.status.${s}`)}</option>)}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="clinic_reply">{t('clinic_quotes.reply')}</Label>
            <Textarea id="clinic_reply" rows={4} value={reply} onChange={(e) => { setReply(e.target.value); setErr(null); }} />
            {err && <p className="text-xs text-[var(--color-destructive)]">{err}</p>}
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending}>{mut.isPending ? t('common.loading') : t('common.save')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicQuotesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<PriceQuoteStatus | undefined>();
  const [replying, setReplying] = useState<PriceQuote | null>(null);

  const params = useMemo(
    () => ({ page, per_page: 15, search: debouncedSearch.trim() || undefined, filter: { status: statusFilter } }),
    [page, debouncedSearch, statusFilter],
  );
  const { data, isLoading, isFetching } = useClinicQuotes(params);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_quotes.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_quotes.subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input className="ps-9" placeholder={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
        <Select value={statusFilter ?? ''} onChange={(e) => { setStatusFilter((e.target.value || undefined) as PriceQuoteStatus | undefined); setPage(1); }} className="w-44">
          <option value="">{t('clinic_quotes.filter_all_statuses')}</option>
          {PRICE_QUOTE_STATUSES.map((s) => <option key={s} value={s}>{t(`price_quotes.status.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_quotes.customer_name')}</TableHead>
            <TableHead>{t('clinic_quotes.customer_phone')}</TableHead>
            <TableHead>{t('clinic_quotes.service_name')}</TableHead>
            <TableHead>{t('clinic_quotes.status_label')}</TableHead>
            <TableHead>{t('clinic_quotes.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((q) => (
              <TableRow key={q.id}>
                <TableCell className="font-medium">{q.customer_name}</TableCell>
                <TableCell>
                  <a href={`tel:${q.customer_phone}`} className="inline-flex items-center gap-1 text-[var(--color-primary)]" dir="ltr">
                    <Phone className="h-3 w-3" />{q.customer_phone}
                  </a>
                </TableCell>
                <TableCell className="max-w-xs truncate">{q.service_name}</TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[q.status]}>{t(`price_quotes.status.${q.status}`)}</Badge></TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(q.created_at)}</TableCell>
                <TableCell className="text-end">
                  <Button variant="ghost" size="icon" onClick={() => setReplying(q)} aria-label={t('clinic_quotes.reply')}>
                    <MessageSquare className="h-4 w-4" />
                  </Button>
                </TableCell>
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

      {replying && <ReplyDialog quote={replying} onClose={() => setReplying(null)} />}
    </div>
  );
}
