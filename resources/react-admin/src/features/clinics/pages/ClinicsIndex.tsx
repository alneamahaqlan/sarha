import { useMemo, useState } from 'react';
import { RotateCcw, Search, Star } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useCityLookup } from '@/features/lookups/hooks';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useClinics, useRestoreClinic } from '../hooks';
import { ClinicActionsMenu } from '../components/ClinicActions';
import { ClinicPlanBadge, ClinicStatusBadge } from '../components/ClinicBadges';
import { CLINIC_PLANS, CLINIC_STATUSES, type ClinicPlan, type ClinicStatus } from '../types';
import type { TrashedFilter } from '../api/clinics.api';

export function ClinicsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<ClinicStatus | undefined>();
  const [planFilter, setPlanFilter] = useState<ClinicPlan | undefined>();
  const [cityFilter, setCityFilter] = useState<number | undefined>();
  const [trashedFilter, setTrashedFilter] = useState<TrashedFilter>('without');

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

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinics.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.subtitle')}</p>
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
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as ClinicStatus | undefined); setPage(1); }}
          className="w-40"
        >
          <option value="">{t('clinics.filter_all_statuses')}</option>
          {CLINIC_STATUSES.map((s) => <option key={s} value={s}>{t(`clinics.status.${s}`)}</option>)}
        </Select>
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
          onChange={(e) => { setTrashedFilter(e.target.value as TrashedFilter); setPage(1); }}
          className="w-40"
        >
          <option value="without">{t('clinics.trashed.without')}</option>
          <option value="with">{t('clinics.trashed.with')}</option>
          <option value="only">{t('clinics.trashed.only')}</option>
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinics.logo')}</TableHead>
            <TableHead>{t('clinics.name')}</TableHead>
            <TableHead>{t('clinics.city')}</TableHead>
            <TableHead>{t('clinics.phone')}</TableHead>
            <TableHead>{t('clinics.status_label')}</TableHead>
            <TableHead>{t('clinics.plan_label')}</TableHead>
            <TableHead>{t('clinics.subscription_ends_at')}</TableHead>
            <TableHead>{t('clinics.featured')}</TableHead>
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
            data.data.map((clinic) => (
              <TableRow key={clinic.id}>
                <TableCell>
                  {clinic.logo ? (
                    <img
                      src={`/storage/${clinic.logo}`}
                      alt=""
                      className="h-9 w-9 rounded-full object-cover"
                      onError={(e) => { (e.currentTarget as HTMLImageElement).style.visibility = 'hidden'; }}
                    />
                  ) : (
                    <div className="h-9 w-9 rounded-full bg-[var(--color-muted)]" />
                  )}
                </TableCell>
                <TableCell className="font-medium">
                  {clinic.name}
                  {clinic.is_trashed && <Badge variant="danger" className="ms-2">{t('clinics.trashed_badge')}</Badge>}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{clinic.city?.name ?? '—'}</TableCell>
                <TableCell dir="ltr">{clinic.phone}</TableCell>
                <TableCell><ClinicStatusBadge status={clinic.status} /></TableCell>
                <TableCell><ClinicPlanBadge plan={clinic.subscription_type} /></TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">
                  {fmtDate(clinic.subscription_ends_at)}
                </TableCell>
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
                    <ClinicActionsMenu clinic={clinic} />
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
    </div>
  );
}
