import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { FieldError } from '@/components/forms/FieldError';
import { useClinicServices } from '@/features/clinic/services/hooks';
import { useClinicOffers } from '@/features/clinic/offers/hooks';

import { useRewardRule, useUpdateRewardRule } from '../hooks';
import type { DiscountType, RewardType } from '../types';

/**
 * The clinic's single auto-grant rule. Mirrors the server's shape rules:
 * offer_discount needs an offer + discount; free_service needs a service.
 * The form only sends the chosen type's fields.
 */
export function RewardRuleForm() {
  const { t } = useTranslation();
  const { data: rule, isLoading } = useRewardRule();
  const update = useUpdateRewardRule();

  const servicesResp = useClinicServices({ per_page: 200 });
  const services = servicesResp.data?.data ?? [];
  const offersResp = useClinicOffers();
  const offers = offersResp.data ?? [];

  const [enabled, setEnabled] = useState(false);
  const [type, setType] = useState<RewardType | ''>('');
  const [offerId, setOfferId] = useState<string>('');
  const [serviceId, setServiceId] = useState<string>('');
  const [discountType, setDiscountType] = useState<DiscountType>('percent');
  const [discountValue, setDiscountValue] = useState<string>('');
  const [validityDays, setValidityDays] = useState<string>('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!rule) return;
    setEnabled(rule.enabled);
    setType(rule.type ?? '');
    setOfferId(rule.offer_id ? String(rule.offer_id) : '');
    setServiceId(rule.service_id ? String(rule.service_id) : '');
    setDiscountType(rule.discount_type ?? 'percent');
    setDiscountValue(rule.discount_value != null ? String(rule.discount_value) : '');
    setValidityDays(rule.validity_days != null ? String(rule.validity_days) : '');
  }, [rule]);

  if (isLoading) {
    return <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>;
  }

  const save = async () => {
    setErrors({});
    const payload = {
      enabled,
      type: type || null,
      offer_id: type === 'offer_discount' && offerId ? Number(offerId) : null,
      service_id: type === 'free_service' && serviceId ? Number(serviceId) : null,
      discount_type: type === 'offer_discount' ? discountType : null,
      discount_value: type === 'offer_discount' && discountValue !== '' ? Number(discountValue) : null,
      validity_days: validityDays !== '' ? Number(validityDays) : null,
    };
    try {
      await update.mutateAsync(payload);
      toast.success(t('common.saved'));
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) {
        const mapped: Record<string, string> = {};
        Object.entries(ve).forEach(([f, m]) => { mapped[f] = m[0]; });
        setErrors(mapped);
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  return (
    <div className="max-w-2xl space-y-5">
      <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_rewards.rule.subtitle')}</p>

      {/* Enable toggle */}
      <div className="flex items-center justify-between gap-4 rounded-xl border border-[var(--color-border)] bg-white p-4">
        <div>
          <Label>{t('clinic_rewards.rule.enabled')}</Label>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_rewards.rule.enabled_hint')}</p>
        </div>
        <Switch checked={enabled} onCheckedChange={setEnabled} />
      </div>

      {/* Reward shape */}
      <div className="space-y-4 rounded-xl border border-[var(--color-border)] bg-white p-4">
        <div className="space-y-1.5">
          <Label>{t('clinic_rewards.rule.type')}</Label>
          <Select value={type} onChange={(e) => setType(e.target.value as RewardType | '')}>
            <option value="">{t('clinic_rewards.rule.type_placeholder')}</option>
            <option value="offer_discount">{t('clinic_rewards.type.offer_discount')}</option>
            <option value="free_service">{t('clinic_rewards.type.free_service')}</option>
          </Select>
          <FieldError message={errors.type} />
        </div>

        {type === 'offer_discount' && (
          <>
            <div className="space-y-1.5">
              <Label>{t('clinic_rewards.rule.offer')}</Label>
              <Select value={offerId} onChange={(e) => setOfferId(e.target.value)}>
                <option value="">{t('clinic_rewards.rule.offer_placeholder')}</option>
                {offers.map((o) => <option key={o.id} value={o.id}>{o.title}</option>)}
              </Select>
              <FieldError message={errors.offer_id} />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label>{t('clinic_rewards.rule.discount_type')}</Label>
                <Select value={discountType} onChange={(e) => setDiscountType(e.target.value as DiscountType)}>
                  <option value="percent">{t('clinic_rewards.discount.percent')}</option>
                  <option value="amount">{t('clinic_rewards.discount.amount')}</option>
                </Select>
                <FieldError message={errors.discount_type} />
              </div>
              <div className="space-y-1.5">
                <Label>{t('clinic_rewards.rule.discount_value')}</Label>
                <Input type="number" min="0" dir="ltr" value={discountValue} onChange={(e) => setDiscountValue(e.target.value)} />
                <FieldError message={errors.discount_value} />
              </div>
            </div>
          </>
        )}

        {type === 'free_service' && (
          <div className="space-y-1.5">
            <Label>{t('clinic_rewards.rule.service')}</Label>
            <Select value={serviceId} onChange={(e) => setServiceId(e.target.value)}>
              <option value="">{t('clinic_rewards.rule.service_placeholder')}</option>
              {services.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>
            <FieldError message={errors.service_id} />
          </div>
        )}

        <div className="space-y-1.5">
          <Label>{t('clinic_rewards.rule.validity_days')}</Label>
          <Input type="number" min="1" dir="ltr" placeholder={t('clinic_rewards.rule.validity_placeholder')}
                 value={validityDays} onChange={(e) => setValidityDays(e.target.value)} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_rewards.rule.validity_hint')}</p>
          <FieldError message={errors.validity_days} />
        </div>
      </div>

      <div className="flex justify-end">
        <Button onClick={save} disabled={update.isPending}>
          {update.isPending ? t('common.loading') : t('common.save')}
        </Button>
      </div>
    </div>
  );
}
