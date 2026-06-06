import { useState } from 'react';
import { toast } from 'sonner';
import { useQuery } from '@tanstack/react-query';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient, extractMessage } from '@/lib/api-client';

import { useCreateReminder } from '../hooks';

interface AssigneeOption { type: string; id: number; name: string; role: string }

interface Props {
  open: boolean;
  onClose: () => void;
  /** Omit for a general team task (no customer). */
  customerId?: number | null;
  customerName?: string | null;
  bookingId?: number | null;
}

/** Format a Date into the local value a <input type="datetime-local"> expects. */
function toLocalInput(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Quick presets relative to now — saves the staff a manual date pick. */
function presets(): { key: string; at: Date }[] {
  const inHours = (h: number) => new Date(Date.now() + h * 3_600_000);
  const tomorrow = (hour: number) => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(hour, 0, 0, 0);
    return d;
  };
  return [
    { key: 'in_1h', at: inHours(1) },
    { key: 'in_3h', at: inHours(3) },
    { key: 'tomorrow_morning', at: tomorrow(9) },
    { key: 'next_week', at: new Date(Date.now() + 7 * 86_400_000) },
  ];
}

export function ReminderDialog({ open, onClose, customerId, customerName, bookingId }: Props) {
  const { t } = useTranslation();
  const mut = useCreateReminder();
  const isGeneral = !customerId;
  const [remindAt, setRemindAt] = useState('');
  const [title, setTitle] = useState('');
  const [note, setNote] = useState('');
  const [assigneeId, setAssigneeId] = useState('');

  // Team members for the optional assignee picker (owner row filtered out —
  // a reminder is assigned to a member, not the clinic itself).
  const { data: members } = useQuery({
    queryKey: ['clinic', 'bookings', 'assignees'],
    queryFn: async () => {
      const res = await apiClient.get<{ data: AssigneeOption[] }>('/clinic/bookings/assignees');
      return res.data.data.filter((a) => a.type === 'ClinicTeamMember');
    },
    staleTime: 60_000,
  });

  function reset() {
    setRemindAt('');
    setTitle('');
    setNote('');
    setAssigneeId('');
  }

  async function onSubmit() {
    if (isGeneral && !title.trim()) {
      toast.error(t('clinic_reminders.errors.no_title'));
      return;
    }
    if (!remindAt) {
      toast.error(t('clinic_reminders.errors.no_time'));
      return;
    }
    const when = new Date(remindAt);
    if (when.getTime() <= Date.now()) {
      toast.error(t('clinic_reminders.errors.past_time'));
      return;
    }
    try {
      await mut.mutateAsync({
        customer_id: customerId ?? null,
        title: title.trim() || null,
        booking_id: bookingId ?? null,
        assignee_member_id: assigneeId ? Number(assigneeId) : null,
        remind_at: when.toISOString(),
        note: note.trim() || null,
      });
      toast.success(t('clinic_reminders.created'));
      reset();
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {isGeneral ? t('clinic_reminders.dialog.title_task') : t('clinic_reminders.dialog.title')}
          </DialogTitle>
          <DialogDescription>
            {customerName
              ? t('clinic_reminders.dialog.subtitle_named', { name: customerName })
              : isGeneral
                ? t('clinic_reminders.dialog.subtitle_task')
                : t('clinic_reminders.dialog.subtitle')}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          {isGeneral && (
            <div className="space-y-1.5">
              <Label htmlFor="rem-title">{t('clinic_reminders.fields.task_title')}</Label>
              <Input
                id="rem-title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder={t('clinic_reminders.fields.task_title_placeholder')}
                maxLength={255}
              />
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="rem-at">{t('clinic_reminders.fields.remind_at')}</Label>
            <Input
              id="rem-at"
              type="datetime-local"
              value={remindAt}
              onChange={(e) => setRemindAt(e.target.value)}
            />
            <div className="flex flex-wrap gap-1.5 pt-1">
              {presets().map((p) => (
                <button
                  key={p.key}
                  type="button"
                  onClick={() => setRemindAt(toLocalInput(p.at))}
                  className="rounded-full border border-[var(--color-border)] px-2.5 py-1 text-xs hover:bg-[var(--color-muted)]"
                >
                  {t(`clinic_reminders.presets.${p.key}`)}
                </button>
              ))}
            </div>
          </div>

          {members && members.length > 0 && (
            <div className="space-y-1.5">
              <Label htmlFor="rem-assignee">{t('clinic_reminders.fields.assignee')}</Label>
              <Select id="rem-assignee" value={assigneeId} onChange={(e) => setAssigneeId(e.target.value)}>
                <option value="">{t('clinic_reminders.fields.assignee_none')}</option>
                {members.map((m) => (
                  <option key={m.id} value={m.id}>{m.name}</option>
                ))}
              </Select>
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="rem-note">{t('clinic_reminders.fields.note')}</Label>
            <Textarea
              id="rem-note"
              rows={2}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder={t('clinic_reminders.fields.note_placeholder')}
              maxLength={1000}
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending}>
            {mut.isPending ? t('common.loading') : t('clinic_reminders.dialog.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
