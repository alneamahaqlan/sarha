import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors, isApiError } from '@/lib/api-client';
import { useCategoryLookup } from '@/features/lookups/hooks';

import {
  useClinicSubClinics, useCreateClinicSubClinic, useDeleteClinicSubClinic, useUpdateClinicSubClinic,
} from '../hooks';
import type { ClinicSubClinic } from '../api';

const schema = z.object({
  name: z.string().min(1).max(255),
  name_en: z.string().max(255).nullish().or(z.literal('')),
  category_id: z.union([z.number(), z.literal('')]).optional().nullable()
    .transform((v) => (v === '' || v === undefined ? null : (v as number))),
  description: z.string().nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});
type FormValues = z.infer<typeof schema>;

function SubClinicDialog({ subClinic, onClose }: { subClinic: ClinicSubClinic | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: categories } = useCategoryLookup();
  const create = useCreateClinicSubClinic();
  const update = useUpdateClinicSubClinic(subClinic?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: subClinic?.name ?? '',
      name_en: subClinic?.name_en ?? '',
      category_id: subClinic?.category_id ?? null,
      description: subClinic?.description ?? '',
      is_active: subClinic?.is_active ?? true,
      sort_order: subClinic?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      if (subClinic) { await update.mutateAsync(v); toast.success(t('clinic_sub_clinics.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_sub_clinics.created')); }
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{subClinic ? t('clinic_sub_clinics.edit') : t('clinic_sub_clinics.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_sub_clinics.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_sub_clinics.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="name_en">{t('clinic_sub_clinics.name_en')}</Label>
              <Input id="name_en" dir="ltr" {...form.register('name_en')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="category_id">{t('clinic_sub_clinics.specialty')}</Label>
              <Select id="category_id" {...form.register('category_id', { setValueAs: (v) => (v === '' ? null : Number(v)) })}>
                <option value="">—</option>
                {categories?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="description">{t('clinic_sub_clinics.description')}</Label>
              <Textarea id="description" rows={3} {...form.register('description')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_sub_clinics.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_sub_clinics.is_active')}</Label>
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicSubClinicsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicSubClinics();
  const del = useDeleteClinicSubClinic();
  const [editing, setEditing] = useState<ClinicSubClinic | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicSubClinic | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_sub_clinics.deleted'));
      setDeleting(null);
    } catch (err) {
      if (isApiError(err) && err.response?.status === 422) toast.error(t('clinic_sub_clinics.cannot_delete_has_services'));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_sub_clinics.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_sub_clinics.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_sub_clinics.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_sub_clinics.name')}</TableHead>
            <TableHead>{t('clinic_sub_clinics.specialty')}</TableHead>
            <TableHead>{t('clinic_sub_clinics.services_count')}</TableHead>
            <TableHead>{t('clinic_sub_clinics.is_active')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((s) => (
              <TableRow key={s.id}>
                <TableCell className="font-medium">
                  {s.name}
                  {s.name_en && <span className="ms-2 text-xs text-[var(--color-muted-foreground)]" dir="ltr">({s.name_en})</span>}
                </TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">
                  {s.category_id ?? '—'}
                </TableCell>
                <TableCell><Badge variant="muted">{s.services_count ?? 0}</Badge></TableCell>
                <TableCell>{s.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(s)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(creating || editing) && <SubClinicDialog subClinic={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_sub_clinics.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('clinic_sub_clinics.delete_confirm_body')}
              {deleting && (deleting.services_count ?? 0) > 0 ? (
                <span className="mt-2 block text-[var(--color-destructive)]">{t('clinic_sub_clinics.cannot_delete_has_services')}</span>
              ) : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>{t('common.delete')}</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
