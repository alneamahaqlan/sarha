import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiClient, extractValidationErrors, extractMessage } from '@/lib/api-client';
import { useAuth } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

const schema = z.object({
  guard: z.enum(['admin', 'clinic']),
  email: z.string().email().optional().or(z.literal('')),
  phone: z.string().optional().or(z.literal('')),
  password: z.string().min(1),
});

type FormValues = z.infer<typeof schema>;

export function LoginPage() {
  const { t } = useTranslation();
  const { locale, setLocale } = useLocale();
  const { refetch } = useAuth();
  const [guard, setGuard] = useState<'admin' | 'clinic'>('admin');

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { guard: 'admin', email: '', phone: '', password: '' },
  });

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload =
        values.guard === 'admin'
          ? { guard: 'admin', email: values.email, password: values.password }
          : { guard: 'clinic', phone: values.phone, password: values.password };
      const res = await apiClient.post('/auth/login', payload);
      return res.data;
    },
    onSuccess: async () => {
      await refetch();
    },
    onError: (err) => {
      const errors = extractValidationErrors(err);
      if (errors) {
        Object.entries(errors).forEach(([field, msgs]) => {
          form.setError(field as keyof FormValues, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    },
  });

  return (
    <div className="flex min-h-screen items-center justify-center bg-gradient-to-b from-[#0066cc] to-[#003a73] p-4">
      <div className="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-xl font-semibold">{t('auth.login_title')}</h1>
          <button
            type="button"
            className="text-xs text-[var(--color-muted-foreground)] hover:underline"
            onClick={() => setLocale(locale === 'ar' ? 'en' : 'ar')}
          >
            {locale === 'ar' ? 'English' : 'العربية'}
          </button>
        </div>

        <div className="mb-4 grid grid-cols-2 gap-2">
          {(['admin', 'clinic'] as const).map((g) => (
            <button
              key={g}
              type="button"
              onClick={() => {
                setGuard(g);
                form.setValue('guard', g);
              }}
              className={`h-9 rounded-md border text-sm transition-colors ${
                guard === g
                  ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                  : 'border-[var(--color-border)] bg-white hover:bg-[var(--color-muted)]'
              }`}
            >
              {t(`auth.${g}`)}
            </button>
          ))}
        </div>

        <form onSubmit={form.handleSubmit((v) => mutation.mutate(v))} className="space-y-3">
          {guard === 'admin' ? (
            <div className="space-y-1">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
              {form.formState.errors.email && (
                <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.email.message}</p>
              )}
            </div>
          ) : (
            <div className="space-y-1">
              <Label htmlFor="phone">{t('auth.phone')}</Label>
              <Input id="phone" autoComplete="tel" {...form.register('phone')} />
              {form.formState.errors.phone && (
                <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.phone.message}</p>
              )}
            </div>
          )}

          <div className="space-y-1">
            <Label htmlFor="password">{t('auth.password')}</Label>
            <Input id="password" type="password" autoComplete="current-password" {...form.register('password')} />
            {form.formState.errors.password && (
              <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.password.message}</p>
            )}
          </div>

          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? t('auth.logging_in') : t('auth.submit')}
          </Button>
        </form>
      </div>
    </div>
  );
}
