import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { Plus, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select } from '@/components/ui/select';
import { FileUpload } from '@/components/forms/FileUpload';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useUpdateLandingPage } from '../hooks';
import { FOOTER_CHROME_MODES, HEADER_CHROME_MODES, type ChromeFormValues, type ChromeLink, type LandingPage } from '../types';

// Page types that resolve a single context clinic (so "clinic" header mode works).
const CLINIC_CONTEXT_TYPES = ['clinic', 'offer', 'custom'];

interface Props {
  page: LandingPage;
}

function defaults(page: LandingPage): ChromeFormValues {
  return {
    header_mode: page.header_mode ?? 'default',
    footer_mode: page.footer_mode ?? 'default',
    header_config: {
      logo_image: page.header_config?.logo_image ?? '',
      show_language: page.header_config?.show_language ?? false,
      sticky: page.header_config?.sticky ?? false,
      bg_color: page.header_config?.bg_color ?? '',
      text_color: page.header_config?.text_color ?? '',
      cta_label: page.header_config?.cta_label ?? '',
      cta_url: page.header_config?.cta_url ?? '',
      links: page.header_config?.links ?? [],
    },
    footer_config: {
      logo_image: page.footer_config?.logo_image ?? '',
      about: page.footer_config?.about ?? '',
      copyright: page.footer_config?.copyright ?? '',
      phone: page.footer_config?.phone ?? '',
      email: page.footer_config?.email ?? '',
      whatsapp: page.footer_config?.whatsapp ?? '',
      bg_color: page.footer_config?.bg_color ?? '',
      text_color: page.footer_config?.text_color ?? '',
      social: {
        instagram: page.footer_config?.social?.instagram ?? '',
        twitter: page.footer_config?.social?.twitter ?? '',
        snapchat: page.footer_config?.social?.snapchat ?? '',
        tiktok: page.footer_config?.social?.tiktok ?? '',
      },
      links: page.footer_config?.links ?? [],
    },
  };
}

export function ChromeTab({ page }: Props) {
  const { t } = useTranslation();
  const form = useForm<ChromeFormValues>({ defaultValues: defaults(page) });
  const update = useUpdateLandingPage(page.id);

  useEffect(() => { form.reset(defaults(page)); }, [page, form]);

  const headerMode = form.watch('header_mode');
  const footerMode = form.watch('footer_mode');

  const onSubmit = async (values: ChromeFormValues) => {
    // Only send a config when the mode actually uses one; keeps the stored
    // JSON tidy and avoids persisting half-filled configs for default/none.
    const payload: Partial<ChromeFormValues> = {
      header_mode: values.header_mode,
      footer_mode: values.footer_mode,
      header_config: ['minimal', 'custom'].includes(values.header_mode) ? values.header_config : {},
      footer_config: ['minimal', 'custom'].includes(values.footer_mode) ? values.footer_config : {},
    };
    try {
      await update.mutateAsync(payload);
      toast.success(t('landing_pages.chrome_saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  // ── Link repeater (shared by header + footer) ──
  const LinkRepeater = ({ field }: { field: 'header_config.links' | 'footer_config.links' }) => {
    const links = (form.watch(field) ?? []) as ChromeLink[];
    const set = (next: ChromeLink[]) => form.setValue(field, next, { shouldDirty: true });
    return (
      <div className="space-y-2">
        <Label>{t('landing_pages.chrome_links')}</Label>
        {links.map((link, i) => (
          <div key={i} className="flex flex-wrap items-center gap-2">
            <Input
              className="flex-1 min-w-[120px]"
              placeholder={t('landing_pages.chrome_link_label')}
              value={link.label}
              onChange={(e) => set(links.map((l, j) => (j === i ? { ...l, label: e.target.value } : l)))}
            />
            <Input
              dir="ltr"
              className="flex-1 min-w-[160px]"
              placeholder="https://…  /  #section"
              value={link.url}
              onChange={(e) => set(links.map((l, j) => (j === i ? { ...l, url: e.target.value } : l)))}
            />
            <label className="flex items-center gap-1.5 text-xs text-[var(--color-muted-foreground)]">
              <Switch
                checked={!!link.new_tab}
                onCheckedChange={(v) => set(links.map((l, j) => (j === i ? { ...l, new_tab: v } : l)))}
              />
              {t('landing_pages.chrome_new_tab')}
            </label>
            <Button type="button" variant="ghost" size="icon" onClick={() => set(links.filter((_, j) => j !== i))} aria-label={t('common.delete')}>
              <X className="h-4 w-4" />
            </Button>
          </div>
        ))}
        <Button type="button" variant="outline" size="sm" onClick={() => set([...links, { label: '', url: '', new_tab: false }])}>
          <Plus className="h-4 w-4 me-1" /> {t('landing_pages.chrome_add_link')}
        </Button>
      </div>
    );
  };

  const ColorRow = ({ bgField, textField }: { bgField: 'header_config.bg_color' | 'footer_config.bg_color'; textField: 'header_config.text_color' | 'footer_config.text_color' }) => (
    <div className="grid grid-cols-2 gap-4">
      <div className="space-y-1.5">
        <Label>{t('landing_pages.chrome_bg_color')}</Label>
        <div className="flex items-center gap-2">
          <Input type="color" className="h-9 w-14 p-1" value={form.watch(bgField) || '#ffffff'} onChange={(e) => form.setValue(bgField, e.target.value, { shouldDirty: true })} />
          <Button type="button" variant="ghost" size="sm" onClick={() => form.setValue(bgField, '', { shouldDirty: true })}>{t('landing_pages.chrome_clear_color')}</Button>
        </div>
      </div>
      <div className="space-y-1.5">
        <Label>{t('landing_pages.chrome_text_color')}</Label>
        <div className="flex items-center gap-2">
          <Input type="color" className="h-9 w-14 p-1" value={form.watch(textField) || '#111827'} onChange={(e) => form.setValue(textField, e.target.value, { shouldDirty: true })} />
          <Button type="button" variant="ghost" size="sm" onClick={() => form.setValue(textField, '', { shouldDirty: true })}>{t('landing_pages.chrome_clear_color')}</Button>
        </div>
      </div>
    </div>
  );

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-6 max-w-3xl">
      <p className="text-sm text-[var(--color-muted-foreground)]">{t('landing_pages.chrome_intro')}</p>

      {/* ── HEADER ── */}
      <div className="rounded-lg border border-[var(--color-border)] p-4 space-y-4">
        <div className="space-y-1.5">
          <Label>{t('landing_pages.chrome_header_mode')}</Label>
          <Select value={headerMode} onChange={(e) => form.setValue('header_mode', e.target.value as ChromeFormValues['header_mode'], { shouldDirty: true })}>
            {HEADER_CHROME_MODES.map((m) => <option key={m} value={m}>{t(`landing_pages.chrome_modes.${m}`)}</option>)}
          </Select>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t(`landing_pages.chrome_modes_hint.${headerMode}`)}</p>
          {headerMode === 'clinic' && !CLINIC_CONTEXT_TYPES.includes(page.type) && (
            <p className="text-xs text-amber-600">{t('landing_pages.chrome_clinic_needs_link')}</p>
          )}
        </div>

        {(headerMode === 'minimal' || headerMode === 'custom') && (
          <FileUpload
            label={t('landing_pages.chrome_logo')}
            hint={t('landing_pages.chrome_logo_hint')}
            directory="landing-pages"
            value={form.watch('header_config.logo_image') || ''}
            onChange={(p) => form.setValue('header_config.logo_image', p ?? '', { shouldDirty: true })}
          />
        )}

        {headerMode === 'custom' && (
          <>
            <LinkRepeater field="header_config.links" />
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-1.5">
                <Label>{t('landing_pages.chrome_cta_label')}</Label>
                <Input {...form.register('header_config.cta_label')} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('landing_pages.chrome_cta_url')}</Label>
                <Input dir="ltr" placeholder="https://…" {...form.register('header_config.cta_url')} />
              </div>
            </div>
            <div className="flex flex-wrap gap-6">
              <label className="flex items-center gap-2 text-sm">
                <Switch checked={!!form.watch('header_config.show_language')} onCheckedChange={(v) => form.setValue('header_config.show_language', v, { shouldDirty: true })} />
                {t('landing_pages.chrome_show_language')}
              </label>
              <label className="flex items-center gap-2 text-sm">
                <Switch checked={!!form.watch('header_config.sticky')} onCheckedChange={(v) => form.setValue('header_config.sticky', v, { shouldDirty: true })} />
                {t('landing_pages.chrome_sticky')}
              </label>
            </div>
            <ColorRow bgField="header_config.bg_color" textField="header_config.text_color" />
          </>
        )}
      </div>

      {/* ── FOOTER ── */}
      <div className="rounded-lg border border-[var(--color-border)] p-4 space-y-4">
        <div className="space-y-1.5">
          <Label>{t('landing_pages.chrome_footer_mode')}</Label>
          <Select value={footerMode} onChange={(e) => form.setValue('footer_mode', e.target.value as ChromeFormValues['footer_mode'], { shouldDirty: true })}>
            {FOOTER_CHROME_MODES.map((m) => <option key={m} value={m}>{t(`landing_pages.chrome_modes.${m}`)}</option>)}
          </Select>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t(`landing_pages.chrome_modes_hint.${footerMode}`)}</p>
        </div>

        {(footerMode === 'minimal' || footerMode === 'custom') && (
          <>
            <FileUpload
              label={t('landing_pages.chrome_logo')}
              hint={t('landing_pages.chrome_logo_hint')}
              directory="landing-pages"
              value={form.watch('footer_config.logo_image') || ''}
              onChange={(p) => form.setValue('footer_config.logo_image', p ?? '', { shouldDirty: true })}
            />
            <div className="space-y-1.5">
              <Label>{t('landing_pages.chrome_copyright')}</Label>
              <Input {...form.register('footer_config.copyright')} />
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.chrome_copyright_hint')}</p>
            </div>
          </>
        )}

        {footerMode === 'custom' && (
          <>
            <div className="space-y-1.5">
              <Label>{t('landing_pages.chrome_about')}</Label>
              <Textarea rows={3} maxLength={600} {...form.register('footer_config.about')} />
            </div>
            <LinkRepeater field="footer_config.links" />
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div className="space-y-1.5">
                <Label>{t('landing_pages.chrome_phone')}</Label>
                <Input dir="ltr" {...form.register('footer_config.phone')} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('landing_pages.chrome_email')}</Label>
                <Input dir="ltr" {...form.register('footer_config.email')} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('landing_pages.chrome_whatsapp')}</Label>
                <Input dir="ltr" placeholder="9665XXXXXXXX" {...form.register('footer_config.whatsapp')} />
              </div>
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Instagram</Label>
                <Input dir="ltr" placeholder="https://instagram.com/…" {...form.register('footer_config.social.instagram')} />
              </div>
              <div className="space-y-1.5">
                <Label>X (Twitter)</Label>
                <Input dir="ltr" placeholder="https://x.com/…" {...form.register('footer_config.social.twitter')} />
              </div>
              <div className="space-y-1.5">
                <Label>Snapchat</Label>
                <Input dir="ltr" placeholder="https://snapchat.com/add/…" {...form.register('footer_config.social.snapchat')} />
              </div>
              <div className="space-y-1.5">
                <Label>TikTok</Label>
                <Input dir="ltr" placeholder="https://tiktok.com/@…" {...form.register('footer_config.social.tiktok')} />
              </div>
            </div>
            <ColorRow bgField="footer_config.bg_color" textField="footer_config.text_color" />
          </>
        )}
      </div>

      <div className="flex justify-end">
        <Button type="submit" disabled={update.isPending}>
          {update.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </form>
  );
}
