import { useMemo, useState } from 'react';
import { X, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { CategoryLookup } from '@/features/lookups/api';

interface Props {
  /** Currently selected category ids — controlled by react-hook-form. */
  value: number[];
  onChange: (ids: number[]) => void;
  /** Full list of categories the user can pick from (lookup data). */
  categories: CategoryLookup[] | undefined;
  /** Hard cap — server validates max 5, mirror here for instant feedback. */
  max?: number;
}

/**
 * Multi-select for specialties used in the clinic "add service" form.
 * Renders selected items as removable chips above + a dropdown below for
 * adding more. Disables the "add" button once `max` is reached.
 */
export function MultiCategorySelect({ value, onChange, categories, max = 5 }: Props) {
  const { t } = useTranslation();
  const [picking, setPicking] = useState('');

  const selected = useMemo(
    () => (categories ?? []).filter((c) => value.includes(c.id)),
    [categories, value],
  );
  const remaining = useMemo(
    () => (categories ?? []).filter((c) => !value.includes(c.id)),
    [categories, value],
  );

  const atMax = value.length >= max;

  const add = (id: number) => {
    if (atMax || value.includes(id)) return;
    onChange([...value, id]);
    setPicking('');
  };

  const remove = (id: number) => {
    onChange(value.filter((v) => v !== id));
  };

  return (
    <div className="space-y-2">
      {/* Selected chips */}
      {selected.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {selected.map((c) => (
            <span
              key={c.id}
              className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-primary)]/10 px-2.5 py-1 text-xs font-medium text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/20"
            >
              {c.emoji ? <span>{c.emoji}</span> : null}
              <span>{c.name}</span>
              <button
                type="button"
                onClick={() => remove(c.id)}
                className="inline-flex h-4 w-4 items-center justify-center rounded-full hover:bg-[var(--color-primary)]/20"
                aria-label={t('common.delete')}
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      ) : (
        <p className="text-xs text-[var(--color-muted-foreground)]">
          {t('clinic_services.no_categories_picked', 'لم تختر أي تخصص بعد.')}
        </p>
      )}

      {/* Add dropdown + button */}
      <div className="flex items-stretch gap-2">
        <select
          value={picking}
          onChange={(e) => setPicking(e.target.value)}
          disabled={atMax || remaining.length === 0}
          className="h-9 flex-1 rounded-md border border-[var(--color-border)] bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] disabled:bg-[var(--color-muted)] disabled:opacity-60"
        >
          <option value="">
            {atMax
              ? t('clinic_services.categories_max_reached', 'وصلت للحد الأقصى من التخصصات')
              : t('clinic_services.pick_category', 'اختر تخصصاً للإضافة')}
          </option>
          {remaining.map((c) => (
            <option key={c.id} value={c.id}>
              {c.emoji ? `${c.emoji} ` : ''}
              {c.name}
            </option>
          ))}
        </select>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={!picking || atMax}
          onClick={() => add(Number(picking))}
        >
          <Plus className="h-4 w-4" />
          {t('homepage_sections.add', 'إضافة')}
        </Button>
      </div>

      {/* Counter */}
      <p className="text-[11px] text-[var(--color-muted-foreground)]">
        {t('clinic_services.categories_counter', '{{count}} من {{max}}', {
          count: value.length,
          max,
        })}
      </p>
    </div>
  );
}
