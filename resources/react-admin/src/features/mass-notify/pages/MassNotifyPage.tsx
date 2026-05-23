import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Send } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { massNotifyApi } from '../api';
import { massNotifySchema, type MassNotifySchema } from '../schema';

const AUDIENCES = ['all', 'premium', 'basic', 'expiring'] as const;
const PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const;
const CHANNELS = ['in_app', 'email', 'both'] as const;

export function MassNotifyPage() {
  const { t } = useTranslation();
  const [confirming, setConfirming] = useState(false);

  const form = useForm<MassNotifySchema>({
    resolver: zodResolver(massNotifySchema),
    defaultValues: { audience: 'all', priority: 'normal', channel: 'in_app', title: '', body: '' },
  });

  const mutation = useMutation({
    mutationFn: (values: MassNotifySchema) => massNotifyApi.send(values),
    onSuccess: (res) => {
      toast.success(res.message);
      form.reset({ audience: 'all', priority: 'normal', channel: 'in_app', title: '', body: '' });
      setConfirming(false);
    },
    onError: (err) => {
      const v = extractValidationErrors(err);
      if (v) {
        Object.entries(v).forEach(([field, msgs]) =>
          form.setError(field as keyof MassNotifySchema, { message: msgs[0] }),
        );
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
      setConfirming(false);
    },
  });

  // Validate first, then open confirm dialog (mirrors Filament requiresConfirmation()).
  const onSubmit = () => setConfirming(true);

  return (
    <div className="max-w-2xl space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('mass_notify.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('mass_notify.subtitle')}</p>
      </div>

      <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4 rounded-lg border border-[var(--color-border)] bg-white p-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="audience">{t('mass_notify.audience')}</Label>
            <Select id="audience" {...form.register('audience')}>
              {AUDIENCES.map((a) => <option key={a} value={a}>{t(`mass_notify.audiences.${a}`)}</option>)}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="priority">{t('mass_notify.priority')}</Label>
            <Select id="priority" {...form.register('priority')}>
              {PRIORITIES.map((p) => <option key={p} value={p}>{t(`mass_notify.priorities.${p}`)}</option>)}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="channel">{t('mass_notify.channel')}</Label>
            <Select id="channel" {...form.register('channel')}>
              {CHANNELS.map((c) => <option key={c} value={c}>{t(`mass_notify.channels.${c}`)}</option>)}
            </Select>
          </div>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="title">{t('mass_notify.subject')}</Label>
          <Input id="title" maxLength={255} {...form.register('title')} />
          {form.formState.errors.title && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.title.message}</p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="body">{t('mass_notify.body')}</Label>
          <Textarea id="body" rows={4} maxLength={2000} {...form.register('body')} />
          {form.formState.errors.body && (
            <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.body.message}</p>
          )}
        </div>

        <div className="border-t border-[var(--color-border)] pt-4">
          <Button type="submit" disabled={mutation.isPending}>
            <Send className="h-4 w-4" />
            {t('mass_notify.send')}
          </Button>
        </div>
      </form>

      <AlertDialog open={confirming} onOpenChange={setConfirming}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('mass_notify.confirm_heading')}</AlertDialogTitle>
            <AlertDialogDescription>{t('mass_notify.confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              disabled={mutation.isPending}
              onClick={() => mutation.mutate(form.getValues())}
            >
              {t('mass_notify.send')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
