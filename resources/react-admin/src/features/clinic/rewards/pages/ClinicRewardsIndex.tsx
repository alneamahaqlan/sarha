import { Gift } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth, useCan } from '@/app/providers/AuthProvider';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { RewardRuleForm } from '../components/RewardRuleForm';
import { VouchersPanel } from '../components/VouchersPanel';

export function ClinicRewardsIndex() {
  const { t } = useTranslation();
  const { hasFeature } = useAuth();
  const canManage = useCan('rewards.manage');

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Gift className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_rewards.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_rewards.subtitle')}</p>
        </div>
      </div>

      {!hasFeature('rewards') ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {t('clinic_rewards.feature_off')}
        </div>
      ) : !canManage ? (
        // Reception: vouchers + redeem only, no rule-config tab.
        <VouchersPanel />
      ) : (
        <Tabs defaultValue="vouchers">
          <TabsList className="grid w-full max-w-sm grid-cols-2">
            <TabsTrigger value="vouchers">{t('clinic_rewards.tab.vouchers')}</TabsTrigger>
            <TabsTrigger value="rule">{t('clinic_rewards.tab.rule')}</TabsTrigger>
          </TabsList>
          <TabsContent value="vouchers" className="pt-4">
            <VouchersPanel />
          </TabsContent>
          <TabsContent value="rule" className="pt-4">
            <RewardRuleForm />
          </TabsContent>
        </Tabs>
      )}
    </div>
  );
}
