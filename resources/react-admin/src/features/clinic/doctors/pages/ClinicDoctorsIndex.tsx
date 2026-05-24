import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, Pencil, Plus, Trash2, User, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { FileUpload } from '@/components/forms/FileUpload';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useClinicDoctors, useCreateClinicDoctor, useDeleteClinicDoctor, useUpdateClinicDoctor } from '../hooks';
import type { ClinicDoctor } from '../api';

const schema = z.object({
  name: z.string().min(1).max(255),
  specialty: z.string().max(255).nullish().or(z.literal('')),
  photo: z.string().nullish(),
  bio: z.string().max(2000).nullish().or(z.literal('')),
  years_experience: z.union([z.number().int().min(0).max(70), z.nan()]).nullish()
    .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});
type FormValues = z.infer<typeof schema>;

function DoctorDialog({ doctor, onClose }: { doctor: ClinicDoctor | null; onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateClinicDoctor();
  const update = useUpdateClinicDoctor(doctor?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: doctor?.name ?? '',
      specialty: doctor?.specialty ?? '',
      photo: doctor?.photo ?? null,
      bio: doctor?.bio ?? '',
      years_experience: doctor?.years_experience ?? null,
      is_active: doctor?.is_active ?? true,
      sort_order: doctor?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      if (doctor) { await update.mutateAsync(v); toast.success(t('clinic_doctors.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_doctors.created')); }
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
          <DialogTitle>{doctor ? t('clinic_doctors.edit') : t('clinic_doctors.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_doctors.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="flex justify-center">
            <FileUpload
              value={form.watch('photo')}
              onChange={(p) => form.setValue('photo', p, { shouldDirty: true })}
              directory="doctors"
              label={t('clinic_doctors.photo')}
            />
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_doctors.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="specialty">{t('clinic_doctors.specialty')}</Label>
              <Input id="specialty" {...form.register('specialty')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="years_experience">{t('clinic_doctors.years_experience')}</Label>
              <Input id="years_experience" type="number" min={0} max={70} {...form.register('years_experience', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="bio">{t('clinic_doctors.bio')}</Label>
              <Textarea id="bio" rows={3} {...form.register('bio')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_doctors.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_doctors.is_active')}</Label>
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

export function ClinicDoctorsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicDoctors();
  const del = useDeleteClinicDoctor();
  const [editing, setEditing] = useState<ClinicDoctor | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicDoctor | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_doctors.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_doctors.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_doctors.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_doctors.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-12"></TableHead>
            <TableHead>{t('clinic_doctors.name')}</TableHead>
            <TableHead>{t('clinic_doctors.specialty')}</TableHead>
            <TableHead>{t('clinic_doctors.years_experience')}</TableHead>
            <TableHead>{t('clinic_doctors.is_active')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((d) => (
              <TableRow key={d.id}>
                <TableCell>
                  {d.photo_url ? (
                    <img src={d.photo_url} alt={d.name} className="h-9 w-9 rounded-full object-cover" />
                  ) : (
                    <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--color-muted)] text-[var(--color-muted-foreground)]"><User className="h-4 w-4" /></span>
                  )}
                </TableCell>
                <TableCell className="font-medium">{d.name}</TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">{d.specialty ?? '—'}</TableCell>
                <TableCell className="text-sm">{d.years_experience ?? '—'}</TableCell>
                <TableCell>{d.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(d)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(d)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(creating || editing) && <DoctorDialog doctor={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_doctors.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_doctors.delete_confirm_body')}</AlertDialogDescription>
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
