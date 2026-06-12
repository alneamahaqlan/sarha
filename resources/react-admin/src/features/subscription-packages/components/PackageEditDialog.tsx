import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useCreatePackage, useUpdatePackage } from '../hooks';
import type {
  ColorToken,
  SimilarSectionConfig,
  SimilarSectionKey,
  SubscriptionPackage,
  SubscriptionPackageFormValues,
} from '../types';

interface Props {
  open: boolean;
  pkg: SubscriptionPackage | null; // null = create mode
  onClose: () => void;
}

const COLORS: { token: ColorToken; chip: string }[] = [
  { token: 'gray', chip: 'bg-gray-200 ring-gray-300' },
  { token: 'sage', chip: 'bg-sage-200 ring-sage-400' },
  { token: 'gold', chip: 'bg-gold-soft ring-gold-deep' },
  { token: 'plum', chip: 'bg-plum-soft ring-plum-deep' },
  { token: 'info', chip: 'bg-blue-200 ring-blue-400' },
  { token: 'warning', chip: 'bg-amber-200 ring-amber-400' },
];

const EMPTY: SubscriptionPackageFormValues = {
  slug: '',
  name_ar: '',
  name_en: '',
  tagline_ar: null,
  tagline_en: null,
  color_token: 'gray',
  monthly_price: 0,
  sort_order: 50,
  is_active: true,
  services_limit: 10,
  articles_monthly_limit: 2,
  ai_article_generation: false,
  featured_in_search: false,
  ai_assistant_priority: 0,
  google_reviews_sync: false,
  verified_badge: false,
  analytics_level: 'basic',
  quote_replies_monthly_limit: 3,
  banner_slots: 0,
  allow_offers_packages: false,
  allow_doctors_before_after: false,
  crm_enabled: true,
  similar_config: {
    limit: 6,
    sections: {
      complex:   { show: true, match_city: true, match_specialty: true },
      service:   { show: true, match_city: true, match_specialty: true },
      offer:     { show: true, match_city: true, match_specialty: true },
      subClinic: { show: true, match_city: true, match_specialty: true },
      doctor:    { show: true, match_city: true, match_specialty: true, match_subclinic: true },
    },
  },
};

/** Rows of the "similar sections" matrix, in display order. */
const SIMILAR_ROWS: { key: SimilarSectionKey; labelKey: string; subclinic: boolean }[] = [
  { key: 'complex',   labelKey: 'packages.similar.row_complex',   subclinic: false },
  { key: 'service',   labelKey: 'packages.similar.row_service',   subclinic: false },
  { key: 'offer',     labelKey: 'packages.similar.row_offer',     subclinic: false },
  { key: 'subClinic', labelKey: 'packages.similar.row_subclinic', subclinic: false },
  { key: 'doctor',    labelKey: 'packages.similar.row_doctor',    subclinic: true  },
];

export function PackageEditDialog({ open, pkg, onClose }: Props) {
  const { t } = useTranslation();
  const [values, setValues] = useState<SubscriptionPackageFormValues>(EMPTY);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const create = useCreatePackage();
  const update = useUpdatePackage(pkg?.id ?? 0);
  const busy = create.isPending || update.isPending;

  // Reset form whenever the dialog re-opens or switches between packages.
  useEffect(() => {
    if (!open) return;
    setErrors({});
    if (pkg) {
      const { id, clinics_count, created_at, updated_at, ...rest } = pkg;
      void id; void clinics_count; void created_at; void updated_at;
      setValues(rest);
    } else {
      setValues(EMPTY);
    }
  }, [open, pkg]);

  const set = <K extends keyof SubscriptionPackageFormValues>(key: K, val: SubscriptionPackageFormValues[K]) =>
    setValues((v) => ({ ...v, [key]: val }));

  /** Toggle one flag of one "similar" section. */
  const setSim = (section: SimilarSectionKey, field: keyof SimilarSectionConfig, val: boolean) =>
    setValues((v) => ({
      ...v,
      similar_config: {
        ...v.similar_config,
        sections: {
          ...v.similar_config.sections,
          [section]: { ...v.similar_config.sections[section], [field]: val },
        },
      },
    }));

  const setSimLimit = (val: number) =>
    setValues((v) => ({ ...v, similar_config: { ...v.similar_config, limit: val } }));

  /** Unlimited toggle for the nullable INT columns — ♾ = null. */
  const limitInput = (key: 'services_limit' | 'articles_monthly_limit' | 'quote_replies_monthly_limit') => {
    const v = values[key];
    const isUnlimited = v === null;
    return (
      <div className="flex items-center gap-2">
        <Input
          type="number"
          min={0}
          disabled={isUnlimited}
          value={isUnlimited ? '' : String(v ?? 0)}
          onChange={(e) => set(key, e.target.value === '' ? 0 : Number(e.target.value))}
          className="w-24"
        />
        <label className="flex items-center gap-1 text-xs text-[var(--color-muted-foreground)]">
          <Switch checked={isUnlimited} onCheckedChange={(c) => set(key, c ? null : 0)} />
          {t('packages.unlimited')}
        </label>
        <FieldError message={errors[key]} />
      </div>
    );
  };

  const onSubmit = async () => {
    setErrors({});
    try {
      if (pkg) {
        await update.mutateAsync(values);
        toast.success(t('packages.updated'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('packages.created'));
      }
      onClose();
    } catch (e) {
      const v = extractValidationErrors(e);
      if (v) {
        const flat: Record<string, string> = {};
        Object.entries(v).forEach(([k, msgs]) => { flat[k] = msgs[0]; });
        setErrors(flat);
      } else {
        toast.error(extractMessage(e, t('errors.generic')));
      }
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{pkg ? t('packages.edit') : t('packages.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('packages.dialog_description')}</DialogDescription>
        </DialogHeader>

        <div className="space-y-5">
          {/* Identity */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold">{t('packages.section.identity')}</h3>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <Label>{t('packages.field.name_ar')}</Label>
                <Input value={values.name_ar} onChange={(e) => set('name_ar', e.target.value)} dir="rtl" />
                <FieldError message={errors.name_ar} />
              </div>
              <div>
                <Label>{t('packages.field.name_en')}</Label>
                <Input value={values.name_en} onChange={(e) => set('name_en', e.target.value)} dir="ltr" />
                <FieldError message={errors.name_en} />
              </div>
              <div>
                <Label>{t('packages.field.tagline_ar')}</Label>
                <Textarea rows={2} value={values.tagline_ar ?? ''} onChange={(e) => set('tagline_ar', e.target.value || null)} dir="rtl" />
              </div>
              <div>
                <Label>{t('packages.field.tagline_en')}</Label>
                <Textarea rows={2} value={values.tagline_en ?? ''} onChange={(e) => set('tagline_en', e.target.value || null)} dir="ltr" />
              </div>
              <div>
                <Label>{t('packages.field.slug')}</Label>
                <Input value={values.slug} onChange={(e) => set('slug', e.target.value)} dir="ltr" />
                <FieldError message={errors.slug} />
              </div>
              <div>
                <Label>{t('packages.field.monthly_price')}</Label>
                <Input
                  type="number" min={0} step={1}
                  value={values.monthly_price}
                  onChange={(e) => set('monthly_price', Number(e.target.value))}
                />
                <FieldError message={errors.monthly_price} />
              </div>
              <div>
                <Label>{t('packages.field.color')}</Label>
                <div className="flex flex-wrap gap-1.5">
                  {COLORS.map((c) => (
                    <button
                      key={c.token}
                      type="button"
                      onClick={() => set('color_token', c.token)}
                      className={`h-7 w-7 rounded-full ring-2 transition-transform ${c.chip} ${values.color_token === c.token ? 'scale-110 ring-offset-2' : 'opacity-60'}`}
                      aria-label={c.token}
                      title={c.token}
                    />
                  ))}
                </div>
              </div>
              <div>
                <Label>{t('packages.field.sort_order')}</Label>
                <Input type="number" min={0} value={values.sort_order} onChange={(e) => set('sort_order', Number(e.target.value))} />
              </div>
              <div className="md:col-span-2 flex items-center gap-2">
                <Switch checked={values.is_active} onCheckedChange={(c) => set('is_active', c)} />
                <Label className="cursor-pointer" onClick={() => set('is_active', !values.is_active)}>
                  {t('packages.field.is_active')}
                </Label>
              </div>
            </div>
          </section>

          {/* Limits */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold">{t('packages.section.limits')}</h3>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <Label>{t('packages.field.services_limit')}</Label>
                {limitInput('services_limit')}
              </div>
              <div>
                <Label>{t('packages.field.articles_monthly_limit')}</Label>
                {limitInput('articles_monthly_limit')}
              </div>
              <div>
                <Label>{t('packages.field.quote_replies_monthly_limit')}</Label>
                {limitInput('quote_replies_monthly_limit')}
              </div>
              <div>
                <Label>{t('packages.field.banner_slots')}</Label>
                <Input type="number" min={0} value={values.banner_slots} onChange={(e) => set('banner_slots', Number(e.target.value))} className="w-24" />
              </div>
            </div>
          </section>

          {/* Features */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold">{t('packages.section.features')}</h3>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              {([
                ['ai_article_generation',      'packages.field.ai_article_generation'],
                ['featured_in_search',         'packages.field.featured_in_search'],
                ['google_reviews_sync',        'packages.field.google_reviews_sync'],
                ['verified_badge',             'packages.field.verified_badge'],
                ['allow_offers_packages',      'packages.field.allow_offers_packages'],
                ['allow_doctors_before_after', 'packages.field.allow_doctors_before_after'],
                ['crm_enabled',                'packages.field.crm_enabled'],
              ] as const).map(([key, labelKey]) => (
                <label key={key} className="flex items-center justify-between gap-2 rounded-md border border-[var(--color-border)] px-3 py-2">
                  <span className="text-sm">{t(labelKey)}</span>
                  <Switch checked={Boolean(values[key])} onCheckedChange={(c) => set(key, c)} />
                </label>
              ))}
            </div>
          </section>

          {/* Similar sections — per-package matrix controlling the public
              "similar / related" card strips for this package's clinics. */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold">{t('packages.section.similar')}</h3>
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('packages.similar.hint')}</p>
            <div className="overflow-x-auto rounded-md border border-[var(--color-border)]">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-[var(--color-border)] text-[var(--color-muted-foreground)]">
                    <th className="px-3 py-2 text-start font-medium"></th>
                    <th className="px-3 py-2 text-center font-medium">{t('packages.similar.col_show')}</th>
                    <th className="px-3 py-2 text-center font-medium">{t('packages.similar.col_city')}</th>
                    <th className="px-3 py-2 text-center font-medium">{t('packages.similar.col_specialty')}</th>
                    <th className="px-3 py-2 text-center font-medium">{t('packages.similar.col_subclinic')}</th>
                  </tr>
                </thead>
                <tbody>
                  {SIMILAR_ROWS.map((row) => {
                    const cfg = values.similar_config.sections[row.key];
                    return (
                      <tr key={row.key} className="border-b border-[var(--color-border)] last:border-0">
                        <td className="px-3 py-2 whitespace-nowrap">{t(row.labelKey)}</td>
                        <td className="px-3 py-2 text-center">
                          <Switch checked={cfg.show} onCheckedChange={(c) => setSim(row.key, 'show', c)} />
                        </td>
                        <td className="px-3 py-2 text-center">
                          <Switch checked={cfg.match_city} disabled={!cfg.show} onCheckedChange={(c) => setSim(row.key, 'match_city', c)} />
                        </td>
                        <td className="px-3 py-2 text-center">
                          <Switch checked={cfg.match_specialty} disabled={!cfg.show} onCheckedChange={(c) => setSim(row.key, 'match_specialty', c)} />
                        </td>
                        <td className="px-3 py-2 text-center">
                          {row.subclinic ? (
                            <Switch checked={Boolean(cfg.match_subclinic)} disabled={!cfg.show} onCheckedChange={(c) => setSim(row.key, 'match_subclinic', c)} />
                          ) : (
                            <span className="text-[var(--color-muted-foreground)]">—</span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div className="flex items-center gap-2">
              <Label htmlFor="similar_limit">{t('packages.similar.limit')}</Label>
              <Input
                id="similar_limit"
                type="number"
                min={1}
                max={24}
                value={values.similar_config.limit}
                onChange={(e) => setSimLimit(Math.max(1, Number(e.target.value) || 1))}
                className="w-24"
              />
            </div>
          </section>

          {/* Tier */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold">{t('packages.section.tier')}</h3>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <Label>{t('packages.field.ai_assistant_priority')}</Label>
                <Select value={String(values.ai_assistant_priority)} onChange={(e) => set('ai_assistant_priority', Number(e.target.value) as 0 | 1 | 2)}>
                  <option value="0">{t('packages.priority.none')}</option>
                  <option value="1">{t('packages.priority.medium')}</option>
                  <option value="2">{t('packages.priority.top')}</option>
                </Select>
              </div>
              <div>
                <Label>{t('packages.field.analytics_level')}</Label>
                <Select value={values.analytics_level} onChange={(e) => set('analytics_level', e.target.value as 'basic' | 'full')}>
                  <option value="basic">{t('packages.analytics.basic')}</option>
                  <option value="full">{t('packages.analytics.full')}</option>
                </Select>
              </div>
            </div>
          </section>
        </div>

        <FormErrorSummary
          errors={errors}
          labels={{
            name_ar: t('packages.field.name_ar'),
            name_en: t('packages.field.name_en'),
            slug: t('packages.field.slug'),
            monthly_price: t('packages.field.monthly_price'),
          }}
        />

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={busy}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={busy}>{busy ? t('common.loading') : t('common.save')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
