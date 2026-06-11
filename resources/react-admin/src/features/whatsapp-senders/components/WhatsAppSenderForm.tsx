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
import { whatsappSenderFormSchema, type WhatsAppSenderFormSchema } from '../schemas/whatsapp-sender.schema';
import { useCreateWhatsAppSender, useUpdateWhatsAppSender } from '../hooks';
import type { WhatsAppSender } from '../types';

interface Props {
  sender?: WhatsAppSender | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function WhatsAppSenderForm({ sender, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();

  const defaults = (): WhatsAppSenderFormSchema => ({
    label: sender?.label ?? '',
    phone: sender?.phone ?? '',
    profile_id: sender?.profile_id ?? '',
    token: '',
    is_active: sender?.is_active ?? true,
    priority: sender?.priority ?? 0,
  });

  const form = useForm<WhatsAppSenderFormSchema>({
    resolver: zodResolver(whatsappSenderFormSchema),
    defaultValues: defaults(),
  });

  useEffect(() => {
    form.reset(defaults());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sender]);

  const create = useCreateWhatsAppSender();
  const update = useUpdateWhatsAppSender(sender?.id ?? 0);

  const onSubmit = async (values: WhatsAppSenderFormSchema) => {
    // An empty token on edit means "keep the stored one" — don't send it.
    const payload = { ...values };
    if (sender && !payload.token) delete payload.token;

    try {
      if (sender) {
        await update.mutateAsync(payload);
        toast.success(t('whatsapp_senders.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('whatsapp_senders.created'));
      }
      onSuccess?.();
    } catch (err) {
      const validation = extractValidationErrors(err);
      if (validation) {
        Object.entries(validation).forEach(([field, msgs]) => {
          form.setError(field as keyof WhatsAppSenderFormSchema, { message: msgs[0] });
        });
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;
  const err = form.formState.errors;

  return (
    <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label htmlFor="label">{t('whatsapp_senders.label')}</Label>
          <Input id="label" {...form.register('label')} placeholder={t('whatsapp_senders.label_placeholder')} />
          <FieldError message={err.label?.message} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="phone">{t('whatsapp_senders.phone')}</Label>
          <Input id="phone" dir="ltr" {...form.register('phone')} placeholder="+9665XXXXXXXX" />
          <FieldError message={err.phone ? (err.phone.message === 'phone_invalid' ? t('whatsapp_senders.phone_invalid') : err.phone.message) : undefined} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="profile_id">{t('whatsapp_senders.profile_id')}</Label>
          <Input id="profile_id" dir="ltr" {...form.register('profile_id')} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('whatsapp_senders.profile_id_help')}</p>
          <FieldError message={err.profile_id?.message} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="token">{t('whatsapp_senders.token')}</Label>
          <Input
            id="token"
            type="password"
            dir="ltr"
            autoComplete="new-password"
            {...form.register('token')}
            placeholder={sender?.token_set ? '••••••••' : ''}
          />
          <p className="text-xs text-[var(--color-muted-foreground)]">
            {sender ? t('whatsapp_senders.token_keep_help') : t('whatsapp_senders.token_help')}
          </p>
          <FieldError message={err.token?.message} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="priority">{t('whatsapp_senders.priority')}</Label>
          <Input id="priority" type="number" {...form.register('priority', { valueAsNumber: true })} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('whatsapp_senders.priority_help')}</p>
          <FieldError message={err.priority?.message} />
        </div>

        <div className="flex items-end gap-3 pb-2">
          <Switch
            checked={form.watch('is_active')}
            onCheckedChange={(v) => form.setValue('is_active', v, { shouldDirty: true })}
          />
          <Label>{t('whatsapp_senders.is_active')}</Label>
        </div>
      </div>

      <FormErrorSummary
        errors={form.formState.errors}
        labels={{
          label: t('whatsapp_senders.label'),
          phone: t('whatsapp_senders.phone'),
          profile_id: t('whatsapp_senders.profile_id'),
          token: t('whatsapp_senders.token'),
          priority: t('whatsapp_senders.priority'),
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
