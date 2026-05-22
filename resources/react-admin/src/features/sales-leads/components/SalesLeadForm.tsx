import { useEffect } from 'react';
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
import { useAdminLookup, useCityLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useCreateSalesLead, useUpdateSalesLead } from '../hooks';
import { SALES_LEAD_STATUSES, type SalesLead, type SalesLeadStatus } from '../types';

const schema = z.object({
  clinic_name: z.string().min(1).max(255),
  contact_name: z.string().max(255).nullish(),
  phone: z.string().min(1).max(20),
  email: z.string().email().nullish().or(z.literal('')),
  license_number: z.string().max(100).nullish(),
  city_id: z.union([z.coerce.number().int().positive(), z.literal('')]).nullish(),
  district: z.string().max(255).nullish(),
  address: z.string().nullish(),
  status: z.enum(['new', 'contacted', 'interested', 'negotiating', 'converted', 'lost']),
  assigned_to: z.union([z.coerce.number().int().positive(), z.literal('')]).nullish(),
  next_follow_up_at: z.string().nullish(),
  last_contact_at: z.string().nullish(),
  notes: z.string().nullish(),
  sales_notes: z.string().nullish(),
});
type FormValues = z.infer<typeof schema>;

interface Props {
  lead?: SalesLead | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

function toLocal(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

export function SalesLeadForm({ lead, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { data: cities } = useCityLookup();
  const { data: admins } = useAdminLookup('sales,admin,super_admin');
  const create = useCreateSalesLead();
  const update = useUpdateSalesLead(lead?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      clinic_name: '', contact_name: '', phone: '', email: '', license_number: '',
      city_id: '', district: '', address: '',
      status: 'new', assigned_to: '',
      next_follow_up_at: '', last_contact_at: '',
      notes: '', sales_notes: '',
    },
  });

  useEffect(() => {
    form.reset({
      clinic_name: lead?.clinic_name ?? '',
      contact_name: lead?.contact_name ?? '',
      phone: lead?.phone ?? '',
      email: lead?.email ?? '',
      license_number: lead?.license_number ?? '',
      city_id: lead?.city_id ?? '',
      district: lead?.district ?? '',
      address: lead?.address ?? '',
      status: lead?.status ?? 'new',
      assigned_to: lead?.assigned_to ?? '',
      next_follow_up_at: toLocal(lead?.next_follow_up_at),
      last_contact_at: toLocal(lead?.last_contact_at),
      notes: lead?.notes ?? '',
      sales_notes: lead?.sales_notes ?? '',
    });
  }, [lead, form]);

  const onSubmit = async (v: FormValues) => {
    try {
      const payload: Record<string, unknown> = { ...v };
      if (payload.city_id === '' || payload.city_id === null) payload.city_id = null;
      if (payload.assigned_to === '' || payload.assigned_to === null) payload.assigned_to = null;
      if (payload.email === '') payload.email = null;
      if (payload.next_follow_up_at === '') payload.next_follow_up_at = null;
      if (payload.last_contact_at === '') payload.last_contact_at = null;

      if (lead) {
        await update.mutateAsync(payload as Partial<FormValues>);
        toast.success(t('sales_leads.updated'));
      } else {
        await create.mutateAsync(payload as FormValues);
        toast.success(t('sales_leads.created'));
      }
      onSuccess?.();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_name">{t('sales_leads.form.clinic_name')}</Label>
          <Input id="clinic_name" {...form.register('clinic_name')} />
          {form.formState.errors.clinic_name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.clinic_name.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="contact_name">{t('sales_leads.form.contact_name')}</Label>
          <Input id="contact_name" {...form.register('contact_name')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="phone">{t('sales_leads.form.phone')}</Label>
          <Input id="phone" type="tel" dir="ltr" {...form.register('phone')} />
          {form.formState.errors.phone && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.phone.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="email">{t('sales_leads.form.email')}</Label>
          <Input id="email" type="email" dir="ltr" {...form.register('email')} />
          {form.formState.errors.email && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.email.message}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="license_number">{t('sales_leads.form.license_number')}</Label>
          <Input id="license_number" {...form.register('license_number')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="city_id">{t('sales_leads.form.city')}</Label>
          <Select
            id="city_id"
            value={(form.watch('city_id') as number | '' | null) ?? ''}
            onChange={(e) => form.setValue('city_id', e.target.value === '' ? '' : Number(e.target.value), { shouldDirty: true })}
          >
            <option value="">—</option>
            {cities?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="district">{t('sales_leads.form.district')}</Label>
          <Input id="district" {...form.register('district')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="address">{t('sales_leads.form.address')}</Label>
          <Textarea id="address" rows={2} {...form.register('address')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="status">{t('sales_leads.form.status')}</Label>
          <Select
            id="status"
            value={form.watch('status')}
            onChange={(e) => form.setValue('status', e.target.value as SalesLeadStatus, { shouldDirty: true })}
          >
            {SALES_LEAD_STATUSES.map((s) => <option key={s} value={s}>{t(`sales_leads.status.${s}`)}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="assigned_to">{t('sales_leads.form.assigned_to')}</Label>
          <Select
            id="assigned_to"
            value={(form.watch('assigned_to') as number | '' | null) ?? ''}
            onChange={(e) => form.setValue('assigned_to', e.target.value === '' ? '' : Number(e.target.value), { shouldDirty: true })}
          >
            <option value="">—</option>
            {admins?.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="next_follow_up_at">{t('sales_leads.form.next_follow_up_at')}</Label>
          <Input id="next_follow_up_at" type="datetime-local" {...form.register('next_follow_up_at')} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="last_contact_at">{t('sales_leads.form.last_contact_at')}</Label>
          <Input id="last_contact_at" type="datetime-local" {...form.register('last_contact_at')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="notes">{t('sales_leads.form.notes')}</Label>
          <Textarea id="notes" rows={3} {...form.register('notes')} />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="sales_notes">{t('sales_leads.form.sales_notes')}</Label>
          <Textarea id="sales_notes" rows={3} {...form.register('sales_notes')} />
        </div>
      </div>

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>{t('common.cancel')}</Button>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  );
}
