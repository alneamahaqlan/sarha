import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select } from '@/components/ui/select';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { createAdminSchema, updateAdminSchema } from '../schemas/admin.schema';
import { useCreateAdmin, useUpdateAdmin } from '../hooks';
import type { AdminFormValues, AdminUser } from '../types';

interface Props {
  admin?: AdminUser | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function AdminForm({ admin, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const isEdit = !!admin;

  const form = useForm<AdminFormValues>({
    resolver: zodResolver(isEdit ? updateAdminSchema : createAdminSchema),
    defaultValues: {
      name: admin?.name ?? '',
      email: admin?.email ?? '',
      password: '',
      password_confirmation: '',
      role: admin?.role ?? 'admin',
      is_active: admin?.is_active ?? true,
    },
  });

  useEffect(() => {
    form.reset({
      name: admin?.name ?? '',
      email: admin?.email ?? '',
      password: '',
      password_confirmation: '',
      role: admin?.role ?? 'admin',
      is_active: admin?.is_active ?? true,
    });
  }, [admin, form]);

  const create = useCreateAdmin();
  const update = useUpdateAdmin(admin?.id ?? 0);

  const onSubmit = async (values: AdminFormValues) => {
    try {
      const payload: AdminFormValues = { ...values };
      if (isEdit && !payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
      }

      if (isEdit) {
        await update.mutateAsync(payload);
        toast.success(t('admins.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('admins.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof AdminFormValues, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="name">{t('admins.name')}</Label>
          <Input id="name" {...form.register('name')} />
          {form.formState.errors.name && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="email">{t('admins.email')}</Label>
          <Input id="email" type="email" dir="ltr" {...form.register('email')} />
          {form.formState.errors.email && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.email.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="role">{t('admins.role')}</Label>
          <Select id="role" {...form.register('role')}>
            <option value="super_admin">{t('admins.role_super_admin')}</option>
            <option value="admin">{t('admin')}</option>
            <option value="sales">{t('admins.role_support')}</option>
          </Select>
          {form.formState.errors.role && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.role.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">{t('admins.password')}</Label>
          <Input id="password" type="password" autoComplete="new-password" {...form.register('password')} />
          {isEdit && (
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('admins.password_optional_hint')}</p>
          )}
          {form.formState.errors.password && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.password.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password_confirmation">{t('admins.password')}*</Label>
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            {...form.register('password_confirmation')}
          />
          {form.formState.errors.password_confirmation && (
            <p className="text-xs text-[var(--color-destructive)]">
              {form.formState.errors.password_confirmation.message}
            </p>
          )}
        </div>

        <div className="flex items-end gap-3 pb-2 md:col-span-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('admins.is_active')}</Label>
        </div>
      </div>

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
