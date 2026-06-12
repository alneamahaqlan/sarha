import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { DialogFooter } from '@/components/ui/dialog';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { userFormSchema, type UserFormSchema } from '../schemas/user.schema';
import { useCreateUser, useUpdateUser } from '../hooks';
import type { User } from '../types';

interface Props {
  user?: User | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function UserForm({ user, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();

  const form = useForm<UserFormSchema>({
    resolver: zodResolver(userFormSchema),
    defaultValues: {
      name: user?.name ?? '',
      phone: user?.phone ?? '',
      email: user?.email ?? '',
      is_active: user?.is_active ?? true,
    },
  });

  useEffect(() => {
    form.reset({
      name: user?.name ?? '',
      phone: user?.phone ?? '',
      email: user?.email ?? '',
      is_active: user?.is_active ?? true,
    });
  }, [user, form]);

  const create = useCreateUser();
  const update = useUpdateUser(user?.id ?? 0);

  const onSubmit = async (values: UserFormSchema) => {
    try {
      if (user) {
        await update.mutateAsync(values);
        toast.success(t('users.updated'));
      } else {
        await create.mutateAsync(values);
        toast.success(t('users.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof UserFormSchema, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="name">{t('users.name')}</Label>
          <Input id="name" {...form.register('name')} />
          <FieldError message={form.formState.errors.name?.message} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="phone">{t('users.phone')}</Label>
          <Input id="phone" type="tel" dir="ltr" {...form.register('phone')} />
          <FieldError message={form.formState.errors.phone?.message} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="email">{t('users.email')}</Label>
          <Input id="email" type="email" dir="ltr" {...form.register('email')} />
          <FieldError message={form.formState.errors.email?.message} />
        </div>

        <div className="flex items-end gap-3 pb-2 md:col-span-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('users.is_active')}</Label>
        </div>
      </div>

      <FormErrorSummary
        errors={form.formState.errors}
        labels={{
          name: t('users.name'),
          phone: t('users.phone'),
          email: t('users.email'),
        }}
      />

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? t('common.loading') : t('common.save')}
        </Button>
      </DialogFooter>
    </form>
  );
}
