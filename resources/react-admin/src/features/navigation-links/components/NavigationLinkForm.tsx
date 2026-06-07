import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useStaticPages } from '@/features/static-pages/hooks';

import { navigationLinkFormSchema, type NavigationLinkFormSchema } from '../schemas/navigation-link.schema';
import { useCreateNavigationLink, useUpdateNavigationLink } from '../hooks';
import type { NavigationLink } from '../types';

interface Props {
  link?: NavigationLink | null;
  defaultLocation?: 'header' | 'footer';
  onSuccess?: () => void;
  onCancel?: () => void;
}

type TargetType = 'page' | 'route' | 'url';

function initialTarget(link?: NavigationLink | null): TargetType {
  if (link?.static_page_id) return 'page';
  if (link?.route_name) return 'route';
  return 'url';
}

function emptyValues(link: NavigationLink | null | undefined, defaultLocation: 'header' | 'footer'): NavigationLinkFormSchema {
  return {
    location: link?.location ?? defaultLocation,
    footer_column: link?.footer_column ?? (defaultLocation === 'footer' ? 1 : null),
    label_ar: link?.label_ar ?? '',
    label_en: link?.label_en ?? '',
    url: link?.url ?? '',
    static_page_id: link?.static_page_id ?? null,
    route_name: link?.route_name ?? '',
    open_new_tab: link?.open_new_tab ?? false,
    is_active: link?.is_active ?? true,
    sort_order: link?.sort_order ?? 0,
  };
}

export function NavigationLinkForm({ link, defaultLocation = 'header', onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const [targetType, setTargetType] = useState<TargetType>(initialTarget(link));
  const { data: pagesData } = useStaticPages({ per_page: 100, sort: 'sort_order' });

  const form = useForm<NavigationLinkFormSchema>({
    resolver: zodResolver(navigationLinkFormSchema),
    defaultValues: emptyValues(link, defaultLocation),
  });

  useEffect(() => {
    form.reset(emptyValues(link, defaultLocation));
    setTargetType(initialTarget(link));
  }, [link, defaultLocation, form]);

  const create = useCreateNavigationLink();
  const update = useUpdateNavigationLink(link?.id ?? 0);

  const location = form.watch('location');

  const onSubmit = async (values: NavigationLinkFormSchema) => {
    // Keep only the chosen target field; null the others so the backend
    // resolves the href unambiguously.
    const payload: NavigationLinkFormSchema = {
      ...values,
      footer_column: values.location === 'footer' ? values.footer_column ?? 1 : null,
      static_page_id: targetType === 'page' ? values.static_page_id ?? null : null,
      route_name: targetType === 'route' ? values.route_name ?? null : null,
      url: targetType === 'url' ? values.url ?? null : null,
    };

    try {
      if (link) {
        await update.mutateAsync(payload);
        toast.success(t('navigation_links.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('navigation_links.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof NavigationLinkFormSchema, { message: msgs[0] });
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
          <Label htmlFor="location">{t('navigation_links.location')}</Label>
          <Select id="location" {...form.register('location')}>
            <option value="header">{t('navigation_links.location_header')}</option>
            <option value="footer">{t('navigation_links.location_footer')}</option>
          </Select>
        </div>

        {location === 'footer' && (
          <div className="space-y-1.5">
            <Label htmlFor="footer_column">{t('navigation_links.footer_column')}</Label>
            <Select id="footer_column" {...form.register('footer_column', { valueAsNumber: true })}>
              <option value={1}>{t('navigation_links.column_1')}</option>
              <option value={2}>{t('navigation_links.column_2')}</option>
              <option value={3}>{t('navigation_links.column_3')}</option>
            </Select>
          </div>
        )}

        <div className="space-y-1.5">
          <Label htmlFor="label_ar">{t('navigation_links.label_ar')}</Label>
          <Input id="label_ar" {...form.register('label_ar')} />
          {form.formState.errors.label_ar && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.label_ar.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="label_en">{t('navigation_links.label_en')}</Label>
          <Input id="label_en" dir="ltr" {...form.register('label_en')} />
        </div>
      </div>

      {/* Target chooser: a static page, a named route, or an arbitrary URL. */}
      <div className="space-y-1.5">
        <Label htmlFor="target_type">{t('navigation_links.target')}</Label>
        <Select id="target_type" value={targetType} onChange={(e) => setTargetType(e.target.value as TargetType)}>
          <option value="page">{t('navigation_links.target_page')}</option>
          <option value="route">{t('navigation_links.target_route')}</option>
          <option value="url">{t('navigation_links.target_url')}</option>
        </Select>
      </div>

      {targetType === 'page' && (
        <div className="space-y-1.5">
          <Label htmlFor="static_page_id">{t('navigation_links.target_page')}</Label>
          <Select
            id="static_page_id"
            value={form.watch('static_page_id') ?? ''}
            onChange={(e) => form.setValue('static_page_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })}
          >
            <option value="">{t('navigation_links.select_page')}</option>
            {pagesData?.data.map((p) => (
              <option key={p.id} value={p.id}>{p.title_ar} (/{p.slug})</option>
            ))}
          </Select>
          {form.formState.errors.url && (
            <p className="text-xs text-[var(--color-destructive)]">{t('navigation_links.target_required')}</p>
          )}
        </div>
      )}

      {targetType === 'route' && (
        <div className="space-y-1.5">
          <Label htmlFor="route_name">{t('navigation_links.route_name')}</Label>
          <Input id="route_name" dir="ltr" placeholder="search" {...form.register('route_name')} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('navigation_links.route_hint')}</p>
        </div>
      )}

      {targetType === 'url' && (
        <div className="space-y-1.5">
          <Label htmlFor="url">{t('navigation_links.url')}</Label>
          <Input id="url" dir="ltr" placeholder="https://" {...form.register('url')} />
          {form.formState.errors.url && (
            <p className="text-xs text-[var(--color-destructive)]">{t('navigation_links.target_required')}</p>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="sort_order">{t('navigation_links.sort_order')}</Label>
          <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
        </div>
        <div className="flex items-end gap-6 pb-2">
          <label className="flex items-center gap-2">
            <Switch checked={form.watch('open_new_tab')} onCheckedChange={(v) => form.setValue('open_new_tab', v, { shouldDirty: true })} />
            <span className="text-sm">{t('navigation_links.open_new_tab')}</span>
          </label>
          <label className="flex items-center gap-2">
            <Switch checked={form.watch('is_active')} onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })} />
            <span className="text-sm">{t('navigation_links.is_active')}</span>
          </label>
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
