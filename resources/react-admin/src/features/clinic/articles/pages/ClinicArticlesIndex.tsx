import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Check, Pencil, Plus, Sparkles, Trash2, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { RichEditor } from '@/components/forms/RichEditor';
import { FileUpload } from '@/components/forms/FileUpload';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import {
  useClinicArticles, useCreateClinicArticle, useDeleteClinicArticle,
  useGenerateArticleAi, useUpdateClinicArticle,
} from '../hooks';
import type { ClinicArticle } from '../api';

const schema = z.object({
  title: z.string().min(1).max(255),
  slug: z.string().min(1).max(255).regex(/^[a-z0-9-]+$/, 'lowercase-dashes').optional().or(z.literal('')),
  body: z.string().min(1),
  meta_description: z.string().max(300).nullish(),
  cover_image: z.string().nullish(),
  is_published: z.boolean(),
});
type FormValues = z.infer<typeof schema>;

function slugify(v: string): string {
  return v.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-').replace(/-+/g, '-');
}

function ArticleDialog({ article, onClose }: { article: ClinicArticle | null; onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateClinicArticle();
  const update = useUpdateClinicArticle(article?.id ?? 0);
  const ai = useGenerateArticleAi();

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: article?.title ?? '',
      slug: article?.slug ?? '',
      body: article?.body ?? '',
      meta_description: article?.meta_description ?? '',
      cover_image: article?.cover_image ?? '',
      is_published: article?.is_published ?? false,
    },
  });

  const onSubmit = async (v: FormValues) => {
    try {
      const payload = { ...v, slug: v.slug || undefined };
      if (article) {
        const saved = await update.mutateAsync(payload);
        if (!saved.is_published && v.is_published) {
          toast.warning(t('clinic_articles.limit_hit'));
        } else {
          toast.success(t('clinic_articles.updated'));
        }
      } else {
        const saved = await create.mutateAsync(payload);
        if (!saved.is_published && v.is_published) {
          toast.warning(t('clinic_articles.limit_hit'));
        } else {
          toast.success(t('clinic_articles.created'));
        }
      }
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) Object.entries(ve).forEach(([f, m]) => form.setError(f as keyof FormValues, { message: m[0] }));
      else toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const generate = async (kind: 'excerpt' | 'article') => {
    const title = form.getValues('title');
    if (!title.trim()) { toast.error(t('clinic_articles.ai_title_first')); return; }
    try {
      const text = await ai.mutateAsync({ kind, title });
      if (kind === 'article') form.setValue('body', text, { shouldDirty: true });
      else form.setValue('meta_description', text, { shouldDirty: true });
      toast.success(t('clinic_articles.ai_generated'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{article ? t('clinic_articles.edit') : t('clinic_articles.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_articles.subtitle')}</DialogDescription>
        </DialogHeader>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="title">{t('clinic_articles.title_field')}</Label>
              <Input
                id="title"
                {...form.register('title', {
                  onBlur: (e) => {
                    if (!form.getValues('slug') || !article) form.setValue('slug', slugify(e.target.value), { shouldDirty: true });
                  },
                })}
              />
              {form.formState.errors.title && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.title.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="slug">{t('clinic_articles.slug')}</Label>
              <Input id="slug" dir="ltr" {...form.register('slug')} />
              {form.formState.errors.slug && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.slug.message}</p>}
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="meta_description">{t('clinic_articles.excerpt')}</Label>
                <Button type="button" variant="ghost" size="sm" disabled={ai.isPending} onClick={() => generate('excerpt')}>
                  <Sparkles className="h-3 w-3" />{t('clinic_articles.ai_excerpt')}
                </Button>
              </div>
              <Textarea id="meta_description" rows={2} maxLength={300} {...form.register('meta_description')} />
            </div>
            <div className="md:col-span-2">
              <FileUpload
                label={t('clinic_articles.cover_image')}
                value={form.watch('cover_image')}
                onChange={(p) => form.setValue('cover_image', p, { shouldDirty: true })}
                directory="articles"
              />
            </div>
            <div className="space-y-1.5 md:col-span-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="body">{t('clinic_articles.body')}</Label>
                <Button type="button" variant="ghost" size="sm" disabled={ai.isPending} onClick={() => generate('article')}>
                  <Sparkles className="h-3 w-3" />{t('clinic_articles.ai_article')}
                </Button>
              </div>
              <RichEditor
                value={form.watch('body') ?? ''}
                onChange={(html) => form.setValue('body', html, { shouldDirty: true, shouldValidate: true })}
                minHeight={260}
              />
              {form.formState.errors.body && <p className="text-xs text-[var(--color-destructive)]">{form.formState.errors.body.message}</p>}
            </div>
            <div className="flex items-end gap-3 pb-2 md:col-span-2">
              <Switch checked={form.watch('is_published')} onCheckedChange={(c) => form.setValue('is_published', c, { shouldDirty: true })} />
              <Label>{t('clinic_articles.is_published')}</Label>
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

export function ClinicArticlesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useClinicArticles();
  const del = useDeleteClinicArticle();
  const [editing, setEditing] = useState<ClinicArticle | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ClinicArticle | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try { await del.mutateAsync(deleting.id); toast.success(t('clinic_articles.deleted')); setDeleting(null); }
    catch (err) { toast.error(extractMessage(err, t('errors.generic'))); }
  };

  const fmtDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US') : '—';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_articles.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_articles.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}><Plus className="h-4 w-4" />{t('clinic_articles.create')}</Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_articles.title_field')}</TableHead>
            <TableHead>{t('clinic_articles.is_published')}</TableHead>
            <TableHead>{t('clinic_articles.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((a) => (
              <TableRow key={a.id}>
                <TableCell className="font-medium">
                  {a.title}
                  {a.ai_generated && <Badge variant="muted" className="ms-2 text-xs">AI</Badge>}
                </TableCell>
                <TableCell>{a.is_published ? <Check className="h-4 w-4 text-emerald-600" /> : <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(a.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(a)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="icon" onClick={() => setDeleting(a)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(creating || editing) && <ArticleDialog article={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_articles.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_articles.delete_confirm_body')}</AlertDialogDescription>
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
