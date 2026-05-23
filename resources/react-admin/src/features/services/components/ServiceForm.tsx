import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useClinicLookup } from '@/features/lookups/hooks';

import { serviceFormSchema, type ServiceFormSchema } from '../schemas/service.schema';
import { useCreateService, useUpdateService } from '../hooks';
import type { Service } from '../types';

interface Props {
  service?: Service | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function toDateTimeLocal(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function ServiceForm({ service, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: clinics } = useClinicLookup();

  const form = useForm<ServiceFormSchema>({
    resolver: zodResolver(serviceFormSchema) as never,
    defaultValues: {
      clinic_id: service?.clinic_id ?? 0,
      name: service?.name ?? '',
      description: service?.description ?? '',
      price: service?.price ?? 0,
      old_price: service?.old_price ?? null,
      offer_expires_at: toDateTimeLocal(service?.offer_expires_at) || null,
      is_active: service?.is_active ?? true,
    },
  });

  useEffect(() => {
    form.reset({
      clinic_id: service?.clinic_id ?? 0,
      name: service?.name ?? '',
      description: service?.description ?? '',
      price: service?.price ?? 0,
      old_price: service?.old_price ?? null,
      offer_expires_at: toDateTimeLocal(service?.offer_expires_at) || null,
      is_active: service?.is_active ?? true,
    });
  }, [service, form]);

  const create = useCreateService();
  const update = useUpdateService(service?.id ?? 0);

  const onSubmit = async (values: ServiceFormSchema) => {
    try {
      const payload = {
        ...values,
        offer_expires_at: values.offer_expires_at ? new Date(values.offer_expires_at).toISOString() : null,
      };
      if (service) {
        await update.mutateAsync(payload);
        toast.success(t('services.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('services.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof ServiceFormSchema, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;
  const showOfferDate = !!form.watch('old_price');

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_id">{t('services.clinic')}</Label>
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

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="name">{t('services.name')}</Label>
          <Input id="name" {...form.register('name')} />
          {form.formState.errors.name && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>
          )}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="description">{t('services.description')}</Label>
          <Textarea id="description" rows={2} {...form.register('description')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="price">{t('services.price')}</Label>
          <Input id="price" type="number" step="0.01" min={0} {...form.register('price', { valueAsNumber: true })} />
          {form.formState.errors.price && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.price.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="old_price">{t('services.old_price')}</Label>
          <Input
            id="old_price"
            type="number"
            step="0.01"
            min={0}
            {...form.register('old_price', {
              setValueAs: (v) => (v === '' || v === null ? null : Number(v)),
            })}
          />
          {form.formState.errors.old_price && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.old_price.message}</p>
          )}
        </div>

        {showOfferDate && (
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="offer_expires_at">{t('services.offer_expires_at')}</Label>
            <Input id="offer_expires_at" type="datetime-local" {...form.register('offer_expires_at')} />
            {form.formState.errors.offer_expires_at && (
              <p className="text-xs text-[var(--color-destructive)]">
                {form.formState.errors.offer_expires_at.message}
              </p>
            )}
          </div>
        )}

        <div className="flex items-end gap-3 pb-2 md:col-span-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('services.is_active')}</Label>
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
