import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';
import { X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { FileUpload } from '@/components/forms/FileUpload';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useClinicLookup, useCityLookup, useCategoryLookup } from '@/features/lookups/hooks';

import { landingPageFormSchema, slugify, type LandingPageFormSchema } from '../schemas/landing-page.schema';
import { useCreateLandingPage, useUpdateLandingPage } from '../hooks';
import { LANDING_STATUSES, LANDING_TYPES, type LandingPage, type LandingPageFormValues } from '../types';

interface Props {
  page?: LandingPage | null;
  onSuccess?: (page: LandingPage) => void;
  onCancel?: () => void;
}

// datetime-local <-> ISO helpers. The backend accepts either; we keep the
// input bound to the "YYYY-MM-DDTHH:mm" shape the native control needs.
function toLocalInput(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function defaults(page?: LandingPage | null): LandingPageFormSchema {
  return {
    type: page?.type ?? 'clinic',
    status: page?.status ?? 'draft',
    slug: page?.slug ?? '',
    title_ar: page?.title_ar ?? '',
    title_en: page?.title_en ?? '',
    internal_name: page?.internal_name ?? '',
    clinic_id: page?.clinic_id ?? null,
    offer_id: page?.offer_id ?? null,
    city_id: page?.city_id ?? null,
    category_id: page?.category_id ?? null,
    comparison_clinic_ids: page?.comparison_clinic_ids ?? [],
    cover_image: page?.cover_image ?? '',
    social_image: page?.social_image ?? '',
    starts_at: toLocalInput(page?.starts_at),
    ends_at: toLocalInput(page?.ends_at),
    cta_label_ar: page?.cta_label_ar ?? '',
    cta_label_en: page?.cta_label_en ?? '',
    cta_url: page?.cta_url ?? '',
    cta_style: (page?.cta_style as LandingPageFormSchema['cta_style']) ?? 'book',
    whatsapp_phone: page?.whatsapp_phone ?? '',
    call_phone: page?.call_phone ?? '',
    whatsapp_enabled: page?.whatsapp_enabled ?? true,
    call_enabled: page?.call_enabled ?? true,
  };
}

export function LandingPageForm({ page, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const isEdit = !!page;

  const form = useForm<LandingPageFormSchema>({
    resolver: zodResolver(landingPageFormSchema),
    defaultValues: defaults(page),
  });

  useEffect(() => { form.reset(defaults(page)); }, [page, form]);

  const { data: clinics } = useClinicLookup();
  const { data: cities } = useCityLookup();
  const { data: categories } = useCategoryLookup();

  const create = useCreateLandingPage();
  const update = useUpdateLandingPage(page?.id ?? 0);

  const type = form.watch('type');
  const comparisonIds = form.watch('comparison_clinic_ids') ?? [];

  const addComparison = (id: number) => {
    if (!id || comparisonIds.includes(id)) return;
    form.setValue('comparison_clinic_ids', [...comparisonIds, id], { shouldDirty: true });
  };
  const removeComparison = (id: number) => {
    form.setValue('comparison_clinic_ids', comparisonIds.filter((x) => x !== id), { shouldDirty: true });
  };

  const onSubmit = async (values: LandingPageFormSchema) => {
    // Laravel's `nullable` only skips null — NOT an empty string. So an empty
    // optional field like cta_url/canonical_url ("") still hits its `url` rule
    // and fails with an error on a hidden field (silent rejection). Convert
    // every blank optional string to null before sending. `type` and `slug`
    // are required and never blank here.
    const payload: Record<string, unknown> = { ...values };
    for (const [k, v] of Object.entries(payload)) {
      if (v === '') payload[k] = null;
    }

    // comparison_clinic_ids is `nullable|array|min:2` on the backend. An empty
    // array is NOT null, so for every non-comparison page the default []
    // tripped `min:2` and silently rejected the save. Only send it when this
    // is a comparison page that actually has clinics selected; otherwise omit.
    if (values.type !== 'comparison' || !(values.comparison_clinic_ids?.length)) {
      delete payload.comparison_clinic_ids;
    }
    try {
      const saved = isEdit
        ? await update.mutateAsync(payload as Partial<LandingPageFormValues>)
        : await create.mutateAsync(payload as LandingPageFormValues);
      toast.success(isEdit ? t('landing_pages.updated') : t('landing_pages.created'));
      onSuccess?.(saved);
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        const entries = Object.entries(validation);
        entries.forEach(([field, msgs]) => {
          form.setError(field as keyof LandingPageFormSchema, { message: msgs[0] });
        });
        // Some failing fields are conditionally hidden (cta_url, comparison
        // clinics), so always surface the first message as a toast too.
        if (entries.length) toast.error(entries[0][1][0]);
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;
  const err = (k: keyof LandingPageFormSchema) => form.formState.errors[k]?.message as string | undefined;

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-5">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label>{t('landing_pages.type')}</Label>
          <Select disabled={isEdit} value={type} onChange={(e) => form.setValue('type', e.target.value as LandingPageFormSchema['type'], { shouldDirty: true })}>
            {LANDING_TYPES.map((tp) => <option key={tp} value={tp}>{t(`landing_pages.types.${tp}`)}</option>)}
          </Select>
          {isEdit && <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.type_locked')}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>{t('landing_pages.status')}</Label>
          <Select value={form.watch('status')} onChange={(e) => form.setValue('status', e.target.value as LandingPageFormSchema['status'], { shouldDirty: true })}>
            {LANDING_STATUSES.map((s) => <option key={s} value={s}>{t(`landing_pages.statuses.${s}`)}</option>)}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="internal_name">{t('landing_pages.internal_name')}</Label>
          <Input id="internal_name" {...form.register('internal_name')} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="slug">{t('landing_pages.slug')}</Label>
          <Input id="slug" dir="ltr" {...form.register('slug')} />
          <FieldError message={err('slug')} />
          <p className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">/l/{form.watch('slug') || '...'}</p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="title_ar">{t('landing_pages.title_ar')}</Label>
          <Input
            id="title_ar"
            {...form.register('title_ar', {
              onBlur: (e) => {
                if (isEdit) return;
                if (!form.getValues('slug')) form.setValue('slug', slugify(e.target.value), { shouldDirty: true });
              },
            })}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="title_en">{t('landing_pages.title_en')}</Label>
          <Input id="title_en" dir="ltr" {...form.register('title_en')} />
        </div>
      </div>

      {/* Linked entity — varies by type */}
      {type === 'clinic' && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.linked_clinic')}</Label>
          <Select value={form.watch('clinic_id') ?? ''} onChange={(e) => form.setValue('clinic_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })}>
            <option value="">—</option>
            {clinics?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          <FieldError message={err('clinic_id')} />
        </div>
      )}

      {type === 'offer' && (
        <div className="space-y-1.5">
          <Label htmlFor="offer_id">{t('landing_pages.linked_offer')}</Label>
          <Input id="offer_id" type="number" dir="ltr" value={form.watch('offer_id') ?? ''} onChange={(e) => form.setValue('offer_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.linked_offer_hint')}</p>
        </div>
      )}

      {type === 'city' && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.linked_city')}</Label>
          <Select value={form.watch('city_id') ?? ''} onChange={(e) => form.setValue('city_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })}>
            <option value="">—</option>
            {cities?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>
      )}

      {type === 'category' && (
        <div className="space-y-1.5">
          <Label>{t('landing_pages.linked_category')}</Label>
          <Select value={form.watch('category_id') ?? ''} onChange={(e) => form.setValue('category_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })}>
            <option value="">—</option>
            {categories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>
      )}

      {type === 'comparison' && (
        <div className="space-y-2">
          <Label>{t('landing_pages.comparison_clinics')}</Label>
          <Select value="" onChange={(e) => { addComparison(Number(e.target.value)); e.target.value = ''; }}>
            <option value="">{t('landing_pages.add_clinic')}</option>
            {clinics?.filter((c) => !comparisonIds.includes(c.id)).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          <div className="flex flex-wrap gap-2">
            {comparisonIds.map((id) => {
              const c = clinics?.find((x) => x.id === id);
              return (
                <Badge key={id} variant="muted" className="gap-1">
                  {c?.name ?? `#${id}`}
                  <button type="button" onClick={() => removeComparison(id)} aria-label="remove"><X className="h-3 w-3" /></button>
                </Badge>
              );
            })}
          </div>
          {comparisonIds.length < 2 && <p className="text-xs text-[var(--color-muted-foreground)]">{t('landing_pages.comparison_min')}</p>}
        </div>
      )}

      {/* Publish window */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="starts_at">{t('landing_pages.starts_at')}</Label>
          <Input id="starts_at" type="datetime-local" dir="ltr" {...form.register('starts_at')} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="ends_at">{t('landing_pages.ends_at')}</Label>
          <Input id="ends_at" type="datetime-local" dir="ltr" {...form.register('ends_at')} />
          <FieldError message={err('ends_at')} />
        </div>
      </div>

      {/* Images */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <FileUpload label={t('landing_pages.cover_image')} directory="landing-pages" value={form.watch('cover_image')} onChange={(p) => form.setValue('cover_image', p ?? '', { shouldDirty: true })} />
        <FileUpload label={t('landing_pages.social_image')} directory="landing-pages" value={form.watch('social_image')} onChange={(p) => form.setValue('social_image', p ?? '', { shouldDirty: true })} />
      </div>

      {/* CTA */}
      <div className="rounded-lg border border-[var(--color-border)] p-4 space-y-4">
        <p className="text-sm font-medium">{t('landing_pages.cta_section')}</p>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div className="space-y-1.5">
            <Label>{t('landing_pages.cta_style')}</Label>
            <Select value={form.watch('cta_style') ?? 'book'} onChange={(e) => form.setValue('cta_style', e.target.value as LandingPageFormSchema['cta_style'], { shouldDirty: true })}>
              <option value="book">{t('landing_pages.cta_styles.book')}</option>
              <option value="scroll_booking">{t('landing_pages.cta_styles.scroll_booking')}</option>
              <option value="link">{t('landing_pages.cta_styles.link')}</option>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cta_label_ar">{t('landing_pages.cta_label_ar')}</Label>
            <Input id="cta_label_ar" {...form.register('cta_label_ar')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="cta_label_en">{t('landing_pages.cta_label_en')}</Label>
            <Input id="cta_label_en" dir="ltr" {...form.register('cta_label_en')} />
          </div>
        </div>
        {form.watch('cta_style') === 'link' && (
          <div className="space-y-1.5">
            <Label htmlFor="cta_url">{t('landing_pages.cta_url')}</Label>
            <Input id="cta_url" dir="ltr" {...form.register('cta_url')} />
            <FieldError message={err('cta_url')} />
          </div>
        )}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <div className="flex items-center gap-3">
              <Switch checked={form.watch('whatsapp_enabled') ?? true} onCheckedChange={(v) => form.setValue('whatsapp_enabled', v, { shouldDirty: true })} />
              <Label>{t('landing_pages.whatsapp_enabled')}</Label>
            </div>
            <Input dir="ltr" placeholder="9665XXXXXXXX" {...form.register('whatsapp_phone')} />
          </div>
          <div className="space-y-1.5">
            <div className="flex items-center gap-3">
              <Switch checked={form.watch('call_enabled') ?? true} onCheckedChange={(v) => form.setValue('call_enabled', v, { shouldDirty: true })} />
              <Label>{t('landing_pages.call_enabled')}</Label>
            </div>
            <Input dir="ltr" placeholder="05XXXXXXXX" {...form.register('call_phone')} />
          </div>
        </div>
      </div>

      <FormErrorSummary
        errors={form.formState.errors}
        labels={{
          slug: t('landing_pages.slug'),
          clinic_id: t('landing_pages.linked_clinic'),
          ends_at: t('landing_pages.ends_at'),
          cta_url: t('landing_pages.cta_url'),
        }}
      />

      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>
            {t('common.cancel')}
          </Button>
        )}
        <Button type="submit" disabled={submitting}>
          {submitting ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </form>
  );
}
