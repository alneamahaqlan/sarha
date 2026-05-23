import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useUpdateWorkingHours, useWorkingHours } from '../hooks';
import type { WorkingHourRow } from '../api';

export function WorkingHoursSection() {
  const { t } = useTranslation();
  const { data, isLoading } = useWorkingHours();
  const mut = useUpdateWorkingHours();
  const [rows, setRows] = useState<WorkingHourRow[]>([]);

  useEffect(() => {
    if (data) setRows(data);
  }, [data]);

  const patch = (day: number, changes: Partial<WorkingHourRow>) => {
    setRows((prev) => prev.map((r) => (r.day_of_week === day ? { ...r, ...changes } : r)));
  };

  const onSave = async () => {
    try {
      await mut.mutateAsync(rows);
      toast.success(t('clinic_profile.working_hours_saved'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  if (isLoading) {
    return (
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-6 text-sm text-[var(--color-muted-foreground)]">
        {t('common.loading')}
      </div>
    );
  }

  return (
    <div className="space-y-4 rounded-lg border border-[var(--color-border)] bg-white p-6">
      <div>
        <h2 className="text-lg font-semibold">{t('clinic_profile.working_hours')}</h2>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_profile.working_hours_hint')}</p>
      </div>

      <div className="space-y-2">
        {rows.map((row) => (
          <div key={row.day_of_week} className="flex flex-wrap items-center gap-3 border-b border-[var(--color-border)] py-2 last:border-0">
            <span className="w-28 text-sm font-medium">{t(`clinic_profile.days.${row.day_of_week}`)}</span>
            <div className="flex items-center gap-2">
              <Switch
                checked={row.is_open}
                onCheckedChange={(c) => patch(row.day_of_week, { is_open: c })}
              />
              <span className="text-xs text-[var(--color-muted-foreground)]">
                {row.is_open ? t('clinic_profile.open') : t('clinic_profile.closed')}
              </span>
            </div>
            <Input
              type="time"
              dir="ltr"
              className="w-32"
              value={row.open_time ?? ''}
              disabled={!row.is_open}
              onChange={(e) => patch(row.day_of_week, { open_time: e.target.value || null })}
            />
            <span className="text-[var(--color-muted-foreground)]">—</span>
            <Input
              type="time"
              dir="ltr"
              className="w-32"
              value={row.close_time ?? ''}
              disabled={!row.is_open}
              onChange={(e) => patch(row.day_of_week, { close_time: e.target.value || null })}
            />
          </div>
        ))}
      </div>

      <div className="border-t border-[var(--color-border)] pt-4">
        <Button type="button" onClick={onSave} disabled={mut.isPending}>
          {mut.isPending ? t('common.loading') : t('clinic_profile.save')}
        </Button>
      </div>
    </div>
  );
}
