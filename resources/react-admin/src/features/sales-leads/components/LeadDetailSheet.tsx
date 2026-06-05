import { useState } from 'react';
import { toast } from 'sonner';
import { Phone, MessageCircle, Mail, Users, StickyNote, ArrowRightLeft } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useLeadActivities, useLogLeadActivity } from '../hooks';
import {
  LOGGABLE_ACTIVITY_TYPES,
  type LeadActivityType,
  type SalesLead,
  type SalesLeadActivity,
  type ScoreTier,
} from '../types';

const TIER_VARIANT: Record<ScoreTier, 'success' | 'warning' | 'muted'> = {
  hot: 'success',
  warm: 'warning',
  cold: 'muted',
};

const ACTIVITY_ICON: Record<string, any> = {
  call: Phone,
  whatsapp: MessageCircle,
  email: Mail,
  meeting: Users,
  note: StickyNote,
  status_change: ArrowRightLeft,
};

interface Props {
  lead: SalesLead;
  onClose: () => void;
}

export function LeadDetailSheet({ lead, onClose }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: activities, isLoading } = useLeadActivities(lead.id);
  const log = useLogLeadActivity(lead.id);

  const [type, setType] = useState<LeadActivityType>('call');
  const [body, setBody] = useState('');

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

  async function submit() {
    try {
      await log.mutateAsync({ type, body: body.trim() || null });
      toast.success(t('sales_leads.activity.logged'));
      setBody('');
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  function activityLabel(a: SalesLeadActivity): string {
    if (a.type === 'status_change') {
      const to = a.meta?.to ? t(`sales_leads.status.${a.meta.to}`) : '—';
      const from = a.meta?.from ? t(`sales_leads.status.${a.meta.from}`) : null;
      return from
        ? t('sales_leads.activity.status_changed', { from, to })
        : t('sales_leads.activity.status_set', { to });
    }
    return t(`sales_leads.activity.type.${a.type}`);
  }

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="end" className="w-full sm:w-[480px] sm:max-w-[92vw] overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            {lead.clinic_name}
            <Badge variant={TIER_VARIANT[lead.score_tier]}>
              {t(`sales_leads.score_tier.${lead.score_tier}`)} · {lead.score}
            </Badge>
          </SheetTitle>
          <SheetDescription className="flex flex-wrap items-center gap-2 text-xs">
            <span dir="ltr">{lead.phone}</span>
            {lead.contact_name && <span>· {lead.contact_name}</span>}
            {lead.source && <Badge variant="muted">{t(`sales_leads.source.${lead.source}`)}</Badge>}
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 p-4">
          {/* Quick contact */}
          <div className="flex gap-2">
            <a href={`tel:${lead.phone}`} className="inline-flex items-center gap-1.5 rounded-md border border-[var(--color-border)] px-3 py-1.5 text-sm hover:bg-[var(--color-muted)]">
              <Phone className="h-4 w-4" />{t('sales_leads.activity.type.call')}
            </a>
            <a
              href={`https://wa.me/${lead.phone.replace(/[^\d]/g, '').replace(/^0/, '966')}`}
              target="_blank" rel="noopener"
              className="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-1.5 text-sm text-emerald-700 hover:bg-emerald-50"
            >
              <MessageCircle className="h-4 w-4" />{t('outreach.whatsapp')}
            </a>
          </div>

          {/* Log activity */}
          <div className="space-y-2 rounded-lg border border-[var(--color-border)] p-3">
            <div className="text-xs font-semibold">{t('sales_leads.activity.log_title')}</div>
            <Select value={type} onChange={(e) => setType(e.target.value as LeadActivityType)}>
              {LOGGABLE_ACTIVITY_TYPES.map((tp) => (
                <option key={tp} value={tp}>{t(`sales_leads.activity.type.${tp}`)}</option>
              ))}
            </Select>
            <Textarea
              rows={2}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder={t('sales_leads.activity.body_placeholder')}
              maxLength={2000}
            />
            <Button size="sm" onClick={submit} disabled={log.isPending}>
              {log.isPending ? t('common.loading') : t('sales_leads.activity.log_button')}
            </Button>
          </div>

          {/* Timeline */}
          <div className="space-y-2">
            <div className="text-xs font-semibold">{t('sales_leads.activity.timeline')}</div>
            {isLoading ? (
              <div className="text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
            ) : !activities || activities.length === 0 ? (
              <div className="rounded-md border border-dashed border-[var(--color-border)] p-4 text-center text-xs text-[var(--color-muted-foreground)]">
                {t('sales_leads.activity.empty')}
              </div>
            ) : (
              <ol className="space-y-2">
                {activities.map((a) => {
                  const Icon = ACTIVITY_ICON[a.type] ?? StickyNote;
                  return (
                    <li key={a.id} className="flex items-start gap-2.5 rounded-md border border-[var(--color-border)] p-2.5">
                      <Icon className="mt-0.5 h-4 w-4 shrink-0 text-[var(--color-muted-foreground)]" />
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between gap-2 text-[12px]">
                          <span className="font-medium">{activityLabel(a)}</span>
                          <span className="text-[10px] text-[var(--color-muted-foreground)]">{fmt(a.created_at)}</span>
                        </div>
                        {a.body && <div className="mt-0.5 text-[11px] text-[var(--color-muted-foreground)]">{a.body}</div>}
                        {a.admin_name && <div className="mt-0.5 text-[10px] text-[var(--color-muted-foreground)]">{a.admin_name}</div>}
                      </div>
                    </li>
                  );
                })}
              </ol>
            )}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
