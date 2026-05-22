import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useClinicLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useCreateSubscription, useUpdateSubscription } from '../hooks';
import {
  SUBSCRIPTION_STATUSES, SUBSCRIPTION_TYPES,
  type Subscription, type SubscriptionStatus, type SubscriptionType,
} from '../types';

const schema = z.object({
  clinic_id: z.coerce.number().int().positive(),
  type: z.enum(['basic', 'premium']),
  amount: z.coerce.number().min(0),
  starts_at: z.string().min(1),
  ends_at: z.string().min(1),
  status: z.enum(['active', 'expired', 'cancelled', 'pending_payment']),
  moyasar_payment_id: z.string().nullish(),
  notes: z.string().nullish(),
});
type FormValues = z.infer<typeof schema>;

interface Props {
  subscription?: Subscription | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function toLocal(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

export function SubscriptionForm({ subscription, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: clinics } = useClinicLookup();
  const create = useCreateSubscription();
  const update = useUpdateSubscription(subscription?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      clinic_id: 0,
      type: 'basic',
      amount: 300,
      starts_at: '',
      ends_at: '',
      status: 'active',
      moyasar_payment_id: '',
      notes: '',
    },
  });

  useEffect(() => {
    if (subscription) {
      form.reset({
        clinic_id: subscription.clinic_id,
        type: subscription.type,
        amount: subscription.amount,
        starts_at: toLocal(subscription.starts_at),
        ends_at: toLocal(subscription.ends_at),
        status: subscription.status,
        moyasar_payment_id: subscription.moyasar_payment_id ?? '',
        notes: subscription.notes ?? '',
      });
    }
  }, [subscription, form]);

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: Record<string, unknown> = { ...v };
      if (payload.moyasar_payment_id === '') payload.moyasar_payment_id = null;
      if (payload.notes === '') payload.notes = null;

      if (subscription) {
        await update.mutateAsync(payload as Partial<FormValues>);
        toast.success(t('subscriptions.updated'));
      } else {
        await create.mutateAsync(payload as FormValues);
        toast.success(t('subscriptions.created'));
      }
      onSuccess?.();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_id">{t('subscriptions.form.clinic')}</Label>
          <Select
            id="clinic_id"
            value={form.watch('clinic_id') || ''}
            onChange={(e) => form.setValue('clinic_id', Number(e.target.value), { shouldDirty: true, shouldValidate: true })}
          >
            <option value="">—</option>
            {clinics?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          {form.formState.errors.clinic_id && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.clinic_id.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="type">{t('subscriptions.form.type')}</Label>
          <Select
            id="type"
            value={form.watch('type')}
            onChange={(e) => {
              const v = e.target.value as SubscriptionType;
              form.setValue('type', v, { shouldDirty: true });
              form.setValue('amount', v === 'premium' ? 400 : 300, { shouldDirty: true });
            }}
          >
            {SUBSCRIPTION_TYPES.map((s) => <option key={s} value={s}>{t(`subscriptions.type.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="amount">{t('subscriptions.form.amount')}</Label>
          <Input id="amount" type="number" step="0.01" min={0} {...form.register('amount', { valueAsNumber: true })} />
          {form.formState.errors.amount && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.amount.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="starts_at">{t('subscriptions.form.starts_at')}</Label>
          <Input id="starts_at" type="datetime-local" {...form.register('starts_at')} />
          {form.formState.errors.starts_at && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.starts_at.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="ends_at">{t('subscriptions.form.ends_at')}</Label>
          <Input id="ends_at" type="datetime-local" {...form.register('ends_at')} />
          {form.formState.errors.ends_at && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.ends_at.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="status">{t('subscriptions.form.status')}</Label>
          <Select
            id="status"
            value={form.watch('status')}
            onChange={(e) => form.setValue('status', e.target.value as SubscriptionStatus, { shouldDirty: true })}
          >
            {SUBSCRIPTION_STATUSES.map((s) => <option key={s} value={s}>{t(`subscriptions.status.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="moyasar_payment_id">{t('subscriptions.form.moyasar_payment_id')}</Label>
          <Input id="moyasar_payment_id" dir="ltr" {...form.register('moyasar_payment_id')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="notes">{t('subscriptions.form.notes')}</Label>
          <Textarea id="notes" rows={3} {...form.register('notes')} />
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>{t('common.cancel')}</Button>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  );
}
