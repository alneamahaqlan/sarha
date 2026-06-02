import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import {
  useCustomerBookings, useCustomerComplaints,
} from '../hooks';
import { CustomerNotesThread } from './CustomerNotesThread';
import { CustomerActivityFeed } from './CustomerActivityFeed';

interface Props {
  customerId: number;
}

function fmtDate(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

export function CustomerTabs({ customerId }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const bookings = useCustomerBookings(customerId);
  const complaints = useCustomerComplaints(customerId);

  return (
    <Tabs defaultValue="bookings" className="space-y-3">
      {/* Price quotes are intentionally NOT shown to the clinic — only the
          platform admin may view a customer's quote-request history. */}
      <TabsList className="grid grid-cols-2 lg:grid-cols-4">
        <TabsTrigger value="bookings">{t('clinic_customers.tabs.bookings')}</TabsTrigger>
        <TabsTrigger value="complaints">{t('clinic_customers.tabs.complaints')}</TabsTrigger>
        <TabsTrigger value="notes">{t('clinic_customers.tabs.notes')}</TabsTrigger>
        <TabsTrigger value="timeline">{t('clinic_customers.tabs.timeline')}</TabsTrigger>
      </TabsList>

      <TabsContent value="bookings">
        {bookings.isLoading ? (
          <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
        ) : !bookings.data?.length ? (
          <div className="rounded-md border border-dashed border-[var(--color-border)] p-4 text-center text-xs text-[var(--color-muted-foreground)]">
            {t('clinic_customers.tabs.empty_bookings')}
          </div>
        ) : (
          <ul className="space-y-2">
            {bookings.data.map((b) => (
              <li key={b.id} className="rounded-md border border-[var(--color-border)] bg-white p-2.5">
                <div className="flex items-center justify-between gap-2">
                  <div className="min-w-0">
                    <div className="font-medium text-sm">{b.service_name ?? '—'}</div>
                    <div className="text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">{b.reference_code}</div>
                  </div>
                  <Badge variant="muted" className="text-[10px]">{b.status}</Badge>
                </div>
                <div className="mt-1 flex items-center justify-between text-[11px] text-[var(--color-muted-foreground)]">
                  <span>{fmtDate(b.appointment_at ?? b.created_at, locale)}</span>
                  {b.assignee_name && <span>{b.assignee_name}</span>}
                </div>
              </li>
            ))}
          </ul>
        )}
      </TabsContent>

      <TabsContent value="complaints">
        {complaints.isLoading ? (
          <div className="p-4 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
        ) : !complaints.data?.length ? (
          <div className="rounded-md border border-dashed border-[var(--color-border)] p-4 text-center text-xs text-[var(--color-muted-foreground)]">
            {t('clinic_customers.tabs.empty_complaints')}
          </div>
        ) : (
          <ul className="space-y-2">
            {complaints.data.map((c) => (
              <li key={c.id} className="rounded-md border border-[var(--color-border)] bg-white p-2.5">
                <div className="flex items-center justify-between gap-2">
                  <div className="font-medium text-sm">{c.subject}</div>
                  <Badge variant="muted" className="text-[10px]">{c.status}</Badge>
                </div>
                <div className="mt-1 flex justify-between text-[11px] text-[var(--color-muted-foreground)]">
                  <span dir="ltr">{c.reference_code}</span>
                  <span>{fmtDate(c.created_at, locale)}</span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </TabsContent>

      <TabsContent value="notes">
        <CustomerNotesThread customerId={customerId} />
      </TabsContent>

      <TabsContent value="timeline">
        <CustomerActivityFeed customerId={customerId} />
      </TabsContent>
    </Tabs>
  );
}
