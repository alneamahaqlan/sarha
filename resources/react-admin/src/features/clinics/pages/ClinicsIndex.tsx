import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { BarChart3, FileSpreadsheet, ListTree, Pencil, Plus, RotateCcw, Search, Star, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useAdminNavBadges } from '@/features/nav-badges/hooks';
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { useCityLookup } from '@/features/lookups/hooks';
import { extractMessage } from '@/lib/api-client';
import { assetUrl } from '@/lib/assets';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useClinic, useClinics, useRestoreClinic, useBulkClinic, useImportClinicsSheet } from '../hooks';
import { ClinicActionsMenu } from '../components/ClinicActions';
import { ClinicPlanBadge, ClinicStatusBadge } from '../components/ClinicBadges';
import { ClinicForm } from '../components/ClinicForm';
import { CLINIC_PLANS, CLINIC_STATUSES, type Clinic, type ClinicPlan, type ClinicStatus } from '../types';
import type { TrashedFilter } from '../api/clinics.api';

type BulkAction = 'delete' | 'restore' | 'force_delete';

export function ClinicsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { data: navBadges } = useAdminNavBadges();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const initialStatus = searchParams.get('status');
  const [statusFilter, setStatusFilter] = useState<ClinicStatus | undefined>(
    initialStatus && (CLINIC_STATUSES as string[]).includes(initialStatus)
      ? (initialStatus as ClinicStatus)
      : undefined,
  );

  // Allow deep-links / the header quick-stat (e.g. /admin/clinics?status=pending)
  // to drive the active tab even when the page is already mounted.
  useEffect(() => {
    const s = searchParams.get('status');
    if (s && (CLINIC_STATUSES as string[]).includes(s)) {
      setStatusFilter(s as ClinicStatus);
      setPage(1);
    }
  }, [searchParams]);
  const [planFilter, setPlanFilter] = useState<ClinicPlan | undefined>();
  const [cityFilter, setCityFilter] = useState<number | undefined>();
  const [trashedFilter, setTrashedFilter] = useState<TrashedFilter>('without');
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
  const [pendingBulk, setPendingBulk] = useState<BulkAction | null>(null);
  const [creating, setCreating] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [importing, setImporting] = useState(false);
  const [sheetUrl, setSheetUrl] = useState('');
  const importSheet = useImportClinicsSheet();

  const { data: editingClinic } = useClinic(editingId ?? undefined);

  // Server query only fires after 300ms of input idle to avoid hammering on every keystroke.
  const debouncedSearch = useDebouncedValue(search, 300);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: { status: statusFilter, subscription_type: planFilter, city_id: cityFilter, trashed: trashedFilter },
    }),
    [page, debouncedSearch, statusFilter, planFilter, cityFilter, trashedFilter],
  );
  const { data, isLoading, isFetching } = useClinics(queryParams);
  const { data: cities } = useCityLookup();
  const restore = useRestoreClinic();
  const bulk = useBulkClinic();

  const pageRows = data?.data ?? [];
  const pageIds = pageRows.map((c) => c.id);
  const allSelectedOnPage = pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));
  const someSelectedOnPage = pageIds.some((id) => selectedIds.has(id));
  const selectedCount = selectedIds.size;

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  // Whole days until the subscription ends — negative once expired. Drives the
  // colour-coded "days remaining" column (green > 10, amber ≤ 10, red expired).
  const daysRemaining = (iso: string | null): number | null =>
    iso ? Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000) : null;

  const renderDaysRemaining = (iso: string | null) => {
    const d = daysRemaining(iso);
    if (d === null) return <span className="text-[var(--color-muted-foreground)]">—</span>;
    if (d < 0) return <Badge variant="danger">{t('clinics.expired')}</Badge>;
    return <Badge variant={d <= 10 ? 'warning' : 'success'}>{t('clinics.days_count', { count: d })}</Badge>;
  };

  const toggleRow = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const togglePage = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (allSelectedOnPage) pageIds.forEach((id) => next.delete(id));
      else pageIds.forEach((id) => next.add(id));
      return next;
    });
  };

  const clearSelection = () => setSelectedIds(new Set());

  const runImportSheet = async () => {
    const url = sheetUrl.trim();
    if (!url) return;
    try {
      const summary = await importSheet.mutateAsync(url);
      toast.success(t('clinics.import_sheet.done', summary));
      setImporting(false);
      setSheetUrl('');
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const runBulk = async () => {
    if (!pendingBulk || selectedIds.size === 0) return;
    const action = pendingBulk;
    try {
      const res = await bulk.mutateAsync({ action, ids: Array.from(selectedIds) });
      toast.success(t('clinics.bulk.done', { count: res.affected }));
      clearSelection();
      setPendingBulk(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinics.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.subtitle')}</p>
        </div>
        {can('clinics.create') && (
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={() => setImporting(true)}>
              <FileSpreadsheet className="h-4 w-4" />
              {t('clinics.import_sheet.button')}
            </Button>
            <Button onClick={() => setCreating(true)}>
              <Plus className="h-4 w-4" />
              {t('clinics.create')}
            </Button>
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-1 border-b border-[var(--color-border)]">
        {([undefined, ...CLINIC_STATUSES] as (ClinicStatus | undefined)[]).map((tab) => {
          const active = statusFilter === tab;
          const showCount = tab === 'pending' && (navBadges?.clinics_pending ?? 0) > 0;
          return (
            <button
              key={tab ?? 'all'}
              type="button"
              onClick={() => { setStatusFilter(tab); setPage(1); }}
              className={cn(
                '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                active
                  ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                  : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]',
              )}
            >
              {tab ? t(`clinics.status.${tab}`) : t('clinics.filter_all_statuses')}
              {showCount && (
                <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[var(--color-destructive)] px-1.5 text-xs font-medium text-white">
                  {(navBadges?.clinics_pending ?? 0) > 99 ? '99+' : navBadges?.clinics_pending}
                </span>
              )}
            </button>
          );
        })}
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
          value={planFilter ?? ''}
          onChange={(e) => { setPlanFilter((e.target.value || undefined) as ClinicPlan | undefined); setPage(1); }}
          className="w-36"
        >
          <option value="">{t('clinics.filter_all_plans')}</option>
          {CLINIC_PLANS.map((p) => <option key={p} value={p}>{t(`clinics.plan.${p}`)}</option>)}
        </Select>
        <Select
          value={cityFilter ?? ''}
          onChange={(e) => { setCityFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('clinics.filter_all_cities')}</option>
          {cities?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>
        <Select
          value={trashedFilter}
          onChange={(e) => { setTrashedFilter(e.target.value as TrashedFilter); setPage(1); clearSelection(); }}
          className="w-40"
        >
          <option value="without">{t('clinics.trashed.without')}</option>
          <option value="with">{t('clinics.trashed.with')}</option>
          <option value="only">{t('clinics.trashed.only')}</option>
        </Select>
      </div>

      {selectedCount > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-[var(--color-primary)] bg-[color-mix(in_oklab,var(--color-primary),white_90%)] px-3 py-2 text-sm">
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="icon" onClick={clearSelection} aria-label={t('common.clear')}>
              <X className="h-4 w-4" />
            </Button>
            <span>{t('common.selected', { count: selectedCount })}</span>
          </div>
          <div className="flex flex-wrap gap-2">
            {trashedFilter !== 'only' && can('clinics.delete') && (
              <Button variant="destructive" size="sm" onClick={() => setPendingBulk('delete')} disabled={bulk.isPending}>
                <Trash2 className="h-3.5 w-3.5" />
                {t('clinics.bulk.delete')}
              </Button>
            )}
            {trashedFilter !== 'without' && can('clinics.restore') && (
              <Button variant="outline" size="sm" onClick={() => setPendingBulk('restore')} disabled={bulk.isPending}>
                <RotateCcw className="h-3.5 w-3.5" />
                {t('clinics.bulk.restore')}
              </Button>
            )}
            {trashedFilter !== 'without' && can('clinics.forceDelete') && (
              <Button variant="destructive" size="sm" onClick={() => setPendingBulk('force_delete')} disabled={bulk.isPending}>
                <Trash2 className="h-3.5 w-3.5" />
                {t('clinics.bulk.force_delete')}
              </Button>
            )}
          </div>
        </div>
      )}

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-10">
              <input
                type="checkbox"
                aria-label={t('common.selected', { count: selectedCount })}
                checked={allSelectedOnPage}
                ref={(el) => { if (el) el.indeterminate = !allSelectedOnPage && someSelectedOnPage; }}
                onChange={togglePage}
                className="h-4 w-4 cursor-pointer"
              />
            </TableHead>
            <TableHead>{t('clinics.logo')}</TableHead>
            <TableHead>{t('clinics.name')}</TableHead>
            <TableHead>{t('clinics.city')}</TableHead>
            <TableHead>{t('clinics.phone')}</TableHead>
            <TableHead>{t('clinics.status_label')}</TableHead>
            <TableHead>{t('clinics.plan_label')}</TableHead>
            <TableHead>{t('clinics.subscription_ends_at')}</TableHead>
            <TableHead>{t('clinics.days_remaining')}</TableHead>
            <TableHead>{t('clinics.visits_30d')}</TableHead>
            <TableHead>{t('clinics.total_bookings')}</TableHead>
            <TableHead>{t('clinics.featured')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={13} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={13} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((clinic) => (
              <TableRow key={clinic.id} data-selected={selectedIds.has(clinic.id) || undefined} className="data-[selected]:bg-[color-mix(in_oklab,var(--color-primary),white_95%)]">
                <TableCell>
                  <input
                    type="checkbox"
                    aria-label={t('common.selected', { count: 1 })}
                    checked={selectedIds.has(clinic.id)}
                    onChange={() => toggleRow(clinic.id)}
                    className="h-4 w-4 cursor-pointer"
                  />
                </TableCell>
                <TableCell>
                  {clinic.logo ? (
                    <img
                      src={assetUrl(clinic.logo) ?? undefined}
                      alt=""
                      className="h-9 w-9 rounded-full object-cover"
                      onError={(e) => { (e.currentTarget as HTMLImageElement).style.visibility = 'hidden'; }}
                    />
                  ) : (
                    <div className="h-9 w-9 rounded-full bg-[var(--color-muted)]" />
                  )}
                </TableCell>
                <TableCell className="font-medium">
                  {can('clinics.viewAny') && !clinic.is_trashed ? (
                    <button
                      type="button"
                      onClick={() => navigate(`/admin/clinics/${clinic.id}/stats`)}
                      className="text-start hover:text-[var(--color-primary)] hover:underline"
                      title={t('clinics.stats.title')}
                    >
                      {clinic.name}
                    </button>
                  ) : (
                    clinic.name
                  )}
                  {clinic.is_trashed && <Badge variant="danger" className="ms-2">{t('clinics.trashed_badge')}</Badge>}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{clinic.city?.name ?? '—'}</TableCell>
                <TableCell dir="ltr">{clinic.phone}</TableCell>
                <TableCell><ClinicStatusBadge status={clinic.status} /></TableCell>
                <TableCell><ClinicPlanBadge plan={clinic.subscription_type} /></TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">
                  {fmtDate(clinic.subscription_ends_at)}
                </TableCell>
                <TableCell>{renderDaysRemaining(clinic.subscription_ends_at)}</TableCell>
                <TableCell className="text-sm">{clinic.visits_30d ?? 0}</TableCell>
                <TableCell className="text-sm">{clinic.bookings_count ?? 0}</TableCell>
                <TableCell>
                  {clinic.is_featured && <Star className="h-4 w-4 fill-amber-400 text-amber-500" />}
                </TableCell>
                <TableCell className="text-end">
                  {clinic.is_trashed ? (
                    <Button
                      variant="ghost"
                      size="icon"
                      aria-label={t('clinics.actions.restore')}
                      disabled={restore.isPending}
                      onClick={async () => {
                        try {
                          await restore.mutateAsync(clinic.id);
                          toast.success(t('clinics.actions.restored'));
                        } catch (err) {
                          toast.error(extractMessage(err, t('errors.generic')));
                        }
                      }}
                    >
                      <RotateCcw className="h-4 w-4" />
                    </Button>
                  ) : (
                    <div className="flex justify-end gap-1">
                      {can('clinics.viewAny') && (
                        <>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => navigate(`/admin/clinics/${clinic.id}/structure`)}
                            aria-label={t('clinics.structure.title')}
                            title={t('clinics.structure.title')}
                          >
                            <ListTree className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => navigate(`/admin/clinics/${clinic.id}/stats`)}
                            aria-label={t('clinics.stats.title')}
                          >
                            <BarChart3 className="h-4 w-4" />
                          </Button>
                        </>
                      )}
                      {can('clinics.update') && (
                        <Button variant="ghost" size="icon" onClick={() => setEditingId(clinic.id)} aria-label={t('common.edit')}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                      )}
                      <ClinicActionsMenu clinic={clinic} />
                    </div>
                  )}
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

      <Dialog
        open={creating || editingId !== null}
        onOpenChange={(open) => {
          if (!open) { setCreating(false); setEditingId(null); }
        }}
      >
        <DialogContent className="max-h-[90vh] overflow-y-auto max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editingId ? t('clinics.edit') : t('clinics.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('clinics.subtitle')}</DialogDescription>
          </DialogHeader>
          <ClinicForm
            clinic={editingId ? editingClinic ?? null : null}
            onSuccess={() => { setCreating(false); setEditingId(null); }}
            onCancel={() => { setCreating(false); setEditingId(null); }}
          />
        </DialogContent>
      </Dialog>

      <Dialog
        open={importing}
        onOpenChange={(open) => {
          if (!open) { setImporting(false); setSheetUrl(''); }
        }}
      >
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('clinics.import_sheet.title')}</DialogTitle>
            <DialogDescription>{t('clinics.import_sheet.description')}</DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <Input
              type="url"
              dir="ltr"
              placeholder="https://docs.google.com/spreadsheets/d/..."
              value={sheetUrl}
              onChange={(e) => setSheetUrl(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') runImportSheet(); }}
            />
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinics.import_sheet.hint')}</p>
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => { setImporting(false); setSheetUrl(''); }}>
                {t('common.cancel')}
              </Button>
              <Button onClick={runImportSheet} disabled={importSheet.isPending || !sheetUrl.trim()}>
                {importSheet.isPending ? t('common.loading') : t('clinics.import_sheet.submit')}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <AlertDialog open={pendingBulk !== null} onOpenChange={(open) => !open && setPendingBulk(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{pendingBulk ? t(`clinics.bulk.confirm_title_${pendingBulk}`) : ''}</AlertDialogTitle>
            <AlertDialogDescription>
              {pendingBulk ? t(`clinics.bulk.confirm_body_${pendingBulk}`, { count: selectedCount }) : ''}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={runBulk} disabled={bulk.isPending}>
              {t('common.confirm')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
