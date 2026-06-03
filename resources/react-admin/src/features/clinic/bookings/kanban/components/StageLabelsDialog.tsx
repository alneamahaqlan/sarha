import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useBookingStages, useUpdateBookingStages } from '../hooks';
import { KANBAN_COLUMNS, type StageLabels } from '../types';

/** Lets the clinic rename the 4 Kanban stage columns. */
export function StageLabelsDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const { data: stages } = useBookingStages();
  const update = useUpdateBookingStages();
  const [labels, setLabels] = useState<StageLabels>(() => stages ?? {});

  const setLabel = (col: string, value: string) => setLabels((prev) => ({ ...prev, [col]: value }));

  const onSave = async () => {
    try {
      await update.mutateAsync(labels);
      toast.success(t('clinic_bookings_kanban.stages.saved'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.stages.title')}</DialogTitle>
          <DialogDescription>{t('clinic_bookings_kanban.stages.subtitle')}</DialogDescription>
        </DialogHeader>
        <div className="space-y-3">
          {KANBAN_COLUMNS.map((col) => (
            <div key={col} className="space-y-1.5">
              <Label htmlFor={`stage-${col}`}>{t(`clinic_bookings_kanban.column.${col}`)}</Label>
              <Input
                id={`stage-${col}`}
                value={labels[col] ?? ''}
                maxLength={40}
                onChange={(e) => setLabel(col, e.target.value)}
                placeholder={t(`clinic_bookings_kanban.column.${col}`)}
              />
            </div>
          ))}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={update.isPending}>{t('common.cancel')}</Button>
          <Button type="button" onClick={onSave} disabled={update.isPending}>
            {update.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
