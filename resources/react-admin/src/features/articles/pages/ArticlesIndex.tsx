import { useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';

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
import { RichEditor } from '@/components/forms/RichEditor';
import { FileUpload } from '@/components/forms/FileUpload';
import { FieldError } from '@/components/forms/FieldError';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { useClinicLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useAdminArticles, useCreateAdminArticle, useDeleteAdminArticle, useUpdateAdminArticle } from '../hooks';
import type { AdminArticle } from '../api';

const schema = z.object({
  clinic_id: z.coerce.number().int().positive(),
  title: z.string().min(1).max(255),
  slug: z.string().min(1).max(255).regex(/^[a-z0-9-]+$/, 'lowercase-dashes').optional().or(z.literal('')),
  body: z.string().min(1),
  meta_description: z.string().max(300).nullish(),
  cover_image: z.string().nullish(),
  is_published: z.boolean(),
  ai_generated: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

function slugify(v: string): string {
  return v.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-').replace(/-+/g, '-');
}

function ArticleDialog({ article, onClose }: { article: AdminArticle | null; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: clinics } = useClinicLookup();
  const create = useCreateAdminArticle();
  const update = useUpdateAdminArticle(article?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      clinic_id: article?.clinic_id ?? (clinics?.[0]?.id ?? 0),
      title: article?.title ?? '',
      slug: article?.slug ?? '',
      body: article?.body ?? '',
      meta_description: article?.meta_description ?? '',
      cover_image: article?.cover_image ?? '',
      is_published: article?.is_published ?? false,
      ai_generated: article?.ai_generated ?? false,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      const payload = { ...v, slug: v.slug || undefined };
      if (article) {
        const saved = await update.mutateAsync(payload);
        if (!saved.is_published && v.is_published) toast.warning(t('admin_articles.limit_hit'));
        else toast.success(t('admin_articles.updated'));
      } else {
        const saved = await create.mutateAsync(payload);
        if (!saved.is_published && v.is_published) toast.warning(t('admin_articles.limit_hit'));
        else toast.success(t('admin_articles.created'));
      }
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
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{article ? t('admin_articles.edit') : t('admin_articles.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('admin_articles.subtitle')}</DialogDescription>
        </DialogHeader>
        <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="clinic_id">{t('admin_articles.clinic')}</Label>
              <Select
                id="clinic_id"
                value={form.watch('clinic_id') || ''}
                onChange={(e) => form.setValue('clinic_id', Number(e.target.value), { shouldDirty: true, shouldValidate: true })}
              >
                <option value="">—</option>
                {clinics?.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </Select>
              <FieldError message={form.formState.errors.clinic_id?.message} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="title">{t('admin_articles.title_field')}</Label>
              <Input
                id="title"
                {...form.register('title', {
                  onBlur: (e) => {
                    if (!form.getValues('slug') || !article) form.setValue('slug', slugify(e.target.value), { shouldDirty: true });
                  },
                })}
              />
              <FieldError message={form.formState.errors.title?.message} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="slug">{t('admin_articles.slug')}</Label>
              <Input id="slug" dir="ltr" {...form.register('slug')} />
              <FieldError message={form.formState.errors.slug?.message} />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="meta_description">{t('admin_articles.excerpt')}</Label>
              <Textarea id="meta_description" rows={2} maxLength={300} {...form.register('meta_description')} />
            </div>
            <div className="md:col-span-2">
              <FileUpload
                label={t('admin_articles.cover_image')}
                value={form.watch('cover_image')}
                onChange={(p) => form.setValue('cover_image', p, { shouldDirty: true })}
                directory="articles"
              />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="body">{t('admin_articles.body')}</Label>
              <RichEditor
                value={form.watch('body') ?? ''}
                onChange={(html) => form.setValue('body', html, { shouldDirty: true, shouldValidate: true })}
                minHeight={260}
              />
              <FieldError message={form.formState.errors.body?.message} />
            </div>
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('is_published')} onCheckedChange={(c) => form.setValue('is_published', c, { shouldDirty: true })} />
              <Label>{t('admin_articles.is_published')}</Label>
            </div>
            <div className="flex items-end gap-3 pb-2">
              <Switch checked={form.watch('ai_generated')} onCheckedChange={(c) => form.setValue('ai_generated', c, { shouldDirty: true })} />
              <Label>{t('admin_articles.ai_generated')}</Label>
            </div>
          </div>
          <FormErrorSummary
            errors={form.formState.errors}
            labels={{
              clinic_id: t('admin_articles.clinic'),
              title: t('admin_articles.title_field'),
              slug: t('admin_articles.slug'),
              body: t('admin_articles.body'),
            }}
          />
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>{t('common.cancel')}</Button>
            <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function ArticlesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can } = useAuth();

  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [clinicFilter, setClinicFilter] = useState<number | undefined>();
  const [publishedFilter, setPublishedFilter] = useState<'all' | 'published' | 'draft'>('all');
  const [editing, setEditing] = useState<AdminArticle | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<AdminArticle | null>(null);

  const debouncedSearch = useDebouncedValue(search, 300);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: {
        clinic_id: clinicFilter,
        is_published: publishedFilter === 'all' ? undefined : publishedFilter === 'published',
      },
    }),
    [page, debouncedSearch, clinicFilter, publishedFilter],
  );

  const { data, isLoading, isFetching } = useAdminArticles(queryParams);
  const { data: clinics } = useClinicLookup();
  const del = useDeleteAdminArticle();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('admin_articles.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const fmtDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('admin_articles.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('admin_articles.subtitle')}</p>
        </div>
        {can('articles.create') && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('admin_articles.create')}
          </Button>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select
          value={clinicFilter ?? ''}
          onChange={(e) => { setClinicFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
          className="w-48"
        >
          <option value="">{t('admin_articles.filter_all_clinics')}</option>
          {clinics?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </Select>
        <Select
          value={publishedFilter}
          onChange={(e) => { setPublishedFilter(e.target.value as 'all' | 'published' | 'draft'); setPage(1); }}
          className="w-40"
        >
          <option value="all">{t('admin_articles.filter_all_statuses')}</option>
          <option value="published">{t('admin_articles.filter_published')}</option>
          <option value="draft">{t('admin_articles.filter_draft')}</option>
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('admin_articles.title_field')}</TableHead>
            <TableHead>{t('admin_articles.clinic')}</TableHead>
            <TableHead>{t('admin_articles.is_published')}</TableHead>
            <TableHead>{t('admin_articles.ai_generated')}</TableHead>
            <TableHead>{t('admin_articles.views_count')}</TableHead>
            <TableHead>{t('admin_articles.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((a) => (
              <TableRow key={a.id}>
                <TableCell className="font-medium">
                  {a.title}
                  {a.ai_generated && <Badge variant="muted" className="ms-2 text-xs">AI</Badge>}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{a.clinic?.name ?? '—'}</TableCell>
                <TableCell>{a.is_published ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell>{a.ai_generated ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell>{a.views_count}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(a.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {can('articles.update') && (
                      <Button variant="ghost" size="icon" onClick={() => setEditing(a)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {can('articles.delete') && (
                      <Button variant="ghost" size="icon" onClick={() => setDeleting(a)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {data.meta.from}–{data.meta.to} / {data.meta.total}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.meta.last_page || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              ›
            </Button>
          </div>
        </div>
      )}

      {(creating || editing) && <ArticleDialog article={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admin_articles.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('admin_articles.delete_confirm_body')}</AlertDialogDescription>
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
