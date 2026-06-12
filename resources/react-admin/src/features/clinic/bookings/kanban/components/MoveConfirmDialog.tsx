import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';

export type MoveIntent =
  | { kind: 'cancel' }
  | { kind: 'completion' }     // moving to a completed-kind stage — pick attended vs no_show
  | { kind: 'backwards' };     // moving out of a terminal-kind stage — confirm only

export interface MoveResult {
  status: string;
  cancel_reason?: string;
  cancel_note?: string;
  completion_note?: string;
  outcome?: 'completed' | 'no_show';
}

interface Props {
  open: boolean;
  intent: MoveIntent;
  toName: string;
  onCancel: () => void;
  onConfirm: (r: MoveResult) => void;
}

const CANCEL_REASONS = ['patient_cancelled', 'no_answer', 'schedule_conflict', 'other'] as const;

export function MoveConfirmDialog({ open, intent, toName, onCancel, onConfirm }: Props) {
  const { t } = useTranslation();
  const [reason, setReason] = useState<(typeof CANCEL_REASONS)[number]>('patient_cancelled');
  const [note, setNote] = useState('');
  const [outcome, setOutcome] = useState<'completed' | 'no_show'>('completed');

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onCancel()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {intent.kind === 'cancel' && t('clinic_bookings_kanban.move.cancel_title')}
            {intent.kind === 'completion' && t('clinic_bookings_kanban.move.completion_title')}
            {intent.kind === 'backwards' && t('clinic_bookings_kanban.move.backwards_title')}
          </DialogTitle>
          <DialogDescription>
            {intent.kind === 'backwards'
              ? t('clinic_bookings_kanban.move.backwards_desc')
              : t('clinic_bookings_kanban.move.target_desc', { column: toName })}
          </DialogDescription>
        </DialogHeader>

        {intent.kind === 'cancel' && (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="reason">{t('clinic_bookings_kanban.move.cancel_reason')}</Label>
              <Select id="reason" value={reason} onChange={(e) => setReason(e.target.value as any)}>
                {CANCEL_REASONS.map((r) => (
                  <option key={r} value={r}>{t(`clinic_bookings_kanban.move.cancel_reason_${r}`)}</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="note">{t('clinic_bookings_kanban.move.note_optional')}</Label>
              <Textarea id="note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
        )}

        {intent.kind === 'completion' && (
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="outcome">{t('clinic_bookings_kanban.move.outcome')}</Label>
              <Select id="outcome" value={outcome} onChange={(e) => setOutcome(e.target.value as any)}>
                <option value="completed">{t('clinic_bookings_kanban.sub_badge.attended')}</option>
                <option value="no_show">{t('clinic_bookings_kanban.sub_badge.no_show')}</option>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="note">{t('clinic_bookings_kanban.move.note_optional')}</Label>
              <Textarea id="note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={onCancel}>{t('common.cancel')}</Button>
          <Button
            onClick={() => {
              if (intent.kind === 'cancel') {
                onConfirm({ status: 'cancelled', cancel_reason: reason, cancel_note: note || undefined });
              } else if (intent.kind === 'completion') {
                onConfirm({ status: outcome, completion_note: note || undefined, outcome });
              } else {
                // backwards: status decided by caller (KanbanBoard).
                onConfirm({ status: '__backwards__' });
              }
            }}
          >
            {t('common.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
