import { useState } from 'react';
import { Plus, Trash2, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { useQuery } from '@tanstack/react-query';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { useCan } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { Money } from '@/lib/money';
import { clinicServicesApi } from '@/features/clinic/services/api';

import { useAddBookingService, useRemoveBookingService, useUpdateBookingService } from '../hooks';
import type { BookingServiceLine } from '../types';

interface Props {
  bookingId: number;
  services: BookingServiceLine[];
  value: number;
}

/**
 * Edits the services taken on a card. Each line carries a net price the
 * staff can discount from the catalog price; the card value is their sum.
 * Shows a "missing data" warning while no service is recorded.
 */
export function BookingServicesEditor({ bookingId, services, value }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const canManage = useCan('customers.manage');

  const add = useAddBookingService(bookingId);
  const update = useUpdateBookingService(bookingId);
  const remove = useRemoveBookingService(bookingId);

  const [serviceId, setServiceId] = useState('');
  const [price, setPrice] = useState('');

  const { data: serviceList } = useQuery({
    queryKey: ['clinic', 'services', 'list', { per_page: 100 }],
    queryFn: () => clinicServicesApi.list({ per_page: 100 }),
    staleTime: 60_000,
    enabled: canManage,
  });

  function onPickService(id: string) {
    setServiceId(id);
    // Prefill the net price from the catalog price (editable = discount).
    const svc = (serviceList?.data ?? []).find((s) => String(s.id) === id);
    setPrice(svc?.price != null ? String(svc.price) : '');
  }

  async function onAdd() {
    if (!serviceId) return;
    try {
      await add.mutateAsync({ service_id: Number(serviceId), net_price: Number(price || 0) });
      setServiceId('');
      setPrice('');
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onUpdate(line: BookingServiceLine, next: string) {
    const n = Number(next);
    if (Number.isNaN(n) || n === line.net_price) return;
    try {
      await update.mutateAsync({ lineId: line.id, net_price: n });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onRemove(lineId: number) {
    try {
      await remove.mutateAsync(lineId);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <section className="space-y-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
      <div className="flex items-center justify-between">
        <div className="text-xs font-semibold">{t('clinic_bookings_kanban.services.title')}</div>
        <div className="text-xs font-semibold text-[var(--color-primary)]">
          <Money value={value} locale={locale} />
        </div>
      </div>

      {services.length === 0 ? (
        <div className="flex items-center gap-1.5 rounded-md bg-amber-50 p-2 text-[11px] text-amber-800">
          <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
          {t('clinic_bookings_kanban.services.incomplete_warning')}
        </div>
      ) : (
        <ul className="space-y-1.5">
          {services.map((line) => (
            <li key={line.id} className="flex items-center gap-2 text-sm">
              <span className="flex-1 truncate">{line.service_name ?? '—'}</span>
              {canManage ? (
                <Input
                  type="number"
                  min={0}
                  defaultValue={line.net_price}
                  dir="ltr"
                  className="h-7 w-24 text-xs"
                  onBlur={(e) => onUpdate(line, e.target.value)}
                />
              ) : (
                <span className="w-24 text-end text-xs"><Money value={line.net_price} locale={locale} /></span>
              )}
              {canManage && (
                <Button
                  size="icon"
                  variant="ghost"
                  className="h-7 w-7 text-rose-600"
                  onClick={() => onRemove(line.id)}
                  aria-label={t('common.delete')}
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}

      {canManage && (
        <div className="flex items-end gap-2 pt-1">
          <div className="flex-1 space-y-1">
            <Select
              value={serviceId}
              onChange={(e) => onPickService(e.target.value)}
              className="h-8 text-xs"
            >
              <option value="">{t('clinic_bookings_kanban.services.pick')}</option>
              {(serviceList?.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </Select>
          </div>
          <Input
            type="number"
            min={0}
            value={price}
            dir="ltr"
            placeholder={t('clinic_bookings_kanban.services.net_price')}
            className="h-8 w-24 text-xs"
            onChange={(e) => setPrice(e.target.value)}
          />
          <Button size="sm" className="h-8 gap-1" onClick={onAdd} disabled={!serviceId || add.isPending}>
            <Plus className="h-3.5 w-3.5" />
            {t('clinic_bookings_kanban.services.add')}
          </Button>
        </div>
      )}
    </section>
  );
}
