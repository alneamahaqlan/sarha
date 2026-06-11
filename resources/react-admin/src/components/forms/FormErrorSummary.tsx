import type { FieldErrors } from 'react-hook-form';
import { AlertCircle } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';

interface Props {
  /**
   * Validation errors. Supports both shapes used across the app:
   *  - react-hook-form `formState.errors` (values are `{ message }` objects), and
   *  - manual validation state `Record<string, string>` (values are plain strings).
   */
  errors: FieldErrors | Record<string, string | undefined>;
  /** Optional map of field name → human label shown in the list. */
  labels?: Record<string, string>;
}

/**
 * Red summary box listing every field that failed validation, rendered below the
 * form (above the save button). Shows nothing when the form is valid. Pair with
 * <FieldError> for the inline message under each field.
 */
export function FormErrorSummary({ errors, labels }: Props) {
  const { t } = useTranslation();

  const items = Object.entries(errors)
    .filter(([, err]) => err != null)
    .map(([name, err]) => {
      const message = typeof err === 'string' ? err : (err as { message?: string } | undefined)?.message;
      const label = labels?.[name] ?? name;
      return { name, text: message ? `${label}: ${message}` : label };
    });

  if (items.length === 0) return null;

  return (
    <div
      role="alert"
      className="rounded-md border border-[var(--color-destructive)] bg-[var(--color-destructive)]/10 p-3 text-sm text-[var(--color-destructive)]"
    >
      <div className="mb-1 flex items-center gap-2 font-semibold">
        <AlertCircle className="h-4 w-4 shrink-0" />
        {t('forms.required_summary')}
      </div>
      <ul className="list-disc space-y-0.5 ps-6">
        {items.map((item) => (
          <li key={item.name}>{item.text}</li>
        ))}
      </ul>
    </div>
  );
}
