import { apiClient } from '@/lib/api-client';
import type { SingleResponse } from '@/types/api';
import type { Clinic } from '@/features/clinics/types';

export interface ProfileFormValues {
  name?: string;
  phone?: string;
  email?: string | null;
  address?: string | null;
  district?: string | null;
  description?: string | null;
  website?: string | null;
  instagram?: string | null;
  twitter?: string | null;
  snapchat?: string | null;
  logo?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  google_place_id?: string | null;
  password?: string;
}

export interface WorkingHourRow {
  day_of_week: number;
  is_open: boolean;
  open_time: string | null;
  close_time: string | null;
}

export interface ClinicReview {
  id: number;
  reviewer_name: string | null;
  reviewer_photo: string | null;
  rating: number;
  review_text: string | null;
  reviewed_at: string | null;
}

export interface ClinicReviews {
  average: number | null;
  count: number;
  reviews: ClinicReview[];
}

export type PackageColorToken = 'gray' | 'sage' | 'gold' | 'plum' | 'info' | 'warning';
export type PackageAnalyticsLevel = 'basic' | 'full';
export type SubscriptionBillingCycle = 'quarterly' | 'annual';
export type SubscriptionStatus = 'active' | 'expired' | 'cancelled' | 'pending_payment';

export interface ClinicSubscriptionPackage {
  id: number;
  slug: string;
  name_ar: string;
  name_en: string;
  tagline_ar: string | null;
  tagline_en: string | null;
  color_token: PackageColorToken;
  monthly_price: number;
  services_limit: number | null;
  articles_monthly_limit: number | null;
  quote_replies_monthly_limit: number | null;
  banner_slots: number;
  ai_article_generation: boolean;
  featured_in_search: boolean;
  ai_assistant_priority: number;
  google_reviews_sync: boolean;
  verified_badge: boolean;
  analytics_level: PackageAnalyticsLevel;
  allow_offers_packages: boolean;
  allow_doctors_before_after: boolean;
}

export interface FeatureGateSnapshot {
  package: {
    id: number;
    slug: string;
    name_ar: string;
    name_en: string;
    color_token: PackageColorToken;
    monthly_price: number;
  } | null;
  limits: {
    services: number | null;
    articles_month: number | null;
    quote_replies: number | null;
    banner_slots: number;
  };
  usage: {
    services: number;
    articles_month: number;
    quote_replies: number;
  };
  flags: {
    ai_article_generation: boolean;
    featured_in_search: boolean;
    ai_assistant_priority: number;
    google_reviews_sync: boolean;
    verified_badge: boolean;
    analytics_level: PackageAnalyticsLevel;
    allow_offers_packages: boolean;
    allow_doctors_before_after: boolean;
  };
}

export interface CurrentSubscription {
  id: number;
  status: SubscriptionStatus;
  billing_cycle: SubscriptionBillingCycle | null;
  bonus_months: number;
  starts_at: string | null;
  ends_at: string | null;
  amount: number;
}

export interface ClinicSubscription {
  /** Legacy slug (kept for backwards compatibility). */
  subscription_type: string | null;
  subscription_starts_at: string | null;
  subscription_ends_at: string | null;
  /** Signed: negative when expired. */
  days_remaining: number;
  is_active: boolean;
  /** Legacy basic/premium prices from SystemSetting. Superseded by packages[]. */
  plans: { basic: number; premium: number };
  contact: { phone: string | null; email: string | null; whatsapp: string | null };
  /** Phase E — current active subscription row. */
  current_subscription: CurrentSubscription | null;
  /** Phase E — FeatureGate snapshot powering the UI. */
  snapshot: FeatureGateSnapshot;
  /** Phase E — catalog of every active package, ordered. */
  packages: ClinicSubscriptionPackage[];
}

export const clinicProfileApi = {
  show: async () => {
    const res = await apiClient.get<SingleResponse<Clinic>>('/clinic/profile');
    return res.data.data;
  },
  update: async (values: ProfileFormValues) => {
    const res = await apiClient.patch<SingleResponse<Clinic>>('/clinic/profile', values);
    return res.data.data;
  },
  extractCoords: async (url: string) => {
    const res = await apiClient.post<SingleResponse<{ latitude: number; longitude: number }>>(
      '/clinic/profile/extract-coords',
      { url },
    );
    return res.data.data;
  },
  syncReviews: async () => {
    const res = await apiClient.post<SingleResponse<{ fetched: number }>>('/clinic/profile/sync-reviews');
    return res.data.data;
  },
  reviews: async () => {
    const res = await apiClient.get<SingleResponse<ClinicReviews>>('/clinic/reviews');
    return res.data.data;
  },
  workingHours: async () => {
    const res = await apiClient.get<{ data: WorkingHourRow[] }>('/clinic/working-hours');
    return res.data.data;
  },
  updateWorkingHours: async (hours: WorkingHourRow[]) => {
    const res = await apiClient.put<{ data: WorkingHourRow[] }>('/clinic/working-hours', { hours });
    return res.data.data;
  },
  subscription: async () => {
    const res = await apiClient.get<SingleResponse<ClinicSubscription>>('/clinic/subscription');
    return res.data.data;
  },
};
