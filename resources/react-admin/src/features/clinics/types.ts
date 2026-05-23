export type ClinicStatus = 'pending' | 'active' | 'suspended' | 'rejected';
export type ClinicPlan = 'basic' | 'premium';

export const CLINIC_STATUSES: ClinicStatus[] = ['pending', 'active', 'suspended', 'rejected'];
export const CLINIC_PLANS: ClinicPlan[] = ['basic', 'premium'];

export interface Clinic {
  id: number;
  name: string;
  slug: string;
  phone: string;
  email: string | null;
  city_id: number;
  address: string | null;
  description: string | null;
  logo: string | null;
  gallery: string[] | null;
  website: string | null;
  instagram: string | null;
  twitter: string | null;
  snapchat: string | null;
  google_place_id: string | null;
  status: ClinicStatus;
  subscription_type: ClinicPlan | null;
  subscription_starts_at: string | null;
  subscription_ends_at: string | null;
  rejection_reason: string | null;
  is_featured: boolean;
  sort_order: number;
  bookings_count?: number;
  visits_30d?: number;
  city?: { id: number; name: string } | null;
  categories?: { id: number; name: string }[];
  category_ids?: number[];
  is_trashed: boolean;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}
