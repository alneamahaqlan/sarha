export interface Category {
  id: number;
  name: string;
  name_en: string | null;
  slug: string;
  emoji: string | null;
  icon: string | null;
  is_active: boolean;
  sort_order: number;
  clinics_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface CategoryFormValues {
  name: string;
  name_en?: string | null;
  slug: string;
  emoji?: string | null;
  icon?: string | null;
  is_active: boolean;
  sort_order: number;
}
