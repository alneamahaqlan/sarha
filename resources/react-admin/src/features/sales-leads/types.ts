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
  assigned_to: number | null;
  next_follow_up_at: string | null;
  last_contact_at: string | null;
  notes: string | null;
  sales_notes: string | null;
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
  assigned_to?: number | null;
  next_follow_up_at?: string | null;
  last_contact_at?: string | null;
  notes?: string | null;
  sales_notes?: string | null;
}
