import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ArrowRightCircle, Pencil, Plus, Search, Star, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { extractMessage } from '@/lib/api-client';

import { useDeleteSalesLead, useSalesLeads } from '../hooks';
import { ConvertDialog } from '../components/ConvertDialog';
import { SalesLeadForm } from '../components/SalesLeadForm';
import { SALES_LEAD_STATUSES, type SalesLead, type SalesLeadStatus, type SubscriptionPlan } from '../types';

const STATUS_VARIANT: Record<SalesLeadStatus, 'default' | 'warning' | 'success' | 'danger' | 'muted'> = {
  new: 'default',
  contacted: 'warning',
  interested: 'warning',
  negotiating: 'default',
  converted: 'success',
  lost: 'danger',
};

export function SalesLeadsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<SalesLeadStatus | undefined>(undefined);
  const [convert, setConvert] = useState<{ lead: SalesLead; plan: SubscriptionPlan } | null>(null);
  const [editing, setEditing] = useState<SalesLead | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<SalesLead | null>(null);

  const del = useDeleteSalesLead();

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: 'next_follow_up_at',
      filter: { status: statusFilter },
    }),
    [page, debouncedSearch, statusFilter],
  );
  const { data, isLoading, isFetching } = useSalesLeads(queryParams);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('sales_leads.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('sales_leads.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('sales_leads.subtitle')}</p>
        </div>
        {can('sales_leads.create') && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('sales_leads.create')}
          </Button>
        )}
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
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as SalesLeadStatus | undefined); setPage(1); }}
          className="w-44"
        >
          <option value="">{t('sales_leads.filter_all_statuses')}</option>
          {SALES_LEAD_STATUSES.map((s) => <option key={s} value={s}>{t(`sales_leads.status.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('sales_leads.clinic_name')}</TableHead>
            <TableHead>{t('sales_leads.contact_name')}</TableHead>
            <TableHead>{t('sales_leads.phone')}</TableHead>
            <TableHead>{t('sales_leads.city')}</TableHead>
            <TableHead>{t('sales_leads.status_label')}</TableHead>
            <TableHead>{t('sales_leads.next_follow_up_at')}</TableHead>
            <TableHead>{t('sales_leads.assigned_to')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((lead) => {
              const canConvert = lead.status !== 'converted';
              return (
                <TableRow key={lead.id}>
                  <TableCell className="font-medium">{lead.clinic_name}</TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{lead.contact_name ?? '—'}</TableCell>
                  <TableCell dir="ltr">{lead.phone}</TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{lead.city?.name ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant={STATUS_VARIANT[lead.status]}>{t(`sales_leads.status.${lead.status}`)}</Badge>
                  </TableCell>
                  <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(lead.next_follow_up_at)}</TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{lead.assigned_admin?.name ?? '—'}</TableCell>
                  <TableCell className="text-end">
                    <div className="flex justify-end gap-1">
                      {canConvert && (
                        <>
                          <Button
                            variant="ghost"
                            size="icon"
                            title={t('sales_leads.actions.convert_to_basic')}
                            onClick={() => setConvert({ lead, plan: 'basic' })}
                          >
                            <ArrowRightCircle className="h-4 w-4 text-amber-600" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            title={t('sales_leads.actions.convert_to_premium')}
                            onClick={() => setConvert({ lead, plan: 'premium' })}
                          >
                            <Star className="h-4 w-4 text-emerald-600" />
                          </Button>
                        </>
                      )}
                      {can('sales_leads.update') && (
                        <Button variant="ghost" size="icon" onClick={() => setEditing(lead)} aria-label={t('common.edit')}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                      )}
                      {can('sales_leads.delete') && (
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => setDeleting(lead)}
                          aria-label={t('common.delete')}
                          className="text-[var(--color-destructive)]"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              );
            })
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

      {convert && (
        <ConvertDialog lead={convert.lead} plan={convert.plan} onClose={() => setConvert(null)} />
      )}

      <Dialog
        open={creating || editing !== null}
        onOpenChange={(open) => { if (!open) { setCreating(false); setEditing(null); } }}
      >
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editing ? t('sales_leads.edit') : t('sales_leads.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('sales_leads.subtitle')}</DialogDescription>
          </DialogHeader>
          <SalesLeadForm
            lead={editing}
            onSuccess={() => { setCreating(false); setEditing(null); }}
            onCancel={() => { setCreating(false); setEditing(null); }}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('sales_leads.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('sales_leads.delete_confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
