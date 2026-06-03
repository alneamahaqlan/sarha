/**
 * Subscription package — the admin-editable tier catalogue.
 *
 * Field shape mirrors `SubscriptionPackageResource` on the server.
 * `null` on the four "limit" integers means unlimited; the UI uses
 * an explicit ♾ toggle so the admin doesn't confuse "0 = no quota"
 * with "no limit at all".
 */

export type ColorToken = 'gray' | 'sage' | 'gold' | 'plum' | 'info' | 'warning';
export type AnalyticsLevel = 'basic' | 'full';

/** The five public "similar / related" sections a package configures. */
export type SimilarSectionKey = 'complex' | 'service' | 'offer' | 'subClinic' | 'doctor';

export interface SimilarSectionConfig {
  show: boolean;
  match_city: boolean;
  match_specialty: boolean;
  match_subclinic?: boolean; // doctor section only
}

export interface SimilarConfig {
  limit: number;
  sections: Record<SimilarSectionKey, SimilarSectionConfig>;
}

export interface SubscriptionPackage {
  id: number;
  slug: string;
  name_ar: string;
  name_en: string;
  tagline_ar: string | null;
  tagline_en: string | null;
  color_token: ColorToken;
  monthly_price: number;
  sort_order: number;
  is_active: boolean;

  services_limit: number | null;
  articles_monthly_limit: number | null;
  ai_article_generation: boolean;
  featured_in_search: boolean;
  ai_assistant_priority: 0 | 1 | 2;
  google_reviews_sync: boolean;
  verified_badge: boolean;
  analytics_level: AnalyticsLevel;
  quote_replies_monthly_limit: number | null;
  banner_slots: number;
  allow_offers_packages: boolean;
  allow_doctors_before_after: boolean;
  similar_config: SimilarConfig;

  clinics_count: number;
  created_at: string | null;
  updated_at: string | null;
}

/** Payload for create + update — same fields, no server-generated ones. */
export type SubscriptionPackageFormValues = Omit<
  SubscriptionPackage,
  'id' | 'clinics_count' | 'created_at' | 'updated_at'
>;
