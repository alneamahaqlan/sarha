import { useMemo, useState } from 'react';
import { Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { CopyBadge } from '@/components/ui/copy-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useClinicLookup } from '@/features/lookups/hooks';
import { extractMessage } from '@/lib/api-client';

import { useBookings, useDeleteBooking, useRestoreBooking } from '../hooks';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { BookingForm } from '../components/BookingForm';
import { BookingStatusBadge } from '../components/StatusBadge';
import { BOOKING_STATUSES, type Booking, type BookingStatus } from '../types';
import type { TrashedFilter } from '../api/bookings.api';

export function BookingsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<BookingStatus | undefined>(undefined);
  const [clinicFilter, setClinicFilter] = useState<number | undefined>(undefined);
  const [trashedFilter, setTrashedFilter] = useState<TrashedFilter>('without');
  const [editing, setEditing] = useState<Booking | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Booking | null>(null);

  const debouncedSearch = useDebouncedValue(search, 300);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: { status: statusFilter, clinic_id: clinicFilter, trashed: trashedFilter },
    }),
    [page, debouncedSearch, statusFilter, clinicFilter, trashedFilter],
  );
  const { data, isLoading, isFetching } = useBookings(queryParams);
  const { data: clinics } = useClinicLookup();
  const del = useDeleteBooking();
  const restore = useRestoreBooking();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('bookings.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const handleRestore = async (b: Booking) => {
    try {
      await restore.mutateAsync(b.id);
      toast.success(t('bookings.restored'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('bookings.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('bookings.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('bookings.create')}
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative max-w-sm flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select
          value={statusFilter ?? ''}
          onChange={(e) => { setStatusFilter((e.target.value || undefined) as BookingStatus | undefined); setPage(1); }}
          className="w-44"
        >
          <option value="">{t('bookings.filter_all_statuses')}</option>
          {BOOKING_STATUSES.map((s) => (
            <option key={s} value={s}>{t(`bookings.status.${s}`)}</option>
          ))}
        </Select>
        <Select
          value={clinicFilter ?? ''}
          onChange={(e) => { setClinicFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
          className="w-44"
        >
          <option value="">{t('bookings.filter_all_clinics')}</option>
          {clinics?.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </Select>
        <Select
          value={trashedFilter}
          onChange={(e) => { setTrashedFilter(e.target.value as TrashedFilter); setPage(1); }}
          className="w-40"
        >
          <option value="without">{t('bookings.trashed.without')}</option>
          <option value="with">{t('bookings.trashed.with')}</option>
          <option value="only">{t('bookings.trashed.only')}</option>
        </Select>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('bookings.reference')}</TableHead>
            <TableHead>{t('bookings.clinic')}</TableHead>
            <TableHead>{t('bookings.customer_name')}</TableHead>
            <TableHead>{t('bookings.customer_phone')}</TableHead>
            <TableHead>{t('bookings.service')}</TableHead>
            <TableHead>{t('bookings.status_label')}</TableHead>
            <TableHead>{t('bookings.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((booking) => (
              <TableRow key={booking.id}>
                <TableCell>
                  <CopyBadge value={booking.reference_code} />
                  {booking.is_trashed && (
                    <Badge variant="danger" className="ms-2">{t('bookings.trashed_badge')}</Badge>
                  )}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{booking.clinic?.name ?? '—'}</TableCell>
                <TableCell className="font-medium">{booking.customer_name}</TableCell>
                <TableCell dir="ltr">{booking.customer_phone}</TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{booking.service?.name ?? '—'}</TableCell>
                <TableCell><BookingStatusBadge status={booking.status} /></TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(booking.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {!booking.is_trashed && (
                      <Button variant="ghost" size="icon" onClick={() => setEditing(booking)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {booking.is_trashed ? (
                      <Button variant="ghost" size="icon" onClick={() => handleRestore(booking)} aria-label={t('bookings.restore')}>
                        <RotateCcw className="h-4 w-4" />
                      </Button>
                    ) : (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleting(booking)}
                        aria-label={t('common.delete')}
                        className="text-[var(--color-destructive)]"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {data.meta.from}–{data.meta.to} / {data.meta.total}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.meta.last_page || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              ›
            </Button>
          </div>
        </div>
      )}

      <Dialog
        open={creating || editing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false);
            setEditing(null);
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('bookings.edit') : t('bookings.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('bookings.subtitle')}</DialogDescription>
          </DialogHeader>
          <BookingForm
            booking={editing}
            onSuccess={() => { setCreating(false); setEditing(null); }}
            onCancel={() => { setCreating(false); setEditing(null); }}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('bookings.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('bookings.delete_confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
