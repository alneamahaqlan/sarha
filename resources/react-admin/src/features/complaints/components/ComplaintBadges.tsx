import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { ComplaintPriority, ComplaintStatus } from '../types';

const STATUS_VARIANT: Record<ComplaintStatus, 'default' | 'warning' | 'success' | 'danger'> = {
  new: 'default',
  in_review: 'warning',
  resolved: 'success',
  rejected: 'danger',
};

const PRIORITY_VARIANT: Record<ComplaintPriority, 'muted' | 'warning' | 'danger'> = {
  low: 'muted',
  medium: 'warning',
  high: 'danger',
};

export function ComplaintStatusBadge({ status }: { status: ComplaintStatus }) {
  const { t } = useTranslation();
  return <Badge variant={STATUS_VARIANT[status]}>{t(`complaints.status.${status}`)}</Badge>;
}

export function ComplaintPriorityBadge({ priority }: { priority: ComplaintPriority }) {
  const { t } = useTranslation();
  return <Badge variant={PRIORITY_VARIANT[priority]}>{t(`complaints.priority.${priority}`)}</Badge>;
}
