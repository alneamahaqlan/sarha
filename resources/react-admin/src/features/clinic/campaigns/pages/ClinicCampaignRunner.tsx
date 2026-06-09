import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ArrowRight, MessageCircle, SkipForward, RotateCcw } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useCampaign, useCampaignRecipients, useMarkRecipient } from '../hooks';
import type { CampaignRecipient, RecipientStatus } from '../types';

const FILTERS: (RecipientStatus | 'all')[] = ['all', 'pending', 'sent', 'skipped'];

const STATUS_VARIANT: Record<RecipientStatus, 'muted' | 'success' | 'warning'> = {
  pending: 'muted',
  sent: 'success',
  skipped: 'warning',
};

/** Build a personalised wa.me link: {{name}} rendered, KSA phone normalised. */
function waLink(phone: string, message: string, name: string | null): string {
  let p = phone.replace(/[^\d]/g, '');
  if (p.startsWith('05')) p = `966${p.slice(1)}`;
  else if (p.startsWith('5') && p.length === 9) p = `966${p}`;
  const text = message.replace(/\{\{\s*name\s*\}\}/g, name ?? '');
  return `https://wa.me/${p}?text=${encodeURIComponent(text)}`;
}

export function ClinicCampaignRunner() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const params = useParams<{ id: string }>();
  const id = params.id ? Number(params.id) : null;
  const canManage = useCan('campaigns.manage');

  const [filter, setFilter] = useState<RecipientStatus | 'all'>('all');
  const { data: campaign, isLoading } = useCampaign(id);
  const { data: recipients } = useCampaignRecipients(id, filter === 'all' ? undefined : filter);
  const mark = useMarkRecipient(id ?? 0);

  const ArrowBack = locale === 'ar' ? ArrowRight : ArrowLeft;

  if (isLoading) return <div className="p-6 text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  if (!campaign) return <div className="p-6 text-sm text-rose-700">{t('clinic_campaigns.not_found')}</div>;

  // Managed campaigns are run by the platform externally — show a read-only
  // summary instead of the manual per-recipient sender.
  if (campaign.type === 'managed') {
    const closed = campaign.managed_status === 'closed';
    return (
      <div className="space-y-4">
        <Link to="/clinic/campaigns" className="inline-flex items-center gap-1.5 text-sm text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]">
          <ArrowBack className="h-4 w-4" />
          {t('clinic_campaigns.back')}
        </Link>
        <div className="rounded-lg border border-[var(--color-border)] bg-white p-4 space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-semibold">{campaign.name}</h1>
            <Badge variant="default">{t('clinic_campaigns.type.managed')}</Badge>
            <Badge variant={closed ? 'success' : 'warning'}>
              {t(`clinic_campaigns.managed_status.${campaign.managed_status ?? 'submitted'}`)}
            </Badge>
          </div>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_campaigns.managed.runner_hint')}</p>
          {campaign.image_url && (
            <img src={campaign.image_url} alt="" className="max-h-64 w-auto rounded-lg border border-[var(--color-border)] object-contain" />
          )}
          <div className="rounded-md bg-[var(--color-muted)]/40 p-2 text-sm whitespace-pre-wrap">
            {campaign.message_template}
          </div>
          <div className="text-sm text-[var(--color-muted-foreground)]">
            {t('clinic_campaigns.recipient_count', { count: campaign.total_recipients })}
          </div>
        </div>
      </div>
    );
  }

  function send(r: CampaignRecipient) {
    if (r.phone) {
      window.open(waLink(r.phone, campaign!.message_template, r.name), '_blank', 'noopener');
    }
    if (canManage && r.status !== 'sent') {
      mark.mutate({ recipientId: r.id, status: 'sent' });
    }
  }

  return (
    <div className="space-y-4">
      <Link to="/clinic/campaigns" className="inline-flex items-center gap-1.5 text-sm text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]">
        <ArrowBack className="h-4 w-4" />
        {t('clinic_campaigns.back')}
      </Link>

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
        <div className="flex items-center gap-2">
          <h1 className="text-xl font-semibold">{campaign.name}</h1>
          <Badge variant={campaign.status === 'completed' ? 'success' : 'default'}>
            {t(`clinic_campaigns.status.${campaign.status}`)}
          </Badge>
        </div>
        <div className="mt-1 text-sm text-[var(--color-muted-foreground)]">
          {t('clinic_campaigns.sent_of', { sent: campaign.sent_count, total: campaign.total_recipients })}
        </div>
        <div className="mt-2 h-2 w-full max-w-md overflow-hidden rounded-full bg-[var(--color-muted)]">
          <div className="h-full rounded-full bg-emerald-500" style={{ width: `${campaign.progress}%` }} />
        </div>
        <div className="mt-3 rounded-md bg-[var(--color-muted)]/40 p-2 text-sm whitespace-pre-wrap">
          {campaign.message_template}
        </div>
        <p className="mt-1 text-[11px] text-[var(--color-muted-foreground)]">{t('clinic_campaigns.runner_hint')}</p>
      </div>

      <div className="flex flex-wrap gap-1.5">
        {FILTERS.map((f) => (
          <button
            key={f}
            type="button"
            onClick={() => setFilter(f)}
            className={`rounded-full px-3 py-1 text-sm ${
              filter === f ? 'bg-[var(--color-primary)] text-white' : 'border border-[var(--color-border)] hover:bg-[var(--color-muted)]'
            }`}
          >
            {t(`clinic_campaigns.recipient_filter.${f}`)}
          </button>
        ))}
      </div>

      <div className="space-y-2">
        {!recipients || recipients.length === 0 ? (
          <div className="rounded-lg border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-muted-foreground)]">
            {t('clinic_campaigns.no_recipients')}
          </div>
        ) : (
          recipients.map((r) => (
            <div key={r.id} className="flex items-center justify-between gap-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className="font-medium">{r.name ?? '—'}</span>
                  <Badge variant={STATUS_VARIANT[r.status]}>{t(`clinic_campaigns.recipient_status.${r.status}`)}</Badge>
                </div>
                <div dir="ltr" className="font-mono text-xs text-[var(--color-muted-foreground)]">{r.phone}</div>
              </div>

              <div className="flex shrink-0 items-center gap-1.5">
                <Button
                  size="sm"
                  className="gap-1 bg-emerald-600 hover:bg-emerald-700"
                  onClick={() => send(r)}
                >
                  <MessageCircle className="h-4 w-4" />
                  {t('clinic_campaigns.action.send')}
                </Button>
                {canManage && r.status === 'pending' && (
                  <Button size="sm" variant="ghost" className="gap-1 text-xs" onClick={() => mark.mutate({ recipientId: r.id, status: 'skipped' })}>
                    <SkipForward className="h-3.5 w-3.5" />
                    {t('clinic_campaigns.action.skip')}
                  </Button>
                )}
                {canManage && r.status !== 'pending' && (
                  <Button size="icon" variant="ghost" onClick={() => mark.mutate({ recipientId: r.id, status: 'pending' })} aria-label={t('clinic_campaigns.action.reset')}>
                    <RotateCcw className="h-3.5 w-3.5" />
                  </Button>
                )}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
