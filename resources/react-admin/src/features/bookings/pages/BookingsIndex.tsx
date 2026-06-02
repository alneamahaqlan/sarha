import { useMemo, useState } from 'react';
import { Download, Pencil, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
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
import { useClinicLookup, useSubClinicLookup, useServiceLookup } from '@/features/lookups/hooks';
import { extractMessage } from '@/lib/api-client';

import { useBookings, useBookingStatusCounts, useDeleteBooking, useRestoreBooking } from '../hooks';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { BookingForm } from '../components/BookingForm';
import { BookingExportDialog } from '../components/BookingExportDialog';
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
  const [subClinicFilter, setSubClinicFilter] = useState<number | undefined>(undefined);
  const [serviceFilter, setServiceFilter] = useState<number | undefined>(undefined);
  const [trashedFilter, setTrashedFilter] = useState<TrashedFilter>('without');
  const [editing, setEditing] = useState<Booking | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<Booking | null>(null);
  const [exporting, setExporting] = useState(false);

  const debouncedSearch = useDebouncedValue(search, 300);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: {
        status: statusFilter,
        clinic_id: clinicFilter,
        sub_clinic_id: subClinicFilter,
        service_id: serviceFilter,
        trashed: trashedFilter,
      },
    }),
    [page, debouncedSearch, statusFilter, clinicFilter, subClinicFilter, serviceFilter, trashedFilter],
  );
  const { data, isLoading, isFetching } = useBookings(queryParams);
  const { data: statusCounts } = useBookingStatusCounts();
  const { data: clinics } = useClinicLookup();
  const { data: subClinics } = useSubClinicLookup(clinicFilter);
  const { data: services } = useServiceLookup(clinicFilter);

  // Sub-clinic and service belong to a complex, so reset them whenever the
  // selected clinic changes (or clears).
  const handleClinicChange = (id: number | undefined) => {
    setClinicFilter(id);
    setSubClinicFilter(undefined);
    setServiceFilter(undefined);
    setPage(1);
  };
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
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('bookings.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('bookings.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => setExporting(true)} className="gap-1.5">
            <Download className="h-4 w-4" />
            {t('bookings.export.cta')}
          </Button>
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('bookings.create')}
          </Button>
        </div>
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
          {t('bookings.tab_all')} ({statusCounts?.all ?? 0})
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
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
        <Select
          value={clinicFilter ?? ''}
          onChange={(e) => handleClinicChange(e.target.value ? Number(e.target.value) : undefined)}
          className="w-44"
        >
          <option value="">{t('bookings.filter_all_clinics')}</option>
          {clinics?.map((c) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
        </Select>
        {clinicFilter && (
          <>
            <Select
              value={subClinicFilter ?? ''}
              onChange={(e) => { setSubClinicFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
              className="w-44"
            >
              <option value="">{t('bookings.filter_all_sub_clinics')}</option>
              {subClinics?.map((sc) => (
                <option key={sc.id} value={sc.id}>{sc.name}</option>
              ))}
            </Select>
            <Select
              value={serviceFilter ?? ''}
              onChange={(e) => { setServiceFilter(e.target.value ? Number(e.target.value) : undefined); setPage(1); }}
              className="w-44"
            >
              <option value="">{t('bookings.filter_all_services')}</option>
              {services?.map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </Select>
          </>
        )}
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
                <TableCell className="font-medium">
                  <div className="flex items-center gap-1.5">
                    <span>{booking.customer_name}</span>
                    {booking.is_for_relative && (
                      <Badge variant="info" className="text-[10px]">{t('bookings.booked_by_proxy_badge')}</Badge>
                    )}
                  </div>
                  {booking.is_for_relative && booking.booker?.name && (
                    <div className="text-xs text-[var(--color-muted-foreground)] mt-0.5">
                      {t('bookings.booked_by_proxy_line', { name: booking.booker.name, phone: booking.booker.phone ?? '' })}
                    </div>
                  )}
                </TableCell>
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

      {exporting && (
        <BookingExportDialog
          params={{ search: queryParams.search, filter: queryParams.filter }}
          onClose={() => setExporting(false)}
        />
      )}

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
