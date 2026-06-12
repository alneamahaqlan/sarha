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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useUpdateClinicPageSection } from '../hooks';
import type { ClinicFaqItem, ClinicPageSection, ClinicPageSectionFormValues } from '../types';

/** Max Q&A rows the FAQ section stores — mirrors ClinicPageSection::FAQ_LIMIT. */
const FAQ_LIMIT = 5;

/** Pad the stored FAQ rows out to FAQ_LIMIT fixed slots for editing. */
function toFaqSlots(content: ClinicFaqItem[] | null): ClinicFaqItem[] {
  const rows = Array.isArray(content) ? content.slice(0, FAQ_LIMIT) : [];
  while (rows.length < FAQ_LIMIT) rows.push({ question: '', answer: '' });
  return rows;
}

interface Props {
  section: ClinicPageSection;
  /** Localized friendly name for the dialog title (e.g. "العروض"). */
  friendlyName: string;
  onClose: () => void;
}

/**
 * Per-section settings dialog. Lets the clinic admin customize:
 *   - title overrides (AR + EN)
 *   - item limit (for sections that render a collection)
 *
 * The is_active switch lives on the parent table row, not here — it's a
 * one-click affordance, not a "settings" choice.
 */
export function ClinicPageSectionEditDialog({ section, friendlyName, onClose }: Props) {
  const { t } = useTranslation();
  const update = useUpdateClinicPageSection(section.id);

  const isFaq = section.key === 'faqs';

  const [form, setForm] = useState<ClinicPageSectionFormValues>({
    title_ar: section.title_ar ?? '',
    title_en: section.title_en ?? '',
    item_limit: section.item_limit,
  });
  const [faqs, setFaqs] = useState<ClinicFaqItem[]>(() => toFaqSlots(section.content));

  useEffect(() => {
    setForm({
      title_ar: section.title_ar ?? '',
      title_en: section.title_en ?? '',
      item_limit: section.item_limit,
    });
    setFaqs(toFaqSlots(section.content));
  }, [section]);

  const setFaq = (idx: number, field: keyof ClinicFaqItem, value: string) =>
    setFaqs((rows) => rows.map((r, i) => (i === idx ? { ...r, [field]: value } : r)));

  // Sections that render a collection and benefit from an item-limit cap.
  // Others (hero, about, contact_info, floating_ctas, social_links,
  // working_hours) ignore the field — hide it for them so the form is less
  // confusing.
  const hasItemLimit = [
    'offers',
    'before_after',
    'google_reviews',
    'articles',
    'similar_services',
    'doctors',
    'sub_clinics',
    'services',
  ].includes(section.key);

  const onSubmit = async () => {
    try {
      const payload: ClinicPageSectionFormValues = {
        title_ar: form.title_ar?.trim() ? form.title_ar : null,
        title_en: form.title_en?.trim() ? form.title_en : null,
        item_limit: hasItemLimit ? form.item_limit ?? null : null,
      };
      if (isFaq) {
        // Keep only fully-filled rows; the backend re-validates + strips too.
        payload.content = faqs
          .map((r) => ({ question: r.question.trim(), answer: r.answer.trim() }))
          .filter((r) => r.question !== '' && r.answer !== '');
      }
      await update.mutateAsync(payload);
      toast.success(t('common.saved', 'تم الحفظ'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className={isFaq ? 'max-w-lg max-h-[85vh] overflow-y-auto' : 'max-w-md'}>
        <DialogHeader>
          <DialogTitle>
            {t('clinic_page_builder.edit_dialog_title', 'تخصيص القسم')}: {friendlyName}
          </DialogTitle>
          <DialogDescription>
            {t(
              'clinic_page_builder.edit_dialog_hint',
              'العنوان المخصص يحل محل العنوان الافتراضي على صفحة المجمع. اتركه فارغاً للنص الافتراضي.',
            )}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="title_ar">{t('clinic_page_builder.title_ar', 'العنوان (عربي)')}</Label>
            <Input
              id="title_ar"
              value={form.title_ar ?? ''}
              onChange={(e) => setForm((s) => ({ ...s, title_ar: e.target.value }))}
              placeholder={t('clinic_page_builder.title_placeholder', 'النص الافتراضي')}
              maxLength={255}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="title_en">{t('clinic_page_builder.title_en', 'Title (English)')}</Label>
            <Input
              id="title_en"
              value={form.title_en ?? ''}
              onChange={(e) => setForm((s) => ({ ...s, title_en: e.target.value }))}
              placeholder={t('clinic_page_builder.title_placeholder', 'النص الافتراضي')}
              maxLength={255}
              dir="ltr"
            />
          </div>

          {isFaq && (
            <div className="space-y-3">
              <div>
                <Label>{t('clinic_page_builder.faqs_label', 'الأسئلة والأجوبة')}</Label>
                <p className="text-xs text-[var(--color-muted-foreground)] mt-0.5">
                  {t(
                    'clinic_page_builder.faqs_hint',
                    'أضِف حتى 5 أسئلة شائعة. تظهر الأسئلة المكتملة فقط (سؤال + جواب) في صفحة مجمعك.',
                  )}
                </p>
              </div>
              {faqs.map((faq, idx) => (
                <div key={idx} className="space-y-1.5 rounded-lg border border-[var(--color-border)] p-3">
                  <Label htmlFor={`faq-q-${idx}`} className="text-xs">
                    {t('clinic_page_builder.faq_question', 'السؤال')} {idx + 1}
                  </Label>
                  <Input
                    id={`faq-q-${idx}`}
                    value={faq.question}
                    onChange={(e) => setFaq(idx, 'question', e.target.value)}
                    placeholder={t('clinic_page_builder.faq_question_placeholder', 'مثال: ما هي ساعات العمل؟')}
                    maxLength={255}
                  />
                  <Textarea
                    id={`faq-a-${idx}`}
                    value={faq.answer}
                    onChange={(e) => setFaq(idx, 'answer', e.target.value)}
                    placeholder={t('clinic_page_builder.faq_answer_placeholder', 'الإجابة…')}
                    maxLength={2000}
                    rows={2}
                  />
                </div>
              ))}
            </div>
          )}

          {hasItemLimit && (
            <div className="space-y-1.5">
              <Label htmlFor="item_limit">
                {t('clinic_page_builder.item_limit', 'حد عدد العناصر')}
              </Label>
              <Input
                id="item_limit"
                type="number"
                min={1}
                max={50}
                value={form.item_limit ?? ''}
                onChange={(e) =>
                  setForm((s) => ({
                    ...s,
                    item_limit: e.target.value === '' ? null : Number(e.target.value),
                  }))
                }
                placeholder={t('clinic_page_builder.item_limit_default', 'الافتراضي')}
              />
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {t(
                  'clinic_page_builder.item_limit_hint',
                  'اتركه فارغاً لاستخدام الحد الافتراضي للنظام.',
                )}
              </p>
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={update.isPending}>
            {t('common.cancel')}
          </Button>
          <Button onClick={onSubmit} disabled={update.isPending}>
            {update.isPending ? t('common.saving', 'يحفظ…') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
