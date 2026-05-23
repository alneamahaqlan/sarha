import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, List, LayoutGrid, Pencil, Plus, Trash2, X } from 'lucide-react';

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
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import type { Service } from '@/features/services/types';
import { useClinicCategories } from '@/features/clinic/categories/hooks';

import {
  useClinicServices, useCreateClinicService, useDeleteClinicService, useSubClinicLookup, useUpdateClinicService,
} from '../hooks';

const schema = z
  .object({
    name: z.string().min(1).max(255),
    custom_category_id: z.union([z.number(), z.literal('')]).optional().nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    sub_clinic_id: z.union([z.number(), z.literal('')]).optional().nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    description: z.string().nullish(),
    price: z.number().min(0),
    old_price: z.union([z.number().min(0), z.literal('')]).optional().nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    offer_expires_at: z.string().nullish(),
    is_active: z.boolean(),
    sort_order: z.number().int().min(0).default(0),
  })
  .superRefine((d, ctx) => {
    if (d.old_price !== null && d.old_price !== undefined) {
      if (d.old_price <= d.price) ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['old_price'], message: 'must_be_greater' });
      if (!d.offer_expires_at) ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['offer_expires_at'], message: 'required' });
    }
  });
type FormValues = z.infer<typeof schema>;

function toLocal(iso?: string | null) {
  if (!iso) return '';
  const d = new Date(iso); const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

function ServiceDialog({ service, onClose }: { service: Service | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: cats } = useClinicCategories();
  const { data: subClinics } = useSubClinicLookup();
  const create = useCreateClinicService();
  const update = useUpdateClinicService(service?.id ?? 0);
  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: service?.name ?? '',
      custom_category_id: service?.custom_category_id ?? null,
      sub_clinic_id: service?.sub_clinic_id ?? null,
      description: service?.description ?? '',
      price: service?.price ?? 0,
      old_price: service?.old_price ?? null,
      offer_expires_at: toLocal(service?.offer_expires_at) || null,
      is_active: service?.is_active ?? true,
      sort_order: service?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      const payload = { ...v, offer_expires_at: v.offer_expires_at ? new Date(v.offer_expires_at).toISOString() : null };
      if (service) { await update.mutateAsync(payload); toast.success(t('clinic_services.updated')); }
      else { await create.mutateAsync(payload); toast.success(t('clinic_services.created')); }
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;
  const showOffer = !!form.watch('old_price');

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{service ? t('clinic_services.edit') : t('clinic_services.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_services.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_services.name')}</Label>
              <Input id="name" {...form.register('name')} />
              {form.formState.errors.name && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.name.message}</p>}
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="custom_category_id">{t('clinic_services.category')}</Label>
              <Select id="custom_category_id" {...form.register('custom_category_id', { setValueAs: (v) => (v === '' ? null : Number(v)) })}>
                <option value="">—</option>
                {cats?.data.map((c) => <option key={c.id} value={c.id}>{c.emoji} {c.name}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="sub_clinic_id">{t('clinic_services.sub_clinic')}</Label>
              <Select id="sub_clinic_id" {...form.register('sub_clinic_id', { setValueAs: (v) => (v === '' ? null : Number(v)) })}>
                <option value="">—</option>
                {subClinics?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="description">{t('clinic_services.description')}</Label>
              <Textarea id="description" rows={2} {...form.register('description')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">{t('clinic_services.price')}</Label>
              <Input id="price" type="number" step="0.01" min={0} {...form.register('price', { valueAsNumber: true })} />
              {form.formState.errors.price && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.price.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="old_price">{t('clinic_services.old_price')}</Label>
              <Input id="old_price" type="number" step="0.01" min={0} {...form.register('old_price', { setValueAs: (v) => (v === '' || v === null ? null : Number(v)) })} />
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_services.old_price_hint')}</p>
              {form.formState.errors.old_price && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.old_price.message}</p>}
            </div>
            {showOffer && (
              <div className="space-y-1.5 md:col-span-2">
                <Label htmlFor="offer_expires_at">{t('clinic_services.offer_expires_at')}</Label>
                <Input id="offer_expires_at" type="datetime-local" {...form.register('offer_expires_at')} />
                <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_services.offer_expires_hint')}</p>
                {form.formState.errors.offer_expires_at && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.offer_expires_at.message}</p>}
              </div>
            )}
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_services.is_active')}</Label>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_services.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} step={1} {...form.register('sort_order', { valueAsNumber: true })} />
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

export function ClinicServicesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useClinicServices({ per_page: 50, sort: 'sort_order' });
  const { data: cats } = useClinicCategories();
  const del = useDeleteClinicService();
  const [editing, setEditing] = useState<Service | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Service | null>(null);
  const [view, setView] = useState<'list' | 'grouped'>('list');

  const fmtCurrency = (n: number) =>
    new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 0 }).format(n);

  const handleDelete = async () => {
    if (!deleting) return;
    try { await del.mutateAsync(deleting.id); toast.success(t('clinic_services.deleted')); setDeleting(null); }
    catch (err) { toast.error(extractMessage(err, t('errors.generic'))); }
  };

  const catName = useMemo(() => {
    const map = new Map<number, string>();
    cats?.data.forEach((c) => map.set(c.id, `${c.emoji ? `${c.emoji} ` : ''}${c.name}`));
    return map;
  }, [cats]);

  // Group services under their category (uncategorized last).
  const groups = useMemo(() => {
    const list = data?.data ?? [];
    const buckets = new Map<number | 'none', Service[]>();
    for (const s of list) {
      const key = s.custom_category_id ?? 'none';
      if (!buckets.has(key)) buckets.set(key, []);
      buckets.get(key)!.push(s);
    }
    const ordered: { key: number | 'none'; label: string; items: Service[] }[] = [];
    cats?.data.forEach((c) => {
      const items = buckets.get(c.id);
      if (items?.length) ordered.push({ key: c.id, label: catName.get(c.id) ?? c.name, items });
    });
    const none = buckets.get('none');
    if (none?.length) ordered.push({ key: 'none', label: t('clinic_services.uncategorized'), items: none });
    return ordered;
  }, [data, cats, catName, t]);

  const renderRow = (s: Service) => (
    <TableRow key={s.id}>
      <TableCell className="font-medium">{s.name}</TableCell>
      <TableCell>
        <div className="flex items-baseline gap-2">
          <span className="font-medium">{fmtCurrency(s.price)}</span>
          {s.old_price !== null && s.has_active_offer && (
            <span className="text-xs text-[var(--color-muted-foreground)] line-through">{fmtCurrency(s.old_price)}</span>
          )}
        </div>
      </TableCell>
      <TableCell>{s.has_active_offer ? <Badge variant="success">-{s.discount_percentage}%</Badge> : <span className="text-[var(--color-muted-foreground)]">—</span>}</TableCell>
      <TableCell>{s.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
      <TableCell className="text-end">
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDeleting(s)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
        </div>
      </TableCell>
    </TableRow>
  );

  const header = (
    <TableHeader>
      <TableRow>
        <TableHead>{t('clinic_services.name')}</TableHead>
        <TableHead>{t('clinic_services.price')}</TableHead>
        <TableHead>{t('clinic_services.offer')}</TableHead>
        <TableHead>{t('clinic_services.is_active')}</TableHead>
        <TableHead className="text-end">{t('common.actions')}</TableHead>
      </TableRow>
    </TableHeader>
  );

  const isEmpty = !isLoading && (!data || data.data.length === 0);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_services.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_services.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <div className="inline-flex rounded-md border border-[var(--color-border)] p-0.5">
            <button
              type="button"
              onClick={() => setView('list')}
              className={cn('inline-flex items-center gap-1 rounded px-2.5 py-1 text-xs font-medium', view === 'list' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-foreground)] hover:bg-[var(--color-muted)]')}
            >
              <List className="h-3.5 w-3.5" />{t('clinic_services.view_list')}
            </button>
            <button
              type="button"
              onClick={() => setView('grouped')}
              className={cn('inline-flex items-center gap-1 rounded px-2.5 py-1 text-xs font-medium', view === 'grouped' ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-foreground)] hover:bg-[var(--color-muted)]')}
            >
              <LayoutGrid className="h-3.5 w-3.5" />{t('clinic_services.view_grouped')}
            </button>
          </div>
          <Button onClick={() => setCreating(true)}><Plus className="h-4 w-4" />{t('clinic_services.create')}</Button>
        </div>
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : isEmpty ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
      ) : view === 'list' ? (
        <Table>
          {header}
          <TableBody>{data!.data.map(renderRow)}</TableBody>
        </Table>
      ) : (
        <div className="space-y-6">
          {groups.map((g) => (
            <div key={g.key} className="space-y-2">
              <div className="flex items-center gap-2">
                <h2 className="text-sm font-semibold">{g.label}</h2>
                <span className="rounded-full bg-[var(--color-muted)] px-2 py-0.5 text-xs text-[var(--color-muted-foreground)]">{g.items.length}</span>
              </div>
              <Table>
                {header}
                <TableBody>{g.items.map(renderRow)}</TableBody>
              </Table>
            </div>
          ))}
        </div>
      )}

      {(creating || editing) && <ServiceDialog service={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_services.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_services.delete_confirm_body')}</AlertDialogDescription>
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
