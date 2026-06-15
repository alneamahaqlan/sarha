import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ExternalLink, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useLandingPages, useDeleteLandingPage } from '../hooks';
import { LandingPageForm } from '../components/LandingPageForm';
import { useLandingScope } from '../scope';
import { LANDING_APPROVAL_STATUSES, LANDING_STATUSES, LANDING_TYPES, type LandingPage } from '../types';

const STATUS_VARIANT: Record<string, 'success' | 'muted' | 'warning'> = {
  published: 'success',
  draft: 'muted',
  archived: 'warning',
};

const APPROVAL_VARIANT: Record<string, 'success' | 'muted' | 'warning' | 'danger'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  draft: 'muted',
};

export function LandingPagesIndex() {
  const { t } = useTranslation();
  const { can } = useAuth();
  const navigate = useNavigate();
  const { scope, basePath, canCreate, canDelete } = useLandingScope();
  const isClinic = scope === 'clinic';

  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [approvalFilter, setApprovalFilter] = useState('');
  const [page, setPage] = useState(1);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<LandingPage | null>(null);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 30,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: isClinic
        ? { approval_status: approvalFilter || undefined }
        : { type: typeFilter || undefined, status: statusFilter || undefined },
    }),
    [page, debouncedSearch, typeFilter, statusFilter, approvalFilter, isClinic],
  );
  const { data, isLoading, isFetching } = useLandingPages(queryParams);
  const del = useDeleteLandingPage();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('landing_pages.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('landing_pages.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('landing_pages.subtitle')}</p>
        </div>
        {can(canCreate) && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('landing_pages.create')}
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input className="ps-9" placeholder={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
        {isClinic ? (
          <Select value={approvalFilter} onChange={(e) => { setApprovalFilter(e.target.value); setPage(1); }} className="w-44">
            <option value="">{t('landing_pages.all_approval')}</option>
            {LANDING_APPROVAL_STATUSES.map((s) => <option key={s} value={s}>{t(`landing_pages.approval.${s}`)}</option>)}
          </Select>
        ) : (
          <>
            <Select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} className="w-44">
              <option value="">{t('landing_pages.all_types')}</option>
              {LANDING_TYPES.map((tp) => <option key={tp} value={tp}>{t(`landing_pages.types.${tp}`)}</option>)}
            </Select>
            <Select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="w-44">
              <option value="">{t('landing_pages.all_statuses')}</option>
              {LANDING_STATUSES.map((s) => <option key={s} value={s}>{t(`landing_pages.statuses.${s}`)}</option>)}
            </Select>
          </>
        )}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('landing_pages.page_title')}</TableHead>
            <TableHead>{isClinic ? t('landing_pages.approval_status') : t('landing_pages.type')}</TableHead>
            <TableHead>{t('landing_pages.slug')}</TableHead>
            <TableHead>{t('landing_pages.status')}</TableHead>
            <TableHead className="text-end">{t('landing_pages.views')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((row) => (
              <TableRow key={row.id} className="cursor-pointer" onClick={() => navigate(`${basePath}/${row.id}`)}>
                <TableCell className="font-medium">{row.internal_name || row.title_ar || `#${row.id}`}</TableCell>
                <TableCell>
                  {isClinic
                    ? <Badge variant={APPROVAL_VARIANT[row.approval_status ?? 'draft']}>{t(`landing_pages.approval.${row.approval_status ?? 'draft'}`)}</Badge>
                    : t(`landing_pages.types.${row.type}`)}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]" dir="ltr">
                  <a href={`/l/${row.slug}`} target="_blank" rel="noopener" className="inline-flex items-center gap-1 hover:underline" onClick={(e) => e.stopPropagation()}>
                    /l/{row.slug}<ExternalLink className="h-3 w-3" />
                  </a>
                </TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[row.status]}>{t(`landing_pages.statuses.${row.status}`)}</Badge></TableCell>
                <TableCell className="text-end tabular-nums">{row.total_views.toLocaleString('ar-SA-u-nu-latn')}</TableCell>
                <TableCell className="text-end" onClick={(e) => e.stopPropagation()}>
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => navigate(`${basePath}/${row.id}`)} aria-label={t('common.edit')}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    {can(canDelete) && (
                      <Button variant="ghost" size="icon" className="text-[var(--color-destructive)]" onClick={() => setDeleting(row)} aria-label={t('common.delete')}>
                        <Trash2 className="h-4 w-4" />
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
          <span className="text-[var(--color-muted-foreground)]">{data.meta.from}–{data.meta.to} / {data.meta.total}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>{t('common.back')}</Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>›</Button>
          </div>
        </div>
      )}

      <Dialog open={creating} onOpenChange={(open) => !open && setCreating(false)}>
        <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('landing_pages.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('landing_pages.subtitle')}</DialogDescription>
          </DialogHeader>
          <LandingPageForm
            onSuccess={(saved) => { setCreating(false); navigate(`${basePath}/${saved.id}`); }}
            onCancel={() => setCreating(false)}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('landing_pages.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('landing_pages.delete_confirm_body')}</AlertDialogDescription>
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
