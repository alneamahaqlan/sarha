import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useCategoryLookup } from '@/features/lookups/hooks';

import { useUpdateHomepageSection } from '../hooks';
import type { HomepageSection, HomepageSectionFormValues } from '../types';

interface Props {
  section: HomepageSection;
  onClose: () => void;
  onOpenSlides: () => void;
}

function toLocal(iso: string | null) {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

export function HomepageSectionEditDialog({ section, onClose, onOpenSlides }: Props) {
  const { t } = useTranslation();
  const update = useUpdateHomepageSection(section.id);
  const { data: categories } = useCategoryLookup();

  const [form, setForm] = useState<HomepageSectionFormValues>({
    title_ar: section.title_ar ?? '',
    title_en: section.title_en ?? '',
    is_active: section.is_active,
    item_limit: section.item_limit,
    banner_interval_seconds: section.banner_interval_seconds,
    show_on_mobile: section.show_on_mobile,
    show_on_desktop: section.show_on_desktop,
    starts_at: toLocal(section.starts_at) || null,
    ends_at: toLocal(section.ends_at) || null,
    config: section.config ?? {},
  });

  useEffect(() => {
    setForm({
      title_ar: section.title_ar ?? '',
      title_en: section.title_en ?? '',
      is_active: section.is_active,
      item_limit: section.item_limit,
      banner_interval_seconds: section.banner_interval_seconds,
      show_on_mobile: section.show_on_mobile,
      show_on_desktop: section.show_on_desktop,
      starts_at: toLocal(section.starts_at) || null,
      ends_at: toLocal(section.ends_at) || null,
      config: section.config ?? {},
    });
  }, [section]);

  const onSubmit = async () => {
    try {
      const payload: HomepageSectionFormValues = {
        ...form,
        title_ar: form.title_ar?.trim() ? form.title_ar : null,
        title_en: form.title_en?.trim() ? form.title_en : null,
        starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : null,
        ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null,
        config: form.config && Object.keys(form.config).length > 0 ? form.config : null,
      };
      await update.mutateAsync(payload);
      toast.success(t('homepage_sections.saved', 'تم حفظ السكشن'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const isBanner = section.type === 'banner';
  const isCategoryOffers = section.type === 'category_offers';
  const isClinicList = section.type === 'clinic_list';

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {t('homepage_sections.edit_section', 'تعديل سكشن')}:{' '}
            {t(`homepage_sections.section_name.${section.key}`, section.key)}
          </DialogTitle>
          <DialogDescription>
            <span className="font-mono text-xs">{section.key}</span> ·{' '}
            {t('homepage_sections.type', 'النوع')}: <code className="text-xs">{section.type}</code>
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-5 py-2">
          {/* ── Active + Schedule ──────────────────────────────── */}
          <div className="space-y-3 rounded-md border border-[var(--color-border)] p-4">
            <div className="flex items-center gap-3">
              <Switch
                checked={form.is_active ?? true}
                onCheckedChange={(c) => setForm({ ...form, is_active: c })}
              />
              <Label>{t('homepage_sections.is_active', 'مفعّل (يظهر للزوار)')}</Label>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="flex items-center gap-2">
                <Switch
                  checked={form.show_on_mobile ?? true}
                  onCheckedChange={(c) => setForm({ ...form, show_on_mobile: c })}
                />
                <Label className="text-sm">{t('homepage_sections.show_on_mobile', 'على الجوال')}</Label>
              </div>
              <div className="flex items-center gap-2">
                <Switch
                  checked={form.show_on_desktop ?? true}
                  onCheckedChange={(c) => setForm({ ...form, show_on_desktop: c })}
                />
                <Label className="text-sm">{t('homepage_sections.show_on_desktop', 'على الديسكتوب')}</Label>
              </div>
            </div>
          </div>

          {/* ── Title override ─────────────────────────────────── */}
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="title_ar">{t('homepage_sections.title_ar', 'عنوان السكشن (عربي)')}</Label>
              <Input
                id="title_ar"
                placeholder={t('homepage_sections.title_placeholder', 'اتركه فارغاً للنص الافتراضي')}
                value={form.title_ar ?? ''}
                onChange={(e) => setForm({ ...form, title_ar: e.target.value })}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="title_en">{t('homepage_sections.title_en', 'Section title (English)')}</Label>
              <Input
                id="title_en"
                placeholder={t('homepage_sections.title_placeholder', 'اتركه فارغاً للنص الافتراضي')}
                value={form.title_en ?? ''}
                onChange={(e) => setForm({ ...form, title_en: e.target.value })}
              />
            </div>
          </div>

          {/* ── Item limit ─────────────────────────────────────── */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="item_limit">{t('homepage_sections.item_limit', 'عدد العناصر المعروضة')}</Label>
              <Input
                id="item_limit"
                type="number"
                min={1}
                max={50}
                value={form.item_limit ?? ''}
                onChange={(e) =>
                  setForm({ ...form, item_limit: e.target.value === '' ? null : Number(e.target.value) })
                }
              />
            </div>
            {isBanner && (
              <div className="space-y-1.5">
                <Label htmlFor="banner_interval">
                  {t('homepage_sections.banner_interval', 'مدة عرض كل صورة (ثوانٍ)')}
                </Label>
                <Input
                  id="banner_interval"
                  type="number"
                  min={2}
                  max={30}
                  value={form.banner_interval_seconds ?? ''}
                  onChange={(e) =>
                    setForm({
                      ...form,
                      banner_interval_seconds: e.target.value === '' ? null : Number(e.target.value),
                    })
                  }
                />
              </div>
            )}
          </div>

          {/* ── Type-specific config ───────────────────────────── */}
          {isCategoryOffers && (
            <div className="space-y-1.5">
              <Label htmlFor="category_slug">{t('homepage_sections.category', 'التخصص')}</Label>
              <Select
                id="category_slug"
                value={form.config?.category_slug ?? ''}
                onChange={(e) =>
                  setForm({
                    ...form,
                    config: { ...(form.config ?? {}), category_slug: e.target.value || undefined },
                  })
                }
              >
                <option value="">— {t('common.select', 'اختر')} —</option>
                {categories?.map((c) => (
                  <option key={c.id} value={c.slug}>
                    {c.emoji ? `${c.emoji} ` : ''}{c.name}
                  </option>
                ))}
              </Select>
            </div>
          )}
          {isClinicList && (
            <div className="space-y-1.5">
              <Label htmlFor="source">{t('homepage_sections.clinic_source', 'مصدر القائمة')}</Label>
              <Select
                id="source"
                value={form.config?.source ?? 'featured'}
                onChange={(e) =>
                  setForm({
                    ...form,
                    config: {
                      ...(form.config ?? {}),
                      source: e.target.value as 'featured' | 'top_rated' | 'best_priced',
                    },
                  })
                }
              >
                <option value="featured">{t('homepage_sections.source_featured', 'المجمعات المميزة')}</option>
                <option value="top_rated">{t('homepage_sections.source_top_rated', 'الأعلى تقييماً')}</option>
                <option value="best_priced">{t('homepage_sections.source_best_priced', 'الأفضل سعراً')}</option>
              </Select>
            </div>
          )}
          {section.type === 'offers' && (
            <div className="space-y-1.5">
              <Label htmlFor="min_discount">
                {t('homepage_sections.min_discount', 'حد أدنى للخصم (%)')}
              </Label>
              <Input
                id="min_discount"
                type="number"
                min={0}
                max={90}
                placeholder="0"
                value={form.config?.min_discount ?? ''}
                onChange={(e) =>
                  setForm({
                    ...form,
                    config: {
                      ...(form.config ?? {}),
                      min_discount: e.target.value === '' ? undefined : Number(e.target.value),
                    },
                  })
                }
              />
            </div>
          )}

          {/* ── Schedule ────────────────────────────────────────── */}
          <details className="rounded-md border border-[var(--color-border)] p-3" open={!!(form.starts_at || form.ends_at)}>
            <summary className="cursor-pointer text-sm font-medium">
              {t('homepage_sections.schedule', 'جدولة (اختياري)')}
            </summary>
            <div className="mt-3 grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="starts_at" className="text-xs">{t('homepage_sections.starts_at', 'يبدأ في')}</Label>
                <Input
                  id="starts_at"
                  type="datetime-local"
                  value={form.starts_at ?? ''}
                  onChange={(e) => setForm({ ...form, starts_at: e.target.value || null })}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="ends_at" className="text-xs">{t('homepage_sections.ends_at', 'ينتهي في')}</Label>
                <Input
                  id="ends_at"
                  type="datetime-local"
                  value={form.ends_at ?? ''}
                  onChange={(e) => setForm({ ...form, ends_at: e.target.value || null })}
                />
              </div>
            </div>
          </details>

          {/* ── Banner-only quick link to slides editor ────────── */}
          {isBanner && (
            <div className="rounded-md border border-dashed border-[var(--color-border)] p-3 text-sm">
              <div className="flex items-center justify-between gap-2">
                <span>{t('homepage_sections.banner_slides_hint', 'صور البنر تُدار من نافذة منفصلة.')}</span>
                <Button type="button" variant="outline" size="sm" onClick={onOpenSlides}>
                  {t('homepage_sections.manage_slides', 'إدارة الصور')}
                </Button>
              </div>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={update.isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={onSubmit} disabled={update.isPending}>
            {update.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
