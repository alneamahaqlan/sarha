import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import {
  serviceCategoryFormSchema,
  slugify,
  type ServiceCategoryFormSchema,
} from '../schemas/service-category.schema';
import { useCreateServiceCategory, useUpdateServiceCategory } from '../hooks';
import type { ServiceCategory } from '../types';

interface Props {
  category?: ServiceCategory | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function ServiceCategoryForm({ category, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();

  const defaults = (c: ServiceCategory | null | undefined) => ({
    name: c?.name ?? '',
    name_en: c?.name_en ?? '',
    slug: c?.slug ?? '',
    emoji: c?.emoji ?? '',
    icon: c?.icon ?? '',
    description: c?.description ?? '',
    is_active: c?.is_active ?? true,
    sort_order: c?.sort_order ?? 0,
  });

  const form = useForm<ServiceCategoryFormSchema>({
    resolver: zodResolver(serviceCategoryFormSchema),
    defaultValues: defaults(category),
  });

  useEffect(() => form.reset(defaults(category)), [category, form]);

  const create = useCreateServiceCategory();
  const update = useUpdateServiceCategory(category?.id ?? 0);

  const onSubmit = async (values: ServiceCategoryFormSchema) => {
    try {
      if (category) {
        await update.mutateAsync(values);
        toast.success(t('service_categories.updated', 'تم تحديث فئة الخدمة'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('service_categories.created', 'تم إنشاء فئة الخدمة'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof ServiceCategoryFormSchema, { message: msgs[0] });
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
          <Label htmlFor="name">{t('service_categories.name', 'الاسم بالعربية')}</Label>
          <Input
            id="name"
            {...form.register('name', {
              onBlur: (e) => {
                const current = form.getValues('slug');
                if (!current || (!category && current === slugify(form.getValues('name')))) {
                  form.setValue('slug', slugify(e.target.value), { shouldDirty: true });
                }
              },
            })}
          />
          {form.formState.errors.name && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="name_en">{t('service_categories.name_en', 'Name (EN)')}</Label>
          <Input id="name_en" {...form.register('name_en')} dir="ltr" />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="slug">{t('service_categories.slug', 'Slug')}</Label>
          <Input id="slug" {...form.register('slug')} dir="ltr" />
          {form.formState.errors.slug && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.slug.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="emoji">{t('service_categories.emoji', 'إيموجي')}</Label>
          <Input id="emoji" maxLength={8} {...form.register('emoji')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="icon">{t('service_categories.icon', 'أيقونة')}</Label>
          <Input id="icon" {...form.register('icon')} dir="ltr" />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="sort_order">{t('service_categories.sort_order', 'الترتيب')}</Label>
          <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="description">{t('service_categories.description', 'وصف مختصر للإدارة (اختياري)')}</Label>
          <Textarea id="description" rows={3} {...form.register('description')} />
        </div>

        <div className="flex items-end gap-3 pb-2 md:col-span-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('service_categories.is_active', 'مفعّلة')}</Label>
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
