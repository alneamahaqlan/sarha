import { useState } from 'react';
import { BellPlus } from 'lucide-react';

import { useCan } from '@/app/providers/AuthProvider';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { ReminderDialog } from './ReminderDialog';

interface Props {
  customerId: number;
  customerName?: string | null;
  bookingId?: number | null;
  /** "button" = bordered pill (page header); "ghost" = compact icon+text (cards). */
  variant?: 'button' | 'ghost';
  className?: string;
}

/**
 * "Remind me to contact" entry point. Hidden entirely when the active
 * role lacks reminders.create, so reception/coordinator/owner see it but
 * a future read-only role wouldn't.
 */
export function ReminderButton({ customerId, customerName, bookingId, variant = 'button', className }: Props) {
  const { t } = useTranslation();
  const canCreate = useCan('reminders.create');
  const [open, setOpen] = useState(false);

  if (!canCreate) return null;

  const base =
    variant === 'ghost'
      ? 'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]'
      : 'inline-flex items-center gap-1.5 rounded-md border border-[var(--color-border)] px-3 py-2 text-sm hover:bg-[var(--color-muted)]';

  return (
    <>
      <button type="button" onClick={() => setOpen(true)} className={`${base} ${className ?? ''}`}>
        <BellPlus className="h-4 w-4" />
        {t('clinic_reminders.add')}
      </button>
      {open && (
        <ReminderDialog
          open={open}
          onClose={() => setOpen(false)}
          customerId={customerId}
          customerName={customerName}
          bookingId={bookingId}
        />
      )}
    </>
  );
}
