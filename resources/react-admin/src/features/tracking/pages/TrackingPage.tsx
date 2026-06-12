import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Radar, Plus, Trash2 } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
  useTrackingSettings, useUpdateTrackingSettings, useTrackingRequests,
  useApproveTracking, useRejectTracking, useDisableTracking,
} from '../hooks';
import type { Pixel, ProviderMeta, TrackingRequestClinic, TrackingStatus } from '../api';

const STATUS_VARIANT: Record<TrackingStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  rejected: 'danger',
  disabled: 'muted',
};

export function TrackingPage() {
  const { t } = useTranslation();

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Radar className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('tracking.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('tracking.subtitle')}</p>
        </div>
      </div>

      <SettingsCard />
      <RequestsSection />
    </div>
  );
}

function SettingsCard() {
  const { t } = useTranslation();
  const { data, isLoading } = useTrackingSettings();
  const update = useUpdateTrackingSettings();

  const [feature, setFeature] = useState(false);
  const [consent, setConsent] = useState(true);
  const [textAr, setTextAr] = useState('');
  const [textEn, setTextEn] = useState('');
  const [privacyUrl, setPrivacyUrl] = useState('');
  const [pixels, setPixels] = useState<Pixel[]>([]);

  useEffect(() => {
    if (data) {
      setFeature(data.feature_enabled);
      setConsent(data.consent_enabled);
      setTextAr(data.consent_text_ar ?? '');
      setTextEn(data.consent_text_en ?? '');
      setPrivacyUrl(data.privacy_url ?? '');
      setPixels(data.platform_pixels ?? []);
    }
  }, [data]);

  const providers = data?.providers ?? [];
  const providerMap = useMemo(
    () => Object.fromEntries(providers.map((p) => [p.key, p])) as Record<string, ProviderMeta>,
    [providers],
  );
  const pixelValid = (p: Pixel) => {
    const meta = providerMap[p.provider];
    if (!meta) return false;
    try { return new RegExp(meta.pattern).test(p.id.trim()); } catch { return false; }
  };
  const allPixelsValid = pixels.every(pixelValid);

  const addPixel = () => setPixels((p) => [...p, { provider: providers[0]?.key ?? 'meta', id: '', enabled: true }]);
  const removePixel = (i: number) => setPixels((p) => p.filter((_, idx) => idx !== i));
  const patchPixel = (i: number, patch: Partial<Pixel>) =>
    setPixels((p) => p.map((row, idx) => (idx === i ? { ...row, ...patch } : row)));

  const save = async () => {
    try {
      await update.mutateAsync({
        feature_enabled: feature,
        consent_enabled: consent,
        consent_text_ar: textAr,
        consent_text_en: textEn,
        privacy_url: privacyUrl,
        platform_pixels: pixels,
      });
      toast.success(t('common.saved'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  if (isLoading) {
    return <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  return (
    <div className="space-y-4 rounded-xl border border-[var(--color-border)] bg-white p-5">
      <h2 className="text-base font-semibold">{t('tracking.settings_title')}</h2>

      <div className="flex items-center justify-between gap-4">
        <div>
          <Label>{t('tracking.feature_enabled')}</Label>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('tracking.feature_enabled_hint')}</p>
        </div>
        <Switch checked={feature} onCheckedChange={setFeature} />
      </div>

      <div className="flex items-center justify-between gap-4">
        <div>
          <Label>{t('tracking.consent_enabled')}</Label>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('tracking.consent_enabled_hint')}</p>
        </div>
        <Switch checked={consent} onCheckedChange={setConsent} />
      </div>

      <div className="space-y-1.5">
        <Label>{t('tracking.consent_text_ar')}</Label>
        <Textarea value={textAr} onChange={(e) => setTextAr(e.target.value)} rows={2}
          placeholder={t('tracking.consent_text_placeholder')} />
      </div>
      <div className="space-y-1.5">
        <Label>{t('tracking.consent_text_en')}</Label>
        <Textarea dir="ltr" value={textEn} onChange={(e) => setTextEn(e.target.value)} rows={2} />
      </div>
      <div className="space-y-1.5">
        <Label>{t('tracking.privacy_url')}</Label>
        <Input dir="ltr" value={privacyUrl} onChange={(e) => setPrivacyUrl(e.target.value)}
          placeholder="https://..." />
      </div>

      {/* Platform-wide pixels — load on every public page. */}
      <div className="space-y-2 border-t border-[var(--color-border)] pt-4">
        <div className="flex items-center justify-between">
          <div>
            <Label>{t('tracking.platform_pixels')}</Label>
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('tracking.platform_pixels_hint')}</p>
          </div>
          <Button size="sm" variant="outline" onClick={addPixel} disabled={pixels.length >= 8}>
            <Plus className="h-4 w-4" /> {t('clinic_tracking.add_pixel')}
          </Button>
        </div>
        {pixels.map((p, i) => {
          const meta = providerMap[p.provider];
          const invalid = p.id.trim() !== '' && !pixelValid(p);
          return (
            <div key={i} className="flex flex-col gap-2 sm:flex-row sm:items-start">
              <Select className="sm:w-44" value={p.provider} onChange={(e) => patchPixel(i, { provider: e.target.value })}>
                {providers.map((pr) => <option key={pr.key} value={pr.key}>{pr.label}</option>)}
              </Select>
              <div className="flex-1">
                <Input dir="ltr" value={p.id} placeholder={meta?.example}
                  onChange={(e) => patchPixel(i, { id: e.target.value })}
                  className={invalid ? 'border-[var(--color-destructive)]' : ''} />
                {invalid && <p className="mt-1 text-xs text-[var(--color-destructive)]">{t('clinic_tracking.invalid_id')}</p>}
              </div>
              <div className="flex items-center gap-2">
                <Switch checked={p.enabled !== false} onCheckedChange={(v) => patchPixel(i, { enabled: v })} />
                <Button size="icon" variant="ghost" onClick={() => removePixel(i)} aria-label={t('common.delete')}>
                  <Trash2 className="h-4 w-4 text-[var(--color-destructive)]" />
                </Button>
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex justify-end">
        <Button onClick={save} disabled={update.isPending || !allPixelsValid}>
          {update.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </div>
  );
}

function RequestsSection() {
  const { t } = useTranslation();
  const [status, setStatus] = useState('pending');
  const { data, isLoading } = useTrackingRequests(status);
  const approve = useApproveTracking();
  const disable = useDisableTracking();
  const [rejecting, setRejecting] = useState<TrackingRequestClinic | null>(null);

  const act = async (fn: () => Promise<unknown>, ok: string) => {
    try {
      await fn();
      toast.success(t(ok));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-3">
      <h2 className="text-base font-semibold">{t('tracking.requests_title')}</h2>

      <Tabs value={status} onValueChange={setStatus}>
        <TabsList>
          <TabsTrigger value="pending">{t('tracking.tab_pending')}</TabsTrigger>
          <TabsTrigger value="active">{t('tracking.tab_active')}</TabsTrigger>
          <TabsTrigger value="all">{t('tracking.tab_all')}</TabsTrigger>
        </TabsList>

        <TabsContent value={status}>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('tracking.col_clinic')}</TableHead>
                <TableHead>{t('tracking.col_pixels')}</TableHead>
                <TableHead>{t('tracking.col_status')}</TableHead>
                <TableHead className="text-end">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.loading')}
                  </TableCell>
                </TableRow>
              ) : !data || data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="py-8 text-center text-[var(--color-muted-foreground)]">
                    {t('common.no_data')}
                  </TableCell>
                </TableRow>
              ) : (
                data.map((c) => (
                  <TableRow key={c.id}>
                    <TableCell className="font-medium">{c.name}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {(c.tracking_pixels ?? []).map((p, i) => (
                          <Badge key={i} variant="outline" dir="ltr" className="font-mono">
                            {p.provider}: {p.id}
                          </Badge>
                        ))}
                        {c.advanced_matching_optin && (
                          <Badge variant="info">{t('tracking.advanced_matching')}</Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={STATUS_VARIANT[c.tracking_status]}>
                        {t(`tracking.status_${c.tracking_status}`)}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-end">
                      <div className="flex justify-end gap-1">
                        {c.tracking_status !== 'active' && (
                          <Button size="sm" disabled={approve.isPending}
                            onClick={() => act(() => approve.mutateAsync(c.id), 'tracking.approved')}>
                            {t('tracking.approve')}
                          </Button>
                        )}
                        {c.tracking_status === 'pending' && (
                          <Button size="sm" variant="outline" onClick={() => setRejecting(c)}>
                            {t('tracking.reject')}
                          </Button>
                        )}
                        {c.tracking_status === 'active' && (
                          <Button size="sm" variant="destructive" disabled={disable.isPending}
                            onClick={() => act(() => disable.mutateAsync(c.id), 'tracking.disabled')}>
                            {t('tracking.disable')}
                          </Button>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TabsContent>
      </Tabs>

      {rejecting && <RejectDialog clinic={rejecting} onClose={() => setRejecting(null)} />}
    </div>
  );
}

function RejectDialog({ clinic, onClose }: { clinic: TrackingRequestClinic; onClose: () => void }) {
  const { t } = useTranslation();
  const reject = useRejectTracking();
  const [reason, setReason] = useState('');

  const submit = async () => {
    try {
      await reject.mutateAsync({ id: clinic.id, reason });
      toast.success(t('tracking.rejected'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tracking.reject_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="reason">{t('tracking.reject_reason')}</Label>
          <Textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={submit} disabled={reject.isPending}>
            {reject.isPending ? t('common.loading') : t('tracking.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
