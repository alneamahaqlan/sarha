import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, ExternalLink, List, LayoutGrid, Pencil, Plus, Sparkle, Trash2, X } from 'lucide-react';

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
import { FileUpload } from '@/components/forms/FileUpload';
import type { Service } from '@/features/services/types';
import { useClinicSubClinics } from '@/features/clinic/sub-clinics/hooks';
import { useClinicProfile } from '@/features/clinic/profile/hooks';
import { useCategoryLookup } from '@/features/lookups/hooks';

import {
  useClinicServices, useCreateClinicService, useDeleteClinicService,
  useSubClinicLookup, useUpdateClinicService,
} from '../hooks';
import { MultiCategorySelect } from '../components/MultiCategorySelect';
import { RequestSpecialtyDialog } from '../components/RequestSpecialtyDialog';

const schema = z
  .object({
    name: z.string().min(1).max(255),
    // 1–5 specialties — mirrors StoreServiceRequest / UpdateServiceRequest.
    // Each service must belong to at least one specialty, up to five.
    category_ids: z.array(z.number().int().positive()).min(1).max(5),
    sub_clinic_id: z.union([z.number(), z.literal('')]).optional().nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    description: z.string().nullish(),
    price: z.number().min(0),
    image: z.string().nullish(),
    is_active: z.boolean(),
    sort_order: z.number().int().min(0).default(0),
  });
type FormValues = z.infer<typeof schema>;

function ServiceDialog({ service, onClose }: { service: Service | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: subClinics } = useSubClinicLookup();
  const { data: categories } = useCategoryLookup();
  const create = useCreateClinicService();
  const update = useUpdateClinicService(service?.id ?? 0);
  const [requestOpen, setRequestOpen] = useState(false);
  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: service?.name ?? '',
      // Edit mode: prefer category_ids (new API); fall back to the legacy
      // single category_id if the row hasn't been resaved since the
      // many-to-many migration. Create mode: empty list.
      category_ids: service?.category_ids ?? [],
      sub_clinic_id: service?.sub_clinic_id ?? null,
      description: service?.description ?? '',
      price: service?.price ?? 0,
      image: service?.image ?? '',
      is_active: service?.is_active ?? true,
      sort_order: service?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      if (service) { await update.mutateAsync(v); toast.success(t('clinic_services.updated')); }
      else { await create.mutateAsync(v); toast.success(t('clinic_services.created')); }
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
              <Label>
                {t('clinic_services.categories', 'التخصصات')} *
              </Label>
              <MultiCategorySelect
                value={form.watch('category_ids') ?? []}
                onChange={(ids) => form.setValue('category_ids', ids, { shouldDirty: true, shouldValidate: true })}
                categories={categories}
                max={5}
              />
              {form.formState.errors.category_ids && (
                <p className="text-xs text-[var(--color-destructive)]">
                  {t('clinic_services.categories_required', 'اختر تخصصاً واحداً على الأقل (وحتى 5).')}
                </p>
              )}
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {t(
                  'clinic_services.categories_hint',
                  'كل خدمة لازم تنتمي لتخصص واحد على الأقل (وحتى 5 تخصصات).',
                )}
              </p>
              <button
                type="button"
                onClick={() => setRequestOpen(true)}
                className="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--color-primary)] hover:underline"
              >
                <Sparkle className="h-3 w-3" />
                {t('clinic_services.request_specialty_link', 'تخصصك غير موجود؟ اقترح إضافته')}
              </button>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="sub_clinic_id">{t('clinic_services.sub_clinic')}</Label>
              <Select id="sub_clinic_id" {...form.register('sub_clinic_id', { setValueAs: (v) => (v === '' || v === null || v === undefined ? null : Number(v)) })}>
                <option value="">—</option>
                {subClinics?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </Select>
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="description">{t('clinic_services.description')}</Label>
              <Textarea id="description" rows={2} {...form.register('description')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label>{t('clinic_services.image', 'صورة الخدمة')}</Label>
              <FileUpload
                value={form.watch('image')}
                onChange={(p) => form.setValue('image', p ?? '', { shouldDirty: true })}
                directory="services"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">{t('clinic_services.price')}</Label>
              <Input id="price" type="number" step="0.01" min={0} {...form.register('price', { valueAsNumber: true })} />
              {form.formState.errors.price && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.price.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_services.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} step={1} {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="flex items-end gap-3 pb-2 md:col-span-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_services.is_active')}</Label>
            </div>
            <p className="md:col-span-2 rounded-md bg-[var(--color-muted)] px-3 py-2 text-xs text-[var(--color-muted-foreground)]">
              {t('clinic_services.offers_moved_hint', 'لإضافة سعر مخفّض على هذه الخدمة، انتقل إلى "العروض" من القائمة الجانبية.')}
            </p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
      <RequestSpecialtyDialog
        open={requestOpen}
        onClose={() => setRequestOpen(false)}
        serviceId={service?.id ?? null}
      />
    </Dialog>
  );
}

export function ClinicServicesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useClinicServices({ per_page: 50, sort: 'sort_order' });
  const { data: subClinics } = useClinicSubClinics();
  const { data: profile } = useClinicProfile();
  const del = useDeleteClinicService();
  const clinicSlug = profile?.slug;
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

  const subClinicName = useMemo(() => {
    const map = new Map<number, string>();
    subClinics?.data.forEach((s) => map.set(s.id, s.name));
    return map;
  }, [subClinics]);

  // Group services under their owning clinic (sub_clinic); unassigned bucket last.
  const groups = useMemo(() => {
    const list = data?.data ?? [];
    const buckets = new Map<number | 'none', Service[]>();
    for (const s of list) {
      const key = s.sub_clinic_id ?? 'none';
      if (!buckets.has(key)) buckets.set(key, []);
      buckets.get(key)!.push(s);
    }
    const ordered: { key: number | 'none'; label: string; items: Service[] }[] = [];
    subClinics?.data.forEach((sc) => {
      const items = buckets.get(sc.id);
      if (items?.length) ordered.push({ key: sc.id, label: subClinicName.get(sc.id) ?? sc.name, items });
    });
    const none = buckets.get('none');
    if (none?.length) ordered.push({ key: 'none', label: t('clinic_services.general'), items: none });
    return ordered;
  }, [data, subClinics, subClinicName, t]);

  const renderRow = (s: Service, showSubClinic: boolean) => (
    <TableRow key={s.id}>
      <TableCell className="font-medium">{s.name}</TableCell>
      {showSubClinic && (
        <TableCell className="text-sm text-[var(--color-muted-foreground)]">
          {s.sub_clinic_id ? subClinicName.get(s.sub_clinic_id) ?? '—' : '—'}
        </TableCell>
      )}
      <TableCell>
        <span className="font-medium">{fmtCurrency(s.price)}</span>
      </TableCell>
      <TableCell>
        {s.approval_status === 'pending'
          ? <Badge variant="warning">{t('clinic_services.pending_review')}</Badge>
          : s.is_active ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}
      </TableCell>
      <TableCell className="text-end">
        <div className="flex justify-end gap-1">
          {clinicSlug && (
            <Button
              variant="ghost"
              size="icon"
              onClick={() => window.open(`/clinic/${clinicSlug}`, '_blank', 'noopener,noreferrer')}
              aria-label={t('clinic_services.preview_public')}
              title={t('clinic_services.preview_public')}
            >
              <ExternalLink className="h-4 w-4" />
            </Button>
          )}
          <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
          <Button variant="ghost" size="icon" onClick={() => setDeleting(s)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
        </div>
      </TableCell>
    </TableRow>
  );

  const header = (showSubClinic: boolean) => (
    <TableHeader>
      <TableRow>
        <TableHead>{t('clinic_services.name')}</TableHead>
        {showSubClinic && <TableHead>{t('clinic_services.sub_clinic')}</TableHead>}
        <TableHead>{t('clinic_services.price')}</TableHead>
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
          {header(true)}
          <TableBody>{data!.data.map((s) => renderRow(s, true))}</TableBody>
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
                {header(false)}
                <TableBody>{g.items.map((s) => renderRow(s, false))}</TableBody>
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
