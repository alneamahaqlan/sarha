import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select } from '@/components/ui/select';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useUpdateLandingPage } from '../hooks';
import type { LandingPage, SeoFormValues } from '../types';

interface Props {
  page: LandingPage;
}

function defaults(page: LandingPage): SeoFormValues {
  return {
    seo_title_ar: page.seo_title_ar ?? '',
    seo_title_en: page.seo_title_en ?? '',
    seo_description_ar: page.seo_description_ar ?? '',
    seo_description_en: page.seo_description_en ?? '',
    seo_keywords: page.seo_keywords ?? '',
    canonical_url: page.canonical_url ?? '',
    meta_robots: page.meta_robots ?? 'index,follow',
    in_sitemap: page.in_sitemap,
    og_title_ar: page.og_title_ar ?? '',
    og_title_en: page.og_title_en ?? '',
    og_description_ar: page.og_description_ar ?? '',
    og_description_en: page.og_description_en ?? '',
  };
}

const ROBOTS = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];

export function SeoTab({ page }: Props) {
  const { t } = useTranslation();
  const form = useForm<SeoFormValues>({ defaultValues: defaults(page) });
  const update = useUpdateLandingPage(page.id);

  useEffect(() => { form.reset(defaults(page)); }, [page, form]);

  const onSubmit = async (values: SeoFormValues) => {
    try {
      await update.mutateAsync({ ...values, canonical_url: values.canonical_url || null });
      toast.success(t('landing_pages.seo_saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-5 max-w-3xl">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="seo_title_ar">{t('landing_pages.seo_title_ar')}</Label>
          <Input id="seo_title_ar" {...form.register('seo_title_ar')} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="seo_title_en">{t('landing_pages.seo_title_en')}</Label>
          <Input id="seo_title_en" dir="ltr" {...form.register('seo_title_en')} />
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="seo_description_ar">{t('landing_pages.seo_description_ar')}</Label>
          <Textarea id="seo_description_ar" rows={3} maxLength={300} {...form.register('seo_description_ar')} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="seo_description_en">{t('landing_pages.seo_description_en')}</Label>
          <Textarea id="seo_description_en" dir="ltr" rows={3} maxLength={300} {...form.register('seo_description_en')} />
        </div>
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="seo_keywords">{t('landing_pages.seo_keywords')}</Label>
        <Input id="seo_keywords" {...form.register('seo_keywords')} />
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="canonical_url">{t('landing_pages.canonical_url')}</Label>
          <Input id="canonical_url" dir="ltr" placeholder="https://…" {...form.register('canonical_url')} />
        </div>
        <div className="space-y-1.5">
          <Label>{t('landing_pages.meta_robots')}</Label>
          <Select value={form.watch('meta_robots') ?? 'index,follow'} onChange={(e) => form.setValue('meta_robots', e.target.value, { shouldDirty: true })}>
            {ROBOTS.map((r) => <option key={r} value={r}>{r}</option>)}
          </Select>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Switch checked={form.watch('in_sitemap') ?? true} onCheckedChange={(v) => form.setValue('in_sitemap', v, { shouldDirty: true })} />
        <Label>{t('landing_pages.in_sitemap')}</Label>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] p-4 space-y-4">
        <p className="text-sm font-medium">{t('landing_pages.og_section')}</p>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="og_title_ar">{t('landing_pages.og_title_ar')}</Label>
            <Input id="og_title_ar" {...form.register('og_title_ar')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="og_title_en">{t('landing_pages.og_title_en')}</Label>
            <Input id="og_title_en" dir="ltr" {...form.register('og_title_en')} />
          </div>
        </div>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="og_description_ar">{t('landing_pages.og_description_ar')}</Label>
            <Textarea id="og_description_ar" rows={2} maxLength={300} {...form.register('og_description_ar')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="og_description_en">{t('landing_pages.og_description_en')}</Label>
            <Textarea id="og_description_en" dir="ltr" rows={2} maxLength={300} {...form.register('og_description_en')} />
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <Button type="submit" disabled={update.isPending}>
          {update.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </form>
  );
}
