export type LandingPageType = 'clinic' | 'offer' | 'city' | 'category' | 'comparison' | 'custom';
export type LandingPageStatus = 'draft' | 'published' | 'archived';
export type LandingApprovalStatus = 'draft' | 'pending' | 'approved' | 'rejected';
export const LANDING_APPROVAL_STATUSES: LandingApprovalStatus[] = ['draft', 'pending', 'approved', 'rejected'];
export type BlockType =
  | 'hero' | 'services' | 'offers' | 'doctors' | 'gallery'
  | 'reviews' | 'faq' | 'map' | 'booking' | 'countdown';

export type ChromeMode = 'default' | 'minimal' | 'custom' | 'none' | 'clinic';
// Footer keeps the 4 base modes; the header adds `clinic` (render the linked
// complex's full profile hero as the header section).
export const FOOTER_CHROME_MODES: ChromeMode[] = ['default', 'minimal', 'custom', 'none'];
export const HEADER_CHROME_MODES: ChromeMode[] = ['default', 'minimal', 'custom', 'none', 'clinic'];

export interface ChromeLink {
  label: string;
  url: string;
  new_tab?: boolean;
}

export interface HeaderConfig {
  logo_image?: string | null;
  show_language?: boolean;
  sticky?: boolean;
  bg_color?: string | null;
  text_color?: string | null;
  cta_label?: string | null;
  cta_url?: string | null;
  links?: ChromeLink[];
}

export interface FooterConfig {
  logo_image?: string | null;
  about?: string | null;
  copyright?: string | null;
  phone?: string | null;
  email?: string | null;
  whatsapp?: string | null;
  bg_color?: string | null;
  text_color?: string | null;
  social?: { instagram?: string | null; twitter?: string | null; snapchat?: string | null; tiktok?: string | null };
  links?: ChromeLink[];
}

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

  header_mode: ChromeMode;
  footer_mode: ChromeMode;
  header_config: HeaderConfig | null;
  footer_config: FooterConfig | null;

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

  // Clinic ownership + one-time approval gate (clinic-built pages).
  owner_clinic_id?: number | null;
  approval_status?: LandingApprovalStatus;
  approval_reason?: string | null;
  submitted_at?: string | null;
  reviewed_at?: string | null;

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

export interface ChromeFormValues {
  header_mode: ChromeMode;
  footer_mode: ChromeMode;
  header_config: HeaderConfig;
  footer_config: FooterConfig;
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
