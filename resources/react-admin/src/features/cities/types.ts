export interface City {
  id: number;
  name: string;
  name_en: string | null;
  is_active: boolean;
  sort_order: number;
  clinics_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface CityFormValues {
  name: string;
  name_en?: string | null;
  is_active: boolean;
  sort_order: number;
}
