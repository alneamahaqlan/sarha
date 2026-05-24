import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { useClinicStatsFull } from '@/features/clinic/stats/hooks';
import type { StatsRange } from '@/features/clinic/stats/api';
import { StatsFilterBar } from '@/features/clinic/stats/components/StatsFilterBar';
import { ClinicStatsView } from '@/features/clinic/stats/components/ClinicStatsView';

import { useClinic } from '../hooks';

export function ClinicStatsPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const clinicId = id ? Number(id) : undefined;
  const [range, setRange] = useState<StatsRange>({ period: 30 });

  const { data: clinic } = useClinic(clinicId);
  const { data: stats, isLoading } = useClinicStatsFull(clinicId, range);

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <Link
          to="/admin/clinics"
          className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[var(--color-border)] hover:bg-[var(--color-muted)]"
          aria-label={t('common.back')}
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
        </Link>
        <div>
          <h1 className="text-2xl font-semibold">{clinic?.name ?? t('clinics.stats.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.stats.title')}</p>
        </div>
      </div>

      <StatsFilterBar range={range} onChange={setRange} />

      {isLoading || !stats ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : (
        <ClinicStatsView data={stats} />
      )}
    </div>
  );
}
