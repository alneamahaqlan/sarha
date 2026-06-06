import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Megaphone, Plus, Trash2, Users } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useCampaigns, useDeleteCampaign } from '../hooks';
import { CreateCampaignDialog } from '../components/CreateCampaignDialog';
import type { CampaignStatus } from '../types';

const STATUS_VARIANT: Record<CampaignStatus, 'default' | 'success' | 'muted'> = {
  draft: 'muted',
  active: 'default',
  completed: 'success',
};

export function ClinicCampaignsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const navigate = useNavigate();
  const { data: campaigns, isLoading } = useCampaigns();
  const canManage = useCan('campaigns.manage');
  const del = useDeleteCampaign();
  const [creating, setCreating] = useState(false);

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { dateStyle: 'medium' }) : '—';

  async function onDelete(id: number) {
    try {
      await del.mutateAsync(id);
      toast.success(t('clinic_campaigns.deleted'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <Megaphone className="h-5 w-5 text-[var(--color-muted-foreground)]" />
          <h1 className="text-xl font-semibold">{t('clinic_campaigns.title')}</h1>
        </div>
        {canManage && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('clinic_campaigns.new')}
          </Button>
        )}
      </div>
      <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_campaigns.subtitle')}</p>

      {isLoading ? (
        <div className="text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : !campaigns || campaigns.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('clinic_campaigns.empty')}
        </div>
      ) : (
        <div className="space-y-2">
          {campaigns.map((c) => (
            <div
              key={c.id}
              className="flex flex-col gap-2 rounded-lg border border-[var(--color-border)] bg-white p-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <button
                type="button"
                onClick={() => navigate(`/clinic/campaigns/${c.id}`)}
                className="min-w-0 flex-1 text-start"
              >
                <div className="flex items-center gap-2">
                  <span className="font-medium hover:underline">{c.name}</span>
                  <Badge variant={STATUS_VARIANT[c.status]}>{t(`clinic_campaigns.status.${c.status}`)}</Badge>
                </div>
                <div className="mt-1 flex items-center gap-3 text-xs text-[var(--color-muted-foreground)]">
                  <span className="inline-flex items-center gap-1">
                    <Users className="h-3.5 w-3.5" />
                    {t('clinic_campaigns.sent_of', { sent: c.sent_count, total: c.total_recipients })}
                  </span>
                  <span>{fmt(c.created_at)}</span>
                </div>
                {/* Progress bar */}
                <div className="mt-1.5 h-1.5 w-full max-w-xs overflow-hidden rounded-full bg-[var(--color-muted)]">
                  <div className="h-full rounded-full bg-emerald-500" style={{ width: `${c.progress}%` }} />
                </div>
              </button>

              <div className="flex shrink-0 items-center gap-1.5">
                <Button size="sm" variant="outline" onClick={() => navigate(`/clinic/campaigns/${c.id}`)}>
                  {t('clinic_campaigns.open')}
                </Button>
                {canManage && (
                  <Button size="icon" variant="ghost" className="text-[var(--color-destructive)]" onClick={() => onDelete(c.id)} disabled={del.isPending} aria-label={t('common.delete')}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {creating && (
        <CreateCampaignDialog
          open={creating}
          onClose={() => setCreating(false)}
          onCreated={(id) => { setCreating(false); navigate(`/clinic/campaigns/${id}`); }}
        />
      )}
    </div>
  );
}
