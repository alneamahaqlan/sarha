import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DialogFooter } from '@/components/ui/dialog';
import { RichEditor } from '@/components/forms/RichEditor';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { staticPageFormSchema, slugify, type StaticPageFormSchema } from '../schemas/static-page.schema';
import { useCreateStaticPage, useUpdateStaticPage } from '../hooks';
import type { StaticPage } from '../types';

interface Props {
  page?: StaticPage | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function emptyValues(page?: StaticPage | null): StaticPageFormSchema {
  return {
    slug: page?.slug ?? '',
    title_ar: page?.title_ar ?? '',
    title_en: page?.title_en ?? '',
    body_ar: page?.body_ar ?? '',
    body_en: page?.body_en ?? '',
    meta_description_ar: page?.meta_description_ar ?? '',
    meta_description_en: page?.meta_description_en ?? '',
    is_active: page?.is_active ?? true,
    sort_order: page?.sort_order ?? 0,
  };
}

export function StaticPageForm({ page, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const isSystem = page?.is_system ?? false;

  const form = useForm<StaticPageFormSchema>({
    resolver: zodResolver(staticPageFormSchema),
    defaultValues: emptyValues(page),
  });

  useEffect(() => {
    form.reset(emptyValues(page));
  }, [page, form]);

  const create = useCreateStaticPage();
  const update = useUpdateStaticPage(page?.id ?? 0);

  const onSubmit = async (values: StaticPageFormSchema) => {
    try {
      if (page) {
        await update.mutateAsync(values);
        toast.success(t('static_pages.updated'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('static_pages.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof StaticPageFormSchema, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="slug">{t('static_pages.slug')}</Label>
          <Input
            id="slug"
            dir="ltr"
            disabled={isSystem}
            {...form.register('slug')}
          />
          {isSystem && <p className="text-xs text-[var(--color-muted-foreground)]">{t('static_pages.slug_locked')}</p>}
          <FieldError message={form.formState.errors.slug?.message} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sort_order">{t('static_pages.sort_order')}</Label>
          <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
        </div>
      </div>

      <Tabs defaultValue="ar" className="w-full">
        <TabsList className="w-full justify-start">
          <TabsTrigger value="ar">{t('static_pages.tab_ar')}</TabsTrigger>
          <TabsTrigger value="en">{t('static_pages.tab_en')}</TabsTrigger>
        </TabsList>

        <TabsContent value="ar" className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="title_ar">{t('static_pages.title_ar')}</Label>
            <Input
              id="title_ar"
              {...form.register('title_ar', {
                onBlur: (e) => {
                  if (page) return;
                  const current = form.getValues('slug');
                  if (!current) form.setValue('slug', slugify(e.target.value), { shouldDirty: true });
                },
              })}
            />
            <FieldError message={form.formState.errors.title_ar?.message} />
          </div>
          <div className="space-y-1.5">
            <Label>{t('static_pages.body_ar')}</Label>
            <RichEditor value={form.watch('body_ar') ?? ''} onChange={(html) => form.setValue('body_ar', html, { shouldDirty: true })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="meta_description_ar">{t('static_pages.meta_description')}</Label>
            <Textarea id="meta_description_ar" rows={2} maxLength={300} {...form.register('meta_description_ar')} />
          </div>
        </TabsContent>

        <TabsContent value="en" dir="ltr" className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="title_en">{t('static_pages.title_en')}</Label>
            <Input id="title_en" {...form.register('title_en')} />
          </div>
          <div className="space-y-1.5">
            <Label>{t('static_pages.body_en')}</Label>
            <RichEditor value={form.watch('body_en') ?? ''} onChange={(html) => form.setValue('body_en', html, { shouldDirty: true })} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="meta_description_en">{t('static_pages.meta_description')}</Label>
            <Textarea id="meta_description_en" rows={2} maxLength={300} {...form.register('meta_description_en')} />
          </div>
        </TabsContent>
      </Tabs>

      <div className="flex items-center gap-3 pt-2">
        <Switch
          checked={form.watch('is_active')}
          onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
        />
        <Label>{t('static_pages.is_active')}</Label>
      </div>

      <FormErrorSummary
        errors={form.formState.errors}
        labels={{
          slug: t('static_pages.slug'),
          title_ar: t('static_pages.title_ar'),
        }}
      />

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
