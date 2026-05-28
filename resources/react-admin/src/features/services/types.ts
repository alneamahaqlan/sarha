export interface ServiceCategoryRef {
  id: number;
  name: string;
  name_en: string | null;
  slug: string | null;
  emoji: string | null;
}

export interface Service {
  id: number;
  clinic_id: number;
  sub_clinic_id: number | null;
  sub_clinic?: { id: number; name: string } | null;
  /** 1–5 specialties this service belongs to (many-to-many). */
  categories?: ServiceCategoryRef[];
  /** Convenience array of just the ids — used to seed edit form defaults. */
  category_ids?: number[];
  name: string;
  description: string | null;
  price: number;
  old_price: number | null;
  offer_expires_at: string | null;
  is_featured_offer: boolean;
  has_active_offer: boolean;
  discount_percentage: number;
  image: string | null;
  is_active: boolean;
  sort_order: number;
  clinic?: { id: number; name: string };
  created_at: string | null;
  updated_at: string | null;
}

export interface ServiceFormValues {
  clinic_id: number;
  category_ids: number[];
  name: string;
  description?: string | null;
  price: number;
  old_price?: number | null;
  offer_expires_at?: string | null;
  is_active: boolean;
}
