import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { ClinicPlan, ClinicStatus } from '../types';

const STATUS_VARIANT: Record<ClinicStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  suspended: 'danger',
  rejected: 'danger',
};

const PLAN_VARIANT: Record<ClinicPlan, 'info' | 'gold' | 'muted'> = {
  free: 'muted',
  standard: 'info',
  basic: 'info',
  premium: 'gold',
};

export function ClinicStatusBadge({ status }: { status: ClinicStatus }) {
  const { t } = useTranslation();
  return <Badge variant={STATUS_VARIANT[status]}>{t(`clinics.status.${status}`)}</Badge>;
}

export function ClinicPlanBadge({ plan }: { plan: ClinicPlan | string | null }) {
  const { t } = useTranslation();
  if (!plan) return <span className="text-[var(--color-muted-foreground)]">—</span>;
  // Fall back to a muted variant + the raw slug for any unmapped plan, so an
  // unexpected value never leaks an untranslated `clinics.plan.*` key.
  return (
    <Badge variant={PLAN_VARIANT[plan as ClinicPlan] ?? 'muted'}>
      {t(`clinics.plan.${plan}`, { defaultValue: plan })}
    </Badge>
  );
}
