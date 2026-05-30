import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useUpdateBooking } from '../hooks';
import type { BookingDetail } from '../types';

interface Props {
  open: boolean;
  booking: BookingDetail;
  onClose: () => void;
}

function toLocal(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

export function EditBookingDialog({ open, booking, onClose }: Props) {
  const { t } = useTranslation();
  const [appt, setAppt] = useState(toLocal(booking.appointment_at));
  const [notes, setNotes] = useState(booking.clinic_notes ?? '');
  const [status, setStatus] = useState(booking.status);
  const mut = useUpdateBooking(booking.id);

  async function onSubmit() {
    try {
      await mut.mutateAsync({
        status,
        appointment_at: appt ? new Date(appt).toISOString() : null,
        clinic_notes: notes || null,
      });
      toast.success(t('clinic_bookings.updated'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.edit.title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="eb-status">{t('clinic_bookings.status_label')}</Label>
            <Select id="eb-status" value={status} onChange={(e) => setStatus(e.target.value as any)}>
              <option value="new">{t('bookings.status.new')}</option>
              <option value="contacted">{t('bookings.status.contacted')}</option>
              <option value="appointment_set">{t('bookings.status.appointment_set')}</option>
              <option value="completed">{t('bookings.status.completed')}</option>
              <option value="no_show">{t('bookings.status.no_show')}</option>
              <option value="cancelled">{t('bookings.status.cancelled')}</option>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="eb-appt">{t('clinic_bookings.appointment_at')}</Label>
            <Input id="eb-appt" type="datetime-local" value={appt} onChange={(e) => setAppt(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="eb-notes">{t('clinic_bookings.clinic_notes')}</Label>
            <Textarea id="eb-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending}>
            {mut.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
