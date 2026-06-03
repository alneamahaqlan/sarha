import { ExternalLink, Calendar, AlertOctagon, FileQuestion } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { useCustomerProfile } from '../hooks';
import { CustomerNotesThread } from '@/features/clinic/customers/components/CustomerNotesThread';

interface Props {
  phone: string;
}

function fmt(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
}

export function Customer360Panel({ phone }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useCustomerProfile(phone);

  if (isLoading) return <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  if (!data) return null;
  const s = data.summary;

  return (
    <div className="space-y-4">
      {data.customer_id && (
        <div className="flex justify-end">
          <Link
            to={`/clinic/customers/${data.customer_id}`}
            className="inline-flex items-center gap-1 text-[11px] text-[var(--color-primary)] hover:underline"
          >
            <ExternalLink className="h-3 w-3" />
            {t('clinic_customers.open_full_profile')}
          </Link>
        </div>
      )}

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-3">
        <div className="text-sm font-semibold">{data.name}</div>
        <div className="mt-0.5 text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">{data.phone}</div>
        <div className="mt-2 flex flex-wrap gap-1.5">
          {s.is_vip && <Badge variant="gold">{t('clinic_bookings_kanban.tag.vip')}</Badge>}
          {s.is_repeat && <Badge variant="info">{t('clinic_bookings_kanban.tag.repeat')}</Badge>}
          {s.is_new && <Badge variant="default">{t('clinic_bookings_kanban.tag.new')}</Badge>}
          {s.has_open_complaint && <Badge variant="danger">{t('clinic_bookings_kanban.tag.complaint')}</Badge>}
          {s.cancel_risk && <Badge variant="warning">{t('clinic_bookings_kanban.tag.cancel_risk')}</Badge>}
        </div>
        <div className="mt-3 grid grid-cols-2 gap-2 text-[11px]">
          <div className="rounded-md bg-[var(--color-muted)]/40 p-2">
            <div className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.c360.total')}</div>
            <div className="text-sm font-semibold">{s.total_bookings}</div>
          </div>
          <div className="rounded-md bg-[var(--color-muted)]/40 p-2">
            <div className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.c360.completed')}</div>
            <div className="text-sm font-semibold">{s.completed_count}</div>
          </div>
          {s.first_seen && (
            <div className="col-span-2 rounded-md bg-[var(--color-muted)]/40 p-2">
              <div className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.c360.first_seen')}</div>
              <div className="text-xs">{fmt(s.first_seen, locale)}</div>
            </div>
          )}
        </div>
      </div>

      <section className="space-y-1.5">
        <div className="flex items-center gap-1.5 text-xs font-semibold">
          <Calendar className="h-3.5 w-3.5" /> {t('clinic_bookings_kanban.c360.past_bookings')} ({data.bookings.length})
        </div>
        {data.bookings.length === 0 ? (
          <div className="rounded-md border border-dashed border-[var(--color-border)] p-3 text-center text-[11px] text-[var(--color-muted-foreground)]">
            {t('clinic_bookings_kanban.c360.no_bookings')}
          </div>
        ) : (
          <ul className="space-y-1.5">
            {data.bookings.map((b) => (
              <li key={b.id} className="rounded-md border border-[var(--color-border)] bg-white p-2 text-[11px]">
                <div className="flex items-center justify-between gap-2">
                  <span className="font-medium">{b.service_name ?? '—'}</span>
                  <Badge variant="muted" className="text-[10px]">{b.status}</Badge>
                </div>
                <div className="mt-0.5 flex items-center justify-between text-[var(--color-muted-foreground)]">
                  <span dir="ltr">{b.reference_code}</span>
                  <span>{fmt(b.appointment_at ?? b.created_at, locale)}</span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {data.customer_id && (
        <section className="space-y-1.5">
          <div className="text-xs font-semibold">{t('clinic_customers.notes.section_title')}</div>
          <CustomerNotesThread customerId={data.customer_id} compact />
        </section>
      )}

      {data.complaints.length > 0 && (
        <section className="space-y-1.5">
          <div className="flex items-center gap-1.5 text-xs font-semibold">
            <AlertOctagon className="h-3.5 w-3.5" /> {t('clinic_bookings_kanban.c360.complaints')} ({data.complaints.length})
          </div>
          <ul className="space-y-1.5">
            {data.complaints.map((c) => (
              <li key={c.id} className="rounded-md border border-[var(--color-border)] bg-white p-2 text-[11px]">
                <div className="font-medium">{c.subject}</div>
                <div className="mt-0.5 flex justify-between text-[var(--color-muted-foreground)]">
                  <span dir="ltr">{c.reference_code}</span>
                  <span>{c.status}</span>
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}

      {data.price_quotes.length > 0 && (
        <section className="space-y-1.5">
          <div className="flex items-center gap-1.5 text-xs font-semibold">
            <FileQuestion className="h-3.5 w-3.5" /> {t('clinic_bookings_kanban.c360.price_quotes')} ({data.price_quotes.length})
          </div>
          <ul className="space-y-1.5">
            {data.price_quotes.map((q) => (
              <li key={q.id} className="rounded-md border border-[var(--color-border)] bg-white p-2 text-[11px]">
                <div className="flex justify-between">
                  <span>{q.service_name}</span>
                  <span className="text-[var(--color-muted-foreground)]">{q.status}</span>
                </div>
              </li>
            ))}
          </ul>
        </section>
      )}

      {data.last_activity && (
        <div className="rounded-md bg-[var(--color-muted)]/30 p-2 text-[11px]">
          <div className="text-[10px] text-[var(--color-muted-foreground)]">{t('clinic_bookings_kanban.c360.last_interaction')}</div>
          <div className="mt-0.5">{data.last_activity.actor_name} — {data.last_activity.action}</div>
        </div>
      )}
    </div>
  );
}
