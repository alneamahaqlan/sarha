import { useState } from 'react';
import { toast } from 'sonner';
import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { bookingKanbanApi } from '../api';
import { useBookingStages } from '../hooks';
import { KANBAN_COLUMNS, type KanbanFilters } from '../types';

interface Props {
  filters: KanbanFilters;
  onClose: () => void;
}

/** Exports bookings to a CSV (Excel-compatible) with scope/stage/order options. */
export function ExportDialog({ filters, onClose }: Props) {
  const { t } = useTranslation();
  const { data: stages } = useBookingStages();
  const [scope, setScope] = useState<'filtered' | 'all'>('filtered');
  const [stageMode, setStageMode] = useState<'all' | 'specific'>('all');
  const [columns, setColumns] = useState<string[]>([]);
  const [order, setOrder] = useState<'desc' | 'asc'>('desc');
  const [busy, setBusy] = useState(false);

  const toggleColumn = (col: string) =>
    setColumns((prev) => (prev.includes(col) ? prev.filter((c) => c !== col) : [...prev, col]));

  const onExport = async () => {
    setBusy(true);
    try {
      await bookingKanbanApi.exportCsv(scope === 'all' ? {} : filters, {
        columns: stageMode === 'specific' ? columns : undefined,
        order,
      });
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setBusy(false);
    }
  };

  const disabled = busy || (stageMode === 'specific' && columns.length === 0);

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.export.title')}</DialogTitle>
          <DialogDescription>{t('clinic_bookings_kanban.export.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('clinic_bookings_kanban.export.scope')}</Label>
            <Select value={scope} onChange={(e) => setScope(e.target.value as 'filtered' | 'all')}>
              <option value="filtered">{t('clinic_bookings_kanban.export.scope_filtered')}</option>
              <option value="all">{t('clinic_bookings_kanban.export.scope_all')}</option>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>{t('clinic_bookings_kanban.export.stages')}</Label>
            <Select value={stageMode} onChange={(e) => setStageMode(e.target.value as 'all' | 'specific')}>
              <option value="all">{t('clinic_bookings_kanban.export.stages_all')}</option>
              <option value="specific">{t('clinic_bookings_kanban.export.stages_specific')}</option>
            </Select>
            {stageMode === 'specific' && (
              <div className="mt-1.5 grid grid-cols-2 gap-1.5">
                {KANBAN_COLUMNS.map((col) => (
                  <label key={col} className="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--color-border)] p-2 text-sm">
                    <input type="checkbox" checked={columns.includes(col)} onChange={() => toggleColumn(col)} className="h-4 w-4" />
                    <span>{stages?.[col]?.trim() || t(`clinic_bookings_kanban.column.${col}`)}</span>
                  </label>
                ))}
              </div>
            )}
          </div>

          <div className="space-y-1.5">
            <Label>{t('clinic_bookings_kanban.export.order')}</Label>
            <Select value={order} onChange={(e) => setOrder(e.target.value as 'desc' | 'asc')}>
              <option value="desc">{t('clinic_bookings_kanban.export.order_newest')}</option>
              <option value="asc">{t('clinic_bookings_kanban.export.order_oldest')}</option>
            </Select>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={busy}>{t('common.cancel')}</Button>
          <Button type="button" onClick={onExport} disabled={disabled} className="gap-1.5">
            <Download className="h-4 w-4" />
            {busy ? t('common.loading') : t('clinic_bookings_kanban.export.cta')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
