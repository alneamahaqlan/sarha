export type RewardType = 'offer_discount' | 'free_service';
export type DiscountType = 'percent' | 'amount';
export type VoucherStatus = 'active' | 'used' | 'expired' | 'void';
export type VoucherSource = 'attendance' | 'manual';

/** Lightweight references echoed inline by the API resources. */
export interface NamedRef { id: number; title?: string; name?: string }

export interface RewardRule {
  enabled: boolean;
  type: RewardType | null;
  offer_id: number | null;
  service_id: number | null;
  discount_type: DiscountType | null;
  discount_value: number | null;
  validity_days: number | null;
  offer?: { id: number; title: string } | null;
  service?: { id: number; name: string } | null;
  is_grantable: boolean;
}

export interface RewardRulePayload {
  enabled: boolean;
  type: RewardType | null;
  offer_id?: number | null;
  service_id?: number | null;
  discount_type?: DiscountType | null;
  discount_value?: number | null;
  validity_days?: number | null;
}

export interface RewardVoucher {
  id: number;
  code: string;
  type: RewardType;
  status: VoucherStatus;
  is_expired: boolean;
  source: VoucherSource;
  phone: string;
  customer_name?: string | null;
  offer?: { id: number; title: string } | null;
  service?: { id: number; name: string } | null;
  discount_type: DiscountType | null;
  discount_value: number | null;
  expires_at: string | null;
  used_at: string | null;
  granted_by_name: string | null;
  origin_reference?: string | null;
  applied_reference?: string | null;
  redeemed_reference?: string | null;
  created_at: string | null;
}

export interface GrantRewardPayload {
  phone: string;
  type: RewardType;
  offer_id?: number | null;
  service_id?: number | null;
  discount_type?: DiscountType | null;
  discount_value?: number | null;
  expires_in_days?: number | null;
}

export interface VoucherFilters {
  status?: VoucherStatus | '';
  search?: string;
  page?: number;
  per_page?: number;
}
