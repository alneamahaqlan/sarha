import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { CheckCircle2, Clock, MessageSquarePlus, XCircle } from 'lucide-react';

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

import { useClinicReports, useCreateClinicReport } from '../hooks';

const TYPES = ['technical', 'abusive_customer', 'fake_review', 'billing', 'suggestion', 'other'] as const;
const PRIORITIES = ['low', 'medium', 'high'] as const;
const STATUS_VARIANT: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  new: 'default', in_review: 'warning', resolved: 'success', rejected: 'danger',
};
const STATUS_ICON: Record<string, { Icon: typeof Clock; className: string }> = {
  new:       { Icon: MessageSquarePlus, className: 'text-blue-500' },
  in_review: { Icon: Clock,             className: 'text-amber-500' },
  resolved:  { Icon: CheckCircle2,      className: 'text-emerald-600' },
  rejected:  { Icon: XCircle,           className: 'text-rose-500' },
};

const schema = z.object({
  type: z.enum(TYPES),
  priority: z.enum(PRIORITIES).optional(),
  subject: z.string().min(1).max(255),
  description: z.string().min(10).max(2000),
});
type FormValues = z.infer<typeof schema>;

function ReportDialog({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateClinicReport();
  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: { type: 'technical', priority: 'medium', subject: '', description: '' },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      await create.mutateAsync(v);
      toast.success(t('clinic_reports.created'));
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
          <DialogTitle>{t('clinic_reports.create')}</DialogTitle>
          <DialogDescription>{t('clinic_reports.dialog_desc')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="type">{t('clinic_reports.type')}</Label>
              <Select id="type" {...form.register('type')}>
                {TYPES.map((ty) => <option key={ty} value={ty}>{t(`clinic_reports.type_${ty}`)}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="priority">{t('clinic_reports.priority')}</Label>
              <Select id="priority" {...form.register('priority')}>
                {PRIORITIES.map((p) => <option key={p} value={p}>{t(`clinic_reports.priority_${p}`)}</option>)}
              </Select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="subject">{t('clinic_reports.subject')}</Label>
            <Input id="subject" {...form.register('subject')} />
            {form.formState.errors.subject && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.subject.message}</p>}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="description">{t('clinic_reports.description')}</Label>
            <Textarea id="description" rows={5} placeholder={t('clinic_reports.description_placeholder')} {...form.register('description')} />
            {form.formState.errors.description && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.description.message}</p>}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={create.isPending}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={create.isPending}>{create.isPending ? t('common.loading') : t('clinic_reports.send')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicReportsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicReports();
  const [creating, setCreating] = useState(false);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_reports.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_reports.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <MessageSquarePlus className="h-4 w-4" />
          {t('clinic_reports.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_reports.reference')}</TableHead>
            <TableHead>{t('clinic_reports.subject')}</TableHead>
            <TableHead>{t('clinic_reports.type')}</TableHead>
            <TableHead>{t('clinic_reports.status_label')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('clinic_reports.empty')}</TableCell></TableRow>
          ) : (
            data.data.map((r) => {
              const Glyph = STATUS_ICON[r.status];
              return (
                <TableRow key={r.id}>
                  <TableCell className="font-mono text-xs" dir="ltr">{r.reference_code}</TableCell>
                  <TableCell className="font-medium">
                    {r.subject}
                    <p className="text-xs text-[var(--color-muted-foreground)] max-w-xs truncate">{r.description}</p>
                  </TableCell>
                  <TableCell className="text-sm">{t(`clinic_reports.type_${r.type}`, r.type)}</TableCell>
                  <TableCell>
                    <Badge variant={STATUS_VARIANT[r.status]} className="inline-flex items-center gap-1.5">
                      <Glyph.Icon className={`h-3.5 w-3.5 ${Glyph.className}`} />
                      {t(`clinic_reports.status_${r.status}`)}
                    </Badge>
                  </TableCell>
                </TableRow>
              );
            })
          )}
        </TableBody>
      </Table>

      {creating && <ReportDialog onClose={() => setCreating(false)} />}
    </div>
  );
}
