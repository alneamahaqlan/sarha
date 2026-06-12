import { useRef, useState } from 'react';
import { Check, Upload, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { apiClient, extractMessage } from '@/lib/api-client';
import { assetUrl } from '@/lib/assets';
import { useTranslation } from '@/app/providers/LocaleProvider';

import {
  BUILT_IN_ICON_KEYS, CategoryIcon, isUploadedIcon,
} from '@/lib/category-icons';

interface Props {
  /** Current `icon` value: a built-in key, an uploaded path, or null/empty. */
  value: string | null | undefined;
  /** The category's emoji — drives the "auto" fallback preview. */
  emoji: string | null | undefined;
  onChange: (icon: string | null) => void;
  disabled?: boolean;
}

interface UploadResponse {
  data: { path: string; url: string };
}

/**
 * Icon chooser for a specialization: a grid of built-in vector icons, an
 * "auto from emoji" option, and a custom-image upload. Stores either a
 * built-in key or the uploaded path back into the form's `icon` field.
 */
export function IconPicker({ value, emoji, onChange, disabled }: Props) {
  const { t } = useTranslation();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  const uploaded = isUploadedIcon(value);

  const onSelectFile = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('file', file);
    fd.append('directory', 'category-icons');

    setUploading(true);
    try {
      const res = await apiClient.post<UploadResponse>('/uploads', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      onChange(res.data.data.path);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  return (
    <div className="space-y-3">
      {/* Built-in icon grid + "auto" tile */}
      <div>
        <div className="mb-1.5 text-xs text-[var(--color-muted-foreground)]">{t('categories.icon_built_in')}</div>
        <div className="grid grid-cols-8 gap-1.5 sm:grid-cols-10">
          {/* Auto-from-emoji tile (clears the explicit icon) */}
          <button
            type="button"
            disabled={disabled}
            title={t('categories.icon_auto')}
            onClick={() => onChange(null)}
            className={`relative flex aspect-square items-center justify-center rounded-md border transition-colors ${
              !value
                ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                : 'border-[var(--color-border)] text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]'
            }`}
          >
            <CategoryIcon icon={null} emoji={emoji} className="h-5 w-5 opacity-70" />
            {!value && <Check className="absolute -end-1 -top-1 h-3.5 w-3.5 rounded-full bg-[var(--color-primary)] p-0.5 text-white" />}
          </button>

          {BUILT_IN_ICON_KEYS.map((key) => {
            const selected = value === key;
            return (
              <button
                key={key}
                type="button"
                disabled={disabled}
                title={key}
                onClick={() => onChange(key)}
                className={`relative flex aspect-square items-center justify-center rounded-md border transition-colors ${
                  selected
                    ? 'border-[var(--color-primary)] bg-[var(--color-primary)]/10 text-[var(--color-primary)]'
                    : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
                }`}
              >
                <CategoryIcon icon={key} className="h-5 w-5" />
                {selected && <Check className="absolute -end-1 -top-1 h-3.5 w-3.5 rounded-full bg-[var(--color-primary)] p-0.5 text-white" />}
              </button>
            );
          })}
        </div>
      </div>

      {/* Custom upload */}
      <div className="flex items-center gap-3">
        <input ref={inputRef} type="file" accept="image/svg+xml,image/png,image/webp" className="hidden" onChange={onSelectFile} disabled={disabled || uploading} />
        {uploaded ? (
          <div className="relative">
            <div className="flex h-12 w-12 items-center justify-center rounded-md border border-[var(--color-primary)] bg-[var(--color-primary)]/5 p-1">
              <img src={assetUrl(value) ?? ''} alt={t('categories.icon_custom_label')} className="max-h-full max-w-full object-contain" />
            </div>
            {!disabled && (
              <button
                type="button"
                onClick={() => onChange(null)}
                className="absolute -end-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow"
                aria-label={t('categories.icon_remove')}
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </div>
        ) : null}
        <Button type="button" variant="outline" size="sm" disabled={disabled || uploading} onClick={() => inputRef.current?.click()}>
          <Upload className="me-1.5 h-4 w-4" />
          {uploading ? t('categories.icon_uploading') : t('categories.icon_upload')}
        </Button>
      </div>

      <p className="text-xs text-[var(--color-muted-foreground)]">{t('categories.icon_size_hint')}</p>
    </div>
  );
}
