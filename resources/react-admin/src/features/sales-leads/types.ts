export type SalesLeadStatus =
  | 'new'
  | 'contacted'
  | 'interested'
  | 'negotiating'
  | 'converted'
  | 'lost';

export const SALES_LEAD_STATUSES: SalesLeadStatus[] = [
  'new',
  'contacted',
  'interested',
  'negotiating',
  'converted',
  'lost',
];

export type LeadSource =
  | 'referral' | 'website' | 'whatsapp' | 'instagram' | 'snapchat'
  | 'tiktok' | 'cold_call' | 'event' | 'walk_in' | 'other';

export const LEAD_SOURCES: LeadSource[] = [
  'referral', 'website', 'whatsapp', 'instagram', 'snapchat',
  'tiktok', 'cold_call', 'event', 'walk_in', 'other',
];

export type LostReason =
  | 'price' | 'no_response' | 'chose_competitor' | 'not_interested'
  | 'not_qualified' | 'bad_timing' | 'other';

export const LOST_REASONS: LostReason[] = [
  'price', 'no_response', 'chose_competitor', 'not_interested',
  'not_qualified', 'bad_timing', 'other',
];

export type ScoreTier = 'hot' | 'warm' | 'cold';

export type LeadActivityType =
  | 'note' | 'call' | 'whatsapp' | 'email' | 'meeting' | 'status_change';

/** Types an admin can log manually (status_change is system-only). */
export const LOGGABLE_ACTIVITY_TYPES: LeadActivityType[] = [
  'call', 'whatsapp', 'email', 'meeting', 'note',
];

export interface SalesLeadActivity {
  id: number;
  type: LeadActivityType;
  body: string | null;
  meta: { from?: string | null; to?: string | null } | null;
  admin_name: string | null;
  created_at: string | null;
}

export type BillingCycle = 'quarterly' | 'annual';

/**
 * Convert payload. The package carries the tier (features + default
 * price); `amount` is the optional per-subscription manual price an
 * admin can set for a clinic on a negotiated rate.
 */
export interface ConvertLeadPayload {
  package_id: number;
  billing_cycle: BillingCycle;
  amount: number;
}

export interface SalesLead {
  id: number;
  clinic_name: string;
  contact_name: string | null;
  phone: string;
  email: string | null;
  license_number: string | null;
  city_id: number | null;
  district: string | null;
  address: string | null;
  status: SalesLeadStatus;
  source: LeadSource | null;
  lost_reason: LostReason | null;
  lost_notes: string | null;
  assigned_to: number | null;
  next_follow_up_at: string | null;
  last_contact_at: string | null;
  notes: string | null;
  sales_notes: string | null;
  activities_count?: number;
  score: number;
  score_tier: ScoreTier;
  city?: { id: number; name: string } | null;
  assigned_admin?: { id: number; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SalesLeadFormValues {
  clinic_name: string;
  contact_name?: string | null;
  phone: string;
  email?: string | null;
  license_number?: string | null;
  city_id?: number | null;
  district?: string | null;
  address?: string | null;
  status: SalesLeadStatus;
  source?: LeadSource | null;
  lost_reason?: LostReason | null;
  lost_notes?: string | null;
  assigned_to?: number | null;
  next_follow_up_at?: string | null;
  last_contact_at?: string | null;
  notes?: string | null;
  sales_notes?: string | null;
}
