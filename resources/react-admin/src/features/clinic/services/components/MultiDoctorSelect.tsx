import { useMemo, useRef, useState } from 'react';
import { X } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { PortalDropdown } from '@/components/ui/portal-dropdown';

export interface DoctorOption {
  id: number;
  name: string;
  specialty?: string | null;
}

interface Props {
  /** Currently selected doctor ids — controlled by react-hook-form. */
  value: number[];
  onChange: (ids: number[]) => void;
  /** Full list of the clinic's doctors to pick from. */
  doctors: DoctorOption[] | undefined;
}

/**
 * Searchable multi-select for the doctors who provide a service. Mirrors
 * MultiCategorySelect: selected doctors render as removable chips; typing
 * filters the rest and a dropdown lets you add them. No hard cap — a service
 * can be linked to any number of the clinic's doctors.
 */
export function MultiDoctorSelect({ value, onChange, doctors }: Props) {
  const { t } = useTranslation();
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const blurTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const anchorRef = useRef<HTMLDivElement | null>(null);

  const selected = useMemo(
    () => (doctors ?? []).filter((d) => value.includes(d.id)),
    [doctors, value],
  );

  const matches = useMemo(() => {
    const q = query.trim().toLowerCase();
    const remaining = (doctors ?? []).filter((d) => !value.includes(d.id));
    if (!q) return remaining.slice(0, 8);
    return remaining
      .filter(
        (d) =>
          d.name.toLowerCase().includes(q) ||
          (d.specialty ?? '').toLowerCase().includes(q),
      )
      .slice(0, 8);
  }, [doctors, value, query]);

  const add = (id: number) => {
    if (value.includes(id)) return;
    onChange([...value, id]);
    setQuery('');
    setOpen(false);
  };

  const remove = (id: number) => {
    onChange(value.filter((v) => v !== id));
  };

  return (
    <div className="space-y-2">
      {selected.length > 0 ? (
        <div className="flex flex-wrap gap-1.5">
          {selected.map((d) => (
            <span
              key={d.id}
              className="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-primary)]/10 px-2.5 py-1 text-xs font-medium text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/20"
            >
              <span>{d.name}</span>
              <button
                type="button"
                onClick={() => remove(d.id)}
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
          {t('clinic_services.no_doctors_picked', 'لم تختر أي طبيب بعد.')}
        </p>
      )}

      <div className="relative" ref={anchorRef}>
        <input
          type="text"
          value={query}
          placeholder={t('clinic_services.search_doctor', 'ابحث عن طبيب للإضافة…')}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
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
          className="h-9 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
        />

        <PortalDropdown anchorRef={anchorRef} open={open && (matches.length > 0 || query.trim() !== '')}>
          {matches.length > 0 ? (
            <ul
              className="max-h-56 overflow-auto rounded-md border border-[var(--color-border)] bg-white py-1 shadow-lg"
              onMouseDown={() => {
                if (blurTimer.current) clearTimeout(blurTimer.current);
              }}
            >
              {matches.map((d) => (
                <li key={d.id}>
                  <button
                    type="button"
                    onMouseDown={(e) => {
                      e.preventDefault();
                      add(d.id);
                    }}
                    className="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm hover:bg-[var(--color-muted)]"
                  >
                    <span>{d.name}</span>
                    {d.specialty ? (
                      <span className="text-xs text-[var(--color-muted-foreground)]">{d.specialty}</span>
                    ) : null}
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <div className="rounded-md border border-[var(--color-border)] bg-white px-3 py-2 text-xs text-[var(--color-muted-foreground)] shadow-lg">
              {t('clinic_services.no_doctor_match', 'لا يوجد طبيب مطابق.')}
            </div>
          )}
        </PortalDropdown>
      </div>
    </div>
  );
}
