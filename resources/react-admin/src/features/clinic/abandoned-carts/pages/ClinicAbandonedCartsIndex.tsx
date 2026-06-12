import { toast } from 'sonner';
import { ShoppingCart, MessageCircle, Phone, CalendarPlus } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useCan } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClinicAbandonedCarts, useContactCart, useConvertCart } from '../hooks';
import type { ClinicAbandonedCart } from '../api';

export function ClinicAbandonedCartsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const canContact = useCan('cart_leads.contact');
  const { data, isLoading } = useClinicAbandonedCarts();
  const contact = useContactCart();
  const convert = useConvertCart();

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  const doContact = async (cart: ClinicAbandonedCart, channel: 'whatsapp' | 'call') => {
    if (!cart.user) return;
    try {
      const res = await contact.mutateAsync({ userId: cart.user.id, channel });
      const link = channel === 'whatsapp' ? res.whatsapp_link : res.tel_link;
      if (link) window.open(link, channel === 'whatsapp' ? '_blank' : '_self');
      toast.success(t('abandoned_carts.contact_logged'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const doConvert = async (cart: ClinicAbandonedCart) => {
    if (!cart.user) return;
    try {
      const res = await convert.mutateAsync(cart.user.id);
      toast.success(t('abandoned_carts.converted', { reference: res.reference_code }));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <ShoppingCart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_abandoned_carts.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_abandoned_carts.subtitle')}</p>
        </div>
      </div>

      {isLoading ? (
        <p className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</p>
      ) : !data || data.length === 0 ? (
        <p className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('clinic_abandoned_carts.empty')}</p>
      ) : (
        <div className="space-y-4">
          {data.map((cart) => (
            <div key={cart.user?.id ?? Math.random()} className="rounded-lg border border-[var(--color-border)] bg-white">
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--color-border)] px-4 py-3">
                <div className="min-w-0">
                  <div className="font-semibold">{cart.user?.name ?? '—'}</div>
                  <div className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">{cart.user?.phone ?? '—'}</div>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant={cart.contacted_recently ? 'success' : 'warning'}>
                    {cart.contacted_recently ? t('abandoned_carts.contacted_recently') : t('abandoned_carts.not_contacted')}
                  </Badge>
                  <span className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(cart.last_contacted_at)}</span>
                </div>
              </div>

              <ul className="divide-y divide-[var(--color-border)]">
                {cart.items.map((item) => (
                  <li key={item.id} className="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                    <div className="min-w-0">
                      <span className="font-medium">{item.name}</span>
                      {item.deleted && <span className="ms-1 text-xs text-red-500">({t('abandoned_carts.item_deleted')})</span>}
                    </div>
                    <Badge variant="muted">{t(`abandoned_carts.type_${item.type}`)}</Badge>
                  </li>
                ))}
              </ul>

              {canContact && (
                <div className="flex flex-wrap items-center gap-2 border-t border-[var(--color-border)] px-4 py-3">
                  <Button size="sm" variant="outline" disabled={contact.isPending || !cart.user?.phone}
                    onClick={() => doContact(cart, 'whatsapp')}>
                    <MessageCircle className="h-4 w-4" /> {t('abandoned_carts.whatsapp')}
                  </Button>
                  <Button size="sm" variant="outline" disabled={contact.isPending || !cart.user?.phone}
                    onClick={() => doContact(cart, 'call')}>
                    <Phone className="h-4 w-4" /> {t('abandoned_carts.call')}
                  </Button>
                  <Button size="sm" disabled={convert.isPending} onClick={() => doConvert(cart)}>
                    <CalendarPlus className="h-4 w-4" /> {t('abandoned_carts.convert')}
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
