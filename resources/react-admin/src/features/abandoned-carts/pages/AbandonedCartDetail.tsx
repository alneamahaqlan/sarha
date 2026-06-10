import { Link, useParams } from 'react-router-dom';
import { ArrowRight, ShoppingCart } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { Badge } from '@/components/ui/badge';
import { useAbandonedCartDetail } from '../hooks';

export function AbandonedCartDetail() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { clinicId } = useParams<{ clinicId: string }>();
  const { data, isLoading } = useAbandonedCartDetail(Number(clinicId));

  const fmtDate = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', { dateStyle: 'short', timeStyle: 'short' }) : '—';

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Link to="/admin/abandoned-carts" className="text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]">
          <ArrowRight className="h-5 w-5 rtl:rotate-180" />
        </Link>
        <ShoppingCart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{data?.clinic.name ?? t('abandoned_carts.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('abandoned_carts.detail_subtitle')}</p>
        </div>
      </div>

      {isLoading ? (
        <p className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</p>
      ) : !data || data.carts.length === 0 ? (
        <p className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('abandoned_carts.empty')}</p>
      ) : (
        <div className="space-y-4">
          {data.carts.map((cart) => (
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
                    <div className="flex items-center gap-2 shrink-0">
                      <Badge variant="muted">{t(`abandoned_carts.type_${item.type}`)}</Badge>
                      <span className="text-xs text-[var(--color-muted-foreground)]">{fmtDate(item.added_at)}</span>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
