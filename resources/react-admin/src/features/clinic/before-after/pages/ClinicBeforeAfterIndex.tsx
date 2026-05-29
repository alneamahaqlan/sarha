import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { FileUpload } from '@/components/forms/FileUpload';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useClinicSubClinics } from '@/features/clinic/sub-clinics/hooks';
import { useClinicServices } from '@/features/clinic/services/hooks';

import { useClinicBeforeAfter, useCreateBeforeAfter, useDeleteBeforeAfter, useUpdateBeforeAfter } from '../hooks';
import type { BeforeAfterPhoto } from '../api';

const schema = z.object({
  title: z.string().max(255).nullish().or(z.literal('')),
  before_image: z.string().min(1),
  after_image: z.string().min(1),
  sub_clinic_id: z.union([z.number(), z.literal('')]).optional().nullable()
    .transform((v) => (v === '' || v === undefined ? null : (v as number))),
  service_id: z.union([z.number(), z.literal('')]).optional().nullable()
    .transform((v) => (v === '' || v === undefined ? null : (v as number))),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});
type FormValues = z.infer<typeof schema>;

function PhotoDialog({ photo, onClose }: { photo: BeforeAfterPhoto | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: subClinics } = useClinicSubClinics();
  const { data: services } = useClinicServices({ per_page: 100 });
  const create = useCreateBeforeAfter();
  const update = useUpdateBeforeAfter(photo?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      title: photo?.title ?? '',
      before_image: photo?.before_image ?? '',
      after_image: photo?.after_image ?? '',
      sub_clinic_id: photo?.sub_clinic_id ?? null,
      service_id: photo?.service_id ?? null,
      is_active: photo?.is_active ?? true,
      sort_order: photo?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    if (!v.before_image || !v.after_image) {
      toast.error(t('clinic_before_after.images_required'));
      return;
    }
    try {
      if (photo) { await update.mutateAsync(v); toast.success(t('clinic_before_after.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_before_after.created')); }
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
          <DialogTitle>{photo ? t('clinic_before_after.edit') : t('clinic_before_after.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_before_after.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>{t('clinic_before_after.before')}</Label>
              <FileUpload value={form.watch('before_image')} onChange={(p) => form.setValue('before_image', p ?? '', { shouldDirty: true })} directory="before-after" />
            </div>
            <div className="space-y-1.5">
              <Label>{t('clinic_before_after.after')}</Label>
              <FileUpload value={form.watch('after_image')} onChange={(p) => form.setValue('after_image', p ?? '', { shouldDirty: true })} directory="before-after" />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="title">{t('clinic_before_after.title_label')}</Label>
            <Input id="title" {...form.register('title')} />
          </div>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="sub_clinic_id">{t('clinic_before_after.sub_clinic')}</Label>
              <Select id="sub_clinic_id" {...form.register('sub_clinic_id', { setValueAs: (v) => (v === '' || v === null || v === undefined ? null : Number(v)) })}>
                <option value="">{t('clinic_before_after.unlinked')}</option>
                {subClinics?.data.map((sc) => <option key={sc.id} value={sc.id}>{sc.name}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="service_id">{t('clinic_before_after.service')}</Label>
              <Select id="service_id" {...form.register('service_id', { setValueAs: (v) => (v === '' || v === null || v === undefined ? null : Number(v)) })}>
                <option value="">{t('clinic_before_after.unlinked')}</option>
                {services?.data.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </Select>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
            <Label>{t('clinic_before_after.is_active')}</Label>
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

export function ClinicBeforeAfterIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicBeforeAfter();
  const del = useDeleteBeforeAfter();
  const [editing, setEditing] = useState<BeforeAfterPhoto | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<BeforeAfterPhoto | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_before_after.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_before_after.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_before_after.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_before_after.create')}
        </Button>
      </div>

      {isLoading ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : !data || data.data.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.data.map((p) => (
            <div key={p.id} className="rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] overflow-hidden">
              <div className="grid grid-cols-2">
                <div className="relative">
                  {p.before_url && <img src={p.before_url} alt="before" className="h-32 w-full object-cover" />}
                  <span className="absolute top-1 start-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded">{t('clinic_before_after.before')}</span>
                </div>
                <div className="relative">
                  {p.after_url && <img src={p.after_url} alt="after" className="h-32 w-full object-cover" />}
                  <span className="absolute top-1 start-1 bg-emerald-600/80 text-white text-[10px] px-1.5 py-0.5 rounded">{t('clinic_before_after.after')}</span>
                </div>
              </div>
              <div className="p-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="font-medium truncate">{p.title || '—'}</p>
                    <div className="mt-1 flex flex-wrap gap-1">
                      {p.sub_clinic && <Badge variant="muted" className="text-[10px]">{p.sub_clinic.name}</Badge>}
                      {p.service && <Badge variant="muted" className="text-[10px]">{p.service.name}</Badge>}
                      {!p.is_active && <Badge variant="warning" className="text-[10px]">{t('common.inactive')}</Badge>}
                    </div>
                  </div>
                  <div className="flex gap-1 flex-shrink-0">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(p)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(p)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {(creating || editing) && <PhotoDialog photo={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_before_after.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_before_after.delete_confirm_body')}</AlertDialogDescription>
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
