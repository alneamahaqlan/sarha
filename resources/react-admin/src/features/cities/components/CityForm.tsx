import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { cityFormSchema, type CityFormSchema } from '../schemas/city.schema';
import { useCreateCity, useUpdateCity } from '../hooks';
import type { City } from '../types';

interface Props {
  city?: City | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function CityForm({ city, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();

  const form = useForm<CityFormSchema>({
    resolver: zodResolver(cityFormSchema),
    defaultValues: {
      name: city?.name ?? '',
      name_en: city?.name_en ?? '',
      is_active: city?.is_active ?? true,
      sort_order: city?.sort_order ?? 0,
    },
  });

  useEffect(() => {
    form.reset({
      name: city?.name ?? '',
      name_en: city?.name_en ?? '',
      is_active: city?.is_active ?? true,
      sort_order: city?.sort_order ?? 0,
    });
  }, [city, form]);

  const create = useCreateCity();
  const update = useUpdateCity(city?.id ?? 0);

  const onSubmit = async (values: CityFormSchema) => {
    try {
      if (city) {
        await update.mutateAsync(values);
        toast.success(t('cities.updated'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('cities.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof CityFormSchema, { message: msgs[0] });
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
        <div className="space-y-1.5">
          <Label htmlFor="name">{t('cities.name')}</Label>
          <Input id="name" {...form.register('name')} />
          {form.formState.errors.name && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="name_en">{t('cities.name_en')}</Label>
          <Input id="name_en" {...form.register('name_en')} />
          {form.formState.errors.name_en && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name_en.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="sort_order">{t('cities.sort_order')}</Label>
          <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
          {form.formState.errors.sort_order && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.sort_order.message}</p>
          )}
        </div>

        <div className="flex items-end gap-3 pb-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('cities.is_active')}</Label>
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
