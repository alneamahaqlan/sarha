import { useState } from 'react';
import { toast } from 'sonner';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FieldError } from '@/components/forms/FieldError';
import { useClinicServices } from '@/features/clinic/services/hooks';
import { useClinicOffers } from '@/features/clinic/offers/hooks';

import { useGrantReward } from '../hooks';
import type { DiscountType, RewardType } from '../types';

interface Props {
  open: boolean;
  onClose: () => void;
}

/** Manually gift a voucher to a customer by phone. */
export function GrantRewardDialog({ open, onClose }: Props) {
  const { t } = useTranslation();
  const grant = useGrantReward();

  const servicesResp = useClinicServices({ per_page: 200 });
  const services = servicesResp.data?.data ?? [];
  const offersResp = useClinicOffers();
  const offers = offersResp.data ?? [];

  const [phone, setPhone] = useState('');
  const [type, setType] = useState<RewardType>('free_service');
  const [offerId, setOfferId] = useState('');
  const [serviceId, setServiceId] = useState('');
  const [discountType, setDiscountType] = useState<DiscountType>('percent');
  const [discountValue, setDiscountValue] = useState('');
  const [expiresInDays, setExpiresInDays] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const submit = async () => {
    setErrors({});
    try {
      await grant.mutateAsync({
        phone,
        type,
        offer_id: type === 'offer_discount' && offerId ? Number(offerId) : null,
        service_id: type === 'free_service' && serviceId ? Number(serviceId) : null,
        discount_type: type === 'offer_discount' ? discountType : null,
        discount_value: type === 'offer_discount' && discountValue !== '' ? Number(discountValue) : null,
        expires_in_days: expiresInDays !== '' ? Number(expiresInDays) : null,
      });
      toast.success(t('clinic_rewards.grant.granted'));
      onClose();
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
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_rewards.grant.title')}</DialogTitle>
        </DialogHeader>

        <div className="space-y-3">
          <div className="space-y-1.5">
            <Label>{t('clinic_rewards.grant.phone')}</Label>
            <Input dir="ltr" placeholder="05xxxxxxxx" value={phone} onChange={(e) => setPhone(e.target.value)} />
            <FieldError message={errors.phone} />
          </div>

          <div className="space-y-1.5">
            <Label>{t('clinic_rewards.rule.type')}</Label>
            <Select value={type} onChange={(e) => setType(e.target.value as RewardType)}>
              <option value="free_service">{t('clinic_rewards.type.free_service')}</option>
              <option value="offer_discount">{t('clinic_rewards.type.offer_discount')}</option>
            </Select>
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
            <Label>{t('clinic_rewards.grant.expires_in_days')}</Label>
            <Input type="number" min="1" dir="ltr" placeholder={t('clinic_rewards.rule.validity_placeholder')}
                   value={expiresInDays} onChange={(e) => setExpiresInDays(e.target.value)} />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
          <Button onClick={submit} disabled={grant.isPending}>
            {grant.isPending ? t('common.loading') : t('clinic_rewards.grant.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
