import { useState } from 'react';
import { toast } from 'sonner';
import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { bookingsApi, type BookingListParams } from '../api/bookings.api';
import { BOOKING_STATUSES, type BookingStatus } from '../types';

interface Props {
  /** The currently active list filters (search + status/clinic/trashed). */
  params: BookingListParams;
  onClose: () => void;
}

/** Admin bookings CSV export with scope/status/order options. */
export function BookingExportDialog({ params, onClose }: Props) {
  const { t } = useTranslation();
  const [scope, setScope] = useState<'filtered' | 'all'>('filtered');
  const [statusMode, setStatusMode] = useState<'all' | 'specific'>('all');
  const [statuses, setStatuses] = useState<BookingStatus[]>([]);
  const [order, setOrder] = useState<'desc' | 'asc'>('desc');
  const [busy, setBusy] = useState(false);

  const toggleStatus = (s: BookingStatus) =>
    setStatuses((prev) => (prev.includes(s) ? prev.filter((x) => x !== s) : [...prev, s]));

  const onExport = async () => {
    setBusy(true);
    try {
      const exportParams: BookingListParams = scope === 'all'
        ? { filter: { trashed: params.filter?.trashed } }
        : { search: params.search, filter: params.filter };
      await bookingsApi.exportCsv(exportParams, {
        statuses: statusMode === 'specific' ? statuses : undefined,
        order,
      });
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setBusy(false);
    }
  };

  const disabled = busy || (statusMode === 'specific' && statuses.length === 0);

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('bookings.export.title')}</DialogTitle>
          <DialogDescription>{t('bookings.export.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>{t('bookings.export.scope')}</Label>
            <Select value={scope} onChange={(e) => setScope(e.target.value as 'filtered' | 'all')}>
              <option value="filtered">{t('bookings.export.scope_filtered')}</option>
              <option value="all">{t('bookings.export.scope_all')}</option>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>{t('bookings.export.statuses')}</Label>
            <Select value={statusMode} onChange={(e) => setStatusMode(e.target.value as 'all' | 'specific')}>
              <option value="all">{t('bookings.export.statuses_all')}</option>
              <option value="specific">{t('bookings.export.statuses_specific')}</option>
            </Select>
            {statusMode === 'specific' && (
              <div className="mt-1.5 grid grid-cols-2 gap-1.5">
                {BOOKING_STATUSES.map((s) => (
                  <label key={s} className="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--color-border)] p-2 text-sm">
                    <input type="checkbox" checked={statuses.includes(s)} onChange={() => toggleStatus(s)} className="h-4 w-4" />
                    <span>{t(`bookings.status.${s}`)}</span>
                  </label>
                ))}
              </div>
            )}
          </div>

          <div className="space-y-1.5">
            <Label>{t('bookings.export.order')}</Label>
            <Select value={order} onChange={(e) => setOrder(e.target.value as 'desc' | 'asc')}>
              <option value="desc">{t('bookings.export.order_newest')}</option>
              <option value="asc">{t('bookings.export.order_oldest')}</option>
            </Select>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={busy}>{t('common.cancel')}</Button>
          <Button type="button" onClick={onExport} disabled={disabled} className="gap-1.5">
            <Download className="h-4 w-4" />
            {busy ? t('common.loading') : t('bookings.export.cta')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
