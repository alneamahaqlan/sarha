import { CalendarCheck, Undo2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useConfirmAttendance, useRevokeAttendance } from '../hooks';
import type { AttendedBy } from '../types';

interface Props {
  bookingId: number;
  isAttended: boolean;
  attendedAt: string | null;
  attendedBy: AttendedBy | null;
}

function fmtDateTime(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

/**
 * Reception's attendance control on the booking detail sheet. Attendance
 * is an INDEPENDENT signal — confirming/undoing here never changes the
 * booking's status or its Kanban column. Confirm is idempotent on the
 * server; undo clears the stamp.
 */
export function AttendanceControl({ bookingId, isAttended, attendedAt, attendedBy }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const confirm = useConfirmAttendance(bookingId);
  const revoke = useRevokeAttendance(bookingId);
  const pending = confirm.isPending || revoke.isPending;

  async function onConfirm() {
    try {
      await confirm.mutateAsync();
      toast.success(t('clinic_bookings_kanban.attendance.confirmed_toast'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onRevoke() {
    try {
      await revoke.mutateAsync();
      toast.success(t('clinic_bookings_kanban.attendance.revoked_toast'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  if (isAttended) {
    return (
      <section className="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-1.5 text-xs font-semibold text-emerald-800">
            <CalendarCheck className="h-4 w-4" />
            {t('clinic_bookings_kanban.attendance.attended')}
          </div>
          <Button
            size="sm"
            variant="ghost"
            disabled={pending}
            onClick={onRevoke}
            className="h-7 gap-1 px-1.5 text-[11px] text-emerald-700 hover:bg-emerald-100"
          >
            <Undo2 className="h-3 w-3" />
            {t('clinic_bookings_kanban.attendance.undo')}
          </Button>
        </div>
        <div className="text-[11px] text-emerald-700">
          {attendedBy?.name && attendedBy.name !== '—'
            ? t('clinic_bookings_kanban.attendance.by_at', { name: attendedBy.name, time: fmtDateTime(attendedAt, locale) })
            : t('clinic_bookings_kanban.attendance.at', { time: fmtDateTime(attendedAt, locale) })}
        </div>
      </section>
    );
  }

  return (
    <section className="space-y-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
      <div className="text-xs font-semibold">{t('clinic_bookings_kanban.attendance.section_title')}</div>
      <Button
        size="sm"
        disabled={pending}
        onClick={onConfirm}
        className="w-full gap-1.5"
      >
        <CalendarCheck className="h-4 w-4" />
        {t('clinic_bookings_kanban.attendance.confirm')}
      </Button>
    </section>
  );
}
