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

import {
  useClinicCategories, useCreateClinicCategory, useDeleteClinicCategory, useUpdateClinicCategory,
} from '../hooks';
import type { ClinicCustomCategory } from '../api';

const schema = z.object({
  name: z.string().min(1).max(255),
  emoji: z.string().max(5).nullish(),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});
type FormValues = z.infer<typeof schema>;

function CategoryDialog({ category, onClose }: { category: ClinicCustomCategory | null; onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateClinicCategory();
  const update = useUpdateClinicCategory(category?.id ?? 0);
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: category?.name ?? '',
      emoji: category?.emoji ?? '🩺',
      is_active: category?.is_active ?? true,
      sort_order: category?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      if (category) { await update.mutateAsync(v); toast.success(t('clinic_categories.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_categories.created')); }
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
          <DialogTitle>{category ? t('clinic_categories.edit') : t('clinic_categories.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_categories.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="emoji">{t('clinic_categories.emoji')}</Label>
              <Input id="emoji" maxLength={5} {...form.register('emoji')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_categories.sort_order')}</Label>
              <Input id="sort_order" type="number" {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_categories.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && (
                <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>
              )}
            </div>
            <div className="flex items-end gap-3 pb-2 md:col-span-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_categories.is_active')}</Label>
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

export function ClinicCategoriesIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicCategories();
  const del = useDeleteClinicCategory();
  const [editing, setEditing] = useState<ClinicCustomCategory | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicCustomCategory | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_categories.deleted'));
      setDeleting(null);
    } catch (err) {
      if (isApiError(err) && err.response?.status === 403) toast.error(t('clinic_categories.cannot_delete_has_services'));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_categories.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_categories.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_categories.create')}
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-10">{t('clinic_categories.emoji')}</TableHead>
            <TableHead>{t('clinic_categories.name')}</TableHead>
            <TableHead>{t('clinic_categories.services_count')}</TableHead>
            <TableHead>{t('clinic_categories.is_active')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
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
                <TableCell className="text-lg">{c.emoji ?? '—'}</TableCell>
                <TableCell className="font-medium">{c.name}</TableCell>
                <TableCell><Badge variant="muted">{c.services_count ?? 0}</Badge></TableCell>
                <TableCell>{c.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(c)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(c)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(creating || editing) && (
        <CategoryDialog category={editing} onClose={() => { setCreating(false); setEditing(null); }} />
      )}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_categories.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('clinic_categories.delete_confirm_body')}
              {deleting && deleting.services_count && deleting.services_count > 0 ? (
                <span className="mt-2 block text-[var(--color-destructive)]">{t('clinic_categories.cannot_delete_has_services')}</span>
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
