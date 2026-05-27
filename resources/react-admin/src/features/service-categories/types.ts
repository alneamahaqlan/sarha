export interface ServiceCategory {
  id: number;
  name: string;
  name_en: string | null;
  slug: string;
  emoji: string | null;
  icon: string | null;
  description: string | null;
  is_active: boolean;
  sort_order: number;
  services_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ServiceCategoryFormValues {
  name: string;
  name_en?: string | null;
  slug: string;
  emoji?: string | null;
  icon?: string | null;
  description?: string | null;
  is_active: boolean;
  sort_order: number;
}
