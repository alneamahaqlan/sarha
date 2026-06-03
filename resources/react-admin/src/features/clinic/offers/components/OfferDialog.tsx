import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { FileUpload } from '@/components/forms/FileUpload';
import { useClinicServices } from '@/features/clinic/services/hooks';

import { useCreateClinicOffer, useUpdateClinicOffer } from '../hooks';
import type { Offer, OfferFormValues, OfferType } from '../types';

/**
 * Local datetime ↔ ISO helpers. The form uses datetime-local inputs
 * (browser-rendered in the user's tz); the API takes ISO 8601.
 */
function toLocalInput(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}
function fromLocalInput(local: string | null | undefined): string | null {
  if (!local) return null;
  return new Date(local).toISOString();
}

const schema = z.object({
  type: z.enum(['service', 'general']),
  service_id: z.union([z.number(), z.nan()]).nullish()
    .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
  title: z.string().min(1).max(255),
  description: z.string().max(2000).nullish().or(z.literal('')),
  image: z.string().nullish(),
  old_price: z.union([z.number(), z.nan()]).nullish()
    .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
  price: z.union([z.number(), z.nan()]).nullish()
    .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
  starts_at_local: z.string().nullish(),
  ends_at_local: z.string().min(1),
  is_featured: z.boolean(),
  is_active: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

interface Props {
  offer: Offer | null;
  onClose: () => void;
}

export function OfferDialog({ offer, onClose }: Props) {
  const { t } = useTranslation();
  const { data: services } = useClinicServices({ per_page: 200 });
  const create = useCreateClinicOffer();
  const update = useUpdateClinicOffer(offer?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      type: offer?.type ?? 'service',
      service_id: offer?.service_id ?? null,
      title: offer?.title ?? '',
      description: offer?.description ?? '',
      image: offer?.image ?? '',
      old_price: offer?.old_price ?? null,
      price: offer?.price ?? null,
      starts_at_local: toLocalInput(offer?.starts_at),
      ends_at_local: toLocalInput(offer?.ends_at),
      is_featured: offer?.is_featured ?? false,
      is_active: offer?.is_active ?? true,
    },
  });

  const offerType = form.watch('type');
  const selectedServiceId = form.watch('service_id');

  // When the admin picks "service" type + a service, prefill the price
  // and (for a fresh create) the title from the service. Editing keeps
  // any admin overrides so we don't overwrite a tailored title.
  const selectedService = services?.data.find((s) => s.id === selectedServiceId) ?? null;
  const [autoFilled, setAutoFilled] = useState(false);
  useEffect(() => {
    if (offerType !== 'service' || !selectedService) return;
    if (!offer && !autoFilled) {
      if (!form.getValues('title')) form.setValue('title', selectedService.name);
      form.setValue('old_price', selectedService.price);
      setAutoFilled(true);
    }
  }, [offerType, selectedService, offer, autoFilled, form]);

  // Switching to "general" wipes service_id so we can't carry over a
  // stale link and trip the backend invariant check.
  useEffect(() => {
    if (offerType === 'general' && form.getValues('service_id')) {
      form.setValue('service_id', null);
    }
  }, [offerType, form]);

  const onSubmit = async (v: FormValues) => {
    const payload: OfferFormValues = {
      type: v.type as OfferType,
      service_id: v.type === 'service' ? v.service_id ?? null : null,
      title: v.title,
      description: v.description?.trim() ? v.description : null,
      image: v.image?.trim() ? v.image : null,
      old_price: v.old_price,
      price: v.price,
      starts_at: fromLocalInput(v.starts_at_local) ?? new Date().toISOString(),
      ends_at: fromLocalInput(v.ends_at_local) as string,
      is_featured: v.is_featured,
      is_active: v.is_active,
    };
    try {
      if (offer) {
        await update.mutateAsync(payload);
        toast.success(t('clinic_offers.updated', 'تم تحديث العرض'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('clinic_offers.created', 'تم إنشاء العرض'));
      }
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) {
        // Map any "*_at" backend errors back onto the local field names.
        const remap: Record<string, string> = { starts_at: 'starts_at_local', ends_at: 'ends_at_local' };
        Object.entries(ve).forEach(([f, m]) => {
          const target = (remap[f] ?? f) as keyof FormValues;
          form.setError(target, { message: m[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;
  const isServiceType = offerType === 'service';

  // Live discount calc — shown as a small chip under the prices so the
  // admin sees instant feedback as they type.
  const oldP = form.watch('old_price');
  const newP = form.watch('price');
  const discountPct = oldP && newP && oldP > newP && oldP > 0
    ? Math.floor(((oldP - newP) / oldP) * 100)
    : null;
  const savedAmount = oldP && newP && oldP > newP ? oldP - newP : null;

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{offer ? t('clinic_offers.edit', 'تعديل العرض') : t('clinic_offers.create', 'إنشاء عرض')}</DialogTitle>
          <DialogDescription>
            {t('clinic_offers.dialog_subtitle', 'إنشاء عرض ترويجي يظهر في تبويب "العروض" على صفحة مجمعك.')}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-5">
          {/* Type chooser — the central decision; everything else depends on it. */}
          <fieldset className="space-y-2">
            <Label>{t('clinic_offers.type', 'نوع العرض')}</Label>
            <div className="grid grid-cols-2 gap-2">
              <button type="button" onClick={() => form.setValue('type', 'service')}
                className={`rounded-md border p-3 text-start text-sm transition-colors ${
                  isServiceType
                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5'
                    : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
                }`}>
                <div className="font-semibold">{t('clinic_offers.type_service', 'على خدمة موجودة')}</div>
                <div className="text-xs text-[var(--color-muted-foreground)]">
                  {t('clinic_offers.type_service_hint', 'العميل يضغط "احجز هذه الخدمة" مباشرة.')}
                </div>
              </button>
              <button type="button" onClick={() => form.setValue('type', 'general')}
                className={`rounded-md border p-3 text-start text-sm transition-colors ${
                  !isServiceType
                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/5'
                    : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
                }`}>
                <div className="font-semibold">{t('clinic_offers.type_general', 'عرض ترويجي عام')}</div>
                <div className="text-xs text-[var(--color-muted-foreground)]">
                  {t('clinic_offers.type_general_hint', 'العميل يضغط "تواصل للاستفسار".')}
                </div>
              </button>
            </div>
          </fieldset>

          {isServiceType && (
            <div className="space-y-1.5">
              <Label htmlFor="service_id">{t('clinic_offers.service', 'الخدمة المرتبطة')}</Label>
              <select
                id="service_id"
                value={form.watch('service_id') ?? ''}
                onChange={(e) => form.setValue('service_id', e.target.value ? Number(e.target.value) : null, { shouldDirty: true })}
                className="flex h-9 w-full rounded-md border border-[var(--color-border)] bg-transparent px-3 py-1 text-sm shadow-sm"
              >
                <option value="">{t('clinic_offers.pick_service', '— اختر خدمة —')}</option>
                {services?.data.map((s) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
              {form.formState.errors.service_id && (
                <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.service_id.message}</p>
              )}
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="title">{t('clinic_offers.title_field', 'عنوان العرض')}</Label>
            <Input id="title" {...form.register('title')} maxLength={255} />
            {form.formState.errors.title && (
              <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.title.message}</p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="old_price">{t('clinic_offers.old_price', 'السعر قبل الخصم')}</Label>
              <Input id="old_price" type="number" min={0} step="0.01"
                {...form.register('old_price', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">{t('clinic_offers.price', 'السعر بعد الخصم')}</Label>
              <Input id="price" type="number" min={0} step="0.01"
                {...form.register('price', { valueAsNumber: true })} />
            </div>
          </div>
          {discountPct !== null && (
            <div className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              {t('clinic_offers.discount_summary', 'خصم {{pct}}% — وفّر {{amount}} ريال', {
                pct: discountPct,
                amount: savedAmount?.toLocaleString() ?? '',
              })}
            </div>
          )}
          {form.formState.errors.old_price && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.old_price.message}</p>
          )}

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="starts_at_local">{t('clinic_offers.starts_at', 'يبدأ في')}</Label>
              <Input id="starts_at_local" type="datetime-local" {...form.register('starts_at_local')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="ends_at_local">{t('clinic_offers.ends_at', 'ينتهي في')}</Label>
              <Input id="ends_at_local" type="datetime-local" {...form.register('ends_at_local')} />
              {form.formState.errors.ends_at_local && (
                <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.ends_at_local.message}</p>
              )}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="description">{t('clinic_offers.description', 'وصف قصير (اختياري)')}</Label>
            <Textarea id="description" rows={2} {...form.register('description')} />
          </div>

          <div className="space-y-1.5">
            <Label>{t('clinic_offers.image', 'صورة العرض')}</Label>
            <FileUpload
              value={form.watch('image')}
              onChange={(p) => form.setValue('image', p ?? '', { shouldDirty: true })}
              directory="offers"
            />
            <p className="text-xs text-[var(--color-muted-foreground)]">
              {t('clinic_offers.image_hint', 'اتركها فارغة لاستخدام صورة الخدمة المرتبطة.')}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-6 pt-2">
            <div className="flex items-center gap-2">
              <Switch checked={form.watch('is_featured')}
                onCheckedChange={(c) => form.setValue('is_featured', c, { shouldDirty: true })} />
              <Label>{t('clinic_offers.is_featured', 'عرض مميّز')}</Label>
            </div>
            <div className="flex items-center gap-2">
              <Switch checked={form.watch('is_active')}
                onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_offers.is_active', 'نشط')}</Label>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? t('common.saving', 'يحفظ…') : t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
