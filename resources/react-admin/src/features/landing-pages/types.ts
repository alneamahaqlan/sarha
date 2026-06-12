export type LandingPageType = 'clinic' | 'offer' | 'city' | 'category' | 'comparison' | 'custom';
export type LandingPageStatus = 'draft' | 'published' | 'archived';
export type BlockType =
  | 'hero' | 'services' | 'offers' | 'doctors' | 'gallery'
  | 'reviews' | 'faq' | 'map' | 'booking' | 'countdown';

export const LANDING_TYPES: LandingPageType[] = ['clinic', 'offer', 'city', 'category', 'comparison', 'custom'];
export const LANDING_STATUSES: LandingPageStatus[] = ['draft', 'published', 'archived'];
export const BLOCK_TYPES: BlockType[] = [
  'hero', 'services', 'offers', 'doctors', 'gallery', 'reviews', 'faq', 'map', 'booking', 'countdown',
];

export interface LandingPageBlock {
  id: number;
  landing_page_id: number;
  type: BlockType;
  sort_order: number;
  is_visible: boolean;
  config: Record<string, unknown>;
  block_version: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface LandingPage {
  id: number;
  type: LandingPageType;
  status: LandingPageStatus;
  slug: string;
  title_ar: string | null;
  title_en: string | null;
  internal_name: string | null;

  clinic_id: number | null;
  offer_id: number | null;
  city_id: number | null;
  category_id: number | null;

  cover_image: string | null;
  social_image: string | null;

  published_at: string | null;
  starts_at: string | null;
  ends_at: string | null;

  cta_label_ar: string | null;
  cta_label_en: string | null;
  cta_url: string | null;
  cta_style: string | null;
  whatsapp_phone: string | null;
  call_phone: string | null;
  whatsapp_enabled: boolean;
  call_enabled: boolean;

  seo_title_ar: string | null;
  seo_title_en: string | null;
  seo_description_ar: string | null;
  seo_description_en: string | null;
  seo_keywords: string | null;
  canonical_url: string | null;
  meta_robots: string | null;
  in_sitemap: boolean;

  og_title_ar: string | null;
  og_title_en: string | null;
  og_description_ar: string | null;
  og_description_en: string | null;

  schema_markup: Record<string, unknown> | null;
  schema_type: string | null;

  total_views: number;
  total_conversions: number;

  comparison_clinic_ids?: number[];
  blocks?: LandingPageBlock[];

  created_at: string | null;
  updated_at: string | null;
}

export interface LandingPageFormValues {
  type: LandingPageType;
  status?: LandingPageStatus;
  slug: string;
  title_ar?: string | null;
  title_en?: string | null;
  internal_name?: string | null;
  clinic_id?: number | null;
  offer_id?: number | null;
  city_id?: number | null;
  category_id?: number | null;
  comparison_clinic_ids?: number[];
  cover_image?: string | null;
  social_image?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  cta_label_ar?: string | null;
  cta_label_en?: string | null;
  cta_url?: string | null;
  cta_style?: string | null;
  whatsapp_phone?: string | null;
  call_phone?: string | null;
  whatsapp_enabled?: boolean;
  call_enabled?: boolean;
}

export interface SeoFormValues {
  seo_title_ar?: string | null;
  seo_title_en?: string | null;
  seo_description_ar?: string | null;
  seo_description_en?: string | null;
  seo_keywords?: string | null;
  canonical_url?: string | null;
  meta_robots?: string | null;
  in_sitemap?: boolean;
  og_title_ar?: string | null;
  og_title_en?: string | null;
  og_description_ar?: string | null;
  og_description_en?: string | null;
}
