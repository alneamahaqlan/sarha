import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { FileUpload } from '@/components/forms/FileUpload';
import { CategoryIcon } from '@/components/ui/CategoryIcon';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useCategoryLookup, useCityLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { apiClient } from '@/lib/api-client';

import { useCreateClinic, useUpdateClinic } from '../hooks';
import { CLINIC_PLANS, CLINIC_STATUSES, type Clinic } from '../types';

const schema = z.object({
  name: z.string().min(1).max(255),
  slug: z.string().max(255).optional().or(z.literal('')),
  phone: z.string().min(1).max(20),
  email: z.string().email().nullish().or(z.literal('')),
  license_number: z.string().max(255).nullish().or(z.literal('')),
  password: z.string().min(8).optional().or(z.literal('')),
  city_id: z.coerce.number().int().positive(),
  address: z.string().nullish(),
  district: z.string().max(255).nullish(),
  description: z.string().nullish(),
  status: z.enum(['pending', 'active', 'suspended', 'rejected']),
  subscription_type: z.enum(['basic', 'premium']).nullish().or(z.literal('')),
  subscription_starts_at: z.string().nullish().or(z.literal('')),
  subscription_ends_at: z.string().nullish().or(z.literal('')),
  is_featured: z.boolean(),
  rejection_reason: z.string().nullish(),
  website: z.string().url().nullish().or(z.literal('')),
  instagram: z.string().max(255).nullish(),
  twitter: z.string().max(255).nullish(),
  snapchat: z.string().max(255).nullish(),
  tiktok: z.string().max(255).nullish(),
  google_place_id: z.string().max(255).nullish(),
  maps_url: z.string().url().nullish().or(z.literal('')),
  logo: z.string().nullish(),
  gallery: z.array(z.string()).default([]),
  categories: z.array(z.coerce.number()).default([]),
});
type FormValues = z.infer<typeof schema>;

interface Props {
  clinic?: Clinic | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function toDateTimeLocal(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function ClinicForm({ clinic, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: cities } = useCityLookup();
  const { data: categories } = useCategoryLookup();
  const create = useCreateClinic();
  const update = useUpdateClinic(clinic?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '', slug: '', phone: '', email: '', license_number: '', password: '',
      city_id: 0, address: '', district: '', description: '',
      status: 'pending', subscription_type: '',
      subscription_starts_at: '', subscription_ends_at: '',
      is_featured: false, rejection_reason: '',
      website: '', instagram: '', twitter: '', snapchat: '', tiktok: '',
      google_place_id: '', maps_url: '',
      logo: '', gallery: [], categories: [],
    },
  });

  useEffect(() => {
    if (clinic) {
      form.reset({
        name: clinic.name ?? '',
        slug: clinic.slug ?? '',
        phone: clinic.phone ?? '',
        email: clinic.email ?? '',
        license_number: clinic.license_number ?? '',
        password: '',
        city_id: clinic.city_id ?? 0,
        address: clinic.address ?? '',
        district: clinic.district ?? '',
        description: clinic.description ?? '',
        status: clinic.status,
        subscription_type: clinic.subscription_type ?? '',
        subscription_starts_at: toDateTimeLocal(clinic.subscription_starts_at),
        subscription_ends_at: toDateTimeLocal(clinic.subscription_ends_at),
        is_featured: clinic.is_featured ?? false,
        rejection_reason: clinic.rejection_reason ?? '',
        website: clinic.website ?? '',
        instagram: clinic.instagram ?? '',
        twitter: clinic.twitter ?? '',
        snapchat: clinic.snapchat ?? '',
        tiktok: clinic.tiktok ?? '',
        google_place_id: clinic.google_place_id ?? '',
        maps_url: clinic.maps_url ?? '',
        logo: clinic.logo ?? '',
        gallery: clinic.gallery ?? [],
        categories: clinic.category_ids ?? clinic.categories?.map((c) => c.id) ?? [],
      });
    }
  }, [clinic, form]);

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: Record<string, unknown> = { ...v };
      if (!payload.password) delete payload.password;
      if (payload.license_number === '') payload.license_number = null;
      if (payload.subscription_type === '') payload.subscription_type = null;
      if (payload.subscription_starts_at === '') payload.subscription_starts_at = null;
      if (payload.subscription_ends_at === '') payload.subscription_ends_at = null;
      if (payload.status !== 'rejected') delete payload.rejection_reason;

      if (clinic) {
        await update.mutateAsync(payload as Partial<FormValues>);
        toast.success(t('clinics.updated'));
      } else {
        await create.mutateAsync(payload as FormValues);
        toast.success(t('clinics.created'));
      }
      onSuccess?.();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;
  const selectedCategoryIds = form.watch('categories');
  const gallery = form.watch('gallery');
  const status = form.watch('status');

  const toggleCategory = (id: number) => {
    const next = selectedCategoryIds.includes(id)
      ? selectedCategoryIds.filter((x) => x !== id)
      : [...selectedCategoryIds, id];
    form.setValue('categories', next, { shouldDirty: true });
  };

  const uploadGallery = async (file: File) => {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('directory', 'clinics/gallery');
    try {
      const res = await apiClient.post<{ data: { path: string } }>('/uploads', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      form.setValue('gallery', [...gallery, res.data.data.path], { shouldDirty: true });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const removeGalleryItem = (path: string) => {
    form.setValue('gallery', gallery.filter((p) => p !== path), { shouldDirty: true });
  };

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <Tabs defaultValue="basic">
        <TabsList className="w-full overflow-x-auto">
          <TabsTrigger value="basic">{t('clinics.form.tab_basic')}</TabsTrigger>
          <TabsTrigger value="subscription">{t('clinics.form.tab_subscription')}</TabsTrigger>
          <TabsTrigger value="categories">{t('clinics.form.tab_categories')}</TabsTrigger>
          <TabsTrigger value="social">{t('clinics.form.tab_social')}</TabsTrigger>
          <TabsTrigger value="images">{t('clinics.form.tab_images')}</TabsTrigger>
        </TabsList>

        <TabsContent value="basic">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinics.form.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="slug">{t('clinics.form.slug')}</Label>
              <Input id="slug" dir="ltr" {...form.register('slug')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="phone">{t('clinics.form.phone')}</Label>
              <Input id="phone" type="tel" dir="ltr" {...form.register('phone')} />
              {form.formState.errors.phone && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.phone.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email">{t('clinics.form.email')}</Label>
              <Input id="email" type="email" dir="ltr" {...form.register('email')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="license_number">{t('clinics.form.license_number')}</Label>
              <Input id="license_number" dir="ltr" {...form.register('license_number')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="password">{clinic ? t('clinics.form.new_password') : t('clinics.form.password')}</Label>
              <Input id="password" type="password" autoComplete="new-password" {...form.register('password')} />
              {clinic && <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinics.form.password_hint')}</p>}
              {form.formState.errors.password && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.password.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="city_id">{t('clinics.form.city')}</Label>
              <Select
                id="city_id"
                value={form.watch('city_id') || ''}
                onChange={(e) => form.setValue('city_id', Number(e.target.value), { shouldDirty: true, shouldValidate: true })}
              >
                <option value="">—</option>
                {cities?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
              {form.formState.errors.city_id && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.city_id.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="district">{t('clinics.form.district')}</Label>
              <Input id="district" {...form.register('district')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="address">{t('clinics.form.address')}</Label>
              <Textarea id="address" rows={2} {...form.register('address')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="description">{t('clinics.form.description')}</Label>
              <Textarea id="description" rows={4} {...form.register('description')} />
            </div>
          </div>
        </TabsContent>

        <TabsContent value="subscription">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="status">{t('clinics.form.status')}</Label>
              <Select
                id="status"
                value={form.watch('status')}
                onChange={(e) => form.setValue('status', e.target.value as FormValues['status'], { shouldDirty: true, shouldValidate: true })}
              >
                {CLINIC_STATUSES.map((s) => <option key={s} value={s}>{t(`clinics.status.${s}`)}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="subscription_type">{t('clinics.form.subscription_type')}</Label>
              <Select
                id="subscription_type"
                value={form.watch('subscription_type') ?? ''}
                onChange={(e) => form.setValue('subscription_type', (e.target.value || '') as FormValues['subscription_type'], { shouldDirty: true })}
              >
                <option value="">—</option>
                {CLINIC_PLANS.map((p) => <option key={p} value={p}>{t(`clinics.plan.${p}`)}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="subscription_starts_at">{t('clinics.form.subscription_starts_at')}</Label>
              <Input id="subscription_starts_at" type="datetime-local" {...form.register('subscription_starts_at')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="subscription_ends_at">{t('clinics.form.subscription_ends_at')}</Label>
              <Input id="subscription_ends_at" type="datetime-local" {...form.register('subscription_ends_at')} />
            </div>
            <div className="flex items-end gap-3 pb-2 md:col-span-2">
              <Switch checked={form.watch('is_featured')} onCheckedChange={(c) => form.setValue('is_featured', c, { shouldDirty: true })} />
              <Label>{t('clinics.form.is_featured')}</Label>
            </div>
            {status === 'rejected' && (
              <div className="space-y-1.5 md:col-span-2">
                <Label htmlFor="rejection_reason">{t('clinics.form.rejection_reason')}</Label>
                <Textarea id="rejection_reason" rows={3} {...form.register('rejection_reason')} />
              </div>
            )}
          </div>
        </TabsContent>

        <TabsContent value="categories">
          <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
            {categories?.map((c) => (
              <label key={c.id} className="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--color-border)] p-2 hover:bg-[var(--color-muted)]">
                <input
                  type="checkbox"
                  checked={selectedCategoryIds.includes(c.id)}
                  onChange={() => toggleCategory(c.id)}
                  className="h-4 w-4"
                />
                <CategoryIcon emoji={c.emoji} className="h-4 w-4 shrink-0 text-[var(--color-muted-foreground)]" />
                <span className="text-sm">{c.name}</span>
              </label>
            ))}
          </div>
        </TabsContent>

        <TabsContent value="social">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="website">{t('clinics.form.website')}</Label>
              <Input id="website" type="url" dir="ltr" {...form.register('website')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="instagram">{t('clinics.form.instagram')}</Label>
              <Input id="instagram" {...form.register('instagram')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="twitter">{t('clinics.form.twitter')}</Label>
              <Input id="twitter" {...form.register('twitter')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="snapchat">{t('clinics.form.snapchat')}</Label>
              <Input id="snapchat" {...form.register('snapchat')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="tiktok">{t('clinics.form.tiktok')}</Label>
              <Input id="tiktok" {...form.register('tiktok')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="maps_url">{t('clinics.form.maps_url')}</Label>
              <Input id="maps_url" type="url" dir="ltr" placeholder="https://maps.app.goo.gl/…" {...form.register('maps_url')} />
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinics.form.maps_url_hint')}</p>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="google_place_id">{t('clinics.form.google_place_id')}</Label>
              <Input id="google_place_id" dir="ltr" {...form.register('google_place_id')} />
            </div>
          </div>
        </TabsContent>

        <TabsContent value="images">
          <div className="space-y-4">
            <FileUpload
              label={t('clinics.form.logo')}
              value={form.watch('logo')}
              onChange={(p) => form.setValue('logo', p, { shouldDirty: true })}
              directory="clinics/logos"
            />
            <div className="space-y-2">
              <Label>{t('clinics.form.gallery')}</Label>
              <div className="grid grid-cols-3 gap-2 md:grid-cols-5">
                {gallery.map((path) => (
                  <div key={path} className="relative aspect-square overflow-hidden rounded-md border border-[var(--color-border)]">
                    <img src={`/storage/${path}`} alt="" className="h-full w-full object-cover" />
                    <button
                      type="button"
                      onClick={() => removeGalleryItem(path)}
                      className="absolute end-1 top-1 inline-flex h-6 w-6 items-center justify-center rounded bg-black/60 text-white hover:bg-black/80"
                      aria-label={t('common.delete')}
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                ))}
                {gallery.length < 10 && (
                  <label className="flex aspect-square cursor-pointer items-center justify-center rounded-md border border-dashed border-[var(--color-border)] text-xs text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]">
                    <input
                      type="file"
                      accept="image/*"
                      className="hidden"
                      onChange={(e) => {
                        const f = e.target.files?.[0];
                        if (f) uploadGallery(f);
                        e.target.value = '';
                      }}
                    />
                    +
                  </label>
                )}
              </div>
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinics.form.gallery_hint')}</p>
            </div>
          </div>
        </TabsContent>
      </Tabs>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>{t('common.cancel')}</Button>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  );
}
