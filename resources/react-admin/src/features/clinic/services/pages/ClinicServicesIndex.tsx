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
import { Money } from '@/lib/money';
import { cn } from '@/lib/utils';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { FileUpload } from '@/components/forms/FileUpload';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import type { Service } from '@/features/services/types';
import { useClinicSubClinics } from '@/features/clinic/sub-clinics/hooks';
import { useClinicProfile } from '@/features/clinic/profile/hooks';
import { useCategoryLookup } from '@/features/lookups/hooks';

import {
  useClinicServices, useCreateClinicService, useDeleteClinicService,
  useSubClinicLookup, useUpdateClinicService,
} from '../hooks';
import { MultiCategorySelect } from '../components/MultiCategorySelect';
import { MultiDoctorSelect } from '../components/MultiDoctorSelect';
import { CatalogServicePicker } from '../components/CatalogServicePicker';
import { RequestSpecialtyDialog } from '../components/RequestSpecialtyDialog';
import { useClinicDoctors } from '@/features/clinic/doctors/hooks';

const schema = z
  .object({
    name: z.string().min(1).max(255),
    // Set when the clinic picked an existing canonical service from the
    // catalog typeahead (→ instant publish); null → backend files a request.
    catalog_service_id: z.number().int().positive().nullable().optional(),
    // 1–5 specialties — mirrors StoreServiceRequest / UpdateServiceRequest.
    // Each service must belong to at least one specialty, up to five.
    category_ids: z.array(z.number().int().positive()).min(1).max(5),
    // Doctors who provide this service (optional, any number).
    doctor_ids: z.array(z.number().int().positive()).default([]),
    sub_clinic_id: z.union([z.number(), z.literal('')]).optional().nullable()
      .transform((v) => (v === '' || v === undefined ? null : (v as number))),
    description: z.string().nullish(),
    price: z.number().min(0),
    // Optional discounted price → backend creates/updates a real service offer.
    offer_price: z.union([z.number(), z.nan()]).nullish()
      .transform((v) => (v === undefined || v === null || Number.isNaN(v) ? null : v)),
    offer_ends_at: z.string().nullish(),
    price_from: z.boolean().default(false),
    price_includes: z.string().nullish(),
    price_excludes: z.string().nullish(),
    image: z.string().nullish(),
    is_active: z.boolean(),
    sort_order: z.number().int().min(0).default(0),
  })
  // The discounted price only makes sense below the regular price.
  .refine((v) => v.offer_price == null || v.offer_price < v.price, {
    path: ['offer_price'],
    message: 'سعر العرض لازم يكون أقل من السعر الأساسي.',
  });
type FormValues = z.infer<typeof schema>;

function ServiceDialog({ service, onClose }: { service: Service | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: subClinics } = useSubClinicLookup();
  const { data: categories } = useCategoryLookup();
  const { data: doctorsPage } = useClinicDoctors();
  const create = useCreateClinicService();
  const update = useUpdateClinicService(service?.id ?? 0);
  const [requestOpen, setRequestOpen] = useState(false);
  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      name: service?.name ?? '',
      catalog_service_id: service?.catalog_service_id ?? null,
      // Edit mode: prefer category_ids (new API); fall back to the legacy
      // single category_id if the row hasn't been resaved since the
      // many-to-many migration. Create mode: empty list.
      category_ids: service?.category_ids ?? [],
      doctor_ids: service?.doctor_ids ?? [],
      sub_clinic_id: service?.sub_clinic_id ?? null,
      description: service?.description ?? '',
      price: service?.price ?? 0,
      offer_price: service?.offer_price ?? null,
      offer_ends_at: service?.offer_ends_at ?? '',
      price_from: service?.price_from ?? false,
      price_includes: service?.price_includes ?? '',
      price_excludes: service?.price_excludes ?? '',
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
        <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="name">{t('clinic_services.name')}</Label>
              <CatalogServicePicker
                name={form.watch('name')}
                catalogServiceId={form.watch('catalog_service_id') ?? null}
                onChange={(n, id) => {
                  form.setValue('name', n, { shouldDirty: true, shouldValidate: true });
                  form.setValue('catalog_service_id', id, { shouldDirty: true });
                }}
                error={form.formState.errors.name?.message}
              />
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
              <FieldError
                message={
                  form.formState.errors.category_ids
                    ? t('clinic_services.categories_required', 'اختر تخصصاً واحداً على الأقل (وحتى 5).')
                    : undefined
                }
              />
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
              <Label>{t('clinic_services.doctors', 'الأطباء المقدّمون لهذه الخدمة')}</Label>
              <MultiDoctorSelect
                value={form.watch('doctor_ids') ?? []}
                onChange={(ids) => form.setValue('doctor_ids', ids, { shouldDirty: true })}
                doctors={(doctorsPage?.data ?? []).map((d) => ({ id: d.id, name: d.name, specialty: d.specialty }))}
              />
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {t('clinic_services.doctors_hint', 'تظهر هذه الخدمة في ملف كل طبيب تختاره.')}
              </p>
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
                hint={t('clinic_services.image_hint')}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price">{t('clinic_services.price')}</Label>
              <Input id="price" type="number" step="0.01" min={0} {...form.register('price', { valueAsNumber: true })} />
              <FieldError message={form.formState.errors.price?.message} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('clinic_services.sort_order')}</Label>
              <Input id="sort_order" type="number" min={0} step={1} {...form.register('sort_order', { valueAsNumber: true })} />
            </div>
            <div className="flex items-center gap-3 md:col-span-2">
              <Switch checked={form.watch('price_from')} onCheckedChange={(c) => form.setValue('price_from', c, { shouldDirty: true })} />
              <Label>{t('clinic_services.price_from', 'السعر يبدأ من (الحد الأدنى)')}</Label>
            </div>
            {/* Inline offer: an optional discounted price that becomes a real
                service offer (shows in the offers section, counts down, etc.). */}
            <div className="md:col-span-2 rounded-lg border border-dashed border-[var(--color-border)] p-3 space-y-3">
              <div className="flex items-center gap-1.5 text-sm font-medium">
                <Sparkle className="h-4 w-4 text-[var(--color-primary)]" />
                {t('clinic_services.offer_section', 'سعر العرض (اختياري)')}
              </div>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="offer_price">{t('clinic_services.offer_price', 'السعر بعد الخصم')}</Label>
                  <Input
                    id="offer_price"
                    type="number"
                    step="0.01"
                    min={0}
                    placeholder={t('clinic_services.offer_price_ph', 'اتركه فارغاً إن لا يوجد عرض')}
                    {...form.register('offer_price', { setValueAs: (v) => (v === '' || v === null ? null : Number(v)) })}
                  />
                  <FieldError message={form.formState.errors.offer_price?.message} />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="offer_ends_at">{t('clinic_services.offer_ends_at', 'ينتهي العرض في')}</Label>
                  <Input id="offer_ends_at" type="date" {...form.register('offer_ends_at')} />
                </div>
              </div>
              {form.watch('offer_price') != null && form.watch('offer_price')! > 0 && form.watch('price') > 0 && form.watch('offer_price')! < form.watch('price') && (
                <p className="text-xs text-emerald-700">
                  {t('clinic_services.offer_discount_hint', 'خصم {{pct}}%', {
                    pct: Math.floor(((form.watch('price') - form.watch('offer_price')!) / form.watch('price')) * 100),
                  })}
                </p>
              )}
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="price_includes">{t('clinic_services.price_includes', 'ما يشمله السعر (اختياري)')}</Label>
              <Textarea id="price_includes" rows={2} placeholder={t('clinic_services.price_includes_ph', 'مثال: الكشف الأولي + استشارة الطبيب')} {...form.register('price_includes')} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="price_excludes">{t('clinic_services.price_excludes', 'ما لا يشمله السعر (اختياري)')}</Label>
              <Textarea id="price_excludes" rows={2} placeholder={t('clinic_services.price_excludes_ph', 'مثال: الأشعة والتحاليل')} {...form.register('price_excludes')} />
            </div>
            <div className="flex items-end gap-3 pb-2 md:col-span-2">
              <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
              <Label>{t('clinic_services.is_active')}</Label>
            </div>
            <p className="md:col-span-2 rounded-md bg-[var(--color-muted)] px-3 py-2 text-xs text-[var(--color-muted-foreground)]">
              {t('clinic_services.offers_moved_hint', 'سعر العرض هنا يظهر تلقائياً ضمن قسم "العروض"؛ لمزيد من إعدادات العرض افتح صفحة العروض.')}
            </p>
          </div>
          <FormErrorSummary
            errors={form.formState.errors}
            labels={{
              name: t('clinic_services.name'),
              category_ids: t('clinic_services.categories', 'التخصصات'),
              price: t('clinic_services.price'),
            }}
          />
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
        <Money value={s.price} locale={locale} className="font-medium" />
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
