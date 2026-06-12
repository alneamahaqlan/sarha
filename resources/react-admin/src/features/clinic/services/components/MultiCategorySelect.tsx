import { useMemo, useRef, useState } from 'react';
import { X } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { PortalDropdown } from '@/components/ui/portal-dropdown';
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
 * Searchable multi-select for specialties used in the clinic "add service"
 * form. Selected items render as removable chips; typing in the input filters
 * the remaining specialties (by Arabic or English name) and the matches show
 * in a dropdown — pick one to add it. Disabled once `max` is reached.
 */
export function MultiCategorySelect({ value, onChange, categories, max = 5 }: Props) {
  const { t } = useTranslation();
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const blurTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const anchorRef = useRef<HTMLDivElement | null>(null);

  const selected = useMemo(
    () => (categories ?? []).filter((c) => value.includes(c.id)),
    [categories, value],
  );

  const atMax = value.length >= max;

  // Remaining (unselected) specialties filtered by the typed query against
  // both the Arabic and English names, case-insensitively.
  const matches = useMemo(() => {
    const q = query.trim().toLowerCase();
    const remaining = (categories ?? []).filter((c) => !value.includes(c.id));
    if (!q) return remaining.slice(0, 8);
    return remaining
      .filter(
        (c) =>
          c.name.toLowerCase().includes(q) ||
          (c.name_en ?? '').toLowerCase().includes(q),
      )
      .slice(0, 8);
  }, [categories, value, query]);

  const add = (id: number) => {
    if (atMax || value.includes(id)) return;
    onChange([...value, id]);
    setQuery('');
    setOpen(false);
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

      {/* Search input + dropdown of matches */}
      <div className="relative" ref={anchorRef}>
        <input
          type="text"
          value={query}
          disabled={atMax}
          placeholder={
            atMax
              ? t('clinic_services.categories_max_reached', 'وصلت للحد الأقصى من التخصصات')
              : t('clinic_services.search_category', 'ابحث عن تخصص للإضافة…')
          }
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            // Delay so a click on a dropdown row registers before we close.
            blurTimer.current = setTimeout(() => setOpen(false), 120);
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault();
              if (matches[0]) add(matches[0].id);
            } else if (e.key === 'Escape') {
              setOpen(false);
            }
          }}
          className="h-9 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] disabled:bg-[var(--color-muted)] disabled:opacity-60"
        />

        <PortalDropdown
          anchorRef={anchorRef}
          open={open && !atMax && (matches.length > 0 || query.trim() !== '')}
        >
          {matches.length > 0 ? (
            <ul
              className="max-h-56 overflow-auto rounded-md border border-[var(--color-border)] bg-white py-1 shadow-lg"
              onMouseDown={() => {
                // Cancel the input blur-close so the click below fires.
                if (blurTimer.current) clearTimeout(blurTimer.current);
              }}
            >
              {matches.map((c) => (
                <li key={c.id}>
                  <button
                    type="button"
                    // onMouseDown + preventDefault: select before the input's
                    // blur fires, so the option click isn't swallowed by the
                    // dropdown closing.
                    onMouseDown={(e) => {
                      e.preventDefault();
                      add(c.id);
                    }}
                    className="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm hover:bg-[var(--color-muted)]"
                  >
                    {c.emoji ? <span>{c.emoji}</span> : null}
                    <span>{c.name}</span>
                    {c.name_en ? (
                      <span className="text-xs text-[var(--color-muted-foreground)]">{c.name_en}</span>
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <div className="rounded-md border border-[var(--color-border)] bg-white px-3 py-2 text-xs text-[var(--color-muted-foreground)] shadow-lg">
              {t('clinic_services.no_category_match', 'لا يوجد تخصص مطابق.')}
            </div>
          )}
        </PortalDropdown>
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
