import { useState } from 'react';
import { toast } from 'sonner';
import { Clock, CheckCircle2, Download, Eye, Users } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useCampaignRequest, useCampaignRequests, useCloseCampaignRequest } from '../hooks';
import { campaignRequestsApi } from '../api';

const STATUS_VARIANT: Record<string, 'warning' | 'success'> = {
  submitted: 'warning',
  closed: 'success',
};

export function CampaignRequestsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [status, setStatus] = useState<string>('submitted');
  const [openId, setOpenId] = useState<number | null>(null);

  const { data, isLoading } = useCampaignRequests({ filter: { status } });
  const { data: detail } = useCampaignRequest(openId);
  const close = useCloseCampaignRequest();

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { dateStyle: 'medium' }) : '—';

  async function onClose(id: number) {
    try {
      const res = await close.mutateAsync(id);
      toast.success(res.message);
      setOpenId(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onExport(id: number, name: string) {
    try {
      await campaignRequestsApi.exportCsv(id, `campaign-${id}-${name}.csv`);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  const audienceChips = (audience: Record<string, unknown> | null) =>
    Object.entries(audience ?? {})
      .filter(([, v]) => v !== null && v !== undefined && v !== '' && v !== false)
      .map(([k, v]) => `${k}: ${String(v)}`);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('campaign_requests.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('campaign_requests.subtitle')}</p>
      </div>

      <div className="flex gap-1">
        {['submitted', 'closed', 'all'].map((s) => (
          <button
            key={s}
            type="button"
            onClick={() => setStatus(s)}
            className={`rounded-md px-3 py-1.5 text-sm ${status === s ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-muted)]'}`}
          >
            {s === 'all' ? t('common.all') : t(`campaign_requests.status.${s}`)}
          </button>
        ))}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('campaign_requests.name')}</TableHead>
            <TableHead>{t('campaign_requests.clinic')}</TableHead>
            <TableHead>{t('campaign_requests.recipients')}</TableHead>
            <TableHead>{t('campaign_requests.status_label')}</TableHead>
            <TableHead>{t('campaign_requests.date')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((r) => (
              <TableRow key={r.id}>
                <TableCell className="font-medium">{r.name}</TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">{r.clinic?.name ?? '—'}</TableCell>
                <TableCell>
                  <span className="inline-flex items-center gap-1 text-sm">
                    <Users className="h-3.5 w-3.5 text-[var(--color-muted-foreground)]" />
                    {r.total_recipients}
                  </span>
                </TableCell>
                <TableCell>
                  <Badge variant={STATUS_VARIANT[r.managed_status ?? 'submitted']} className="inline-flex items-center gap-1.5">
                    {r.managed_status === 'closed'
                      ? <CheckCircle2 className="h-3.5 w-3.5 text-emerald-600" />
                      : <Clock className="h-3.5 w-3.5 text-amber-500" />}
                    {t(`campaign_requests.status.${r.managed_status ?? 'submitted'}`)}
                  </Badge>
                </TableCell>
                <TableCell className="text-sm text-[var(--color-muted-foreground)]">{fmt(r.created_at)}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" aria-label={t('campaign_requests.view')} onClick={() => setOpenId(r.id)}>
                      <Eye className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" aria-label={t('campaign_requests.export')} onClick={() => onExport(r.id, r.name)}>
                      <Download className="h-4 w-4" />
                    </Button>
                    {r.managed_status === 'submitted' && (
                      <Button variant="outline" size="sm" onClick={() => onClose(r.id)} disabled={close.isPending}>
                        {t('campaign_requests.close')}
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {/* Detail dialog */}
      <Dialog open={openId !== null} onOpenChange={(o) => !o && setOpenId(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{detail?.name ?? t('campaign_requests.title')}</DialogTitle>
            <DialogDescription>{detail?.clinic?.name ?? ''}</DialogDescription>
          </DialogHeader>
          {detail && (
            <div className="space-y-3">
              <div className="flex flex-wrap items-center gap-2 text-sm">
                <Badge variant={STATUS_VARIANT[detail.managed_status ?? 'submitted']}>
                  {t(`campaign_requests.status.${detail.managed_status ?? 'submitted'}`)}
                </Badge>
                <span className="inline-flex items-center gap-1 text-[var(--color-muted-foreground)]">
                  <Users className="h-3.5 w-3.5" />
                  {detail.total_recipients}
                </span>
              </div>
              {detail.image_url && (
                <img src={detail.image_url} alt="" className="max-h-56 w-auto rounded-lg border border-[var(--color-border)] object-contain" />
              )}
              <div className="rounded-md bg-[var(--color-muted)]/40 p-2 text-sm whitespace-pre-wrap">
                {detail.message_template}
              </div>
              {audienceChips(detail.audience).length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {audienceChips(detail.audience).map((c) => (
                    <span key={c} className="rounded-full bg-[var(--color-muted)] px-2 py-0.5 text-xs text-[var(--color-muted-foreground)]">{c}</span>
                  ))}
                </div>
              )}
              {detail.closed_by && (
                <p className="text-xs text-[var(--color-muted-foreground)]">
                  {t('campaign_requests.closed_by', { name: detail.closed_by })} · {fmt(detail.closed_at)}
                </p>
              )}
              <div className="flex justify-end gap-2 pt-2">
                <Button variant="outline" onClick={() => onExport(detail.id, detail.name)}>
                  <Download className="h-4 w-4" />
                  {t('campaign_requests.export')}
                </Button>
                {detail.managed_status === 'submitted' && (
                  <Button onClick={() => onClose(detail.id)} disabled={close.isPending}>
                    {t('campaign_requests.close')}
                  </Button>
                )}
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
