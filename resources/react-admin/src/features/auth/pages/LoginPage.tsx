import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
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

        <form noValidate onSubmit={form.handleSubmit((v) => mutation.mutate(v))} className="space-y-3">
          {guard === 'admin' ? (
            <div className="space-y-1">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
              <FieldError message={form.formState.errors.email?.message} />
            </div>
          ) : (
            <div className="space-y-1">
              <Label htmlFor="phone">{t('auth.phone')}</Label>
              <Input id="phone" autoComplete="tel" {...form.register('phone')} />
              <FieldError message={form.formState.errors.phone?.message} />
            </div>
          )}

          <div className="space-y-1">
            <Label htmlFor="password">{t('auth.password')}</Label>
            <Input id="password" type="password" autoComplete="current-password" {...form.register('password')} />
            <FieldError message={form.formState.errors.password?.message} />
          </div>

          <FormErrorSummary
            errors={form.formState.errors}
            labels={{
              email: t('auth.email'),
              phone: t('auth.phone'),
              password: t('auth.password'),
            }}
          />

          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? t('auth.logging_in') : t('auth.submit')}
          </Button>
        </form>

        <div className="mt-5 border-t border-[var(--color-border)] pt-4 text-center">
          <p className="mb-2 text-xs text-[var(--color-muted-foreground)]">{t('auth.no_account')}</p>
          <a
            href="/register-clinic"
            className="inline-flex w-full items-center justify-center gap-2 rounded-md border border-[var(--color-primary)] px-4 py-2 text-sm font-medium text-[var(--color-primary)] transition-colors hover:bg-[var(--color-primary)] hover:text-white"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              className="h-4 w-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2}
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
              />
            </svg>
            {t('auth.create_clinic_account')}
          </a>
        </div>
      </div>
    </div>
  );
}
