import { useParams, useNavigate } from 'react-router-dom';
import { ArrowRight, ExternalLink, Send } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useLandingPage, useSubmitLandingPage } from '../hooks';
import { useLandingScope } from '../scope';
import { LandingPageForm } from '../components/LandingPageForm';
import { SeoTab } from '../components/SeoTab';
import { ChromeTab } from '../components/ChromeTab';
import { BlockBuilder } from '../components/builder/BlockBuilder';
import { LandingStatsTab } from '../components/LandingStatsTab';
import { LandingCustomersTab } from '../components/LandingCustomersTab';
import { AiGenerateButton } from '../components/AiGenerateButton';

const APPROVAL_VARIANT: Record<string, 'success' | 'muted' | 'warning' | 'danger'> = {
  approved: 'success',
  pending: 'warning',
  rejected: 'danger',
  draft: 'muted',
};

export function LandingPageEditor() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams();
  const { scope, basePath } = useLandingScope();
  const isClinic = scope === 'clinic';
  const pageId = id ? Number(id) : null;
  const { data: page, isLoading } = useLandingPage(pageId);
  const submit = useSubmitLandingPage();

  if (isLoading || !page) {
    return <p className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</p>;
  }

  const approval = page.approval_status ?? 'draft';
  const canSubmit = isClinic && (approval === 'draft' || approval === 'rejected');

  const handleSubmit = async () => {
    try {
      await submit.mutateAsync(page.id);
      toast.success(t('landing_pages.submitted'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => navigate(basePath)} aria-label={t('common.back')}>
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Button>
          <div>
            <h1 className="text-xl font-semibold">{page.internal_name || page.title_ar || `#${page.id}`}</h1>
            <a href={`/l/${page.slug}`} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-sm text-[var(--color-muted-foreground)] hover:underline" dir="ltr">
              /l/{page.slug}<ExternalLink className="h-3 w-3" />
            </a>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {!isClinic && <AiGenerateButton page={page} />}
          {isClinic ? (
            <>
              <Badge variant={APPROVAL_VARIANT[approval]}>{t(`landing_pages.approval.${approval}`)}</Badge>
              {canSubmit && (
                <Button size="sm" onClick={handleSubmit} disabled={submit.isPending} className="gap-1">
                  <Send className="h-4 w-4" />
                  {t('landing_pages.submit_for_approval')}
                </Button>
              )}
            </>
          ) : (
            <Badge variant={page.status === 'published' ? 'success' : page.status === 'archived' ? 'warning' : 'muted'}>
              {t(`landing_pages.statuses.${page.status}`)}
            </Badge>
          )}
        </div>
      </div>

      {/* Clinic: show the admin's rejection note so the complex can fix + resubmit. */}
      {isClinic && approval === 'rejected' && page.approval_reason && (
        <div className="rounded-lg border border-[var(--color-destructive)]/30 bg-red-50 p-3 text-sm text-[var(--color-destructive)]">
          <span className="font-medium">{t('landing_pages.rejection_reason')}: </span>{page.approval_reason}
        </div>
      )}
      {isClinic && approval === 'pending' && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
          {t('landing_pages.pending_review_hint')}
        </div>
      )}

      <Tabs defaultValue="settings" className="w-full">
        <TabsList>
          <TabsTrigger value="settings">{t('landing_pages.tab_settings')}</TabsTrigger>
          <TabsTrigger value="builder">{t('landing_pages.tab_builder')}</TabsTrigger>
          <TabsTrigger value="chrome">{t('landing_pages.tab_chrome')}</TabsTrigger>
          <TabsTrigger value="seo">{t('landing_pages.tab_seo')}</TabsTrigger>
          <TabsTrigger value="analytics">{t('landing_pages.tab_analytics')}</TabsTrigger>
          <TabsTrigger value="customers">{t('landing_pages.tab_customers')}</TabsTrigger>
        </TabsList>

        <TabsContent value="settings" className="pt-4">
          <LandingPageForm page={page} />
        </TabsContent>
        <TabsContent value="builder" className="pt-4">
          <BlockBuilder pageId={page.id} clinicId={page.clinic_id} />
        </TabsContent>
        <TabsContent value="chrome" className="pt-4">
          <ChromeTab page={page} />
        </TabsContent>
        <TabsContent value="seo" className="pt-4">
          <SeoTab page={page} />
        </TabsContent>
        <TabsContent value="analytics" className="pt-4">
          <LandingStatsTab pageId={page.id} />
        </TabsContent>
        <TabsContent value="customers" className="pt-4">
          <LandingCustomersTab pageId={page.id} />
        </TabsContent>
      </Tabs>
    </div>
  );
}
