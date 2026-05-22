import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useClinicLookup } from '@/features/lookups/hooks';

import { bookingFormSchema, type BookingFormSchema } from '../schemas/booking.schema';
import { useCreateBooking, useUpdateBooking } from '../hooks';
import { BOOKING_STATUSES, type Booking } from '../types';

interface Props {
  booking?: Booking | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function toDateTimeLocal(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function BookingForm({ booking, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: clinics } = useClinicLookup();

  const form = useForm<BookingFormSchema>({
    resolver: zodResolver(bookingFormSchema),
    defaultValues: {
      clinic_id: booking?.clinic_id ?? 0,
      customer_name: booking?.customer_name ?? '',
      customer_phone: booking?.customer_phone ?? '',
      service_id: booking?.service_id ?? null,
      status: booking?.status ?? 'new',
      appointment_at: toDateTimeLocal(booking?.appointment_at) || null,
      notes: booking?.notes ?? '',
      clinic_notes: booking?.clinic_notes ?? '',
    },
  });

  useEffect(() => {
    form.reset({
      clinic_id: booking?.clinic_id ?? 0,
      customer_name: booking?.customer_name ?? '',
      customer_phone: booking?.customer_phone ?? '',
      service_id: booking?.service_id ?? null,
      status: booking?.status ?? 'new',
      appointment_at: toDateTimeLocal(booking?.appointment_at) || null,
      notes: booking?.notes ?? '',
      clinic_notes: booking?.clinic_notes ?? '',
    });
  }, [booking, form]);

  const create = useCreateBooking();
  const update = useUpdateBooking(booking?.id ?? 0);

  const onSubmit = async (values: BookingFormSchema) => {
    try {
      const payload = {
        ...values,
        appointment_at: values.appointment_at ? new Date(values.appointment_at).toISOString() : null,
      };
      if (booking) {
        await update.mutateAsync(payload);
        toast.success(t('bookings.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('bookings.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof BookingFormSchema, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_id">{t('bookings.clinic')}</Label>
          <Select id="clinic_id" {...form.register('clinic_id', { valueAsNumber: true })}>
            <option value={0}>—</option>
            {clinics?.map((c) => (
              <option key={c.id} value={c.id}>{c.name}</option>
            ))}
          </Select>
          {form.formState.errors.clinic_id && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.clinic_id.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="customer_name">{t('bookings.customer_name')}</Label>
          <Input id="customer_name" {...form.register('customer_name')} />
          {form.formState.errors.customer_name && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.customer_name.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="customer_phone">{t('bookings.customer_phone')}</Label>
          <Input id="customer_phone" type="tel" dir="ltr" {...form.register('customer_phone')} />
          {form.formState.errors.customer_phone && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.customer_phone.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="status">{t('bookings.status_label')}</Label>
          <Select id="status" {...form.register('status')}>
            {BOOKING_STATUSES.map((s) => (
              <option key={s} value={s}>{t(`bookings.status.${s}`)}</option>
            ))}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="appointment_at">{t('bookings.appointment_at')}</Label>
          <Input id="appointment_at" type="datetime-local" {...form.register('appointment_at')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="notes">{t('bookings.notes')}</Label>
          <Textarea id="notes" rows={2} {...form.register('notes')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_notes">{t('bookings.clinic_notes')}</Label>
          <Textarea id="clinic_notes" rows={2} {...form.register('clinic_notes')} />
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? t('common.loading') : t('common.save')}
        </Button>
      </DialogFooter>
    </form>
  );
}
