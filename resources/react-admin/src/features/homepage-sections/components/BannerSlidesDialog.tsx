import { useState } from 'react';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { FileUpload } from '@/components/forms/FileUpload';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useBannerSlides,
  useCreateBannerSlide,
  useDeleteBannerSlide,
  useReorderBannerSlides,
  useUpdateBannerSlide,
} from '../hooks';
import type { HomepageBannerSlide, HomepageSection } from '../types';

interface Props {
  section: HomepageSection;
  onClose: () => void;
}

export function BannerSlidesDialog({ section, onClose }: Props) {
  const { t } = useTranslation();
  const { data: slides, isLoading } = useBannerSlides(section.id);
  const create = useCreateBannerSlide(section.id);
  const del = useDeleteBannerSlide(section.id);
  const reorder = useReorderBannerSlides(section.id);

  const [draft, setDraft] = useState<{ image: string | null; link_url: string }>({
    image: null,
    link_url: '',
  });
  const [deleting, setDeleting] = useState<HomepageBannerSlide | null>(null);

  const onAdd = async () => {
    if (!draft.image) {
      toast.error(t('homepage_sections.image_required', 'يجب رفع صورة أولاً'));
      return;
    }
    try {
      await create.mutateAsync({
        image: draft.image,
        link_url: draft.link_url.trim() || null,
        is_active: true,
      });
      setDraft({ image: null, link_url: '' });
      toast.success(t('homepage_sections.slide_added', 'تمت إضافة الصورة'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const move = async (idx: number, direction: -1 | 1) => {
    if (!slides) return;
    const list = [...slides];
    const target = idx + direction;
    if (target < 0 || target >= list.length) return;
    [list[idx], list[target]] = [list[target], list[idx]];
    const payload = list.map((s, i) => ({ id: s.id, sort_order: i + 1 }));
    try {
      await reorder.mutateAsync({ order: payload });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const onDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('homepage_sections.slide_deleted', 'تم حذف الصورة'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <>
      <Dialog open onOpenChange={(o) => !o && onClose()}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('homepage_sections.banner_slides_title', 'صور البنر')}</DialogTitle>
            <DialogDescription>
              {t(
                'homepage_sections.banner_slides_desc',
                'JPG / PNG / GIF / WebP — كحد أقصى 4MB لكل صورة. السحب لأعلى/أسفل لإعادة الترتيب.',
              )}
            </DialogDescription>
          </DialogHeader>

          {/* ── Add a new slide ──────────────────────────────────── */}
          <div className="space-y-3 rounded-md border border-dashed border-[var(--color-border)] p-4">
            <h3 className="text-sm font-semibold">{t('homepage_sections.add_slide', 'إضافة صورة جديدة')}</h3>
            <FileUpload
              value={draft.image}
              onChange={(p) => setDraft({ ...draft, image: p })}
              directory="homepage/banners"
              label={t('homepage_sections.slide_image', 'الصورة (jpg/png/gif/webp)')}
            />
            <div className="space-y-1.5">
              <Label htmlFor="new_link">{t('homepage_sections.slide_link', 'رابط عند الضغط (اختياري)')}</Label>
              <Input
                id="new_link"
                placeholder="https://…"
                value={draft.link_url}
                onChange={(e) => setDraft({ ...draft, link_url: e.target.value })}
              />
            </div>
            <Button type="button" onClick={onAdd} disabled={!draft.image || create.isPending}>
              {create.isPending ? t('common.loading') : t('homepage_sections.add', 'إضافة')}
            </Button>
          </div>

          {/* ── Existing slides ──────────────────────────────────── */}
          <div className="space-y-2 mt-4">
            <h3 className="text-sm font-semibold">
              {t('homepage_sections.existing_slides', 'الصور الحالية')}
              {slides && <span className="ms-2 text-xs text-[var(--color-muted-foreground)]">({slides.length})</span>}
            </h3>
            {isLoading ? (
              <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
            ) : !slides || slides.length === 0 ? (
              <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
                {t('homepage_sections.no_slides', 'لا توجد صور بعد')}
              </div>
            ) : (
              <ul className="space-y-2">
                {slides.map((s, idx) => (
                  <SlideRow
                    key={s.id}
                    slide={s}
                    sectionId={section.id}
                    isFirst={idx === 0}
                    isLast={idx === slides.length - 1}
                    onMoveUp={() => move(idx, -1)}
                    onMoveDown={() => move(idx, 1)}
                    onDelete={() => setDeleting(s)}
                  />
                ))}
              </ul>
            )}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              {t('common.close', 'إغلاق')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(o) => !o && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('homepage_sections.delete_slide_title', 'حذف الصورة')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('homepage_sections.delete_slide_body', 'لا يمكن التراجع عن هذا الإجراء.')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={onDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

interface SlideRowProps {
  slide: HomepageBannerSlide;
  sectionId: number;
  isFirst: boolean;
  isLast: boolean;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onDelete: () => void;
}

function SlideRow({ slide, sectionId, isFirst, isLast, onMoveUp, onMoveDown, onDelete }: SlideRowProps) {
  const { t } = useTranslation();
  const update = useUpdateBannerSlide(sectionId, slide.id);
  const [link, setLink] = useState(slide.link_url ?? '');

  const onToggleActive = async (c: boolean) => {
    try {
      await update.mutateAsync({ is_active: c });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const onSaveLink = async () => {
    try {
      await update.mutateAsync({ link_url: link.trim() || null });
      toast.success(t('homepage_sections.link_updated', 'تم تحديث الرابط'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <li className="flex items-center gap-3 rounded-md border border-[var(--color-border)] p-3">
      <img
        src={slide.image_url ?? `/storage/${slide.image}`}
        alt=""
        className="h-16 w-28 rounded object-cover ring-1 ring-[var(--color-border)] bg-[var(--color-muted)]"
        onError={(e) => { (e.currentTarget as HTMLImageElement).style.visibility = 'hidden'; }}
      />
      <div className="flex-1 min-w-0 space-y-1.5">
        <div className="flex items-center gap-2">
          <Input
            value={link}
            placeholder="https://…"
            onChange={(e) => setLink(e.target.value)}
            onBlur={() => link !== (slide.link_url ?? '') && onSaveLink()}
          />
          <div className="flex items-center gap-1.5">
            <Switch checked={slide.is_active} onCheckedChange={onToggleActive} />
            <span className="text-xs text-[var(--color-muted-foreground)]">
              {slide.is_active ? t('homepage_sections.on', 'مفعل') : t('homepage_sections.off', 'معطل')}
            </span>
          </div>
        </div>
        <div className="text-xs text-[var(--color-muted-foreground)] truncate">{slide.image}</div>
      </div>
      <div className="flex items-center gap-1 shrink-0">
        <Button variant="ghost" size="icon" onClick={onMoveUp} disabled={isFirst}>
          <ArrowUp className="h-4 w-4" />
        </Button>
        <Button variant="ghost" size="icon" onClick={onMoveDown} disabled={isLast}>
          <ArrowDown className="h-4 w-4" />
        </Button>
        <Button variant="ghost" size="icon" onClick={onDelete} className="text-[var(--color-destructive)]">
          <Trash2 className="h-4 w-4" />
        </Button>
      </div>
    </li>
  );
}
