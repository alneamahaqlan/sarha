import { useState } from 'react';
import { toast } from 'sonner';
import { Plus, Trash2, ArrowUp, ArrowDown, Check } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useBookingStages, useCreateStage, useUpdateStage, useDeleteStage, useReorderStages,
} from '../hooks';
import { STAGE_COLORS, STAGE_KINDS, type BookingStage, type StageColor, type StageKind } from '../types';

const COLOR_DOT: Record<StageColor, string> = {
  rose: 'bg-rose-400', amber: 'bg-amber-400', emerald: 'bg-emerald-400',
  sky: 'bg-sky-400', violet: 'bg-violet-400', slate: 'bg-slate-400',
};

/** Per-clinic Kanban stages: add, rename, recolour, reorder, delete. */
export function StagesManagerDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const { data: stages } = useBookingStages();
  const createMut = useCreateStage();
  const updateMut = useUpdateStage();
  const deleteMut = useDeleteStage();
  const reorderMut = useReorderStages();

  const [newName, setNewName] = useState('');
  const [newKind, setNewKind] = useState<StageKind>('new');
  const [newColor, setNewColor] = useState<StageColor>('sky');
  const [pendingDelete, setPendingDelete] = useState<BookingStage | null>(null);

  const list = stages ?? [];

  // Where a deleted stage's bookings land: the base (first remaining)
  // stage of the same kind. Mirrors Booking::scopeForStage() on the API.
  const deleteTarget = pendingDelete
    ? list.find((s) => s.kind === pendingDelete.kind && s.id !== pendingDelete.id)
    : undefined;

  async function onAdd() {
    const name = newName.trim();
    if (!name) return;
    try {
      await createMut.mutateAsync({ name, kind: newKind, color: newColor });
      setNewName('');
      toast.success(t('clinic_bookings_kanban.stages.added'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onSaveRow(s: BookingStage, patch: Partial<Pick<BookingStage, 'name' | 'kind' | 'color'>>) {
    try {
      await updateMut.mutateAsync({ id: s.id, name: s.name, kind: s.kind, color: s.color, ...patch });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function confirmDelete() {
    if (!pendingDelete) return;
    try {
      await deleteMut.mutateAsync(pendingDelete.id);
      toast.success(t('clinic_bookings_kanban.stages.deleted'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setPendingDelete(null);
    }
  }

  async function move(index: number, dir: -1 | 1) {
    const target = index + dir;
    if (target < 0 || target >= list.length) return;
    const ids = list.map((s) => s.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    try {
      await reorderMut.mutateAsync(ids);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-[640px]">
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.stages.title')}</DialogTitle>
          <DialogDescription>{t('clinic_bookings_kanban.stages.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="max-h-[50vh] space-y-2 overflow-y-auto pe-1">
          {list.map((s, i) => (
            <StageRow
              key={s.id}
              stage={s}
              isFirst={i === 0}
              isLast={i === list.length - 1}
              busy={updateMut.isPending || reorderMut.isPending || deleteMut.isPending}
              onSave={(patch) => onSaveRow(s, patch)}
              onDelete={() => setPendingDelete(s)}
              onUp={() => move(i, -1)}
              onDown={() => move(i, 1)}
            />
          ))}
        </div>

        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-3">
          <Label className="text-xs">{t('clinic_bookings_kanban.stages.add_new')}</Label>
          <div className="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-end">
            <div className="flex-1">
              <Input
                value={newName}
                maxLength={40}
                placeholder={t('clinic_bookings_kanban.stages.name_placeholder')}
                onChange={(e) => setNewName(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1 sm:max-w-[160px]">
              <span className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.stages.scope_label')}</span>
              <Select value={newKind} onChange={(e) => setNewKind(e.target.value as StageKind)}>
                {STAGE_KINDS.map((k) => (
                  <option key={k} value={k}>{t(`clinic_bookings_kanban.column.${k}`)}</option>
                ))}
              </Select>
            </div>
            <div className="flex flex-col gap-1 sm:max-w-[130px]">
              <span className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.stages.color_label')}</span>
              <Select value={newColor} onChange={(e) => setNewColor(e.target.value as StageColor)}>
                {STAGE_COLORS.map((c) => (
                  <option key={c} value={c}>{t(`clinic_bookings_kanban.tags.color_${c}`)}</option>
                ))}
              </Select>
            </div>
            <Button onClick={onAdd} disabled={!newName.trim() || createMut.isPending} className="gap-1 shrink-0">
              <Plus className="h-4 w-4" />
              {t('common.add')}
            </Button>
          </div>
          <p className="mt-2 text-[11px] text-[var(--color-muted-foreground)]">
            {t('clinic_bookings_kanban.stages.kind_hint')}
          </p>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.close')}</Button>
        </DialogFooter>
      </DialogContent>

      <AlertDialog open={!!pendingDelete} onOpenChange={(o) => !o && setPendingDelete(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {t('clinic_bookings_kanban.stages.delete_title', { name: pendingDelete?.name ?? '' })}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {deleteTarget
                ? t('clinic_bookings_kanban.stages.delete_desc', { target: deleteTarget.name })
                : t('clinic_bookings_kanban.stages.delete_desc_fallback')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete} disabled={deleteMut.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Dialog>
  );
}

function StageRow({
  stage, isFirst, isLast, busy, onSave, onDelete, onUp, onDown,
}: {
  stage: BookingStage;
  isFirst: boolean;
  isLast: boolean;
  busy: boolean;
  onSave: (patch: Partial<Pick<BookingStage, 'name' | 'kind' | 'color'>>) => void;
  onDelete: () => void;
  onUp: () => void;
  onDown: () => void;
}) {
  const { t } = useTranslation();
  const [name, setName] = useState(stage.name);
  const [kind, setKind] = useState<StageKind>(stage.kind);
  const [color, setColor] = useState<StageColor>(stage.color);
  const dirty = name.trim() !== stage.name || kind !== stage.kind || color !== stage.color;

  return (
    <div className="flex items-center gap-2 rounded-md border border-[var(--color-border)] bg-white p-2">
      <span className={`h-3 w-3 shrink-0 rounded-full ${COLOR_DOT[color]}`} />
      <Input
        value={name}
        maxLength={40}
        onChange={(e) => setName(e.target.value)}
        disabled={stage.is_default}
        title={stage.is_default ? t('clinic_bookings_kanban.stages.base_name_locked') : undefined}
        className="flex-1"
      />
      {stage.is_default && (
        <span className="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">
          {t('clinic_bookings_kanban.stages.base_badge')}
        </span>
      )}
      <Select
        value={kind}
        onChange={(e) => setKind(e.target.value as StageKind)}
        disabled={stage.is_default}
        title={stage.is_default ? t('clinic_bookings_kanban.stages.base_kind_locked') : undefined}
        className="max-w-[140px]"
      >
        {STAGE_KINDS.map((k) => (
          <option key={k} value={k}>{t(`clinic_bookings_kanban.column.${k}`)}</option>
        ))}
      </Select>
      <Select value={color} onChange={(e) => setColor(e.target.value as StageColor)} className="max-w-[120px]">
        {STAGE_COLORS.map((c) => (
          <option key={c} value={c}>{t(`clinic_bookings_kanban.tags.color_${c}`)}</option>
        ))}
      </Select>
      <Button
        size="sm" variant="ghost" className="h-8 w-8 p-0"
        disabled={!dirty || !name.trim() || busy}
        onClick={() => onSave({ name: name.trim(), kind, color })}
        title={t('common.save')}
      >
        <Check className="h-4 w-4 text-emerald-600" />
      </Button>
      <div className="flex flex-col">
        <Button size="sm" variant="ghost" className="h-4 w-6 p-0" disabled={isFirst || busy} onClick={onUp}>
          <ArrowUp className="h-3 w-3" />
        </Button>
        <Button size="sm" variant="ghost" className="h-4 w-6 p-0" disabled={isLast || busy} onClick={onDown}>
          <ArrowDown className="h-3 w-3" />
        </Button>
      </div>
      <Button
        size="sm" variant="ghost" className="h-8 w-8 p-0"
        disabled={busy || stage.is_default}
        onClick={onDelete}
        title={stage.is_default ? t('clinic_bookings_kanban.stages.base_protected') : t('common.delete')}
      >
        <Trash2 className="h-4 w-4 text-rose-600" />
      </Button>
    </div>
  );
}
