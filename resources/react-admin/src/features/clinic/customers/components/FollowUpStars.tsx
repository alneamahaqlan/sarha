import { useEffect, useState } from 'react';
import { Star } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';

interface Props {
  /** Current priority 0..3. */
  value: number;
  /** When provided, the stars become interactive (click to set). */
  onChange?: (value: number) => void;
  size?: 'sm' | 'md';
  /** Show the textual state label beside the stars. */
  showLabel?: boolean;
  disabled?: boolean;
  className?: string;
}

/** Per-level accent for the textual label (0 = neutral … 3 = ready/green). */
const LABEL_TONE: Record<number, string> = {
  0: 'text-slate-400',
  1: 'text-amber-600',
  2: 'text-orange-600',
  3: 'text-emerald-600',
};

/**
 * Odoo-style 0–3 star follow-up priority. Bright amber stars with a soft
 * glow; hovering previews a level and surfaces its name, clicking the
 * active level clears back to 0.
 */
export function FollowUpStars({ value, onChange, size = 'md', showLabel = true, disabled, className }: Props) {
  const { t } = useTranslation();
  const [hover, setHover] = useState<number | null>(null);
  // Optimistic value: fill instantly on click instead of waiting for the
  // server round-trip + refetch to flow the new value back through props.
  const [optimistic, setOptimistic] = useState<number | null>(null);
  useEffect(() => { setOptimistic(null); }, [value]);
  const interactive = !!onChange && !disabled;

  const current = optimistic ?? value;
  const shown = hover ?? current; // 0..3 currently reflected by the stars
  const labelFor = (lvl: number) => t(`clinic_customers.priority.level_${lvl}`);
  const starSize = size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <div
        className="flex items-center gap-0.5"
        onMouseLeave={() => setHover(null)}
        role={interactive ? 'radiogroup' : undefined}
        aria-label={t('clinic_customers.priority.label')}
        title={!interactive ? labelFor(value) : undefined}
      >
        {[1, 2, 3].map((i) => {
          const filled = i <= shown;
          return (
            <button
              key={i}
              type="button"
              disabled={!interactive}
              title={labelFor(i)}
              aria-label={labelFor(i)}
              aria-checked={current === i}
              onMouseEnter={interactive ? () => setHover(i) : undefined}
              onClick={
                interactive
                  ? (e) => { e.stopPropagation(); const next = current === i ? 0 : i; setOptimistic(next); onChange!(next); }
                  : undefined
              }
              className={cn(
                'rounded-full p-0.5 transition-transform duration-150',
                interactive && 'cursor-pointer hover:scale-125 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300',
                !interactive && 'cursor-default',
              )}
            >
              <Star
                className={cn(
                  starSize,
                  'transition-colors duration-150',
                  filled
                    ? 'fill-amber-400 text-amber-400 drop-shadow-[0_0_4px_rgba(251,191,36,0.55)]'
                    : 'fill-transparent text-slate-300',
                )}
              />
            </button>
          );
        })}
      </div>
      {showLabel && (
        <span className={cn('text-xs font-medium transition-colors', LABEL_TONE[shown] ?? 'text-slate-400')}>
          {labelFor(shown)}
        </span>
      )}
    </div>
  );
}
