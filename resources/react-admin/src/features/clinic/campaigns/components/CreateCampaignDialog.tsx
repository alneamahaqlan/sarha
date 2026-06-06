import { useState } from 'react';
import { toast } from 'sonner';
import { useQuery } from '@tanstack/react-query';
import { Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { campaignsApi } from '../api';
import { useCreateCampaign } from '../hooks';
import {
  CAMPAIGN_BOOKING_RANGES, CAMPAIGN_LAST_INTERACTION, CAMPAIGN_SEGMENTS,
  type CampaignAudience,
} from '../types';

interface Props {
  open: boolean;
  onClose: () => void;
  onCreated: (id: number) => void;
}

export function CreateCampaignDialog({ open, onClose, onCreated }: Props) {
  const { t } = useTranslation();
  const create = useCreateCampaign();

  const [name, setName] = useState('');
  const [message, setMessage] = useState('');
  const [audience, setAudience] = useState<CampaignAudience>({});

  function patch<K extends keyof CampaignAudience>(key: K, value: CampaignAudience[K]) {
    setAudience((prev) => ({ ...prev, [key]: value }));
  }

  // Live recipient count for the current audience (opt-outs/phoneless excluded
  // server-side). Keyed by audience so it refetches as filters change.
  const { data: count, isFetching } = useQuery({
    enabled: open,
    queryKey: ['clinic', 'campaigns', 'preview', audience],
    queryFn: () => campaignsApi.preview(audience),
    staleTime: 5_000,
  });

  function reset() {
    setName('');
    setMessage('');
    setAudience({});
  }

  async function submit() {
    if (!name.trim()) {
      toast.error(t('clinic_campaigns.errors.no_name'));
      return;
    }
    if (!message.trim()) {
      toast.error(t('clinic_campaigns.errors.no_message'));
      return;
    }
    if (!count || count === 0) {
      toast.error(t('clinic_campaigns.errors.no_recipients'));
      return;
    }
    try {
      const campaign = await create.mutateAsync({
        name: name.trim(),
        message_template: message.trim(),
        audience,
      });
      toast.success(t('clinic_campaigns.created'));
      reset();
      onCreated(campaign.id);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('clinic_campaigns.create.title')}</DialogTitle>
          <DialogDescription>{t('clinic_campaigns.create.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label htmlFor="cmp-name">{t('clinic_campaigns.fields.name')}</Label>
            <Input id="cmp-name" value={name} onChange={(e) => setName(e.target.value)} maxLength={120} />
          </div>

          {/* Audience */}
          <div className="rounded-lg border border-[var(--color-border)] p-3 space-y-2">
            <div className="text-xs font-semibold">{t('clinic_campaigns.fields.audience')}</div>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <Select value={audience.segment ?? ''} onChange={(e) => patch('segment', e.target.value || null)}>
                <option value="">{t('clinic_campaigns.audience.any_segment')}</option>
                {CAMPAIGN_SEGMENTS.map((s) => (
                  <option key={s} value={s}>{t(`clinic_campaigns.segment.${s}`)}</option>
                ))}
              </Select>
              <Select value={audience.last_interaction ?? ''} onChange={(e) => patch('last_interaction', e.target.value || null)}>
                <option value="">{t('clinic_campaigns.audience.any_interaction')}</option>
                {CAMPAIGN_LAST_INTERACTION.map((w) => (
                  <option key={w} value={w}>{t(`clinic_campaigns.last_interaction.${w}`)}</option>
                ))}
              </Select>
              <Select value={audience.booking_range ?? ''} onChange={(e) => patch('booking_range', e.target.value || null)}>
                <option value="">{t('clinic_campaigns.audience.any_bookings')}</option>
                {CAMPAIGN_BOOKING_RANGES.map((r) => (
                  <option key={r} value={r}>{t('clinic_campaigns.booking_range', { range: r })}</option>
                ))}
              </Select>
              <label className="flex items-center gap-2 text-sm">
                <Switch checked={!!audience.has_notes} onCheckedChange={(v) => patch('has_notes', v)} />
                {t('clinic_campaigns.audience.has_notes')}
              </label>
            </div>
            <div className="flex items-center gap-1.5 pt-1 text-sm text-[var(--color-muted-foreground)]">
              <Users className="h-4 w-4" />
              {isFetching
                ? t('common.loading')
                : t('clinic_campaigns.recipient_count', { count: count ?? 0 })}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="cmp-msg">{t('clinic_campaigns.fields.message')}</Label>
            <Textarea
              id="cmp-msg"
              rows={4}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              placeholder={t('clinic_campaigns.fields.message_placeholder')}
              maxLength={2000}
            />
            <p className="text-[11px] text-[var(--color-muted-foreground)]">{t('clinic_campaigns.fields.message_hint')}</p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={submit} disabled={create.isPending}>
            {create.isPending ? t('common.loading') : t('clinic_campaigns.create.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
