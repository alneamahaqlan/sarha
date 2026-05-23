import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { ClinicPlan, ClinicStatus } from '../types';

const STATUS_VARIANT: Record<ClinicStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  suspended: 'danger',
  rejected: 'danger',
};

const PLAN_VARIANT: Record<ClinicPlan, 'default' | 'warning'> = {
  basic: 'default',
  premium: 'warning',
};

export function ClinicStatusBadge({ status }: { status: ClinicStatus }) {
  const { t } = useTranslation();
  return <Badge variant={STATUS_VARIANT[status]}>{t(`clinics.status.${status}`)}</Badge>;
}

export function ClinicPlanBadge({ plan }: { plan: ClinicPlan | null }) {
  const { t } = useTranslation();
  if (!plan) return <span className="text-[var(--color-muted-foreground)]">—</span>;
  return <Badge variant={PLAN_VARIANT[plan]}>{t(`clinics.plan.${plan}`)}</Badge>;
}
