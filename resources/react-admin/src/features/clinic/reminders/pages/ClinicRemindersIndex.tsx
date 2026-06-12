import { useState } from 'react';
import { Link } from 'react-router-dom';
import { BellRing, Check, Phone, MessageCircle, Plus, X } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/app/providers/AuthProvider';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useReminders, useCompleteReminder, useCancelReminder } from '../hooks';
import { ReminderDialog } from '../components/ReminderDialog';
import type { CustomerReminder, ReminderListStatus } from '../types';

const FILTERS: ReminderListStatus[] = ['open', 'upcoming', 'overdue', 'done', 'all'];

function waLink(phone: string) {
  const digits = phone.replace(/[^\d]/g, '');
  return `https://wa.me/${digits.startsWith('0') ? '966' + digits.slice(1) : digits}`;
}

export function ClinicRemindersIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const [filter, setFilter] = useState<ReminderListStatus>('open');
  const [mine, setMine] = useState(false);
  const [creating, setCreating] = useState(false);
  const { data: reminders, isLoading } = useReminders(filter, mine);
  const canManage = useCan('reminders.manage');
  const canCreate = useCan('reminders.create');

  const complete = useCompleteReminder();
  const cancel = useCancelReminder();

  function fmt(iso: string | null) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  }

  async function onComplete(r: CustomerReminder) {
    try {
      await complete.mutateAsync(r.id);
      toast.success(t('clinic_reminders.marked_done'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  async function onCancel(r: CustomerReminder) {
    try {
      await cancel.mutateAsync(r.id);
      toast.success(t('clinic_reminders.cancelled'));
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <BellRing className="h-5 w-5 text-[var(--color-muted-foreground)]" />
          <h1 className="text-xl font-semibold">{t('clinic_reminders.title')}</h1>
        </div>
        {canCreate && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('clinic_reminders.new_task')}
          </Button>
        )}
      </div>
      <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_reminders.subtitle')}</p>

      <div className="flex flex-wrap items-center gap-1.5">
        {FILTERS.map((f) => (
          <button
            key={f}
            type="button"
            onClick={() => setFilter(f)}
            className={`rounded-full px-3 py-1 text-sm ${
              filter === f
                ? 'bg-[var(--color-primary)] text-white'
                : 'border border-[var(--color-border)] hover:bg-[var(--color-muted)]'
            }`}
          >
            {t(`clinic_reminders.filter.${f}`)}
          </button>
        ))}
        <span className="mx-1 h-4 w-px bg-[var(--color-border)]" />
        <button
          type="button"
          onClick={() => setMine((v) => !v)}
          className={`rounded-full px-3 py-1 text-sm ${
            mine
              ? 'bg-[var(--color-primary)] text-white'
              : 'border border-[var(--color-border)] hover:bg-[var(--color-muted)]'
          }`}
        >
          {t('clinic_reminders.filter.mine')}
        </button>
      </div>

      {isLoading ? (
        <div className="text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : !reminders || reminders.length === 0 ? (
        <div className="rounded-lg border border-dashed border-[var(--color-border)] p-8 text-center text-sm text-[var(--color-muted-foreground)]">
          {t('clinic_reminders.empty')}
        </div>
      ) : (
        <div className="space-y-2">
          {reminders.map((r) => (
            <div
              key={r.id}
              className="flex flex-col gap-2 rounded-lg border border-[var(--color-border)] bg-white p-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div className="min-w-0 space-y-1">
                <div className="flex items-center gap-2">
                  {r.customer_id ? (
                    <Link
                      to={`/clinic/customers/${r.customer_id}`}
                      className="font-medium hover:underline"
                    >
                      {r.customer_name ?? r.customer_phone ?? '—'}
                    </Link>
                  ) : (
                    <span className="font-medium">{r.title ?? '—'}</span>
                  )}
                  {r.status === 'pending' && r.is_overdue && (
                    <Badge variant="danger">{t('clinic_reminders.badge.overdue')}</Badge>
                  )}
                  {r.status === 'done' && (
                    <Badge variant="muted">{t('clinic_reminders.badge.done')}</Badge>
                  )}
                  {r.assignee_name && (
                    <Badge variant="info">{t('clinic_reminders.assigned_to', { name: r.assignee_name })}</Badge>
                  )}
                </div>
                <div className="text-sm text-[var(--color-muted-foreground)]">
                  {t('clinic_reminders.due_at', { time: fmt(r.remind_at) })}
                </div>
                {r.note && <div className="text-sm">{r.note}</div>}
                {r.created_by_name && (
                  <div className="text-[11px] text-[var(--color-muted-foreground)]">
                    {t('clinic_reminders.set_by', { name: r.created_by_name })}
                  </div>
                )}
              </div>

              <div className="flex shrink-0 items-center gap-1.5">
                {r.customer_phone && (
                  <>
                    <a
                      href={`tel:${r.customer_phone}`}
                      className="inline-flex items-center gap-1 rounded-md border border-[var(--color-border)] px-2 py-1.5 text-xs hover:bg-[var(--color-muted)]"
                    >
                      <Phone className="h-3.5 w-3.5" />
                      {t('outreach.call')}
                    </a>
                    <a
                      href={waLink(r.customer_phone)}
                      target="_blank"
                      rel="noopener"
                      className="inline-flex items-center gap-1 rounded-md border border-emerald-300 px-2 py-1.5 text-xs text-emerald-700 hover:bg-emerald-50"
                    >
                      <MessageCircle className="h-3.5 w-3.5" />
                      {t('outreach.whatsapp')}
                    </a>
                  </>
                )}
                {canManage && r.status === 'pending' && (
                  <>
                    <Button size="sm" variant="outline" className="h-7 gap-1 text-xs" onClick={() => onComplete(r)} disabled={complete.isPending}>
                      <Check className="h-3.5 w-3.5" />
                      {t('clinic_reminders.action.done')}
                    </Button>
                    <Button size="sm" variant="ghost" className="h-7 gap-1 text-xs" onClick={() => onCancel(r)} disabled={cancel.isPending}>
                      <X className="h-3.5 w-3.5" />
                      {t('clinic_reminders.action.cancel')}
                    </Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {creating && (
        <ReminderDialog open={creating} onClose={() => setCreating(false)} />
      )}
    </div>
  );
}
