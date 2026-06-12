import { toast } from 'sonner';
import { ShoppingCart } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { useClinicCart, useUpdateClinicCart, useRequestClinicCart } from '../hooks';

export function ClinicCartPage() {
  const { t } = useTranslation();
  const { data, isLoading } = useClinicCart();
  const update = useUpdateClinicCart();
  const request = useRequestClinicCart();

  if (isLoading) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  const status = data?.cart_status ?? 'disabled';
  const canRequest = status === 'disabled' || status === 'rejected';

  const requestActivation = async () => {
    try {
      await request.mutateAsync();
      toast.success(t('clinic_cart.requested'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const toggleStorefront = async (v: boolean) => {
    try {
      await update.mutateAsync({ cart_storefront_enabled: v });
      toast.success(t('common.saved'));
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="max-w-3xl space-y-6">
      <div className="flex items-center gap-2">
        <ShoppingCart className="h-6 w-6 text-[var(--color-primary)]" />
        <div>
          <h1 className="text-2xl font-semibold">{t('clinic_cart.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_cart.subtitle')}</p>
        </div>
      </div>

      {/* Status banner */}
      {status === 'disabled' && <Notice tone="muted">{t('clinic_cart.status_disabled')}</Notice>}
      {status === 'pending' && <Notice tone="warning">{t('clinic_cart.status_pending')}</Notice>}
      {status === 'active' && <Notice tone="success">{t('clinic_cart.status_active')}</Notice>}
      {status === 'rejected' && (
        <Notice tone="danger">
          {t('clinic_cart.status_rejected')}
          {data?.cart_rejection_reason ? ` — ${data.cart_rejection_reason}` : ''}
        </Notice>
      )}

      {/* Storefront show/hide — only meaningful while active */}
      {status === 'active' && (
        <div className="space-y-2 rounded-xl border border-[var(--color-border)] bg-white p-5">
          <div className="flex items-center justify-between gap-4">
            <div>
              <Label>{t('clinic_cart.storefront_enabled')}</Label>
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_cart.storefront_enabled_hint')}</p>
            </div>
            <Switch
              checked={data?.cart_storefront_enabled ?? true}
              disabled={update.isPending}
              onCheckedChange={toggleStorefront}
            />
          </div>
        </div>
      )}

      {/* Request activation */}
      {canRequest && (
        <div className="flex justify-end">
          <Button onClick={requestActivation} disabled={request.isPending}>
            {request.isPending ? t('common.loading') : t('clinic_cart.request_activation')}
          </Button>
        </div>
      )}
    </div>
  );
}

function Notice({ tone, children }: { tone: 'muted' | 'warning' | 'success' | 'danger'; children: React.ReactNode }) {
  const cls = {
    muted: 'border-[var(--color-border)] bg-[var(--color-muted)] text-[var(--color-muted-foreground)]',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    danger: 'border-red-200 bg-red-50 text-red-800',
  }[tone];
  return <div className={`rounded-lg border px-4 py-3 text-sm ${cls}`}>{children}</div>;
}
