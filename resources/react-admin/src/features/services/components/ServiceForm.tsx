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
import { FileUpload } from '@/components/forms/FileUpload';
import { useClinicLookup } from '@/features/lookups/hooks';

import { serviceFormSchema, type ServiceFormSchema } from '../schemas/service.schema';
import { useCreateService, useUpdateService } from '../hooks';
import type { Service } from '../types';

interface Props {
  service?: Service | null;
  onSuccess?: () => void;
  onCancel?: () => void;
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
      image: service?.image ?? '',
      is_active: service?.is_active ?? true,
    },
  });

  useEffect(() => {
    form.reset({
      clinic_id: service?.clinic_id ?? 0,
      name: service?.name ?? '',
      description: service?.description ?? '',
      price: service?.price ?? 0,
      image: service?.image ?? '',
      is_active: service?.is_active ?? true,
    });
  }, [service, form]);

  const create = useCreateService();
  const update = useUpdateService(service?.id ?? 0);

  const onSubmit = async (values: ServiceFormSchema) => {
    try {
      if (service) {
        await update.mutateAsync(values);
        toast.success(t('services.updated'));
      } else {
        await create.mutateAsync(values);
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

        <div className="space-y-1.5 md:col-span-2">
          <Label>{t('services.image', 'صورة الخدمة')}</Label>
          <FileUpload
            value={form.watch('image')}
            onChange={(p) => form.setValue('image', p ?? '', { shouldDirty: true })}
            directory="services"
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="price">{t('services.price')}</Label>
          <Input id="price" type="number" step="0.01" min={0} {...form.register('price', { valueAsNumber: true })} />
          {form.formState.errors.price && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.price.message}</p>
          )}
        </div>

        <div className="flex items-end gap-3 pb-2">
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
