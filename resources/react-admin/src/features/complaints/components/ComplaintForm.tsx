import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAdminLookup, useClinicLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useCreateComplaint } from '../hooks';
import {
  COMPLAINT_PRIORITIES, COMPLAINT_STATUSES, COMPLAINT_TYPES,
  type ComplaintPriority, type ComplaintStatus, type ComplaintType,
} from '../types';

const schema = z.object({
  clinic_id: z.union([z.coerce.number().int().positive(), z.literal('')]).nullish(),
  customer_name: z.string().min(1).max(255),
  customer_phone: z.string().min(1).max(20),
  customer_email: z.string().email().nullish().or(z.literal('')),
  type: z.enum(['quality', 'pricing', 'misleading_info', 'other']),
  priority: z.enum(['low', 'medium', 'high']),
  status: z.enum(['new', 'in_review', 'resolved', 'rejected']),
  assigned_admin_id: z.union([z.coerce.number().int().positive(), z.literal('')]).nullish(),
  subject: z.string().min(1).max(255),
  description: z.string().min(1),
  admin_notes: z.string().nullish(),
  resolution: z.string().nullish(),
});
type FormValues = z.infer<typeof schema>;

interface Props {
  onSuccess?: () => void;
  onCancel?: () => void;
}

export function ComplaintForm({ onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: clinics } = useClinicLookup();
  const { data: admins } = useAdminLookup('admin,super_admin');
  const create = useCreateComplaint();

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      clinic_id: '', customer_name: '', customer_phone: '', customer_email: '',
      type: 'other', priority: 'medium', status: 'new', assigned_admin_id: '',
      subject: '', description: '', admin_notes: '', resolution: '',
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: Record<string, unknown> = { ...v };
      if (payload.clinic_id === '' || payload.clinic_id === null) payload.clinic_id = null;
      if (payload.assigned_admin_id === '' || payload.assigned_admin_id === null) payload.assigned_admin_id = null;
      if (payload.customer_email === '') payload.customer_email = null;
      if (payload.admin_notes === '') payload.admin_notes = null;
      if (payload.resolution === '') payload.resolution = null;

      await create.mutateAsync(payload as FormValues);
      toast.success(t('complaints.created'));
      onSuccess?.();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_id">{t('complaints.form.clinic')}</Label>
          <Select
            id="clinic_id"
            value={(form.watch('clinic_id') as number | '' | null) ?? ''}
            onChange={(e) => form.setValue('clinic_id', e.target.value === '' ? '' : Number(e.target.value), { shouldDirty: true })}
          >
            <option value="">—</option>
            {clinics?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="customer_name">{t('complaints.form.customer_name')}</Label>
          <Input id="customer_name" {...form.register('customer_name')} />
          {form.formState.errors.customer_name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.customer_name.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="customer_phone">{t('complaints.form.customer_phone')}</Label>
          <Input id="customer_phone" type="tel" dir="ltr" {...form.register('customer_phone')} />
          {form.formState.errors.customer_phone && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.customer_phone.message}</p>}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="customer_email">{t('complaints.form.customer_email')}</Label>
          <Input id="customer_email" type="email" dir="ltr" {...form.register('customer_email')} />
          {form.formState.errors.customer_email && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.customer_email.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="type">{t('complaints.form.type')}</Label>
          <Select
            id="type"
            value={form.watch('type')}
            onChange={(e) => form.setValue('type', e.target.value as ComplaintType, { shouldDirty: true })}
          >
            {COMPLAINT_TYPES.map((s) => <option key={s} value={s}>{t(`complaints.type.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="priority">{t('complaints.form.priority')}</Label>
          <Select
            id="priority"
            value={form.watch('priority')}
            onChange={(e) => form.setValue('priority', e.target.value as ComplaintPriority, { shouldDirty: true })}
          >
            {COMPLAINT_PRIORITIES.map((s) => <option key={s} value={s}>{t(`complaints.priority.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="status">{t('complaints.form.status')}</Label>
          <Select
            id="status"
            value={form.watch('status')}
            onChange={(e) => form.setValue('status', e.target.value as ComplaintStatus, { shouldDirty: true })}
          >
            {COMPLAINT_STATUSES.map((s) => <option key={s} value={s}>{t(`complaints.status.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="assigned_admin_id">{t('complaints.form.assigned_admin')}</Label>
          <Select
            id="assigned_admin_id"
            value={(form.watch('assigned_admin_id') as number | '' | null) ?? ''}
            onChange={(e) => form.setValue('assigned_admin_id', e.target.value === '' ? '' : Number(e.target.value), { shouldDirty: true })}
          >
            <option value="">—</option>
            {admins?.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="subject">{t('complaints.form.subject')}</Label>
          <Input id="subject" {...form.register('subject')} />
          {form.formState.errors.subject && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.subject.message}</p>}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="description">{t('complaints.form.description')}</Label>
          <Textarea id="description" rows={4} {...form.register('description')} />
          {form.formState.errors.description && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.description.message}</p>}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="admin_notes">{t('complaints.form.admin_notes')}</Label>
          <Textarea id="admin_notes" rows={3} {...form.register('admin_notes')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="resolution">{t('complaints.form.resolution')}</Label>
          <Textarea id="resolution" rows={3} {...form.register('resolution')} />
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>{t('common.cancel')}</Button>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  );
}
