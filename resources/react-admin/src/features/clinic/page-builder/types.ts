/**
 * The fixed 14-key set the page builder controls. The backend treats `key`
 * as a stable identifier — never renamed or removed without a migration.
 */
export type ClinicPageSectionKey =
  | 'hero'
  | 'offers'
  | 'services'
  | 'sub_clinics'
  | 'doctors'
  | 'before_after'
  | 'google_reviews'
  | 'articles'
  | 'about'
  | 'faqs'
  | 'contact_info'
  | 'working_hours'
  | 'social_links'
  | 'similar_services'
  | 'floating_ctas';

/** A single FAQ row stored in the `faqs` section's content bag. */
export interface ClinicFaqItem {
  question: string;
  answer: string;
}

export interface ClinicPageSection {
  id: number;
  key: ClinicPageSectionKey;
  is_active: boolean;
  sort_order: number;
  title_ar: string | null;
  title_en: string | null;
  item_limit: number | null;
  /** Free-form content bag — only the `faqs` section populates it. */
  content: ClinicFaqItem[] | null;
  /** Protected sections can be reordered but never hidden. */
  is_protected: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface ClinicPageSectionFormValues {
  is_active?: boolean;
  title_ar?: string | null;
  title_en?: string | null;
  item_limit?: number | null;
  content?: ClinicFaqItem[] | null;
}
