import { useState } from 'react';
import { toast } from 'sonner';
import { Award, Plus, RefreshCw, Pencil, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge as UiBadge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useBadges, useDeleteBadge, useRecomputeBadges, useUpdateBadge } from '../hooks';
import { BadgeChip } from '../components/BadgeChip';
import { BadgeEditDialog } from '../components/BadgeEditDialog';
import type { Badge } from '../types';

export function BadgesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: badges, isLoading } = useBadges();
  const del = useDeleteBadge();
  const recompute = useRecomputeBadges();

  const [editing, setEditing] = useState<Badge | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Badge | null>(null);

  const onRecompute = async () => {
    try {
      const summary = await recompute.mutateAsync();
      const total = Object.values(summary).reduce((a, b) => a + b, 0);
      toast.success(t('badges.recomputed', { count: total }));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const onDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('badges.deleted'));
      setDeleting(null);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-semibold">
            <Award className="h-6 w-6" /> {t('badges.title')}
          </h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('badges.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={onRecompute} disabled={recompute.isPending}>
            <RefreshCw className={`h-4 w-4 ${recompute.isPending ? 'animate-spin' : ''}`} />
            {t('badges.recompute_now')}
          </Button>
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> {t('badges.new')}
          </Button>
        </div>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('badges.col_badge')}</TableHead>
              <TableHead>{t('badges.col_mode')}</TableHead>
              <TableHead>{t('badges.col_placement')}</TableHead>
              <TableHead>{t('badges.col_assigned')}</TableHead>
              <TableHead>{t('badges.is_active')}</TableHead>
              <TableHead className="text-end">{t('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
            ) : !badges?.length ? (
              <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('badges.empty')}</TableCell></TableRow>
            ) : (
              badges.map((b) => (
                <TableRow key={b.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <BadgeChip icon={b.icon} color={b.color} label={locale === 'en' ? b.label_en : b.label_ar} />
                      <span className="font-mono text-xs text-[var(--color-muted-foreground)]">{b.key}</span>
                    </div>
                  </TableCell>
                  <TableCell>
                    {b.mode === 'auto'
                      ? <UiBadge variant="info">{t('badges.mode_auto')} · {t(`badges.rule_name.${b.rule_key}`, b.rule_key ?? '')}</UiBadge>
                      : <UiBadge variant="muted">{t('badges.mode_manual')}</UiBadge>}
                  </TableCell>
                  <TableCell className="text-sm">{t(`badges.placement_${b.placement}`)}</TableCell>
                  <TableCell className="text-sm font-semibold">{b.assigned_count}</TableCell>
                  <TableCell>
                    <ActiveToggle badge={b} />
                  </TableCell>
                  <TableCell className="text-end">
                    <div className="inline-flex gap-1">
                      <Button variant="ghost" size="icon" onClick={() => setEditing(b)}><Pencil className="h-4 w-4" /></Button>
                      <Button variant="ghost" size="icon" onClick={() => setDeleting(b)}><Trash2 className="h-4 w-4 text-red-600" /></Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <BadgeEditDialog open={creating || editing !== null} badge={editing} onClose={() => { setCreating(false); setEditing(null); }} />

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('badges.delete_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('badges.delete_confirm', { name: deleting ? (locale === 'en' ? deleting.label_en : deleting.label_ar) : '' })}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={onDelete} disabled={del.isPending}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

/** Inline active toggle — persists immediately via update. */
function ActiveToggle({ badge }: { badge: Badge }) {
  const update = useUpdateBadge(badge.id);
  const { t } = useTranslation();
  return (
    <Switch
      checked={badge.is_active}
      disabled={update.isPending}
      onCheckedChange={async (checked) => {
        try {
          await update.mutateAsync({
            key: badge.key, label_ar: badge.label_ar, label_en: badge.label_en,
            icon: badge.icon, color: badge.color, placement: badge.placement,
            mode: badge.mode, rule_key: badge.rule_key, rule_params: badge.rule_params,
            is_active: checked, sort_order: badge.sort_order,
          });
        } catch (e) {
          toast.error(extractMessage(e, t('errors.generic')));
        }
      }}
    />
  );
}
