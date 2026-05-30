import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useClinicLookup } from '@/features/lookups/hooks';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { usePackages } from '@/features/subscription-packages/hooks';

import { useCreateSubscription, useUpdateSubscription } from '../hooks';
import type { BillingCycle, Subscription, SubscriptionFormValues } from '../types';

interface Props {
  subscription?: Subscription | null;
  onSuccess?: () => void;
  onCancel?: () => void;
}

/**
 * The admin recorded a bank transfer — type the four inputs that the
 * server can't derive on its own, and let SubscriptionService figure
 * out amount + dates from the chosen package + cycle. Renew / cancel
 * have dedicated row actions on the index page, so this form is the
 * "add subscription" + "edit notes" path only.
 */
export function SubscriptionForm({ subscription, onSuccess, onCancel }: Props) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { data: clinics } = useClinicLookup();
  const { data: packages } = usePackages();
  const create = useCreateSubscription();
  const update = useUpdateSubscription(subscription?.id ?? 0);

  const [values, setValues] = useState<SubscriptionFormValues>({
    clinic_id: 0,
    subscription_package_id: 0,
    billing_cycle: 'quarterly',
    bonus_months: 0,
    notes: '',
  });
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (subscription) {
      setValues({
        clinic_id: subscription.clinic_id,
        subscription_package_id: subscription.subscription_package_id ?? 0,
        billing_cycle: (subscription.billing_cycle ?? 'quarterly'),
        bonus_months: subscription.bonus_months ?? 0,
        notes: subscription.notes ?? '',
      });
    } else if (packages?.length) {
      // Default the package picker to the first non-free entry so the
      // common case (admin recording a paid transfer) is one-tap less.
      const firstPaid = packages.find((p) => p.monthly_price > 0) ?? packages[0];
      setValues((v) => ({ ...v, subscription_package_id: firstPaid.id }));
    }
  }, [subscription, packages]);

  const set = <K extends keyof SubscriptionFormValues>(key: K, val: SubscriptionFormValues[K]) =>
    setValues((v) => ({ ...v, [key]: val }));

  /** Live preview of the amount derived from package × cycle. */
  const preview = useMemo(() => {
    const pkg = packages?.find((p) => p.id === values.subscription_package_id);
    if (!pkg) return null;
    const paidMonths = values.billing_cycle === 'annual' ? 12 : 3;
    const months = paidMonths + (values.bonus_months ?? 0);
    return {
      pkgName: locale === 'ar' ? pkg.name_ar : pkg.name_en,
      monthly: pkg.monthly_price,
      months,
      paidMonths,
      total: pkg.monthly_price * paidMonths,
    };
  }, [packages, values.subscription_package_id, values.billing_cycle, values.bonus_months, locale]);

  const onSubmit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    setErrors({});
    const payload: SubscriptionFormValues = {
      ...values,
      notes: (values.notes ?? '').trim() || null,
    };
    try {
      if (subscription) {
        await update.mutateAsync(payload as Partial<SubscriptionFormValues>);
        toast.success(t('subscriptions.updated'));
      } else {
        await create.mutateAsync(payload);
        toast.success(t('subscriptions.created'));
      }
      onSuccess?.();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) {
        const flat: Record<string, string> = {};
        Object.entries(ve).forEach(([k, msgs]) => { flat[k] = msgs[0]; });
        setErrors(flat);
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  const submitting = create.isPending || update.isPending;

  return (
    <form onSubmit={onSubmit} className="space-y-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="clinic_id">{t('subscriptions.form.clinic')}</Label>
          <Select
            id="clinic_id"
            value={values.clinic_id || ''}
            onChange={(e) => set('clinic_id', Number(e.target.value))}
            disabled={Boolean(subscription)}
          >
            <option value="">—</option>
            {clinics?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          {errors.clinic_id && <p className="text-xs text-[var(--color-destructive)]">{errors.clinic_id}</p>}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="subscription_package_id">{t('subscriptions.form.package')}</Label>
          <Select
            id="subscription_package_id"
            value={values.subscription_package_id || ''}
            onChange={(e) => set('subscription_package_id', Number(e.target.value))}
          >
            <option value="">—</option>
            {packages?.map((p) => {
              const name = locale === 'ar' ? p.name_ar : p.name_en;
              const price = p.monthly_price === 0 ? t('packages.free') : `${p.monthly_price} ${t('packages.sar_per_month')}`;
              return <option key={p.id} value={p.id}>{`${name} — ${price}`}</option>;
            })}
          </Select>
          {errors.subscription_package_id && <p className="text-xs text-[var(--color-destructive)]">{errors.subscription_package_id}</p>}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="billing_cycle">{t('subscriptions.form.billing_cycle')}</Label>
          <Select
            id="billing_cycle"
            value={values.billing_cycle}
            onChange={(e) => {
              const cycle = e.target.value as BillingCycle;
              // Quarterly disables the bonus_months input — zero the
              // underlying value too so a previously-typed bonus from
              // "annual" doesn't get silently POSTed.
              setValues((v) => ({
                ...v,
                billing_cycle: cycle,
                bonus_months: cycle === 'quarterly' ? 0 : v.bonus_months,
              }));
            }}
          >
            <option value="quarterly">{t('subscriptions.cycle.quarterly')}</option>
            <option value="annual">{t('subscriptions.cycle.annual')}</option>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="bonus_months">{t('subscriptions.form.bonus_months')}</Label>
          <Input
            id="bonus_months"
            type="number" min={0} max={24}
            value={values.bonus_months}
            onChange={(e) => set('bonus_months', Number(e.target.value))}
            disabled={values.billing_cycle === 'quarterly'}
          />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('subscriptions.form.bonus_months_hint')}</p>
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="notes">{t('subscriptions.form.notes')}</Label>
          <Textarea id="notes" rows={2} value={values.notes ?? ''} onChange={(e) => set('notes', e.target.value)} />
        </div>
      </div>

      {/* Live total preview — what gets stored as `amount`. */}
      {preview && !subscription && (
        <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-muted)]/30 px-3 py-2 text-sm space-y-1">
          <div className="flex items-center justify-between">
            <span className="text-[var(--color-muted-foreground)]">{t('subscriptions.form.preview_package')}</span>
            <span className="font-medium">{preview.pkgName}</span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-[var(--color-muted-foreground)]">{t('subscriptions.form.preview_months')}</span>
            <span className="font-medium">
              {preview.months}
              {preview.months > preview.paidMonths && (
                <span className="ms-2 text-xs text-emerald-600">
                  ({t('subscriptions.form.preview_bonus_label', { n: preview.months - preview.paidMonths })})
                </span>
              )}
            </span>
          </div>
          <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-1 mt-1">
            <span className="font-medium">{t('subscriptions.form.preview_total')}</span>
            <span className="font-bold">{preview.total.toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US')} {t('packages.sar_per_month').replace('/mo', '').replace('/شهر', '')}</span>
          </div>
        </div>
      )}

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={submitting}>{t('common.cancel')}</Button>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.loading') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  );
}
