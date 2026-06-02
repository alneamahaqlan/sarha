import { useState } from 'react';
import { Eye, EyeOff, KeyRound, Copy, Check } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { clinicsApi } from '../api/clinics.api';

/**
 * Super-admin only: reveal the clinic's stored login password, or regenerate a
 * fresh one. Server enforces the super-admin policy + audit-logs every access.
 */
export function ClinicPasswordReveal({ clinicId, available }: { clinicId: number; available?: boolean }) {
  const { t } = useTranslation();
  const [password, setPassword] = useState<string | null>(null);
  const [shown, setShown] = useState(false);
  const [loading, setLoading] = useState<'reveal' | 'regenerate' | null>(null);
  const [copied, setCopied] = useState(false);

  const reveal = async () => {
    setLoading('reveal');
    try {
      const pw = await clinicsApi.revealPassword(clinicId);
      if (pw) { setPassword(pw); setShown(true); }
      else toast.error(t('clinics.password.not_available'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    } finally {
      setLoading(null);
    }
  };

  const regenerate = async () => {
    setLoading('regenerate');
    try {
      const pw = await clinicsApi.regeneratePassword(clinicId);
      setPassword(pw);
      setShown(true);
      toast.success(t('clinics.password.regenerated'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    } finally {
      setLoading(null);
    }
  };

  const copy = async () => {
    if (!password) return;
    try {
      await navigator.clipboard.writeText(password);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch { /* clipboard blocked — ignore */ }
  };

  return (
    <div className="space-y-2 rounded-md border border-[var(--color-border)] bg-[var(--color-muted)] p-3 md:col-span-2">
      <Label className="flex items-center gap-1.5 text-sm">
        <KeyRound className="h-3.5 w-3.5" />
        {t('clinics.password.title')}
      </Label>

      {password ? (
        <div className="flex items-center gap-2">
          <code dir="ltr" className="flex-1 rounded bg-white px-3 py-1.5 font-mono text-sm">
            {shown ? password : '••••••••••'}
          </code>
          <Button type="button" variant="ghost" size="icon" onClick={() => setShown((s) => !s)} aria-label={t(shown ? 'clinics.password.hide' : 'clinics.password.show')}>
            {shown ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </Button>
          <Button type="button" variant="ghost" size="icon" onClick={copy} aria-label={t('common.copy')}>
            {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
          </Button>
        </div>
      ) : (
        <p className="text-xs text-[var(--color-muted-foreground)]">
          {available ? t('clinics.password.hint') : t('clinics.password.none_stored')}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        {available && (
          <Button type="button" variant="outline" size="sm" onClick={reveal} disabled={loading !== null}>
            <Eye className="h-3.5 w-3.5" />
            {loading === 'reveal' ? t('common.loading') : t('clinics.password.reveal')}
          </Button>
        )}
        <Button type="button" variant="outline" size="sm" onClick={regenerate} disabled={loading !== null}>
          <KeyRound className="h-3.5 w-3.5" />
          {loading === 'regenerate' ? t('common.loading') : t('clinics.password.regenerate')}
        </Button>
      </div>
    </div>
  );
}
