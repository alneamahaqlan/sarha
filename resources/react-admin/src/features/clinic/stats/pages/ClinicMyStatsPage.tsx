import { useState } from 'react';

import { useTranslation } from '@/app/providers/LocaleProvider';

import { useMyStats } from '../hooks';
import type { StatsRange } from '../api';
import { StatsFilterBar } from '../components/StatsFilterBar';
import { ClinicStatsView } from '../components/ClinicStatsView';

export function ClinicMyStatsPage() {
  const { t } = useTranslation();
  const [range, setRange] = useState<StatsRange>({ period: 30 });
  const { data, isLoading } = useMyStats(range);

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_stats.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_stats.subtitle')}</p>
      </div>

      <StatsFilterBar range={range} onChange={setRange} />

      {isLoading || !data ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : (
        <ClinicStatsView data={data} />
      )}
    </div>
  );
}
