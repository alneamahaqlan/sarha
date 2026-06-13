export type HomepageSectionType =
  | 'hero'
  | 'stats'
  | 'banner'
  | 'offers'
  | 'articles'
  | 'categories'
  | 'category_offers'
  | 'ai_highlight'
  | 'how_it_works'
  | 'clinic_list'
  | 'map'
  | 'cta'
  | 'price_quote'
  | 'faqs'
  | 'followed_offers'
  | 'followed_clinics';

/** A single Q&A row stored in a `faqs` section's config.faqs bag. */
export interface HomepageFaqItem {
  question: string;
  answer: string;
}

export interface HomepageBannerSlide {
  id: number;
  homepage_section_id: number;
  image: string;
  image_url: string | null;
  link_url: string | null;
  is_active: boolean;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface HomepageSectionConfig {
  category_slug?: string;
  source?: 'featured' | 'top_rated' | 'best_priced';
  min_discount?: number;
  only_published?: boolean;
  interval?: number;
  faqs?: HomepageFaqItem[];
}

export interface HomepageSection {
  id: number;
  key: string;
  type: HomepageSectionType;
  title_ar: string | null;
  title_en: string | null;
  is_active: boolean;
  sort_order: number;
  item_limit: number | null;
  banner_interval_seconds: number | null;
  show_on_mobile: boolean;
  show_on_desktop: boolean;
  starts_at: string | null;
  ends_at: string | null;
  config: HomepageSectionConfig | null;
  banner_slides?: HomepageBannerSlide[];
  banner_slides_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface HomepageSectionFormValues {
  title_ar?: string | null;
  title_en?: string | null;
  is_active?: boolean;
  item_limit?: number | null;
  banner_interval_seconds?: number | null;
  show_on_mobile?: boolean;
  show_on_desktop?: boolean;
  starts_at?: string | null;
  ends_at?: string | null;
  config?: HomepageSectionConfig | null;
}

export interface BannerSlideFormValues {
  image: string;
  link_url?: string | null;
  is_active?: boolean;
  sort_order?: number;
}
