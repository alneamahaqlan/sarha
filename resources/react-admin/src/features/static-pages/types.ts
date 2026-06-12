export interface StaticPage {
  id: number;
  slug: string;
  title_ar: string;
  title_en: string | null;
  body_ar: string | null;
  body_en: string | null;
  meta_description_ar: string | null;
  meta_description_en: string | null;
  is_active: boolean;
  is_system: boolean;
  published_at: string | null;
  sort_order: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface StaticPageFormValues {
  slug: string;
  title_ar: string;
  title_en?: string | null;
  body_ar?: string | null;
  body_en?: string | null;
  meta_description_ar?: string | null;
  meta_description_en?: string | null;
  is_active: boolean;
  sort_order: number;
}
