export type SubscriptionType = 'basic' | 'premium';
export type SubscriptionStatus = 'active' | 'expired' | 'cancelled' | 'pending_payment';

export const SUBSCRIPTION_TYPES: SubscriptionType[] = ['basic', 'premium'];
export const SUBSCRIPTION_STATUSES: SubscriptionStatus[] = ['active', 'expired', 'cancelled', 'pending_payment'];

export interface Subscription {
  id: number;
  clinic_id: number;
  type: SubscriptionType;
  amount: number;
  starts_at: string | null;
  ends_at: string | null;
  status: SubscriptionStatus;
  moyasar_payment_id: string | null;
  notes: string | null;
  clinic?: { id: number; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SubscriptionFormValues {
  clinic_id: number;
  type: SubscriptionType;
  amount: number;
  starts_at: string;
  ends_at: string;
  status: SubscriptionStatus;
  moyasar_payment_id?: string | null;
  notes?: string | null;
}
