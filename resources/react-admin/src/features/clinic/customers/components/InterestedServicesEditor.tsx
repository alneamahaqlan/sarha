import { useState } from 'react';
import { Plus, X, Heart } from 'lucide-react';
import { toast } from 'sonner';
import { useQuery } from '@tanstack/react-query';

import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useCan } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { clinicServicesApi } from '@/features/clinic/services/api';

import { useAddInterestedService, useRemoveInterestedService } from '../hooks';

interface Props {
  customerId: number;
  interested: Array<{ id: number; name: string | null }>;
}

/**
 * Edits the services a customer is interested in (intent list). Feeds the
 * interest reports. Removable chips + an add dropdown.
 */
export function InterestedServicesEditor({ customerId, interested }: Props) {
  const { t } = useTranslation();
  const canManage = useCan('customers.manage');
  const add = useAddInterestedService(customerId);
  const remove = useRemoveInterestedService(customerId);
  const [serviceId, setServiceId] = useState('');

  const { data: serviceList } = useQuery({
    queryKey: ['clinic', 'services', 'list', { per_page: 100 }],
    queryFn: () => clinicServicesApi.list({ per_page: 100 }),
    staleTime: 60_000,
    enabled: canManage,
  });

  const existingIds = new Set(interested.map((i) => i.id));

  async function onAdd() {
    if (!serviceId) return;
    try {
      await add.mutateAsync(Number(serviceId));
      setServiceId('');
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onRemove(id: number) {
    try {
      await remove.mutateAsync(id);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <section className="space-y-2">
      <div className="flex items-center gap-1.5 text-xs font-semibold">
        <Heart className="h-3.5 w-3.5" /> {t('clinic_customers.interested_services.title')}
      </div>

      {interested.length === 0 ? (
        <div className="rounded-md border border-dashed border-[var(--color-border)] p-2 text-center text-[11px] text-[var(--color-muted-foreground)]">
          {t('clinic_customers.interested_services.empty')}
        </div>
      ) : (
        <div className="flex flex-wrap gap-1.5">
          {interested.map((i) => (
            <span
              key={i.id}
              className="inline-flex items-center gap-1 rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-700"
            >
              {i.name ?? '—'}
              {canManage && (
                <button type="button" onClick={() => onRemove(i.id)} aria-label={t('common.delete')}>
                  <X className="h-3 w-3" />
                </button>
              )}
            </span>
          ))}
        </div>
      )}

      {canManage && (
        <div className="flex items-center gap-2">
          <Select value={serviceId} onChange={(e) => setServiceId(e.target.value)} className="h-8 flex-1 text-xs">
            <option value="">{t('clinic_customers.interested_services.pick')}</option>
            {(serviceList?.data ?? [])
              .filter((s) => !existingIds.has(s.id))
              .map((s) => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
          </Select>
          <Button size="sm" className="h-8 gap-1" onClick={onAdd} disabled={!serviceId || add.isPending}>
            <Plus className="h-3.5 w-3.5" />
            {t('clinic_customers.interested_services.add')}
          </Button>
        </div>
      )}
    </section>
  );
}
