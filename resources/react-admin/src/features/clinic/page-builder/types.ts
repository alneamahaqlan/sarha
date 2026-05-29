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
  | 'contact_info'
  | 'working_hours'
  | 'social_links'
  | 'similar_services'
  | 'floating_ctas';

export interface ClinicPageSection {
  id: number;
  key: ClinicPageSectionKey;
  is_active: boolean;
  sort_order: number;
  title_ar: string | null;
  title_en: string | null;
  item_limit: number | null;
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
}
