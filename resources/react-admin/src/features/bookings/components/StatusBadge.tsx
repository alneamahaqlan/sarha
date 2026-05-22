import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { BookingStatus } from '../types';

// Mirror of Filament TextColumn::color() closure for booking status.
const STATUS_VARIANT: Record<BookingStatus, 'default' | 'warning' | 'success' | 'danger' | 'muted'> = {
  new: 'default',
  contacted: 'warning',
  appointment_set: 'default',
  completed: 'success',
  no_show: 'danger',
  cancelled: 'danger',
};

export function BookingStatusBadge({ status }: { status: BookingStatus }) {
  const { t } = useTranslation();
  return <Badge variant={STATUS_VARIANT[status]}>{t(`bookings.status.${status}`)}</Badge>;
}
