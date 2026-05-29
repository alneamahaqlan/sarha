import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from '@/app/providers/LocaleProvider';

import { AiSettingsTab } from '../components/AiSettingsTab';
import { AiRestrictionsTab } from '../components/AiRestrictionsTab';
import { AiAnalyticsTab } from '../phase2/components/AiAnalyticsTab';
import { AiConversationsTab } from '../phase2/components/AiConversationsTab';

/**
 * AI Assistant Center — the super-admin's single entry point for
 * everything that drives the public AI assistant ("سلمى").
 *
 * Phase 1: Settings + Restrictions/Instructions.
 * Phase 2: Analytics + Conversations.
 */
export function AiCenterPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('ai_center.title', 'مركز المساعد الذكي')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">
          {t('ai_center.subtitle', 'تحكم بإعدادات المساعد وموانعه من مكان واحد. التغييرات تَسري على ردود المساعد فوراً.')}
        </p>
      </div>

      <Tabs defaultValue="analytics">
        <TabsList>
          <TabsTrigger value="analytics">{t('ai_center.tab_analytics', 'الإحصائيات')}</TabsTrigger>
          <TabsTrigger value="conversations">{t('ai_center.tab_conversations', 'سجل المحادثات')}</TabsTrigger>
          <TabsTrigger value="settings">{t('ai_center.tab_settings', 'الإعدادات')}</TabsTrigger>
          <TabsTrigger value="restrictions">{t('ai_center.tab_restrictions', 'الموانع والتوجيهات')}</TabsTrigger>
        </TabsList>
        <TabsContent value="analytics" className="pt-4">
          <AiAnalyticsTab />
        </TabsContent>
        <TabsContent value="conversations" className="pt-4">
          <AiConversationsTab />
        </TabsContent>
        <TabsContent value="settings" className="pt-4">
          <AiSettingsTab />
        </TabsContent>
        <TabsContent value="restrictions" className="pt-4">
          <AiRestrictionsTab />
        </TabsContent>
      </Tabs>
    </div>
  );
}
