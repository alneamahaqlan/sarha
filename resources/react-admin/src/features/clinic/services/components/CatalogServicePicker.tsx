import { useRef, useState } from 'react';
import { Check, Sparkle } from 'lucide-react';

import { FieldError } from '@/components/forms/FieldError';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { useCatalogSuggest } from '../hooks';

interface Props {
  /** Current service name (controlled by the form). */
  name: string;
  /** Linked canonical catalog id, or null when the name is a new proposal. */
  catalogServiceId: number | null;
  /**
   * Fires on every change. `catalogServiceId` is the id when the user picked a
   * suggestion, or null when they typed a free name (→ new-service request).
   */
  onChange: (name: string, catalogServiceId: number | null) => void;
  error?: string;
}

/**
 * Service-name field backed by the unified catalog. As the clinic types we
 * suggest existing canonical services; picking one links it (publishes
 * instantly). Typing a name with no pick is treated as a request for a new
 * canonical service (the backend files it for admin review).
 */
export function CatalogServicePicker({ name, catalogServiceId, onChange, error }: Props) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const blurTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const debounced = useDebouncedValue(name, 250);
  const { data: suggestions } = useCatalogSuggest(debounced);

  // "New service" hint: the user typed something but hasn't linked a catalog
  // entry — submitting will file a request rather than publish immediately.
  const showNewHint = !catalogServiceId && name.trim() !== '';

  return (
    <div className="space-y-1.5">
      <div className="relative">
        <input
          type="text"
          value={name}
          autoComplete="off"
          placeholder={t('clinic_services.search_service', 'اكتب اسم الخدمة…')}
          onChange={(e) => {
            // Manual edit breaks any prior catalog link until they pick again.
            onChange(e.target.value, null);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            blurTimer.current = setTimeout(() => setOpen(false), 120);
          }}
          onKeyDown={(e) => {
            if (e.key === 'Escape') setOpen(false);
          }}
          className="h-9 w-full rounded-md border border-[var(--color-border)] bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
        />

        {open && (suggestions?.length ?? 0) > 0 && (
          <ul
            className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-[var(--color-border)] bg-white py-1 shadow-lg"
            onMouseDown={() => {
              if (blurTimer.current) clearTimeout(blurTimer.current);
            }}
          >
            {suggestions!.map((s) => (
              <li key={s.id}>
                <button
                  type="button"
                  // onMouseDown + preventDefault: select before the input's
                  // blur fires, so the option click isn't swallowed.
                  onMouseDown={(e) => {
                    e.preventDefault();
                    onChange(s.name, s.id);
                    setOpen(false);
                  }}
                  className="flex w-full items-center gap-2 px-3 py-1.5 text-start text-sm hover:bg-[var(--color-muted)]"
                >
                  <span>{s.name}</span>
                  {s.name_en ? (
                    <span className="text-xs text-[var(--color-muted-foreground)]">{s.name_en}</span>
                  ) : null}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {catalogServiceId ? (
        <p className="flex items-center gap-1 text-xs text-emerald-600">
          <Check className="h-3.5 w-3.5" />
          {t('clinic_services.linked_catalog', 'خدمة معتمدة — تُنشر فوراً')}
        </p>
      ) : showNewHint ? (
        <p className="flex items-center gap-1 text-xs text-amber-600">
          <Sparkle className="h-3.5 w-3.5" />
          {t('clinic_services.new_service_hint', 'خدمة جديدة — تُرسل لمراجعة الإدارة قبل النشر')}
        </p>
      ) : null}

      <FieldError message={error} />
    </div>
  );
}
