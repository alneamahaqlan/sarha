import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Radar, Plus, Trash2, ShieldAlert } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useClinicTracking, useUpdateClinicTracking, useRequestClinicTracking } from '../hooks';
import type { Pixel, ProviderMeta } from '../api';

export function ClinicTrackingPage() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicTracking();
  const update = useUpdateClinicTracking();
  const request = useRequestClinicTracking();

  const [pixels, setPixels] = useState<Pixel[]>([]);
  const [advanced, setAdvanced] = useState(false);

  useEffect(() => {
    if (data) {
      setPixels(data.tracking_pixels ?? []);
      setAdvanced(data.advanced_matching_optin);
    }
  }, [data]);

  const providers = data?.providers ?? [];
  const providerMap = useMemo(
    () => Object.fromEntries(providers.map((p) => [p.key, p])) as Record<string, ProviderMeta>,
    [providers],
  );

  const isValid = (p: Pixel) => {
    const meta = providerMap[p.provider];
    if (!meta) return false;
    try { return new RegExp(meta.pattern).test(p.id.trim()); } catch { return false; }
  };
  const allValid = pixels.every(isValid);

  if (isLoading) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  const addRow = () => setPixels((p) => [...p, { provider: providers[0]?.key ?? 'meta', id: '', enabled: true }]);
  const removeRow = (i: number) => setPixels((p) => p.filter((_, idx) => idx !== i));
  const patchRow = (i: number, patch: Partial<Pixel>) =>
    setPixels((p) => p.map((row, idx) => (idx === i ? { ...row, ...patch } : row)));

  const save = async () => {
    try {
      await update.mutateAsync({ tracking_pixels: pixels, advanced_matching_optin: advanced });
      toast.success(t('common.saved'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const requestActivation = async () => {
    try {
      // Persist the latest draft first so the request reflects what's shown.
      await update.mutateAsync({ tracking_pixels: pixels, advanced_matching_optin: advanced });
      await request.mutateAsync();
      toast.success(t('clinic_tracking.requested'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const status = data?.tracking_status ?? 'disabled';
  const canRequest = (status === 'disabled' || status === 'rejected') && pixels.length > 0 && allValid;

  return (
    <div className="max-w-3xl space-y-6">
      <div className="flex items-center gap-2">
        <Radar className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_tracking.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_tracking.subtitle')}</p>
        </div>
      </div>

      {/* Feature-off notice */}
      {data && !data.feature_enabled && (
        <Notice tone="warning">{t('clinic_tracking.feature_off')}</Notice>
      )}

      {/* Status banner */}
      {status === 'pending' && <Notice tone="warning">{t('clinic_tracking.status_pending')}</Notice>}
      {status === 'active' && <Notice tone="success">{t('clinic_tracking.status_active')}</Notice>}
      {status === 'rejected' && (
        <Notice tone="danger">
          {t('clinic_tracking.status_rejected')}
          {data?.tracking_rejection_reason ? ` — ${data.tracking_rejection_reason}` : ''}
        </Notice>
      )}

      {/* Pixel editor */}
      <div className="space-y-3 rounded-xl border border-[var(--color-border)] bg-white p-5">
        <div className="flex items-center justify-between">
          <h2 className="text-base font-semibold">{t('clinic_tracking.pixels_title')}</h2>
          <Button size="sm" variant="outline" onClick={addRow} disabled={pixels.length >= 8}>
            <Plus className="h-4 w-4" /> {t('clinic_tracking.add_pixel')}
          </Button>
        </div>

        {pixels.length === 0 ? (
          <p className="py-4 text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_tracking.empty')}</p>
        ) : (
          <div className="space-y-3">
            {pixels.map((p, i) => {
              const meta = providerMap[p.provider];
              const invalid = p.id.trim() !== '' && !isValid(p);
              return (
                <div key={i} className="flex flex-col gap-2 sm:flex-row sm:items-start">
                  <Select
                    className="sm:w-44"
                    value={p.provider}
                    onChange={(e) => patchRow(i, { provider: e.target.value })}
                  >
                    {providers.map((pr) => <option key={pr.key} value={pr.key}>{pr.label}</option>)}
                  </Select>
                  <div className="flex-1">
                    <Input
                      dir="ltr"
                      value={p.id}
                      placeholder={meta?.example}
                      onChange={(e) => patchRow(i, { id: e.target.value })}
                      className={invalid ? 'border-[var(--color-destructive)]' : ''}
                    />
                    {invalid && <p className="mt-1 text-xs text-[var(--color-destructive)]">{t('clinic_tracking.invalid_id')}</p>}
                  </div>
                  <div className="flex items-center gap-2">
                    <Switch checked={p.enabled !== false} onCheckedChange={(v) => patchRow(i, { enabled: v })} />
                    <Button size="icon" variant="ghost" onClick={() => removeRow(i)} aria-label={t('common.delete')}>
                      <Trash2 className="h-4 w-4 text-[var(--color-destructive)]" />
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Advanced matching */}
      <div className="space-y-2 rounded-xl border border-[var(--color-border)] bg-white p-5">
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-start gap-2">
            <ShieldAlert className="mt-0.5 h-4 w-4 text-amber-500" />
            <div>
              <Label>{t('clinic_tracking.advanced_matching')}</Label>
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_tracking.advanced_matching_hint')}</p>
            </div>
          </div>
          <Switch checked={advanced} onCheckedChange={setAdvanced} />
        </div>
      </div>

      {/* Actions */}
      <div className="flex flex-wrap items-center justify-end gap-2">
        <Button variant="outline" onClick={save} disabled={update.isPending || !allValid}>
          {update.isPending ? t('common.loading') : t('clinic_tracking.save_draft')}
        </Button>
        {canRequest && (
          <Button onClick={requestActivation} disabled={request.isPending || update.isPending}>
            {request.isPending ? t('common.loading') : t('clinic_tracking.request_activation')}
          </Button>
        )}
      </div>
    </div>
  );
}

function Notice({ tone, children }: { tone: 'warning' | 'success' | 'danger'; children: React.ReactNode }) {
  const cls = {
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    danger: 'border-red-200 bg-red-50 text-red-800',
  }[tone];
  return <div className={`rounded-lg border px-4 py-3 text-sm ${cls}`}>{children}</div>;
}
