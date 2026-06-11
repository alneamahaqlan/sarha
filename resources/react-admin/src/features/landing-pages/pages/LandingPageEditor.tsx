import { useParams, useNavigate } from 'react-router-dom';
import { ArrowRight, ExternalLink } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from '@/app/providers/LocaleProvider';

import { useLandingPage } from '../hooks';
import { LandingPageForm } from '../components/LandingPageForm';
import { SeoTab } from '../components/SeoTab';
import { BlockBuilder } from '../components/builder/BlockBuilder';
import { LandingStatsTab } from '../components/LandingStatsTab';
import { AiGenerateButton } from '../components/AiGenerateButton';

export function LandingPageEditor() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { id } = useParams();
  const pageId = id ? Number(id) : null;
  const { data: page, isLoading } = useLandingPage(pageId);

  if (isLoading || !page) {
    return <p className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</p>;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => navigate('/admin/landing-pages')} aria-label={t('common.back')}>
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
          <AiGenerateButton page={page} />
          <Badge variant={page.status === 'published' ? 'success' : page.status === 'archived' ? 'warning' : 'muted'}>
            {t(`landing_pages.statuses.${page.status}`)}
          </Badge>
        </div>
      </div>

      <Tabs defaultValue="settings" className="w-full">
        <TabsList>
          <TabsTrigger value="settings">{t('landing_pages.tab_settings')}</TabsTrigger>
          <TabsTrigger value="builder">{t('landing_pages.tab_builder')}</TabsTrigger>
          <TabsTrigger value="seo">{t('landing_pages.tab_seo')}</TabsTrigger>
          <TabsTrigger value="analytics">{t('landing_pages.tab_analytics')}</TabsTrigger>
        </TabsList>

        <TabsContent value="settings" className="pt-4">
          <LandingPageForm page={page} />
        </TabsContent>
        <TabsContent value="builder" className="pt-4">
          <BlockBuilder pageId={page.id} clinicId={page.clinic_id} />
        </TabsContent>
        <TabsContent value="seo" className="pt-4">
          <SeoTab page={page} />
        </TabsContent>
        <TabsContent value="analytics" className="pt-4">
          <LandingStatsTab pageId={page.id} />
        </TabsContent>
      </Tabs>
    </div>
  );
}
