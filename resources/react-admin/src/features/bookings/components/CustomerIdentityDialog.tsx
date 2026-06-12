import { BadgeCheck } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useCustomerIdentity } from '../hooks';

interface Props {
  phone: string | null;
  onClose: () => void;
}

/**
 * Admin-only customer identity card: the primary (self-registered) name
 * plus every alternative name, each labeled with the clinic that added
 * it. Opened from the bookings table's customer-name cell.
 */
export function CustomerIdentityDialog({ phone, onClose }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useCustomerIdentity(phone);

  return (
    <Dialog open={phone !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('bookings.identity.title')}</DialogTitle>
          <DialogDescription className="sr-only">{t('bookings.identity.title')}</DialogDescription>
        </DialogHeader>

        {isLoading || !data ? (
          <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
            {t('common.loading')}
          </div>
        ) : (
          <div className="space-y-4">
            {/* Primary name */}
            <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)]/30 p-3">
              <div className="text-[11px] text-[var(--color-muted-foreground)]">
                {t('bookings.identity.primary_name')}
              </div>
              <div className="mt-0.5 flex items-center gap-1.5">
                <span className="text-base font-semibold">{data.primary_name}</span>
                {data.is_verified && (
                  <span
                    className="inline-flex items-center gap-0.5 text-[var(--color-primary)]"
                    title={t('bookings.identity.verified')}
                  >
                    <BadgeCheck className="h-4 w-4" />
                    <span className="text-[10px]">{t('bookings.identity.verified')}</span>
                  </span>
                )}
              </div>
              <div className="mt-1 text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">
                {data.phone}
              </div>
            </div>

            {/* Alternative names, each labeled with its origin clinic */}
            <div>
              <div className="mb-1.5 text-xs font-semibold">{t('bookings.identity.alt_names')}</div>
              {data.alternatives.length === 0 ? (
                <div className="rounded-md border border-dashed border-[var(--color-border)] p-3 text-center text-[11px] text-[var(--color-muted-foreground)]">
                  {t('bookings.identity.no_alts')}
                </div>
              ) : (
                <ul className="space-y-1.5">
                  {data.alternatives.map((a, i) => (
                    <li
                      key={`${a.name}-${a.clinic_id}-${i}`}
                      className="flex items-center justify-between rounded-md border border-[var(--color-border)] bg-white p-2 text-sm"
                    >
                      <span className="font-medium">{a.name}</span>
                      {a.source === 'self' ? (
                        <Badge variant="info" className="text-[10px]">
                          {t('bookings.identity.source_self')}
                        </Badge>
                      ) : (
                        <span className="text-[11px] text-[var(--color-muted-foreground)]">
                          {a.clinic_name ?? t('bookings.identity.unknown_clinic')}
                        </span>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
