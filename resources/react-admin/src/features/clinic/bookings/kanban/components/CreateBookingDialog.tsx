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
import { extractMessage } from '@/lib/api-client';
import { clinicServicesApi } from '@/features/clinic/services/api';

import { useAssignees, useCreateBooking } from '../hooks';
import { ACQUISITION_SOURCES, type CreateBookingInput } from '../types';

interface Props {
  open: boolean;
  onClose: () => void;
}

const EMPTY: CreateBookingInput = {
  customer_name: '',
  customer_phone: '',
  service_id: null,
  appointment_at: null,
  notes: null,
  clinic_notes: null,
  status: 'new',
  acquisition_source: null,
  assignee_type: null,
  assignee_id: null,
};

export function CreateBookingDialog({ open, onClose }: Props) {
  const { t } = useTranslation();
  const [v, setV] = useState<CreateBookingInput>(EMPTY);
  const mut = useCreateBooking();
  const { data: assignees } = useAssignees();
  const { data: services } = useQuery({
    queryKey: ['clinic', 'services', 'list', { per_page: 100 }],
    queryFn: () => clinicServicesApi.list({ per_page: 100 }),
    staleTime: 60_000,
  });

  function patch<K extends keyof CreateBookingInput>(key: K, value: CreateBookingInput[K]) {
    setV((prev) => ({ ...prev, [key]: value }));
  }

  // Same Saudi-mobile policy enforced by the backend + the public flows.
  const phone = v.customer_phone.trim();
  const phoneValid = /^05\d{8}$/.test(phone);
  const phoneError = phone.length > 0 && !phoneValid;

  async function onSubmit() {
    if (!v.customer_name.trim() || !phone) {
      toast.error(t('clinic_bookings_kanban.create.missing_fields'));
      return;
    }
    if (!phoneValid) {
      toast.error(t('clinic_bookings_kanban.create.phone_invalid'));
      return;
    }
    try {
      const payload: CreateBookingInput = {
        ...v,
        customer_name: v.customer_name.trim(),
        customer_phone: v.customer_phone.trim(),
        appointment_at: v.appointment_at ? new Date(v.appointment_at).toISOString() : null,
        notes: v.notes?.trim() || null,
        clinic_notes: v.clinic_notes?.trim() || null,
      };
      await mut.mutateAsync(payload);
      toast.success(t('clinic_bookings_kanban.create.success'));
      setV(EMPTY);
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings_kanban.create.title')}</DialogTitle>
          <DialogDescription>{t('clinic_bookings_kanban.create.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="cb-name">{t('clinic_bookings.customer_name')}</Label>
            <Input id="cb-name" value={v.customer_name} onChange={(e) => patch('customer_name', e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cb-phone">{t('clinic_bookings.customer_phone')}</Label>
            <Input
              id="cb-phone"
              value={v.customer_phone}
              onChange={(e) => patch('customer_phone', e.target.value)}
              dir="ltr"
              inputMode="numeric"
              placeholder="05XXXXXXXX"
              aria-invalid={phoneError}
              className={phoneError ? 'border-rose-400 focus-visible:ring-rose-400' : undefined}
            />
            <p className={`text-[11px] ${phoneError ? 'text-rose-600' : 'text-[var(--color-muted-foreground)]'}`}>
              {phoneError
                ? t('clinic_bookings_kanban.create.phone_invalid')
                : t('clinic_bookings_kanban.create.phone_hint')}
            </p>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="cb-service">{t('clinic_bookings.service')}</Label>
            <Select id="cb-service" value={v.service_id ? String(v.service_id) : ''} onChange={(e) => patch('service_id', e.target.value ? Number(e.target.value) : null)}>
              <option value="">—</option>
              {(services?.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cb-appt">{t('clinic_bookings.appointment_at')}</Label>
            <Input
              id="cb-appt"
              type="datetime-local"
              value={v.appointment_at ?? ''}
              onChange={(e) => patch('appointment_at', e.target.value || null)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cb-status">{t('clinic_bookings.status_label')}</Label>
            <Select id="cb-status" value={v.status ?? 'new'} onChange={(e) => patch('status', e.target.value as any)}>
              <option value="new">{t('bookings.status.new')}</option>
              <option value="contacted">{t('bookings.status.contacted')}</option>
              <option value="appointment_set">{t('bookings.status.appointment_set')}</option>
            </Select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="cb-source">{t('clinic_bookings_kanban.source.label')}</Label>
            <Select
              id="cb-source"
              value={v.acquisition_source ?? ''}
              onChange={(e) => patch('acquisition_source', (e.target.value || null) as any)}
            >
              <option value="">{t('clinic_bookings_kanban.source.unset')}</option>
              {ACQUISITION_SOURCES.filter((s) => s !== 'other').map((s) => (
                <option key={s} value={s}>{t(`clinic_bookings_kanban.source.opt.${s}`)}</option>
              ))}
            </Select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="cb-assignee">{t('clinic_bookings_kanban.assignee.label')}</Label>
            <Select
              id="cb-assignee"
              value={v.assignee_id ? `${v.assignee_type}:${v.assignee_id}` : ''}
              onChange={(e) => {
                if (!e.target.value) {
                  patch('assignee_type', null); patch('assignee_id', null); return;
                }
                const [type, id] = e.target.value.split(':');
                patch('assignee_type', type as any); patch('assignee_id', Number(id));
              }}
            >
              <option value="">{t('clinic_bookings_kanban.card.unassigned')}</option>
              {(assignees ?? []).map((a) => (
                <option key={`${a.type}:${a.id}`} value={`${a.type}:${a.id}`}>{a.name}</option>
              ))}
            </Select>
          </div>
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="cb-notes">{t('clinic_bookings.clinic_notes')}</Label>
            <Textarea id="cb-notes" rows={2} value={v.clinic_notes ?? ''} onChange={(e) => patch('clinic_notes', e.target.value)} />
          </div>
        </div>

        <p className="text-xs text-[var(--color-muted-foreground)]">
          {t('clinic_bookings_kanban.create.audit_hint')}
        </p>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending || phoneError}>
            {mut.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
