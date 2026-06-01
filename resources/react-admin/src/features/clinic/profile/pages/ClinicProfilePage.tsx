import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { MapPin, Star } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { FileUpload } from '@/components/forms/FileUpload';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useClinicProfile, useClinicReviews, useExtractCoords, useSyncReviews, useUpdateClinicProfile } from '../hooks';
import { WorkingHoursSection } from '../components/WorkingHoursSection';

const schema = z.object({
  name: z.string().min(1).max(255),
  phone: z.string().min(1).max(20),
  email: z.string().email().nullish().or(z.literal('')),
  address: z.string().nullish(),
  district: z.string().max(255).nullish(),
  description: z.string().nullish(),
  website: z.string().url().nullish().or(z.literal('')),
  instagram: z.string().max(255).nullish(),
  twitter: z.string().max(255).nullish(),
  snapchat: z.string().max(255).nullish(),
  logo: z.string().nullish(),
  latitude: z.union([z.number(), z.nan()]).nullish(),
  longitude: z.union([z.number(), z.nan()]).nullish(),
  google_place_id: z.string().max(255).nullish(),
  password: z.string().min(8).optional().or(z.literal('')),
});
type FormValues = z.infer<typeof schema>;

const toNum = (v: number | string | null | undefined): number | null => {
  if (v === null || v === undefined || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

export function ClinicProfilePage() {
  const { t } = useTranslation();
  const { data: clinic, isLoading } = useClinicProfile();
  const mut = useUpdateClinicProfile();
  const extract = useExtractCoords();
  const sync = useSyncReviews();
  const [mapLink, setMapLink] = useState('');

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '', phone: '', email: '', address: '', district: '', description: '',
      website: '', instagram: '', twitter: '', snapchat: '', logo: '',
      latitude: null, longitude: null, google_place_id: '', password: '',
    },
  });

  useEffect(() => {
    if (clinic) {
      form.reset({
        name: clinic.name ?? '',
        phone: clinic.phone ?? '',
        email: clinic.email ?? '',
        address: clinic.address ?? '',
        district: clinic.district ?? '',
        description: clinic.description ?? '',
        website: clinic.website ?? '',
        instagram: clinic.instagram ?? '',
        twitter: clinic.twitter ?? '',
        snapchat: clinic.snapchat ?? '',
        logo: clinic.logo ?? '',
        latitude: toNum(clinic.latitude),
        longitude: toNum(clinic.longitude),
        google_place_id: clinic.google_place_id ?? '',
        password: '',
      });
    }
  }, [clinic, form]);

  const onExtractCoords = async () => {
    if (!mapLink.trim()) return;
    try {
      const c = await extract.mutateAsync(mapLink.trim());
      form.setValue('latitude', c.latitude, { shouldDirty: true });
      form.setValue('longitude', c.longitude, { shouldDirty: true });
      toast.success(t('clinic_profile.coords_extracted'));
    } catch (err) {
      toast.error(extractMessage(err, t('clinic_profile.coords_failed')));
    }
  };

  const onSyncReviews = async () => {
    try {
      const r = await sync.mutateAsync();
      toast.success(t('clinic_profile.reviews_synced', { count: r.fetched }));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: FormValues = { ...v };
      if (!payload.password) delete payload.password;
      // Coerce NaN (empty number inputs) to null so backend validation passes.
      if (typeof payload.latitude === 'number' && Number.isNaN(payload.latitude)) payload.latitude = null;
      if (typeof payload.longitude === 'number' && Number.isNaN(payload.longitude)) payload.longitude = null;
      await mut.mutateAsync(payload);
      form.setValue('password', '');
      toast.success(t('clinic_profile.saved'));
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  if (isLoading) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  return (
    <div className="max-w-3xl space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_profile.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_profile.subtitle')}</p>
      </div>

      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4 rounded-lg border border-[var(--color-border)] bg-white p-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="name">{t('clinic_profile.name')}</Label>
            <Input id="name" {...form.register('name')} />
            {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="phone">{t('clinic_profile.phone')}</Label>
            <Input id="phone" type="tel" dir="ltr" {...form.register('phone')} />
            {form.formState.errors.phone && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.phone.message}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="email">{t('clinic_profile.email')}</Label>
            <Input id="email" type="email" dir="ltr" {...form.register('email')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="district">{t('clinic_profile.district')}</Label>
            <Input id="district" {...form.register('district')} />
            {form.formState.errors.district && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.district.message}</p>}
          </div>
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="address">{t('clinic_profile.address')}</Label>
            <Textarea id="address" rows={2} {...form.register('address')} />
          </div>
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="description">{t('clinic_profile.description')}</Label>
            <Textarea id="description" rows={4} {...form.register('description')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="website">{t('clinic_profile.website')}</Label>
            <Input id="website" type="url" dir="ltr" {...form.register('website')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="instagram">{t('clinic_profile.instagram')}</Label>
            <Input id="instagram" {...form.register('instagram')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="twitter">{t('clinic_profile.twitter')}</Label>
            <Input id="twitter" {...form.register('twitter')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="snapchat">{t('clinic_profile.snapchat')}</Label>
            <Input id="snapchat" {...form.register('snapchat')} />
          </div>
          <div className="md:col-span-2">
            <FileUpload
              label={t('clinic_profile.logo')}
              value={form.watch('logo')}
              onChange={(p) => form.setValue('logo', p, { shouldDirty: true })}
              directory="logos"
            />
          </div>

          {/* Location — Google Maps link → lat/lng */}
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="map_link">{t('clinic_profile.map_link')}</Label>
            <div className="flex gap-2">
              <Input
                id="map_link"
                dir="ltr"
                placeholder="https://maps.google.com/..."
                value={mapLink}
                onChange={(e) => setMapLink(e.target.value)}
              />
              <Button type="button" variant="outline" onClick={onExtractCoords} disabled={extract.isPending || !mapLink.trim()}>
                <MapPin className="h-4 w-4" />
                {extract.isPending ? t('common.loading') : t('clinic_profile.extract_coords')}
              </Button>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="latitude">{t('clinic_profile.latitude')}</Label>
            <Input
              id="latitude"
              type="number"
              step="any"
              dir="ltr"
              {...form.register('latitude', { setValueAs: (v) => (v === '' || v === null ? null : Number(v)) })}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="longitude">{t('clinic_profile.longitude')}</Label>
            <Input
              id="longitude"
              type="number"
              step="any"
              dir="ltr"
              {...form.register('longitude', { setValueAs: (v) => (v === '' || v === null ? null : Number(v)) })}
            />
          </div>

          {/* Google reviews */}
          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="google_place_id">{t('clinic_profile.place_id')}</Label>
            <div className="flex gap-2">
              <Input id="google_place_id" dir="ltr" {...form.register('google_place_id')} />
              <Button type="button" variant="outline" onClick={onSyncReviews} disabled={sync.isPending}>
                <Star className="h-4 w-4" />
                {sync.isPending ? t('common.loading') : t('clinic_profile.fetch_reviews')}
              </Button>
            </div>
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_profile.place_id_hint')}</p>
          </div>

          <div className="space-y-1.5 md:col-span-2">
            <Label htmlFor="password">{t('clinic_profile.new_password')}</Label>
            <Input id="password" type="password" autoComplete="new-password" {...form.register('password')} />
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_profile.password_hint')}</p>
            {form.formState.errors.password && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.password.message}</p>}
          </div>
        </div>

        <div className="border-t border-[var(--color-border)] pt-4">
          <Button type="submit" disabled={mut.isPending}>{mut.isPending ? t('common.loading') : t('clinic_profile.save')}</Button>
        </div>
      </form>

      <WorkingHoursSection />

      <ClinicReviewsSection />
    </div>
  );
}

function ClinicReviewsSection() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicReviews();

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString('ar-SA') : '';

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-6">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">{t('clinic_profile.reviews_title')}</h2>
        {data && data.count > 0 && (
          <div className="flex items-center gap-1.5 text-sm">
            <Star className="h-4 w-4 fill-amber-400 text-amber-500" />
            <span className="font-semibold">{data.average?.toFixed(1)}</span>
            <span className="text-[var(--color-muted-foreground)]">
              ({t('clinic_profile.reviews_count', { count: data.count })})
            </span>
          </div>
        )}
      </div>

      {isLoading ? (
        <p className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</p>
      ) : !data || data.reviews.length === 0 ? (
        <p className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_profile.reviews_empty')}</p>
      ) : (
        <ul className="mt-4 space-y-4">
          {data.reviews.map((r) => (
            <li key={r.id} className="border-b border-[var(--color-border)] pb-4 last:border-0 last:pb-0">
              <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">{r.reviewer_name ?? '—'}</span>
                <div className="flex items-center gap-0.5">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star
                      key={i}
                      className={`h-3.5 w-3.5 ${i < r.rating ? 'fill-amber-400 text-amber-500' : 'text-[var(--color-border)]'}`}
                    />
                  ))}
                </div>
              </div>
              {r.review_text && <p className="mt-1 text-sm text-[var(--color-muted-foreground)]">{r.review_text}</p>}
              {r.reviewed_at && <p className="mt-1 text-xs text-[var(--color-muted-foreground)]">{fmtDate(r.reviewed_at)}</p>}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
