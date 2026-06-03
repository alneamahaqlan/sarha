/**
 * Subscription types — extended to model the new package + billing
 * cycle shape introduced in Phase D. Legacy `type` (now a free-form
 * slug carrying the package slug) is kept for backward compatibility
 * with any pre-Phase-A code that still reads it.
 */

export type SubscriptionStatus = 'active' | 'expired' | 'cancelled' | 'pending_payment';
export type BillingCycle = 'quarterly' | 'annual';

export const SUBSCRIPTION_STATUSES: SubscriptionStatus[] = ['active', 'expired', 'cancelled', 'pending_payment'];
export const BILLING_CYCLES: BillingCycle[] = ['quarterly', 'annual'];

/** Slim package shape sent inline on Subscription rows for table rendering. */
export interface SubscriptionPackageInline {
  id: number;
  slug: string;
  name_ar: string;
  name_en: string;
  color_token: string;
  monthly_price: number;
}

export interface Subscription {
  id: number;
  clinic_id: number;
  subscription_package_id: number | null;
  /** Legacy slug carrying the package slug — kept for back-compat. */
  type: string;
  billing_cycle: BillingCycle | null;
  bonus_months: number;
  amount: number;
  starts_at: string | null;
  ends_at: string | null;
  /** Signed days remaining; negative if `ends_at` is in the past. */
  days_remaining: number | null;
  status: SubscriptionStatus;
  moyasar_payment_id: string | null;
  notes: string | null;
  clinic?: { id: number; name: string } | null;
  package?: SubscriptionPackageInline | null;
  created_at: string | null;
  updated_at: string | null;
}

/**
 * Payload for create — just the inputs the admin types in. The
 * server's SubscriptionService derives amount + dates from
 * package_id + cycle + bonus_months.
 */
export interface SubscriptionFormValues {
  clinic_id: number;
  subscription_package_id: number;
  billing_cycle: BillingCycle;
  bonus_months: number;
  /**
   * Manual per-subscription price. Prefilled from the package default
   * (monthly_price × cycle months) but editable so an admin can charge
   * a clinic a negotiated rate.
   */
  amount: number;
  notes?: string | null;
}
