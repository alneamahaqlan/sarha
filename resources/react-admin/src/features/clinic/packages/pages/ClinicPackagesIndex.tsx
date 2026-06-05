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
import { RiyalSymbol } from '@/lib/money';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useClinicServices } from '@/features/clinic/services/hooks';

import { useClinicPackages, useCreateClinicPackage, useDeleteClinicPackage, useUpdateClinicPackage } from '../hooks';
import type { ClinicPackage } from '../api';

const schema = z.object({
  name: z.string().min(1).max(255),
  description: z.string().max(2000).nullish().or(z.literal('')),
  price: z.number().min(0),
  old_price: z.union([z.number().min(0), z.nan()]).nullish()
    .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
  expires_at: z.string().nullish().or(z.literal('')),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
  service_ids: z.array(z.number()).min(1),
  service_notes: z.record(z.string(), z.string().max(255)).optional(),
});
type FormValues = z.infer<typeof schema>;

function PackageDialog({ pkg, onClose }: { pkg: ClinicPackage | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: services } = useClinicServices({ per_page: 100 });
  const create = useCreateClinicPackage();
  const update = useUpdateClinicPackage(pkg?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: pkg?.name ?? '',
      description: pkg?.description ?? '',
      price: pkg?.price ?? 0,
      old_price: pkg?.old_price ?? null,
      expires_at: pkg?.expires_at ?? '',
      is_active: pkg?.is_active ?? true,
      sort_order: pkg?.sort_order ?? 0,
      service_ids: pkg?.service_ids ?? [],
      service_notes: pkg?.service_notes
        ? Object.fromEntries(Object.entries(pkg.service_notes).map(([k, val]) => [k, val ?? '']))
        : {},
    },
  });

  const selectedIds = form.watch('service_ids');
  const notes = form.watch('service_notes') ?? {};
  const toggleService = (id: number) => {
    const next = selectedIds.includes(id) ? selectedIds.filter((x) => x !== id) : [...selectedIds, id];
    form.setValue('service_ids', next, { shouldDirty: true, shouldValidate: true });
    if (!next.includes(id)) {
      const { [String(id)]: _removed, ...rest } = form.getValues('service_notes') ?? {};
      form.setValue('service_notes', rest, { shouldDirty: true });
    }
  };
  const setNote = (id: number, value: string) => {
    form.setValue('service_notes', { ...(form.getValues('service_notes') ?? {}), [String(id)]: value }, { shouldDirty: true });
  };

  const onSubmit = async (v: FormValues) => {
    // Keep only notes for selected services that actually have text.
    const cleanedNotes = Object.fromEntries(
      Object.entries(v.service_notes ?? {}).filter(([k, val]) => v.service_ids.includes(Number(k)) && val.trim() !== ''),
    );
    v = { ...v, service_notes: cleanedNotes };
    try {
      if (pkg) { await update.mutateAsync(v); toast.success(t('clinic_packages.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_packages.created')); }
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
          <DialogTitle>{pkg ? t('clinic_packages.edit') : t('clinic_packages.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_packages.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_packages.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">{t('clinic_packages.price')}</Label>
              <Input id="price" type="number" min={0} step="0.01" {...form.register('price', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="old_price">{t('clinic_packages.old_price')}</Label>
              <Input id="old_price" type="number" min={0} step="0.01" {...form.register('old_price', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="expires_at">{t('clinic_packages.expires_at')}</Label>
              <Input id="expires_at" type="date" {...form.register('expires_at')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_packages.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="description">{t('clinic_packages.description')}</Label>
              <Textarea id="description" rows={2} {...form.register('description')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label>{t('clinic_packages.services')}</Label>
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_packages.services_hint')}</p>
              <div className="max-h-56 space-y-1 overflow-auto rounded-md border border-[var(--color-border)] p-2">
                {!services || services.data.length === 0 ? (
                  <p className="py-3 text-center text-xs text-[var(--color-muted-foreground)]">{t('common.no_data')}</p>
                ) : (
                  services.data.map((s) => (
                    <div key={s.id} className="rounded-md p-1.5 hover:bg-[var(--color-muted)]">
                      <label className="flex cursor-pointer items-center gap-2">
                        <input type="checkbox" checked={selectedIds.includes(s.id)} onChange={() => toggleService(s.id)} className="h-4 w-4" />
                        <span className="text-sm">{s.name}</span>
                        {s.sub_clinic && <span className="text-xs text-[var(--color-muted-foreground)]">· {s.sub_clinic.name}</span>}
                      </label>
                      {selectedIds.includes(s.id) && (
                        <Input
                          value={notes[String(s.id)] ?? ''}
                          onChange={(e) => setNote(s.id, e.target.value)}
                          placeholder={t('clinic_packages.service_note_placeholder')}
                          maxLength={255}
                          className="mt-1 ms-6 h-8 text-xs"
                        />
                      )}
                    </div>
                  ))
                )}
              </div>
              {form.formState.errors.service_ids && <p className="text-xs text-[var(--color-destructive)]">{t('clinic_packages.services_required')}</p>}
            </div>
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_packages.is_active')}</Label>
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

export function ClinicPackagesIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicPackages();
  const del = useDeleteClinicPackage();
  const [editing, setEditing] = useState<ClinicPackage | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicPackage | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_packages.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_packages.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_packages.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_packages.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_packages.name')}</TableHead>
            <TableHead>{t('clinic_packages.price')}</TableHead>
            <TableHead>{t('clinic_packages.services')}</TableHead>
            <TableHead>{t('clinic_packages.is_active')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((p) => (
              <TableRow key={p.id}>
                <TableCell className="font-medium">{p.name}</TableCell>
                <TableCell>
                  {p.old_price && <span className="me-1 text-xs text-[var(--color-muted-foreground)] line-through">{p.old_price.toLocaleString()}</span>}
                  <span className="font-semibold">{p.price.toLocaleString()}</span> <RiyalSymbol />
                </TableCell>
                <TableCell><Badge variant="muted">{p.services?.length ?? 0}</Badge></TableCell>
                <TableCell>{p.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(p)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(p)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(creating || editing) && <PackageDialog pkg={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_packages.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_packages.delete_confirm_body')}</AlertDialogDescription>
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
