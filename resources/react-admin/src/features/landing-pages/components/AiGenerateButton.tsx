import { useState } from 'react';
import { Sparkles } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useGenerateLanding, useUpdateLandingPage, useLandingPageBlocks, useUpdateBlock } from '../hooks';
import type { AiDraft } from '../api/landing-pages.api';
import type { LandingPage } from '../types';

interface Props {
  page: LandingPage;
}

export function AiGenerateButton({ page }: Props) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [service, setService] = useState('');
  const [draft, setDraft] = useState<AiDraft | null>(null);

  const gen = useGenerateLanding();
  const update = useUpdateLandingPage(page.id);
  const updateBlock = useUpdateBlock(page.id);
  const { data: blocks } = useLandingPageBlocks(open ? page.id : null);

  const generate = async () => {
    try {
      const d = await gen.mutateAsync({
        clinic_id: page.clinic_id,
        city_id: page.city_id,
        category_id: page.category_id,
        service: service || undefined,
      });
      setDraft(d);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const apply = async () => {
    if (!draft) return;
    try {
      await update.mutateAsync({
        title_ar: draft.title || undefined,
        seo_title_ar: draft.seo_title || undefined,
        seo_description_ar: draft.seo_description || undefined,
        seo_keywords: draft.keywords || undefined,
        cta_label_ar: draft.cta || undefined,
      });

      // Push the generated FAQ into the page's FAQ block if there is one.
      const faqBlock = blocks?.find((b) => b.type === 'faq');
      if (faqBlock && draft.faq.length) {
        await updateBlock.mutateAsync({ blockId: faqBlock.id, values: { config: { ...faqBlock.config, items: draft.faq } } });
      }

      toast.success(t('landing_pages.ai_applied'));
      setOpen(false);
      setDraft(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        <Sparkles className="h-4 w-4" />
        {t('landing_pages.ai_generate')}
      </Button>

      <Dialog open={open} onOpenChange={(o) => { setOpen(o); if (!o) setDraft(null); }}>
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t('landing_pages.ai_generate')}</DialogTitle>
            <DialogDescription>{t('landing_pages.ai_hint')}</DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="ai-service">{t('landing_pages.ai_service')}</Label>
              <Input id="ai-service" value={service} onChange={(e) => setService(e.target.value)} placeholder={t('landing_pages.ai_service_ph')} />
            </div>

            <Button type="button" onClick={generate} disabled={gen.isPending}>
              <Sparkles className="h-4 w-4" />
              {gen.isPending ? t('landing_pages.ai_generating') : t('landing_pages.ai_run')}
            </Button>

            {draft && (
              <div className="space-y-3 rounded-lg border border-[var(--color-border)] p-4 text-sm">
                <div><span className="font-medium">{t('landing_pages.title_ar')}: </span>{draft.title}</div>
                <div><span className="font-medium">{t('landing_pages.seo_description_ar')}: </span>{draft.description}</div>
                <div><span className="font-medium">{t('landing_pages.cta_label_ar')}: </span>{draft.cta}</div>
                <div><span className="font-medium">{t('landing_pages.seo_keywords')}: </span>{draft.keywords}</div>
                {draft.faq.length > 0 && (
                  <div>
                    <span className="font-medium">{t('landing_pages.blocks.faq')}:</span>
                    <ul className="mt-1 list-disc space-y-1 ps-5">
                      {draft.faq.map((f, i) => <li key={i}>{f.q}</li>)}
                    </ul>
                  </div>
                )}
                <div className="flex justify-end gap-2 pt-2">
                  <Button type="button" variant="outline" onClick={() => setDraft(null)}>{t('common.cancel')}</Button>
                  <Button type="button" onClick={apply} disabled={update.isPending}>{t('landing_pages.ai_apply')}</Button>
                </div>
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
