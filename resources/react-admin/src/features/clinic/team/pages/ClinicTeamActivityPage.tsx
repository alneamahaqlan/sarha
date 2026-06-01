import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Activity, Calendar, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { useAuth } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { RoleBadge } from '../components/RoleBadge';
import { useClinicTeam, useClinicTeamActivity } from '../hooks';
import type { ActivityFilters, ActivityPeriod, ClinicActivityLog } from '../types';

/**
 * Owner-only activity feed. Supports filters by member, event family,
 * date period, and free text. Paginated by the backend (30 per page).
 *
 * Drill-down support: navigating from the team table to
 * `/clinic/team-activity?actor_type=member&actor_id=X` pre-fills the
 * filter so the owner sees that single person's history.
 */
export function ClinicTeamActivityPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { user } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const { data: members = [] } = useClinicTeam();

  // Initial filters from query string (drill-down support).
  const initialActorId = searchParams.get('actor_id');
  const initialActorType = searchParams.get('actor_type') as ActivityFilters['actor_type'] | null;

  const [filters, setFilters] = useState<ActivityFilters>({
    actor_type: initialActorType ?? undefined,
    actor_id: initialActorId ? Number(initialActorId) : undefined,
    period: 'month',
  });
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  const effectiveFilters = useMemo<ActivityFilters>(() => ({
    ...filters,
    q: search.trim() || undefined,
    page,
  }), [filters, search, page]);

  const { data, isLoading } = useClinicTeamActivity(effectiveFilters);

  const setActor = (val: string) => {
    if (val === '') {
      const { actor_id, actor_type, ...rest } = filters;
      setFilters({ ...rest });
      searchParams.delete('actor_id'); searchParams.delete('actor_type');
      setSearchParams(searchParams);
    } else if (val === 'owner') {
      // The owner row is the clinic itself; we send its id from the auth payload.
      setFilters({ ...filters, actor_type: 'owner', actor_id: user?.user.id });
    } else {
      setFilters({ ...filters, actor_type: 'member', actor_id: Number(val) });
    }
    setPage(1);
  };

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', {
      dateStyle: 'short', timeStyle: 'short',
    }) : '—';

  const labelFor = (log: ClinicActivityLog) => {
    // Use a translated string keyed by the action; fall back to the
    // raw action slug so unknown new events don't blank out.
    return t(`clinic_team_activity.action.${log.action}`, {
      defaultValue: log.action,
      ...log.summary,
    });
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl sm:text-2xl font-semibold flex items-center gap-2">
            <Activity className="h-5 w-5" />
            {t('clinic_team_activity.title')}
          </h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_team_activity.subtitle')}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="rounded-md border border-[var(--color-border)] bg-white p-3">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative">
            <Search className="absolute start-2 top-2.5 h-4 w-4 text-[var(--color-muted-foreground)]" />
            <Input
              placeholder={t('clinic_team_activity.search_placeholder')}
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              className="ps-8 w-56"
            />
          </div>

          <Select
            value={
              filters.actor_type === 'owner' ? 'owner'
              : filters.actor_type === 'member' && filters.actor_id ? String(filters.actor_id)
              : ''
            }
            onChange={(e) => setActor(e.target.value)}
            aria-label={t('clinic_team_activity.filter_actor')}
          >
            <option value="">{t('clinic_team_activity.filter_actor_all')}</option>
            <option value="owner">{t('clinic_team_activity.filter_actor_owner')}</option>
            {members.map((m) => (
              <option key={m.id} value={m.id}>{m.name}</option>
            ))}
          </Select>

          <Select
            value={filters.period ?? ''}
            onChange={(e) => { setFilters({ ...filters, period: e.target.value as ActivityPeriod || undefined }); setPage(1); }}
            aria-label={t('clinic_team_activity.filter_period')}
          >
            <option value="">{t('clinic_team_activity.period_all')}</option>
            <option value="today">{t('clinic_team_activity.period_today')}</option>
            <option value="week">{t('clinic_team_activity.period_week')}</option>
            <option value="month">{t('clinic_team_activity.period_month')}</option>
          </Select>
        </div>
      </div>

      {/* Table */}
      <div className="rounded-md border border-[var(--color-border)] bg-white overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="hidden sm:table-cell w-44">{t('clinic_team_activity.table.time')}</TableHead>
              <TableHead>{t('clinic_team_activity.table.actor')}</TableHead>
              <TableHead>{t('clinic_team_activity.table.event')}</TableHead>
              <TableHead className="hidden md:table-cell">{t('clinic_team_activity.table.summary')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading && (
              <TableRow>
                <TableCell colSpan={4} className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">
                  {t('common.loading')}
                </TableCell>
              </TableRow>
            )}
            {!isLoading && (data?.data ?? []).length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">
                  {t('clinic_team_activity.empty')}
                </TableCell>
              </TableRow>
            )}
            {(data?.data ?? []).map((log) => (
              <TableRow key={log.id}>
                <TableCell className="hidden sm:table-cell text-xs text-[var(--color-muted-foreground)]">
                  <div className="flex items-center gap-1.5">
                    <Calendar className="h-3 w-3" />
                    {fmtDate(log.created_at)}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex flex-col">
                    <span className="font-medium text-sm flex items-center gap-1.5">
                      {log.actor_name}
                      {log.actor_role && <RoleBadge role={log.actor_role} color={log.actor_color} inactive={log.actor_removed} />}
                    </span>
                    {/* mobile: show timestamp inline since the cell is hidden */}
                    <span className="sm:hidden text-[10px] text-[var(--color-muted-foreground)]">{fmtDate(log.created_at)}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline" className="font-normal text-[10px]">
                    {t(`clinic_team_activity.event_kind.${log.action.split('.')[0]}`, { defaultValue: log.action.split('.')[0] })}
                  </Badge>
                  <div className="text-sm mt-1">{labelFor(log)}</div>
                </TableCell>
                <TableCell className="hidden md:table-cell text-xs text-[var(--color-muted-foreground)]">
                  {Object.entries(log.summary)
                    .filter(([k]) => !['fields'].includes(k))
                    .slice(0, 3)
                    .map(([k, v]) => `${k}: ${String(v)}`).join(' · ')}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {data && data.meta.last_page > 1 && (
        <div className="flex justify-between items-center text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {t('clinic_team_activity.page_of', { current: data.meta.current_page, total: data.meta.last_page })}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>
              {locale === 'ar' ? '›' : '‹'}
            </Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>
              {locale === 'ar' ? '‹' : '›'}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
