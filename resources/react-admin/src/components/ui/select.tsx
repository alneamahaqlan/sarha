import {
  Children,
  forwardRef,
  isValidElement,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEventHandler,
  type MutableRefObject,
  type ReactNode,
  type SelectHTMLAttributes,
} from 'react';
import { Check, ChevronDown, Search } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useTranslation } from '@/app/providers/LocaleProvider';

export interface NativeSelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  /** Force the search box on/off. Defaults to auto (shown when options > 5). */
  searchable?: boolean;
  /** Placeholder shown when no option is selected. */
  placeholder?: string;
}

/** Number of options above which the search box appears automatically. */
const SEARCH_THRESHOLD = 5;

/** Arabic-aware normalization so "احمد" matches "أحمد", ignoring diacritics. */
function normalize(input: string): string {
  return input
    .toLowerCase()
    .replace(/[ً-ٰٟ]/g, '') // tashkeel / superscript alef
    .replace(/[إأآا]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ئ/g, 'ي')
    .replace(/ؤ/g, 'و')
    .replace(/ة/g, 'ه')
    .replace(/\s+/g, ' ')
    .trim();
}

interface OptionItem {
  value: string;
  label: string;
  disabled?: boolean;
}

function extractText(node: ReactNode): string {
  if (node == null || node === false || node === true) return '';
  if (typeof node === 'string' || typeof node === 'number') return String(node);
  if (Array.isArray(node)) return node.map(extractText).join('');
  if (isValidElement(node)) return extractText((node.props as { children?: ReactNode }).children);
  return '';
}

/** Flatten <option> / <optgroup> children into a searchable list. */
function collectOptions(children: ReactNode): OptionItem[] {
  const out: OptionItem[] = [];
  Children.forEach(children, (child) => {
    if (!isValidElement(child)) return;
    if (child.type === 'option') {
      const props = child.props as { value?: unknown; disabled?: boolean; children?: ReactNode };
      out.push({
        value: String(props.value ?? ''),
        label: extractText(props.children),
        disabled: !!props.disabled,
      });
    } else if (child.type === 'optgroup') {
      out.push(...collectOptions((child.props as { children?: ReactNode }).children));
    }
  });
  return out;
}

/**
 * Searchable dropdown that preserves the native <select> contract.
 *
 * A real (visually hidden) <select> remains the form element, so react-hook-form
 * `register(...)`, controlled `value`/`onChange`, `name`, and `<option>` children
 * all keep working unchanged across the ~55 call sites. A visible layer adds the
 * search box; picking an option sets the hidden select via the native value setter
 * and dispatches a bubbling `change` event so React's onChange fires normally.
 */
export const Select = forwardRef<HTMLSelectElement, NativeSelectProps>(function Select(
  { className, children, searchable, placeholder, onChange, disabled, ...props },
  ref,
) {
  const { t } = useTranslation();
  const innerRef = useRef<HTMLSelectElement | null>(null);
  const wrapRef = useRef<HTMLDivElement | null>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [currentValue, setCurrentValue] = useState('');

  const options = useMemo(() => collectOptions(children), [children]);
  const showSearch = searchable ?? options.length > SEARCH_THRESHOLD;

  const setRefs = (el: HTMLSelectElement | null) => {
    innerRef.current = el;
    if (typeof ref === 'function') ref(el);
    else if (ref) (ref as MutableRefObject<HTMLSelectElement | null>).current = el;
  };

  // Mirror the hidden <select> value into the visible label. Covers controlled
  // `value` AND register/reset (which write the DOM via ref). Guarded → no loop.
  useEffect(() => {
    const el = innerRef.current;
    if (el && el.value !== currentValue) setCurrentValue(el.value);
  });

  const selectedLabel = options.find((o) => o.value === currentValue)?.label ?? '';

  const filtered = useMemo(() => {
    if (!query) return options;
    const q = normalize(query);
    return options.filter((o) => normalize(o.label).includes(q));
  }, [options, query]);

  const pick = (value: string) => {
    const el = innerRef.current;
    if (!el) return;
    const setter = Object.getOwnPropertyDescriptor(window.HTMLSelectElement.prototype, 'value')?.set;
    setter?.call(el, value);
    el.dispatchEvent(new Event('change', { bubbles: true }));
    setCurrentValue(value);
    setOpen(false);
    setQuery('');
  };

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const handleNativeChange: ChangeEventHandler<HTMLSelectElement> = (e) => {
    setCurrentValue(e.target.value);
    onChange?.(e);
  };

  return (
    <div ref={wrapRef} className="relative">
      {/* Real form element — visually hidden but fully functional. */}
      <select
        ref={setRefs}
        onChange={handleNativeChange}
        disabled={disabled}
        aria-hidden="true"
        tabIndex={-1}
        className="sr-only"
        {...props}
      >
        {children}
      </select>

      <button
        type="button"
        disabled={disabled}
        onClick={() => !disabled && setOpen((v) => !v)}
        className={cn(
          'flex h-9 w-full items-center justify-between gap-2 rounded-md border border-[var(--color-input)] bg-white px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] disabled:cursor-not-allowed disabled:opacity-50',
          className,
        )}
      >
        <span className={cn('truncate', !selectedLabel && 'text-[var(--color-muted-foreground)]')}>
          {selectedLabel || placeholder || t('common.select')}
        </span>
        <ChevronDown className="h-4 w-4 shrink-0 opacity-50" />
      </button>

      {open && (
        <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-[var(--color-input)] bg-[var(--color-popover)] shadow-lg">
          {showSearch && (
            <div className="flex items-center gap-2 border-b border-[var(--color-input)] px-2">
              <Search className="h-4 w-4 shrink-0 opacity-50" />
              <input
                autoFocus
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder={t('common.search')}
                className="h-9 w-full bg-transparent text-sm outline-none"
              />
            </div>
          )}
          <ul className="max-h-60 overflow-auto py-1">
            {filtered.length === 0 && (
              <li className="px-3 py-2 text-sm text-[var(--color-muted-foreground)]">{t('common.no_results')}</li>
            )}
            {filtered.map((o) => (
              <li key={o.value}>
                <button
                  type="button"
                  disabled={o.disabled}
                  onClick={() => pick(o.value)}
                  className={cn(
                    'flex w-full items-center justify-between gap-2 px-3 py-2 text-start text-sm hover:bg-[var(--color-accent)] disabled:cursor-not-allowed disabled:opacity-50',
                    o.value === currentValue && 'bg-[var(--color-accent)]',
                  )}
                >
                  <span className="truncate">{o.label}</span>
                  {o.value === currentValue && <Check className="h-4 w-4 shrink-0" />}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
});
Select.displayName = 'Select';
