import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useClinicProfile, useUpdateClinicProfile } from '../hooks';

const schema = z.object({
  name: z.string().min(1).max(255),
  phone: z.string().min(1).max(20),
  email: z.string().email().nullish().or(z.literal('')),
  address: z.string().nullish(),
  description: z.string().nullish(),
  website: z.string().url().nullish().or(z.literal('')),
  instagram: z.string().max(255).nullish(),
  twitter: z.string().max(255).nullish(),
  snapchat: z.string().max(255).nullish(),
  password: z.string().min(8).optional().or(z.literal('')),
});
type FormValues = z.infer<typeof schema>;

export function ClinicProfilePage() {
  const { t } = useTranslation();
  const { data: clinic, isLoading } = useClinicProfile();
  const mut = useUpdateClinicProfile();

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: '', phone: '', email: '', address: '', description: '',
      website: '', instagram: '', twitter: '', snapchat: '', password: '',
    },
  });

  useEffect(() => {
    if (clinic) {
      form.reset({
        name: clinic.name ?? '',
        phone: clinic.phone ?? '',
        email: clinic.email ?? '',
        address: clinic.address ?? '',
        description: clinic.description ?? '',
        website: clinic.website ?? '',
        instagram: clinic.instagram ?? '',
        twitter: clinic.twitter ?? '',
        snapchat: clinic.snapchat ?? '',
        password: '',
      });
    }
  }, [clinic, form]);

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: FormValues = { ...v };
      if (!payload.password) delete payload.password;
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
    </div>
  );
}
