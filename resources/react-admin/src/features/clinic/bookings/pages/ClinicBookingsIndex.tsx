import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Pencil, Phone, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { CopyBadge } from '@/components/ui/copy-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { OutreachButtons } from '@/features/clinic/outreach/OutreachButtons';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { BookingStatusBadge } from '@/features/bookings/components/StatusBadge';
import { BOOKING_STATUSES, type Booking, type BookingStatus } from '@/features/bookings/types';

import { useClinicBookings, useClinicBookingStatusCounts, useUpdateClinicBooking } from '../hooks';

function toLocal(iso?: string | null) {
  if (!iso) return '';
  const d = new Date(iso); const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

function EditBookingDialog({ booking, onClose }: { booking: Booking; onClose: () => void }) {
  const { t } = useTranslation();
  const [status, setStatus] = useState<BookingStatus>(booking.status);
  const [appointmentAt, setAppointmentAt] = useState(toLocal(booking.appointment_at));
  const [notes, setNotes] = useState(booking.clinic_notes ?? '');
  const mut = useUpdateClinicBooking(booking.id);

  const onSubmit = async () => {
    try {
      await mut.mutateAsync({
        status,
        appointment_at: appointmentAt ? new Date(appointmentAt).toISOString() : null,
        clinic_notes: notes,
      });
      toast.success(t('clinic_bookings.updated'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_bookings.edit')}</DialogTitle>
          <DialogDescription>
            {booking.customer_name} · <span dir="ltr">{booking.customer_phone}</span>
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="status">{t('clinic_bookings.status_label')}</Label>
            <Select id="status" value={status} onChange={(e) => setStatus(e.target.value as BookingStatus)}>
              {BOOKING_STATUSES.map((s) => <option key={s} value={s}>{t(`bookings.status.${s}`)}</option>)}
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="appointment_at">{t('clinic_bookings.appointment_at')}</Label>
            <Input id="appointment_at" type="datetime-local" value={appointmentAt} onChange={(e) => setAppointmentAt(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="clinic_notes">{t('clinic_bookings.clinic_notes')}</Label>
            <Textarea id="clinic_notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={onSubmit} disabled={mut.isPending}>{mut.isPending ? t('common.loading') : t('common.save')}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicBookingsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<BookingStatus | undefined>();
  const [editing, setEditing] = useState<Booking | null>(null);

  const params = useMemo(
    () => ({ page, per_page: 15, search: debouncedSearch.trim() || undefined, filter: { status: statusFilter } }),
    [page, debouncedSearch, statusFilter],
  );
  const { data, isLoading, isFetching } = useClinicBookings(params);
  const { data: statusCounts } = useClinicBookingStatusCounts();

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_bookings.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_bookings.subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        <button
          type="button"
          onClick={() => { setStatusFilter(undefined); setPage(1); }}
          className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
            statusFilter === undefined
              ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
              : 'border-[var(--color-border)] text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]'
          }`}
        >
          {t('clinic_bookings.tab_all')} ({statusCounts?.all ?? 0})
        </button>
        {BOOKING_STATUSES.map((s) => (
          <button
            key={s}
            type="button"
            onClick={() => { setStatusFilter(s); setPage(1); }}
            className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
              statusFilter === s
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                : 'border-[var(--color-border)] text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]'
            }`}
          >
            {t(`bookings.status.${s}`)} ({statusCounts?.[s] ?? 0})
          </button>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input className="ps-9" placeholder={t('common.search')} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_bookings.reference')}</TableHead>
            <TableHead>{t('clinic_bookings.customer_name')}</TableHead>
            <TableHead>{t('clinic_bookings.customer_phone')}</TableHead>
            <TableHead>{t('clinic_bookings.service')}</TableHead>
            <TableHead>{t('clinic_bookings.appointment_at')}</TableHead>
            <TableHead>{t('clinic_bookings.status_label')}</TableHead>
            <TableHead>{t('clinic_bookings.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((b) => (
              <TableRow key={b.id}>
                <TableCell><CopyBadge value={b.reference_code} /></TableCell>
                <TableCell className="font-medium">
                  <div className="flex items-center gap-1.5">
                    <span>{b.customer_name}</span>
                    {b.is_for_relative && (
                      <Badge variant="info" className="text-[10px]">{t('bookings.booked_by_proxy_badge')}</Badge>
                    )}
                  </div>
                  {b.is_for_relative && b.booker?.name && (
                    <div className="text-xs text-[var(--color-muted-foreground)] mt-0.5">
                      {t('bookings.booked_by_proxy_line', { name: b.booker.name, phone: b.booker.phone ?? '' })}
                    </div>
                  )}
                </TableCell>
                <TableCell>
                  <a href={`tel:${b.customer_phone}`} className="inline-flex items-center gap-1 text-[var(--color-primary)]" dir="ltr">
                    <Phone className="h-3 w-3" />{b.customer_phone}
                  </a>
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{b.service?.name ?? '—'}</TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(b.appointment_at)}</TableCell>
                <TableCell><BookingStatusBadge status={b.status} /></TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmt(b.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex items-center justify-end gap-1">
                    <OutreachButtons phone={b.customer_phone} context="booking" refId={b.id} />
                    <Button variant="ghost" size="icon" onClick={() => setEditing(b)} aria-label={t('common.edit')}><Pencil className="h-4 w-4" /></Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">{data.meta.from}–{data.meta.to} / {data.meta.total}</span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>{t('common.back')}</Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>›</Button>
          </div>
        </div>
      )}

      {editing && <EditBookingDialog booking={editing} onClose={() => setEditing(null)} />}
    </div>
  );
}
