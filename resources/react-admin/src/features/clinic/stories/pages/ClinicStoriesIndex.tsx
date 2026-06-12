import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { toast } from 'sonner';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { FileUpload } from '@/components/forms/FileUpload';
import { FormErrorSummary } from '@/components/forms/FormErrorSummary';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useClinicStories, useCreateStory, useDeleteStory, useUpdateStory } from '../hooks';
import type { Story } from '../api';

const schema = z.object({
  image: z.string().min(1),
  caption: z.string().max(255).nullish().or(z.literal('')),
  ends_at: z.string().nullish().or(z.literal('')),
  is_active: z.boolean(),
  sort_order: z.number().int().min(0),
});
type FormValues = z.infer<typeof schema>;

// "2026-06-12T14:30:00.000000Z" -> "2026-06-12T14:30" for datetime-local.
function toLocalInput(iso: string | null): string {
  if (!iso) return '';
  return iso.slice(0, 16);
}

function StoryDialog({ story, onClose }: { story: Story | null; onClose: () => void }) {
  const { t } = useTranslation();
  const create = useCreateStory();
  const update = useUpdateStory(story?.id ?? 0);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema) as never,
    defaultValues: {
      image: story?.image ?? '',
      caption: story?.caption ?? '',
      ends_at: toLocalInput(story?.ends_at ?? null),
      is_active: story?.is_active ?? true,
      sort_order: story?.sort_order ?? 0,
    },
  });

  const onSubmit = async (v: FormValues) => {
    if (!v.image) {
      toast.error(t('clinic_stories.image_required'));
      return;
    }
    const payload = { ...v, ends_at: v.ends_at ? v.ends_at : null };
    try {
      if (story) { await update.mutateAsync(payload); toast.success(t('clinic_stories.updated')); }
      else { await create.mutateAsync(payload); toast.success(t('clinic_stories.created')); }
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
          <DialogTitle>{story ? t('clinic_stories.edit') : t('clinic_stories.create')}</DialogTitle>
          <DialogDescription className="sr-only">{t('clinic_stories.subtitle')}</DialogDescription>
        </DialogHeader>
        <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('clinic_stories.image')}</Label>
            <FileUpload value={form.watch('image')} onChange={(p) => form.setValue('image', p ?? '', { shouldDirty: true })} directory="stories" />
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_stories.image_hint')}</p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="caption">{t('clinic_stories.caption')}</Label>
            <Input id="caption" {...form.register('caption')} placeholder={t('clinic_stories.caption_placeholder')} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ends_at">{t('clinic_stories.ends_at')}</Label>
            <Input id="ends_at" type="datetime-local" {...form.register('ends_at')} />
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_stories.ends_at_hint')}</p>
          </div>
          <div className="flex items-center gap-3">
            <Switch checked={form.watch('is_active')} onCheckedChange={(c) => form.setValue('is_active', c, { shouldDirty: true })} />
            <Label>{t('clinic_stories.is_active')}</Label>
          </div>
          <FormErrorSummary
            errors={form.formState.errors}
            labels={{ image: t('clinic_stories.image'), caption: t('clinic_stories.caption') }}
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

export function ClinicStoriesIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const nf = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const { data, isLoading } = useClinicStories();
  const del = useDeleteStory();
  const totalViews = (data?.data ?? []).reduce((sum, s) => sum + (s.views_count || 0), 0);
  const [editing, setEditing] = useState<Story | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Story | null>(null);

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('clinic_stories.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const expired = (s: Story) => s.ends_at !== null && new Date(s.ends_at).getTime() <= Date.now();

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_stories.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_stories.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('clinic_stories.create')}
        </Button>
      </div>

      {isLoading ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : !data || data.data.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_stories.empty')}</div>
      ) : (
        <>
        {/* Total story views across all stories — the headline metric above the grid. */}
        <div className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] px-4 py-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-md bg-sky-50 text-sky-600"><Eye className="h-5 w-5" /></span>
          <div>
            <div className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_stories.total_views')}</div>
            <div className="text-xl font-semibold">{nf.format(totalViews)}</div>
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {data.data.map((s) => (
            <div key={s.id} className="rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] overflow-hidden">
              <div className="relative aspect-[9/16] bg-[var(--color-muted)]">
                {s.image_url && <img src={s.image_url} alt="" className="absolute inset-0 h-full w-full object-cover" />}
                <div className="absolute top-1.5 start-1.5 flex flex-wrap gap-1">
                  {!s.is_active && <Badge variant="warning" className="text-[10px]">{t('common.inactive')}</Badge>}
                  {expired(s) && <Badge variant="muted" className="text-[10px]">{t('clinic_stories.expired')}</Badge>}
                </div>
                {/* Per-story view count (Instagram-style), bottom corner. */}
                <div className="absolute bottom-1.5 end-1.5 inline-flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-[11px] font-medium text-white">
                  <Eye className="h-3 w-3" /> {nf.format(s.views_count)}
                </div>
              </div>
              <div className="p-2.5">
                <p className="text-sm truncate">{s.caption || '—'}</p>
                <div className="mt-2 flex justify-end gap-1">
                  <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                  <Button variant="ghost" size="icon" onClick={() => setDeleting(s)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]"><Trash2 className="h-4 w-4" /></Button>
                </div>
              </div>
            </div>
          ))}
        </div>
        </>
      )}

      {(creating || editing) && <StoryDialog story={editing} onClose={() => { setCreating(false); setEditing(null); }} />}

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('clinic_stories.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('clinic_stories.delete_confirm_body')}</AlertDialogDescription>
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
