export type ClinicStatus = 'pending' | 'active' | 'suspended' | 'rejected';
// 'free' | 'standard' | 'premium' are the current package slugs (see
// SubscriptionPackagesSeeder); 'basic' is a legacy value still stored on
// older clinics, kept here so their badge renders a translated label.
export type ClinicPlan = 'free' | 'standard' | 'basic' | 'premium';

export const CLINIC_STATUSES: ClinicStatus[] = ['pending', 'active', 'suspended', 'rejected'];
// Selectable plans in forms/filters — the live offering, no legacy 'basic'.
export const CLINIC_PLANS: ClinicPlan[] = ['free', 'standard', 'premium'];

export interface Clinic {
  id: number;
  name: string;
  slug: string;
  phone: string;
  email: string | null;
  license_number: string | null;
  tax_number: string | null;
  commercial_registration: string | null;
  /** True when a reversible password copy is stored (drives reveal vs regenerate). */
  password_available?: boolean;
  city_id: number;
  address: string | null;
  district: string | null;
  description: string | null;
  logo: string | null;
  gallery: string[] | null;
  website: string | null;
  instagram: string | null;
  twitter: string | null;
  snapchat: string | null;
  tiktok: string | null;
  latitude: number | string | null;
  longitude: number | string | null;
  google_place_id: string | null;
  maps_url: string | null;
  status: ClinicStatus;
  subscription_type: ClinicPlan | null;
  subscription_starts_at: string | null;
  subscription_ends_at: string | null;
  rejection_reason: string | null;
  is_featured: boolean;
  sort_order: number;
  bookings_count?: number;
  services_count?: number;
  offers_count?: number;
  customers_count?: number;
  visits_30d?: number;
  city?: { id: number; name: string } | null;
  categories?: { id: number; name: string }[];
  category_ids?: number[];
  is_trashed: boolean;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}
