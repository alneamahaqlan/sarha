import { useRef, useState } from 'react';
import { ImagePlus, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { apiClient, extractMessage } from '@/lib/api-client';
import { useTranslation } from '@/app/providers/LocaleProvider';

interface Props {
  value: string | null | undefined;
  onChange: (path: string | null) => void;
  directory: 'clinics/logos' | 'clinics/gallery' | 'articles' | 'doctors' | 'before-after' | 'homepage/banners';
  disabled?: boolean;
  label?: string;
}

interface UploadResponse {
  data: { path: string; url: string };
}

export function FileUpload({ value, onChange, directory, disabled, label }: Props) {
  const { t } = useTranslation();
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);

  const previewUrl = value ? `/storage/${value}` : null;

  const onSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const fd = new FormData();
    fd.append('file', file);
    fd.append('directory', directory);

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
    <div className="space-y-2">
      {label && <div className="text-sm font-medium">{label}</div>}
      <input ref={inputRef} type="file" accept="image/*" className="hidden" onChange={onSelect} disabled={disabled || uploading} />
      <div className="flex items-center gap-3">
        {previewUrl ? (
          <div className="relative">
            <img
              src={previewUrl}
              alt=""
              className="h-20 w-20 rounded-md object-cover ring-1 ring-[var(--color-border)]"
              onError={(e) => { (e.currentTarget as HTMLImageElement).style.visibility = 'hidden'; }}
            />
            {!disabled && (
              <button
                type="button"
                onClick={() => onChange(null)}
                className="absolute -end-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow"
                aria-label={t('uploads.remove')}
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </div>
        ) : (
          <div className="flex h-20 w-20 items-center justify-center rounded-md border border-dashed border-[var(--color-border)] bg-[var(--color-muted)]">
            <ImagePlus className="h-6 w-6 text-[var(--color-muted-foreground)]" />
          </div>
        )}
        <Button
          type="button"
          variant="outline"
          disabled={disabled || uploading}
          onClick={() => inputRef.current?.click()}
        >
          {uploading ? t('uploads.uploading') : previewUrl ? t('uploads.change') : t('uploads.upload')}
        </Button>
      </div>
    </div>
  );
}
