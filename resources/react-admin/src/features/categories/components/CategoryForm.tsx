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

import { categoryFormSchema, slugify, type CategoryFormSchema } from '../schemas/category.schema';
import { useCreateCategory, useUpdateCategory } from '../hooks';
import type { Category } from '../types';

interface Props {
  category?: Category | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function CategoryForm({ category, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();

  const form = useForm<CategoryFormSchema>({
    resolver: zodResolver(categoryFormSchema),
    defaultValues: {
      name: category?.name ?? '',
      name_en: category?.name_en ?? '',
      slug: category?.slug ?? '',
      emoji: category?.emoji ?? '',
      icon: category?.icon ?? '',
      is_active: category?.is_active ?? true,
      sort_order: category?.sort_order ?? 0,
    },
  });

  useEffect(() => {
    form.reset({
      name: category?.name ?? '',
      name_en: category?.name_en ?? '',
      slug: category?.slug ?? '',
      emoji: category?.emoji ?? '',
      icon: category?.icon ?? '',
      is_active: category?.is_active ?? true,
      sort_order: category?.sort_order ?? 0,
    });
  }, [category, form]);

  const create = useCreateCategory();
  const update = useUpdateCategory(category?.id ?? 0);

  const onSubmit = async (values: CategoryFormSchema) => {
    try {
      if (category) {
        await update.mutateAsync(values);
        toast.success(t('categories.updated'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('categories.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof CategoryFormSchema, { message: msgs[0] });
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
          <Label htmlFor="name">{t('categories.name')}</Label>
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
          <Label htmlFor="name_en">{t('categories.name_en')}</Label>
          <Input id="name_en" {...form.register('name_en')} />
          {form.formState.errors.name_en && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name_en.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="slug">{t('categories.slug')}</Label>
          <Input id="slug" {...form.register('slug')} dir="ltr" />
          {form.formState.errors.slug && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.slug.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="emoji">{t('categories.emoji')}</Label>
          <Input id="emoji" maxLength={5} {...form.register('emoji')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="icon">{t('categories.icon')}</Label>
          <Input id="icon" {...form.register('icon')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="sort_order">{t('categories.sort_order')}</Label>
          <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
        </div>

        <div className="flex items-end gap-3 pb-2 md:col-span-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('categories.is_active')}</Label>
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
