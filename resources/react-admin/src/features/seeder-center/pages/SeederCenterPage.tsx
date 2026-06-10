import { useEffect, useState } from 'react';
import { Database, Eye, EyeOff, Loader2, RotateCw, Trash2, TriangleAlert } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useBatchConflicts,
  useHideBatch,
  usePurgeBatch,
  useReseedBatch,
  useRunStatus,
  useSeederInventory,
  useUnhideBatch,
} from '../hooks';
import type { SeederBatch } from '../types';

export function SeederCenterPage() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: batches, isLoading } = useSeederInventory();

  const hide = useHideBatch();
  const unhide = useUnhideBatch();
  const reseed = useReseedBatch();
  const purge = usePurgeBatch();

  const [purgeTarget, setPurgeTarget] = useState<SeederBatch | null>(null);
  const [activeRunId, setActiveRunId] = useState<number | null>(null);

  const { data: conflicts, isFetching: conflictsLoading } = useBatchConflicts(purgeTarget?.key ?? null);
  const { data: run } = useRunStatus(activeRunId);

  // React to a heavy/async reseed finishing.
  useEffect(() => {
    if (!run) return;
    if (run.status === 'done') {
      toast.success(t('seeder_center.reseed_done', { count: run.rows_created ?? 0 }));
      setActiveRunId(null);
    } else if (run.status === 'failed') {
      toast.error(run.message || t('seeder_center.reseed_failed'));
      setActiveRunId(null);
    }
  }, [run, t]);

  const label = (b: SeederBatch) => (locale === 'ar' ? b.label_ar : b.label_en);
  const desc = (b: SeederBatch) => (locale === 'ar' ? b.desc_ar : b.desc_en);

  const statusBadge = (b: SeederBatch) => {
    if (b.total_rows === 0) return <Badge variant="muted">{t('seeder_center.status_empty')}</Badge>;
    if (b.hidden_rows === 0) return <Badge variant="default">{t('seeder_center.status_visible')}</Badge>;
    return <Badge variant="warning">{t('seeder_center.status_hidden')}</Badge>;
  };

  const onHide = async (b: SeederBatch) => {
    try {
      const r = await hide.mutateAsync(b.key);
      toast.success(t('seeder_center.hide_done', { count: r.hidden }));
    } catch (err) {
      toast.error(extractMessage(err));
    }
  };

  const onUnhide = async (b: SeederBatch) => {
    try {
      const r = await unhide.mutateAsync(b.key);
      toast.success(t('seeder_center.unhide_done', { count: r.restored }));
    } catch (err) {
      toast.error(extractMessage(err));
    }
  };

  const onReseed = async (b: SeederBatch) => {
    try {
      const r = await reseed.mutateAsync(b.key);
      if (r.status === 'done') {
        toast.success(t('seeder_center.reseed_done', { count: r.rows_created ?? 0 }));
      } else if (r.status === 'failed') {
        toast.error(r.message || t('seeder_center.reseed_failed'));
      } else {
        toast.message(t('seeder_center.reseed_running'));
        setActiveRunId(r.id);
      }
    } catch (err) {
      toast.error(extractMessage(err));
    }
  };

  const onConfirmPurge = async () => {
    if (!purgeTarget) return;
    const force = (conflicts?.length ?? 0) > 0;
    try {
      const r = await purge.mutateAsync({ batch: purgeTarget.key, force });
      toast.success(t('seeder_center.purge_done', { count: r.deleted }));
      setPurgeTarget(null);
    } catch (err) {
      toast.error(extractMessage(err));
    }
  };

  const busy = (b: SeederBatch) =>
    (hide.isPending && hide.variables === b.key) ||
    (unhide.isPending && unhide.variables === b.key) ||
    (reseed.isPending && reseed.variables === b.key) ||
    (activeRunId !== null && run?.batch === b.key);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <Database className="h-7 w-7 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('seeder_center.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('seeder_center.subtitle')}</p>
        </div>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)]/40 p-4 text-sm text-[var(--color-foreground)]">
        {t('seeder_center.intro')}
      </div>

      {isLoading ? (
        <div className="flex h-40 items-center justify-center">
          <Loader2 className="h-6 w-6 animate-spin text-[var(--color-primary)]" />
        </div>
      ) : !batches || batches.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('seeder_center.empty_state')}
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {batches.map((b) => (
            <div key={b.key} className="flex flex-col gap-3 rounded-lg border border-[var(--color-border)] bg-white p-4">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="truncate font-medium">{label(b)}</span>
                    {b.heavy && <Badge variant="muted">{t('seeder_center.heavy_badge')}</Badge>}
                  </div>
                  <div className="mt-1 text-xs text-[var(--color-muted-foreground)]">
                    {t('seeder_center.rows_count', { count: b.total_rows })}
                    {b.hidden_rows > 0 && <> · {t('seeder_center.hidden_count', { count: b.hidden_rows })}</>}
                  </div>
                </div>
                {statusBadge(b)}
              </div>

              <p className="text-xs leading-relaxed text-[var(--color-muted-foreground)]">{desc(b)}</p>

              <div className="mt-auto flex flex-wrap gap-2">
                {b.hideable && b.hidden_rows === 0 && b.total_rows > 0 && (
                  <Button variant="outline" size="sm" disabled={busy(b)} onClick={() => onHide(b)}>
                    <EyeOff className="me-1 h-3.5 w-3.5" /> {t('seeder_center.action_hide')}
                  </Button>
                )}
                {b.hidden_rows > 0 && (
                  <Button variant="default" size="sm" disabled={busy(b)} onClick={() => onUnhide(b)}>
                    <Eye className="me-1 h-3.5 w-3.5" /> {t('seeder_center.action_unhide')}
                  </Button>
                )}
                <Button variant="secondary" size="sm" disabled={busy(b)} onClick={() => onReseed(b)}>
                  {busy(b) ? <Loader2 className="me-1 h-3.5 w-3.5 animate-spin" /> : <RotateCw className="me-1 h-3.5 w-3.5" />}
                  {t('seeder_center.action_reseed')}
                </Button>
                {b.total_rows > 0 && (
                  <Button variant="destructive" size="sm" disabled={busy(b)} onClick={() => setPurgeTarget(b)}>
                    <Trash2 className="me-1 h-3.5 w-3.5" /> {t('seeder_center.action_purge')}
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <AlertDialog open={!!purgeTarget} onOpenChange={(open) => !open && setPurgeTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {(conflicts?.length ?? 0) > 0
                ? t('seeder_center.conflict_title')
                : t('seeder_center.purge_confirm_title')}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {(conflicts?.length ?? 0) > 0
                ? t('seeder_center.conflict_body')
                : t('seeder_center.purge_confirm_body')}
            </AlertDialogDescription>
          </AlertDialogHeader>

          {conflictsLoading && (
            <div className="flex items-center gap-2 text-sm text-[var(--color-muted-foreground)]">
              <Loader2 className="h-4 w-4 animate-spin" /> …
            </div>
          )}

          {(conflicts?.length ?? 0) > 0 && (
            <ul className="space-y-1 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
              {conflicts!.map((c, i) => (
                <li key={i} className="flex items-start gap-2">
                  <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                  <span>{t('seeder_center.conflict_row', { count: c.count, child: c.child, parent: c.parent })}</span>
                </li>
              ))}
            </ul>
          )}

          <AlertDialogFooter>
            <AlertDialogCancel>
              {(conflicts?.length ?? 0) > 0 ? t('seeder_center.conflict_cancel') : t('common.cancel')}
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={onConfirmPurge}
              disabled={purge.isPending || conflictsLoading}
              className="bg-[var(--color-destructive)] hover:opacity-90"
            >
              {purge.isPending && <Loader2 className="me-1 h-4 w-4 animate-spin" />}
              {(conflicts?.length ?? 0) > 0 ? t('seeder_center.conflict_force') : t('seeder_center.action_purge')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
