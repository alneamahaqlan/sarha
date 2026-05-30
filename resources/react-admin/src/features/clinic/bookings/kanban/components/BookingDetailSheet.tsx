import { Phone, MessageCircle, Clock, User2 } from 'lucide-react';

import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { useLocale, useTranslation } from '@/app/providers/LocaleProvider';

import { useBookingDetail } from '../hooks';
import { CardAutoTags } from './CardAutoTags';
import { CardHeatBar } from './CardHeatBar';
import { CardSuggestions } from './CardSuggestions';
import { QuickActionsBar } from './QuickActionsBar';
import { ActivityTimeline } from './ActivityTimeline';
import { AssigneePicker } from './AssigneePicker';
import { TagsManager } from './TagsManager';
import { Customer360Panel } from './Customer360Panel';

interface Props {
  bookingId: number;
  customerPhone: string;
  onClose: () => void;
}

function fmtDate(iso: string | null, locale: string) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-GB', {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  });
}

export function BookingDetailSheet({ bookingId, customerPhone, onClose }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data, isLoading } = useBookingDetail(bookingId);

  const waPhone = customerPhone.replace(/[^0-9]/g, '');
  const waLink = waPhone ? `https://wa.me/${waPhone.startsWith('0') ? '966' + waPhone.slice(1) : waPhone}` : null;
  const telLink = customerPhone ? `tel:${customerPhone}` : null;

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="end" className="w-full sm:w-[460px] sm:max-w-[90vw]">
        <SheetHeader>
          <SheetTitle>
            {data?.customer_name ?? '…'}
          </SheetTitle>
          <SheetDescription className="flex items-center gap-2 text-xs">
            <span dir="ltr">{customerPhone}</span>
            {telLink && <a href={telLink} className="text-[var(--color-primary)] hover:underline"><Phone className="inline h-3 w-3" /></a>}
            {waLink && <a href={waLink} target="_blank" rel="noopener" className="text-emerald-600 hover:underline"><MessageCircle className="inline h-3 w-3" /></a>}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto">
          {isLoading || !data ? (
            <div className="p-6 text-xs text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
          ) : (
            <Tabs defaultValue="overview" className="flex flex-col">
              <TabsList className="mx-4 mt-3 grid grid-cols-3">
                <TabsTrigger value="overview">{t('clinic_bookings_kanban.tab.overview')}</TabsTrigger>
                <TabsTrigger value="customer">{t('clinic_bookings_kanban.tab.customer')}</TabsTrigger>
                <TabsTrigger value="timeline">{t('clinic_bookings_kanban.tab.timeline')}</TabsTrigger>
              </TabsList>

              <TabsContent value="overview" className="space-y-4 p-4">
                <div className="overflow-hidden rounded-lg border border-[var(--color-border)] bg-white">
                  <div className="space-y-2 p-3">
                    <div className="flex items-center justify-between">
                      <div className="text-[11px] text-[var(--color-muted-foreground)]" dir="ltr">{data.reference_code}</div>
                      <Badge variant="muted">{t(`clinic_bookings_kanban.column.${data.kanban_column}`)}</Badge>
                    </div>
                    <CardAutoTags tags={data.auto_tags} />
                    <CardSuggestions suggestions={data.suggestions} />
                    {data.service && (
                      <div className="text-sm">{data.service.name}{data.service.price ? ` — ${data.service.price}` : ''}</div>
                    )}
                    {data.appointment_at && (
                      <div className="flex items-center gap-1 text-xs">
                        <Clock className="h-3 w-3" />{fmtDate(data.appointment_at, locale)}
                      </div>
                    )}
                    {data.notes && (
                      <div className="rounded-md bg-amber-50 p-2 text-xs text-amber-800">
                        <div className="text-[10px] uppercase">{t('clinic_bookings_kanban.detail.customer_notes')}</div>
                        {data.notes}
                      </div>
                    )}
                    {data.clinic_notes && (
                      <div className="rounded-md bg-[var(--color-muted)]/40 p-2 text-xs">
                        <div className="text-[10px] uppercase">{t('clinic_bookings_kanban.detail.clinic_notes')}</div>
                        {data.clinic_notes}
                      </div>
                    )}
                    {data.is_for_relative && data.relative && (
                      <div className="rounded-md border border-blue-200 bg-blue-50 p-2 text-xs">
                        <div className="text-[10px] uppercase text-blue-700">{t('clinic_bookings_kanban.detail.for_relative')}</div>
                        <div>{data.relative.name} · <span dir="ltr">{data.relative.phone}</span></div>
                        {data.booker && (
                          <div className="text-[var(--color-muted-foreground)]">
                            <User2 className="inline h-3 w-3" /> {data.booker.name}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                  <CardHeatBar heat={data.heat} />
                </div>

                <section className="space-y-2">
                  <div className="text-xs font-semibold">{t('clinic_bookings_kanban.detail.quick_actions')}</div>
                  <QuickActionsBar bookingId={data.id} />
                </section>

                <section className="space-y-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
                  <AssigneePicker bookingId={data.id} current={data.assignee} />
                </section>

                <section className="space-y-2 rounded-lg border border-[var(--color-border)] bg-white p-3">
                  <div className="text-xs font-semibold">{t('clinic_bookings_kanban.detail.tags')}</div>
                  <TagsManager bookingId={data.id} bookingTags={data.tags} customerTags={data.customer_tags} />
                </section>
              </TabsContent>

              <TabsContent value="customer" className="p-4">
                <Customer360Panel phone={customerPhone} />
              </TabsContent>

              <TabsContent value="timeline" className="p-4">
                <ActivityTimeline bookingId={data.id} />
              </TabsContent>
            </Tabs>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
