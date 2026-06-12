import { useMemo, useState } from 'react';
import { Pencil, Plus, RotateCcw, Search, XCircle } from 'lucide-react';
import { toast } from 'sonner';

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
import { Money } from '@/lib/money';
import { useAuth } from '@/app/providers/AuthProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { cn } from '@/lib/utils';
import { extractMessage } from '@/lib/api-client';
import { useAdminNavBadges } from '@/features/nav-badges/hooks';
import { usePackages } from '@/features/subscription-packages/hooks';

import { useCancelSubscription, useRenewSubscription, useSubscriptions } from '../hooks';
import { SubscriptionForm } from '../components/SubscriptionForm';
import { type Subscription, type SubscriptionStatus } from '../types';

const STATUS_VARIANT: Record<SubscriptionStatus, 'success' | 'danger' | 'warning' | 'muted'> = {
  active: 'success',
  expired: 'danger',
  cancelled: 'danger',
  pending_payment: 'warning',
};

/**
 * Maps the package's abstract color token to a Tailwind Badge variant
 * so the package chip on each row matches the palette the cards page uses.
 */
const PACKAGE_VARIANT: Record<string, 'muted' | 'success' | 'gold' | 'ai' | 'info' | 'warning'> = {
  gray:    'muted',
  sage:    'success',
  gold:    'gold',
  plum:    'ai',
  info:    'info',
  warning: 'warning',
};

type TabKey = 'all' | 'active' | 'expiring' | 'expired' | 'pending_payment';

export function SubscriptionsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [packageFilter, setPackageFilter] = useState<number | undefined>();
  const [tab, setTab] = useState<TabKey>('all');
  const [editing, setEditing] = useState<Subscription | null>(null);
  const [creating, setCreating] = useState(false);
  const [cancelling, setCancelling] = useState<Subscription | null>(null);

  const { data: packages } = usePackages();
  const { data: navBadges } = useAdminNavBadges();
  const renew = useRenewSubscription();
  const cancel = useCancelSubscription();

  // Tab → server filters. `expiring` is a custom flag the controller
  // handles; the other tabs map straight to the status filter.
  const queryParams = useMemo(() => {
    const filter: Record<string, unknown> = { subscription_package_id: packageFilter };
    if (tab === 'expiring') filter.expiring = true;
    else if (tab !== 'all') filter.status = tab;
    return {
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter,
    };
  }, [page, debouncedSearch, packageFilter, tab]);

  const { data, isLoading, isFetching } = useSubscriptions(queryParams);

  const rowTint = (sub: Subscription): string => {
    if (sub.status === 'expired' || sub.status === 'cancelled') return 'bg-red-50/40';
    if (sub.status === 'active' && sub.ends_at) {
      const days = Math.ceil((new Date(sub.ends_at).getTime() - Date.now()) / 86_400_000);
      if (days >= 0 && days <= 10) return 'bg-amber-50/40';
    }
    return '';
  };

  const fmtDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  const handleRenew = async (sub: Subscription) => {
    try {
      await renew.mutateAsync(sub.id);
      toast.success(t('subscriptions.renewed'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const handleCancel = async () => {
    if (!cancelling) return;
    try {
      await cancel.mutateAsync(cancelling.id);
      toast.success(t('subscriptions.cancelled'));
      setCancelling(null);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
      setCancelling(null);
    }
  };

  const TABS: { key: TabKey; label: string; badge?: number }[] = [
    { key: 'all', label: t('subscriptions.tab_all') },
    { key: 'active', label: t('subscriptions.status.active') },
    { key: 'expiring', label: t('subscriptions.expiring_soon'), badge: navBadges?.subscriptions_expiring },
    { key: 'pending_payment', label: t('subscriptions.status.pending_payment') },
    { key: 'expired', label: t('subscriptions.status.expired') },
  ];

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-xl sm:text-2xl font-semibold">{t('subscriptions.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('subscriptions.subtitle')}</p>
        </div>
        {can('subscriptions.create') && (
          <Button onClick={() => setCreating(true)} className="self-start sm:self-auto">
            <Plus className="h-4 w-4" />
            {t('subscriptions.create')}
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-1 border-b border-[var(--color-border)]">
        {TABS.map((tabDef) => (
          <button
            key={tabDef.key}
            type="button"
            onClick={() => { setTab(tabDef.key); setPage(1); }}
            className={cn(
              '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
              tab === tabDef.key
                ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]',
            )}
          >
            {tabDef.label}
            {tabDef.badge && tabDef.badge > 0 ? (
              <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-medium text-white">
                {tabDef.badge > 99 ? '99+' : tabDef.badge}
              </span>
            ) : null}
          </button>
        ))}
      </div>

      <div className="flex flex-col gap-2 md:flex-row md:items-center">
        <div className="relative w-full md:max-w-sm md:flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select
          value={packageFilter ?? ''}
          onChange={(e) => { setPackageFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
          className="w-44"
        >
          <option value="">{t('subscriptions.filter_all_packages')}</option>
          {packages?.map((p) => (
            <option key={p.id} value={p.id}>{locale === 'ar' ? p.name_ar : p.name_en}</option>
          ))}
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('subscriptions.clinic')}</TableHead>
            <TableHead>{t('subscriptions.package_label')}</TableHead>
            <TableHead className="hidden md:table-cell">{t('subscriptions.cycle_label')}</TableHead>
            <TableHead>{t('subscriptions.amount')}</TableHead>
            <TableHead className="hidden lg:table-cell">{t('subscriptions.starts_at')}</TableHead>
            <TableHead>{t('subscriptions.ends_at')}</TableHead>
            <TableHead>{t('subscriptions.status_label')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((sub) => {
              const pkgVariant = sub.package ? PACKAGE_VARIANT[sub.package.color_token] ?? 'muted' : 'muted';
              const pkgName = sub.package ? (locale === 'ar' ? sub.package.name_ar : sub.package.name_en) : '—';
              return (
                <TableRow key={sub.id} className={rowTint(sub)}>
                  <TableCell className="font-medium">{sub.clinic?.name ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant={pkgVariant}>{pkgName}</Badge>
                  </TableCell>
                  <TableCell className="hidden md:table-cell">
                    {sub.billing_cycle ? t(`subscriptions.cycle.${sub.billing_cycle}`) : '—'}
                    {sub.bonus_months > 0 && (
                      <span className="ms-1 text-[10px] text-emerald-600">+{sub.bonus_months}m</span>
                    )}
                  </TableCell>
                  <TableCell className="font-medium"><Money value={sub.amount} locale={locale} /></TableCell>
                  <TableCell className="hidden lg:table-cell text-xs text-[var(--color-muted-foreground)]">{fmtDate(sub.starts_at)}</TableCell>
                  <TableCell className="text-xs">
                    <div>{fmtDate(sub.ends_at)}</div>
                    {/* Only show countdown for genuinely-active rows that
                        haven't passed their ends_at yet. A row with
                        status='active' AND days<0 is a stale pre-cron
                        state — surface it via the badge below, not a
                        contradictory countdown line. */}
                    {sub.days_remaining !== null && sub.status === 'active' && sub.days_remaining >= 0 && (
                      <div className={cn('text-[10px]', sub.days_remaining <= 10 ? 'text-amber-700' : 'text-[var(--color-muted-foreground)]')}>
                        {t('subscriptions.days_remaining', { count: sub.days_remaining })}
                      </div>
                    )}
                    {sub.status !== 'active' && sub.days_remaining !== null && sub.days_remaining < 0 && (
                      <div className="text-[10px] text-red-600">
                        {t('subscriptions.expired_days_ago', { count: -sub.days_remaining })}
                      </div>
                    )}
                  </TableCell>
                  <TableCell>
                    {/* Visual truth: an "active" row whose ends_at is in
                        the past is effectively expired until the daily
                        lifecycle cron flips its status. Render it as
                        expired so the admin doesn't see green + "expired
                        N days ago" together. */}
                    {(() => {
                      const effective = sub.status === 'active' && sub.days_remaining !== null && sub.days_remaining < 0
                        ? 'expired'
                        : sub.status;
                      return <Badge variant={STATUS_VARIANT[effective]}>{t(`subscriptions.status.${effective}`)}</Badge>;
                    })()}
                  </TableCell>
                  <TableCell className="text-end">
                    <div className="flex justify-end gap-0.5">
                      {can('subscriptions.update') && (
                        <>
                          <Button
                            variant="ghost" size="icon"
                            onClick={() => handleRenew(sub)}
                            disabled={renew.isPending || sub.status === 'cancelled'}
                            aria-label={t('subscriptions.renew')}
                            title={t('subscriptions.renew')}
                          >
                            <RotateCcw className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            variant="ghost" size="icon"
                            onClick={() => setCancelling(sub)}
                            disabled={sub.status === 'cancelled' || sub.status === 'expired'}
                            aria-label={t('subscriptions.cancel_sub')}
                            title={t('subscriptions.cancel_sub')}
                            className="text-[var(--color-destructive)]"
                          >
                            <XCircle className="h-3.5 w-3.5" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => setEditing(sub)} aria-label={t('common.edit')}>
                            <Pencil className="h-3.5 w-3.5" />
                          </Button>
                        </>
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
          <span className="text-[var(--color-muted-foreground)]">{data.meta.from}–{data.meta.to} / {data.meta.total}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
              ›
            </Button>
          </div>
        </div>
      )}

      <Dialog open={creating || editing !== null} onOpenChange={(o) => { if (!o) { setCreating(false); setEditing(null); } }}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing ? t('subscriptions.edit') : t('subscriptions.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('subscriptions.subtitle')}</DialogDescription>
          </DialogHeader>
          <SubscriptionForm
            subscription={editing}
            onSuccess={() => { setCreating(false); setEditing(null); }}
            onCancel={() => { setCreating(false); setEditing(null); }}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={cancelling !== null} onOpenChange={(o) => !o && setCancelling(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('subscriptions.cancel_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('subscriptions.cancel_confirm_body', { clinic: cancelling?.clinic?.name ?? '' })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleCancel} disabled={cancel.isPending}>{t('subscriptions.cancel_sub')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
