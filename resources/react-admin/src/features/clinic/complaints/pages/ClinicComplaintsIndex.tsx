import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Plus } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useClinicComplaints, useCreateClinicComplaint } from '../hooks';

const TYPES = ['quality', 'pricing', 'misleading_info', 'other'] as const;
const STATUS_VARIANT: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  new: 'default', in_review: 'warning', resolved: 'success', rejected: 'danger',
};

const schema = z.object({
  type: z.enum(TYPES),
  subject: z.string().min(1).max(255),
  description: z.string().min(10).max(2000),
});
type FormValues = z.infer<typeof schema>;

function ComplaintDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateClinicComplaint();
  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: { type: 'other', subject: '', description: '' },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      await create.mutateAsync(v);
      toast.success(t('clinic_complaints.created'));
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_complaints.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_complaints.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="type">{t('clinic_complaints.type')}</Label>
            <Select id="type" {...form.register('type')}>
              {TYPES.map((ty) => <option key={ty} value={ty}>{t(`clinic_complaints.type_${ty}`)}</option>)}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="subject">{t('clinic_complaints.subject')}</Label>
            <Input id="subject" {...form.register('subject')} />
            {form.formState.errors.subject && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.subject.message}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="description">{t('clinic_complaints.description')}</Label>
            <Textarea id="description" rows={4} {...form.register('description')} />
            {form.formState.errors.description && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.description.message}</p>}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={create.isPending}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={create.isPending}>{create.isPending ? t('common.loading') : t('common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicComplaintsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicComplaints();
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_complaints.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_complaints.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_complaints.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_complaints.reference')}</TableHead>
            <TableHead>{t('clinic_complaints.subject')}</TableHead>
            <TableHead>{t('clinic_complaints.type')}</TableHead>
            <TableHead>{t('clinic_complaints.status_label')}</TableHead>
            <TableHead>{t('clinic_complaints.resolution')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((c) => (
              <TableRow key={c.id}>
                <TableCell className="font-mono text-xs" dir="ltr">{c.reference_code}</TableCell>
                <TableCell className="font-medium">
                  {c.subject}
                  <p className="text-xs text-[var(--color-muted-foreground)] max-w-xs truncate">{c.description}</p>
                </TableCell>
                <TableCell className="text-sm">{t(`clinic_complaints.type_${c.type}`)}</TableCell>
                <TableCell><Badge variant={STATUS_VARIANT[c.status]}>{t(`clinic_complaints.status_${c.status}`)}</Badge></TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)] max-w-xs truncate">{c.resolution ?? '—'}</TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {creating && <ComplaintDialog onClose={() => setCreating(false)} />}
    </div>
  );
}
