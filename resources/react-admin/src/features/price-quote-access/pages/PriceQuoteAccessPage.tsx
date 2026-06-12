import { useState } from 'react';
import { toast } from 'sonner';
import { DollarSign, Search } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
  useQuoteAccessRequests, useApproveQuoteAccess, useRejectQuoteAccess, useDisableQuoteAccess,
} from '../hooks';
import type { QuoteAccessClinic, QuoteAccessStatus } from '../api';

const STATUS_VARIANT: Record<QuoteAccessStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  rejected: 'danger',
  disabled: 'muted',
};

export function PriceQuoteAccessPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <DollarSign className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('price_quote_access.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('price_quote_access.subtitle')}</p>
        </div>
      </div>

      <RequestsSection />
    </div>
  );
}

function RequestsSection() {
  const { t } = useTranslation();
  const [status, setStatus] = useState('active');
  const [search, setSearch] = useState('');
  const debounced = useDebouncedValue(search, 300);
  const { data, isLoading } = useQuoteAccessRequests(status, debounced);
  const approve = useApproveQuoteAccess();
  const disable = useDisableQuoteAccess();
  const [rejecting, setRejecting] = useState<QuoteAccessClinic | null>(null);

  const act = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn();
      toast.success(t(ok));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-3">
      <Tabs value={status} onValueChange={setStatus}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <TabsList>
            <TabsTrigger value="active">{t('price_quote_access.tab_active')}</TabsTrigger>
            <TabsTrigger value="pending">{t('price_quote_access.tab_pending')}</TabsTrigger>
            <TabsTrigger value="disabled">{t('price_quote_access.tab_disabled')}</TabsTrigger>
            <TabsTrigger value="all">{t('price_quote_access.tab_all')}</TabsTrigger>
          </TabsList>
          <div className="relative">
            <Search className="absolute start-2.5 top-2.5 h-4 w-4 text-[var(--color-muted-foreground)]" />
            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('common.search')} className="ps-8 w-56" />
          </div>
        </div>

        <TabsContent value={status}>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('price_quote_access.col_clinic')}</TableHead>
                <TableHead>{t('price_quote_access.col_status')}</TableHead>
                <TableHead className="text-end">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.loading')}
                  </TableCell>
                </TableRow>
              ) : !data || data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.no_data')}
                  </TableCell>
                </TableRow>
              ) : (
                data.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.name}</TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[c.price_quote_status]}>
                        {t(`price_quote_access.status_${c.price_quote_status}`)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-end">
                      <div className="flex justify-end gap-1">
                        {c.price_quote_status !== 'active' && (
                          <Button size="sm" disabled={approve.isPending}
                            onClick={() => act(() => approve.mutateAsync(c.id), 'price_quote_access.approved')}>
                            {c.price_quote_status === 'pending' ? t('price_quote_access.approve') : t('price_quote_access.enable')}
                          </Button>
                        )}
                        {c.price_quote_status === 'pending' && (
                          <Button size="sm" variant="outline" onClick={() => setRejecting(c)}>
                            {t('price_quote_access.reject')}
                          </Button>
                        )}
                        {c.price_quote_status === 'active' && (
                          <Button size="sm" variant="destructive" disabled={disable.isPending}
                            onClick={() => act(() => disable.mutateAsync(c.id), 'price_quote_access.disabled')}>
                            {t('price_quote_access.disable')}
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TabsContent>
      </Tabs>

      {rejecting && <RejectDialog clinic={rejecting} onClose={() => setRejecting(null)} />}
    </div>
  );
}

function RejectDialog({ clinic, onClose }: { clinic: QuoteAccessClinic; onClose: () => void }) {
  const { t } = useTranslation();
  const reject = useRejectQuoteAccess();
  const [reason, setReason] = useState('');

  const submit = async () => {
    try {
      await reject.mutateAsync({ id: clinic.id, reason });
      toast.success(t('price_quote_access.rejected'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('price_quote_access.reject_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="reason">{t('price_quote_access.reject_reason')}</Label>
          <Textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={submit} disabled={reject.isPending}>
            {reject.isPending ? t('common.loading') : t('price_quote_access.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
