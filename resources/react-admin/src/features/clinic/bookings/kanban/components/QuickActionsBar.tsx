import { useState } from 'react';
import { Phone, MessageCircle, Bell, CheckCircle2, XCircle, FileText } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useLogActivity } from '../hooks';
import type { QuickAction } from '../types';

interface Props {
  bookingId: number;
}

const ACTIONS: Array<{ key: QuickAction; icon: any; tone: string }> = [
  { key: 'call_attempted',           icon: Phone,         tone: 'border-sky-300 text-sky-700 hover:bg-sky-50' },
  { key: 'whatsapped',               icon: MessageCircle, tone: 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' },
  { key: 'reminder_sent',            icon: Bell,          tone: 'border-amber-300 text-amber-700 hover:bg-amber-50' },
  { key: 'patient_confirmed_verbally', icon: CheckCircle2, tone: 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' },
  { key: 'patient_cancelled_verbally', icon: XCircle,      tone: 'border-rose-300 text-rose-700 hover:bg-rose-50' },
  { key: 'note_added',               icon: FileText,      tone: 'border-slate-300 text-slate-700 hover:bg-slate-50' },
];

export function QuickActionsBar({ bookingId }: Props) {
  const { t } = useTranslation();
  const mut = useLogActivity(bookingId);
  const [callDialog, setCallDialog] = useState(false);
  const [outcome, setOutcome] = useState<'answered' | 'no_answer'>('answered');
  const [noteDialog, setNoteDialog] = useState(false);
  const [note, setNote] = useState('');

  async function fire(action: QuickAction, extra?: { outcome?: 'answered' | 'no_answer'; note?: string }) {
    try {
      await mut.mutateAsync({ action, ...extra });
      toast.success(t('clinic_bookings_kanban.quick.logged'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {ACTIONS.map(({ key, icon: Icon, tone }) => {
          const onClick = () => {
            if (key === 'call_attempted') return setCallDialog(true);
            if (key === 'note_added') return setNoteDialog(true);
            return fire(key);
          };
          return (
            <button
              key={key}
              type="button"
              onClick={onClick}
              disabled={mut.isPending}
              className={`flex items-center justify-center gap-1.5 rounded-md border px-2 py-2 text-xs font-medium transition disabled:opacity-50 ${tone}`}
            >
              <Icon className="h-3.5 w-3.5" />
              <span className="truncate">{t(`clinic_bookings_kanban.quick.${key}`)}</span>
            </button>
          );
        })}
      </div>

      <Dialog open={callDialog} onOpenChange={(o) => !o && setCallDialog(false)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('clinic_bookings_kanban.quick.call_attempted')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="outcome">{t('clinic_bookings_kanban.quick.outcome_label')}</Label>
            <Select id="outcome" value={outcome} onChange={(e) => setOutcome(e.target.value as any)}>
              <option value="answered">{t('clinic_bookings_kanban.quick.outcome_answered')}</option>
              <option value="no_answer">{t('clinic_bookings_kanban.quick.outcome_no_answer')}</option>
            </Select>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCallDialog(false)}>{t('common.cancel')}</Button>
            <Button onClick={async () => { await fire('call_attempted', { outcome }); setCallDialog(false); }}>{t('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={noteDialog} onOpenChange={(o) => !o && setNoteDialog(false)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('clinic_bookings_kanban.quick.note_added')}</DialogTitle>
          </DialogHeader>
          <Textarea rows={4} value={note} onChange={(e) => setNote(e.target.value)} placeholder={t('clinic_bookings_kanban.quick.note_placeholder')} />
          <DialogFooter>
            <Button variant="outline" onClick={() => setNoteDialog(false)}>{t('common.cancel')}</Button>
            <Button
              disabled={!note.trim()}
              onClick={async () => { await fire('note_added', { note: note.trim() }); setNote(''); setNoteDialog(false); }}
            >{t('common.save')}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
