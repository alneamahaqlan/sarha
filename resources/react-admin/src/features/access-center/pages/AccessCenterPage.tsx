import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import { ShieldCheck, Inbox, Building2, Package, ArrowLeft, Check, X, Ban, ExternalLink } from 'lucide-react';

import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import {
  Table, TableHeader, TableBody, TableRow, TableHead, TableCell,
} from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import {
  useAccessMeta, useAccessPending, useAccessClinics, useAccessPackages, useGateAction, useLandingRequestAction,
} from '../hooks';
import type {
  GateMeta, GateStatus, PendingItem, ClinicGates, FlagValue, LandingRequest,
} from '../api';

const STATUS_VARIANT: Record<GateStatus, 'warning' | 'success' | 'danger' | 'muted'> = {
  pending: 'warning',
  active: 'success',
  rejected: 'danger',
  disabled: 'muted',
};

function StatusBadge({ status }: { status: GateStatus }) {
  const { t } = useTranslation();
  return <Badge variant={STATUS_VARIANT[status]}>{t(`access_center.status_${status}`)}</Badge>;
}

function gateLabel(meta: GateMeta[], key: string, isAr: boolean, fallback: string): string {
  const g = meta.find((m) => m.key === key);
  if (!g) return fallback;
  return isAr ? g.label_ar : g.label_en;
}

export function AccessCenterPage() {
  const { t } = useTranslation();
  const { data: pending } = useAccessPending();
  const pendingCount = pending?.pending.length ?? 0;
  const queueCount = pending?.queues.reduce((s, q) => s + q.count, 0) ?? 0;
  const landingCount = pending?.landing_requests.length ?? 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <ShieldCheck className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('access_center.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('access_center.subtitle')}</p>
        </div>
      </div>

      <Tabs defaultValue="inbox">
        <TabsList>
          <TabsTrigger value="inbox" className="gap-1.5">
            <Inbox className="h-4 w-4" />
            {t('access_center.tab_inbox')}
            {pendingCount + queueCount + landingCount > 0 && (
              <span className="ms-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[var(--color-primary)] px-1 text-[11px] font-bold text-white">
                {pendingCount + queueCount + landingCount}
              </span>
            )}
          </TabsTrigger>
          <TabsTrigger value="clinics" className="gap-1.5">
            <Building2 className="h-4 w-4" />
            {t('access_center.tab_clinics')}
          </TabsTrigger>
          <TabsTrigger value="packages" className="gap-1.5">
            <Package className="h-4 w-4" />
            {t('access_center.tab_packages')}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="inbox"><InboxTab /></TabsContent>
        <TabsContent value="clinics"><ClinicsTab /></TabsContent>
        <TabsContent value="packages"><PackagesTab /></TabsContent>
      </Tabs>
    </div>
  );
}

function InboxTab() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const navigate = useNavigate();
  const { data: meta } = useAccessMeta();
  const { data, isLoading } = useAccessPending();
  const act = useGateAction();
  const [rejecting, setRejecting] = useState<PendingItem | null>(null);

  const run = async (item: PendingItem, action: 'approve' | 'disable') => {
    try {
      await act.mutateAsync({ gate: item.gate, clinicId: item.clinic_id, action });
      toast.success(t('access_center.done'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-5">
      {/* Pending gate requests (clinic-initiated, actionable inline). */}
      <div className="space-y-2">
        <h2 className="text-sm font-semibold text-[var(--color-muted-foreground)]">{t('access_center.pending_requests')}</h2>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('access_center.col_clinic')}</TableHead>
              <TableHead>{t('access_center.col_service')}</TableHead>
              <TableHead className="text-end">{t('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
            ) : !data || data.pending.length === 0 ? (
              <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('access_center.no_pending')}</TableCell></TableRow>
            ) : (
              data.pending.map((item) => (
                <TableRow key={`${item.gate}-${item.clinic_id}`}>
                  <TableCell className="font-medium">{item.clinic_name}</TableCell>
                  <TableCell>
                    <Badge variant="muted">{gateLabel(meta?.gates ?? [], item.gate, locale === 'ar', item.gate)}</Badge>
                  </TableCell>
                  <TableCell className="text-end">
                    <div className="flex justify-end gap-1">
                      <Button size="sm" disabled={act.isPending} onClick={() => run(item, 'approve')} className="gap-1">
                        <Check className="h-4 w-4" />{t('access_center.approve')}
                      </Button>
                      <Button size="sm" variant="outline" onClick={() => setRejecting(item)} className="gap-1">
                        <X className="h-4 w-4" />{t('access_center.reject')}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      {/* Landing-page approval requests (per-page, actionable inline + preview). */}
      <LandingRequestsSection requests={data?.landing_requests ?? []} loading={isLoading} />

      {/* External request queues (different shapes — deep-link to their pages). */}
      <div className="space-y-2">
        <h2 className="text-sm font-semibold text-[var(--color-muted-foreground)]">{t('access_center.other_queues')}</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          {(data?.queues ?? []).map((q) => (
            <button
              key={q.key}
              type="button"
              onClick={() => navigate(q.route)}
              className="flex items-center justify-between rounded-lg border border-[var(--color-border)] bg-white p-3 text-start transition hover:border-[var(--color-primary)] hover:shadow-sm"
            >
              <div>
                <p className="text-sm font-medium">{t(`access_center.queue_${q.key}`)}</p>
                <p className="text-xs text-[var(--color-muted-foreground)]">{t('access_center.open')}</p>
              </div>
              <div className="flex items-center gap-2">
                {q.count > 0 && (
                  <span className="inline-flex h-6 min-w-[24px] items-center justify-center rounded-full bg-amber-100 px-1.5 text-xs font-bold text-amber-700">
                    {q.count}
                  </span>
                )}
                <ArrowLeft className="h-4 w-4 text-[var(--color-muted-foreground)] rtl:rotate-180" />
              </div>
            </button>
          ))}
        </div>
      </div>

      {rejecting && <RejectDialog item={rejecting} onClose={() => setRejecting(null)} />}
    </div>
  );
}

function RejectDialog({ item, onClose }: { item: PendingItem; onClose: () => void }) {
  const { t } = useTranslation();
  const act = useGateAction();
  const [reason, setReason] = useState('');

  const submit = async () => {
    try {
      await act.mutateAsync({ gate: item.gate, clinicId: item.clinic_id, action: 'reject', reason });
      toast.success(t('access_center.rejected'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('access_center.reject_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="reason">{t('access_center.reject_reason')}</Label>
          <Textarea id="reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={submit} disabled={act.isPending}>
            {act.isPending ? t('common.loading') : t('access_center.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function LandingRequestsSection({ requests, loading }: { requests: LandingRequest[]; loading: boolean }) {
  const { t } = useTranslation();
  const act = useLandingRequestAction();
  const [rejecting, setRejecting] = useState<LandingRequest | null>(null);

  const approve = async (req: LandingRequest) => {
    try {
      await act.mutateAsync({ pageId: req.landing_page_id, action: 'approve' });
      toast.success(t('access_center.done'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-2">
      <h2 className="text-sm font-semibold text-[var(--color-muted-foreground)]">{t('access_center.landing_requests')}</h2>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('access_center.col_clinic')}</TableHead>
            <TableHead>{t('access_center.col_landing_page')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {loading ? (
            <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : requests.length === 0 ? (
            <TableRow><TableCell colSpan={3} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('access_center.no_landing_requests')}</TableCell></TableRow>
          ) : (
            requests.map((req) => (
              <TableRow key={req.landing_page_id}>
                <TableCell className="font-medium">{req.clinic_name ?? `#${req.clinic_id}`}</TableCell>
                <TableCell>
                  <a href={req.preview_url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 hover:underline">
                    {req.title || req.slug}
                    <ExternalLink className="h-3.5 w-3.5 text-[var(--color-muted-foreground)]" />
                  </a>
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button size="sm" disabled={act.isPending} onClick={() => approve(req)} className="gap-1">
                      <Check className="h-4 w-4" />{t('access_center.approve')}
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => setRejecting(req)} className="gap-1">
                      <X className="h-4 w-4" />{t('access_center.reject')}
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {rejecting && <LandingRejectDialog req={rejecting} onClose={() => setRejecting(null)} />}
    </div>
  );
}

function LandingRejectDialog({ req, onClose }: { req: LandingRequest; onClose: () => void }) {
  const { t } = useTranslation();
  const act = useLandingRequestAction();
  const [reason, setReason] = useState('');

  const submit = async () => {
    try {
      await act.mutateAsync({ pageId: req.landing_page_id, action: 'reject', reason });
      toast.success(t('access_center.rejected'));
      onClose();
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('access_center.reject_title')}</DialogTitle>
        </DialogHeader>
        <div className="space-y-1.5">
          <Label htmlFor="lp-reason">{t('access_center.reject_reason')}</Label>
          <Textarea id="lp-reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button variant="destructive" onClick={submit} disabled={act.isPending}>
            {act.isPending ? t('common.loading') : t('access_center.reject')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ClinicsTab() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [search, setSearch] = useState('');
  const debounced = useDebouncedValue(search, 300);
  const { data: meta } = useAccessMeta();
  const { data, isLoading } = useAccessClinics(debounced);
  const act = useGateAction();
  const gates = meta?.gates ?? [];

  const toggle = async (clinic: ClinicGates, gateKey: string, status: GateStatus) => {
    const action = status === 'active' ? 'disable' : 'approve';
    try {
      await act.mutateAsync({ gate: gateKey, clinicId: clinic.id, action });
      toast.success(t('access_center.done'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-3">
      <Input
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder={t('access_center.search_clinic')}
        className="w-72"
      />
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('access_center.col_clinic')}</TableHead>
              {gates.map((g) => (
                <TableHead key={g.key}>{locale === 'ar' ? g.label_ar : g.label_en}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={gates.length + 1} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
            ) : !data || data.length === 0 ? (
              <TableRow><TableCell colSpan={gates.length + 1} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
            ) : (
              data.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-medium">{c.name}</TableCell>
                  {gates.map((g) => {
                    const status = c.gates[g.key] ?? (g.default as GateStatus);
                    return (
                      <TableCell key={g.key}>
                        <div className="flex items-center gap-2">
                          <StatusBadge status={status} />
                          <Button
                            size="sm"
                            variant={status === 'active' ? 'destructive' : 'outline'}
                            disabled={act.isPending}
                            onClick={() => toggle(c, g.key, status)}
                            className="gap-1"
                          >
                            {status === 'active' ? <Ban className="h-3.5 w-3.5" /> : <Check className="h-3.5 w-3.5" />}
                            {status === 'active' ? t('access_center.disable') : t('access_center.enable')}
                          </Button>
                        </div>
                      </TableCell>
                    );
                  })}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

function flagCell(v: FlagValue) {
  if (typeof v === 'boolean') {
    return v
      ? <Check className="mx-auto h-4 w-4 text-emerald-600" />
      : <X className="mx-auto h-4 w-4 text-rose-400" />;
  }
  if (v === null || v === '') return <span className="text-[var(--color-muted-foreground)]">—</span>;
  return <span>{String(v)}</span>;
}

function PackagesTab() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const navigate = useNavigate();
  const { data, isLoading } = useAccessPackages();
  const flags = data?.flags ?? [];

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('access_center.packages_hint')}</p>
        <Button variant="outline" size="sm" onClick={() => navigate('/admin/subscription-packages')}>
          {t('access_center.manage_packages')}
        </Button>
      </div>
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('access_center.col_feature')}</TableHead>
              {(data?.packages ?? []).map((p) => (
                <TableHead key={p.id} className="text-center">{locale === 'ar' ? p.name_ar : p.name_en}</TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading ? (
              <TableRow><TableCell colSpan={(data?.packages.length ?? 0) + 1} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
            ) : (
              flags.map((f) => (
                <TableRow key={f}>
                  <TableCell className="font-medium">{t(`access_center.flag_${f}`, { defaultValue: f })}</TableCell>
                  {(data?.packages ?? []).map((p) => (
                    <TableCell key={p.id} className="text-center">{flagCell(p.flags[f])}</TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
