import type { DiscountType, RewardType } from './types';

interface Describable {
  type: RewardType;
  discount_type: DiscountType | null;
  discount_value: number | null;
  offer?: { title: string } | null;
  service?: { name: string } | null;
}

/** One-line human description of what a reward grants. */
export function describeReward(t: (k: string, o?: Record<string, unknown>) => string, r: Describable): string {
  if (r.type === 'free_service') {
    return t('clinic_rewards.desc.free_service', { service: r.service?.name ?? '—' });
  }
  const offer = r.offer?.title ?? '—';
  const value = r.discount_value ?? 0;
  return r.discount_type === 'percent'
    ? t('clinic_rewards.desc.offer_percent', { value, offer })
    : t('clinic_rewards.desc.offer_amount', { value, offer });
}
