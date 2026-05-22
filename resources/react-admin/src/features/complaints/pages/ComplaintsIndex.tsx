import { useMemo, useState } from 'react';
import { Check, CheckCircle2, Eye, Megaphone, Search, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useComplaints } from '../hooks';
import {
  MarkInReviewDialog,
  NotifyClinicDialog,
  RejectDialog,
  ResolveDialog,
} from '../components/ActionDialogs';
import { ComplaintPriorityBadge, ComplaintStatusBadge } from '../components/ComplaintBadges';
import { COMPLAINT_PRIORITIES, COMPLAINT_STATUSES, COMPLAINT_TYPES, type Complaint, type ComplaintPriority, type ComplaintStatus, type ComplaintType } from '../types';

type ActionDialog =
  | { kind: 'mark_in_review'; complaint: Complaint }
  | { kind: 'resolve'; complaint: Complaint }
  | { kind: 'reject'; complaint: Complaint }
  | { kind: 'notify_clinic'; complaint: Complaint }
  | null;

export function ComplaintsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<ComplaintStatus | undefined>();
  const [typeFilter, setTypeFilter] = useState<ComplaintType | undefined>();
  const [priorityFilter, setPriorityFilter] = useState<ComplaintPriority | undefined>();
  const [actionDialog, setActionDialog] = useState<ActionDialog>(null);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: search.trim() || undefined,
      sort: '-created_at',
      filter: { status: statusFilter, type: typeFilter, priority: priorityFilter },
    }),
    [page, search, statusFilter, typeFilter, priorityFilter],
  );
  const { data, isLoading, isFetching } = useComplaints(queryParams);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('complaints.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('complaints.subtitle')}</p>
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
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as ComplaintStatus | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('complaints.filter_all_statuses')}</option>
          {COMPLAINT_STATUSES.map((s) => <option key={s} value={s}>{t(`complaints.status.${s}`)}</option>)}
        </Select>
        <Select
          value={typeFilter ?? ''}
          onChange={(e) => { setTypeFilter((e.target.value || undefined) as ComplaintType | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('complaints.filter_all_types')}</option>
          {COMPLAINT_TYPES.map((s) => <option key={s} value={s}>{t(`complaints.type.${s}`)}</option>)}
        </Select>
        <Select
          value={priorityFilter ?? ''}
          onChange={(e) => { setPriorityFilter((e.target.value || undefined) as ComplaintPriority | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('complaints.filter_all_priorities')}</option>
          {COMPLAINT_PRIORITIES.map((s) => <option key={s} value={s}>{t(`complaints.priority.${s}`)}</option>)}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('complaints.reference')}</TableHead>
            <TableHead>{t('complaints.customer_name')}</TableHead>
            <TableHead>{t('complaints.clinic')}</TableHead>
            <TableHead>{t('complaints.type_label')}</TableHead>
            <TableHead>{t('complaints.priority_label')}</TableHead>
            <TableHead>{t('complaints.status_label')}</TableHead>
            <TableHead>{t('complaints.notified')}</TableHead>
            <TableHead>{t('complaints.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={9} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={9} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((c) => (
              <TableRow key={c.id}>
                <TableCell>
                  <Badge variant="muted" className="font-mono">{c.reference_code}</Badge>
                </TableCell>
                <TableCell className="font-medium">{c.customer_name}</TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{c.clinic?.name ?? '—'}</TableCell>
                <TableCell>
                  <Badge variant="outline">{t(`complaints.type.${c.type}`)}</Badge>
                </TableCell>
                <TableCell><ComplaintPriorityBadge priority={c.priority} /></TableCell>
                <TableCell><ComplaintStatusBadge status={c.status} /></TableCell>
                <TableCell>
                  {c.clinic_notified ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <span className="text-[var(--color-muted-foreground)]">—</span>
                  )}
                </TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(c.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {c.status === 'new' && (
                      <Button
                        variant="ghost"
                        size="icon"
                        title={t('complaints.actions.mark_in_review')}
                        onClick={() => setActionDialog({ kind: 'mark_in_review', complaint: c })}
                      >
                        <Eye className="h-4 w-4 text-amber-600" />
                      </Button>
                    )}
                    {(c.status === 'new' || c.status === 'in_review') && (
                      <>
                        <Button
                          variant="ghost"
                          size="icon"
                          title={t('complaints.actions.resolve')}
                          onClick={() => setActionDialog({ kind: 'resolve', complaint: c })}
                        >
                          <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          title={t('complaints.actions.reject')}
                          onClick={() => setActionDialog({ kind: 'reject', complaint: c })}
                        >
                          <XCircle className="h-4 w-4 text-red-600" />
                        </Button>
                      </>
                    )}
                    {c.clinic_id && !c.clinic_notified && (
                      <Button
                        variant="ghost"
                        size="icon"
                        title={t('complaints.actions.notify_clinic')}
                        onClick={() => setActionDialog({ kind: 'notify_clinic', complaint: c })}
                      >
                        <Megaphone className="h-4 w-4 text-blue-600" />
                      </Button>
                    )}
                  </div>
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

      {actionDialog?.kind === 'mark_in_review' && (
        <MarkInReviewDialog complaint={actionDialog.complaint} onClose={() => setActionDialog(null)} />
      )}
      {actionDialog?.kind === 'resolve' && (
        <ResolveDialog complaint={actionDialog.complaint} onClose={() => setActionDialog(null)} />
      )}
      {actionDialog?.kind === 'reject' && (
        <RejectDialog complaint={actionDialog.complaint} onClose={() => setActionDialog(null)} />
      )}
      {actionDialog?.kind === 'notify_clinic' && (
        <NotifyClinicDialog complaint={actionDialog.complaint} onClose={() => setActionDialog(null)} />
      )}
    </div>
  );
}
