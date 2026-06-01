import { useState } from 'react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';
import type { StatsRange } from '../api';

const QUICK = [7, 14, 30, 90];

export function StatsFilterBar({ range, onChange }: { range: StatsRange; onChange: (r: StatsRange) => void }) {
  const { t } = useTranslation();
  const isCustom = 'from' in range;
  const [showCustom, setShowCustom] = useState(isCustom);
  const [from, setFrom] = useState(isCustom ? range.from : '');
  const [to, setTo] = useState(isCustom ? range.to : '');
  // Local "today" (NOT toISOString, which is UTC and lags behind local
  // time after midnight — it would block selecting today's date for
  // users ahead of UTC, e.g. Asia/Riyadh just past midnight).
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

  const pill = (active: boolean) =>
    cn(
      'inline-flex h-8 items-center rounded-md border px-3 text-xs font-medium transition-colors',
      active
        ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
        : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]',
    );

  return (
    <div className="flex flex-wrap items-center gap-2">
      {QUICK.map((p) => (
        <button
          key={p}
          type="button"
          onClick={() => { setShowCustom(false); onChange({ period: p }); }}
          className={pill(!isCustom && 'period' in range && range.period === p)}
        >
          {t('clinic_stats.period_days', { count: p })}
        </button>
      ))}
      <button type="button" onClick={() => setShowCustom((s) => !s)} className={pill(isCustom)}>
        {t('clinic_stats.custom')}
      </button>

      {showCustom && (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-[var(--color-border)] p-1.5">
          <input
            type="date" value={from} max={to || today}
            onChange={(e) => setFrom(e.target.value)}
            className="h-8 rounded-md border border-[var(--color-border)] px-2 text-xs"
          />
          <span className="text-[var(--color-muted-foreground)]">—</span>
          <input
            type="date" value={to} max={today} min={from || undefined}
            onChange={(e) => setTo(e.target.value)}
            className="h-8 rounded-md border border-[var(--color-border)] px-2 text-xs"
          />
          <button
            type="button"
            disabled={!from || !to}
            onClick={() => from && to && onChange({ from, to })}
            className="inline-flex h-8 items-center rounded-md bg-[var(--color-primary)] px-3 text-xs font-medium text-white disabled:opacity-50"
          >
            {t('clinic_stats.apply')}
          </button>
        </div>
      )}
    </div>
  );
}
